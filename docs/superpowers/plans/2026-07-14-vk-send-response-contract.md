# VK Send Response Contract Fix Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make a successful VK `messages.send` response provide both `result.message_id` and `result.text` so `ConnectorDB` can persist `MessageData` without terminating.

**Architecture:** Add a dependency-free `VkResponseNormalizer` that builds the established internal success shape. Call it only from the successful branch of `Notifier::sendVkMessage()`; retain existing VK error handling and leave `ConnectorDB` unchanged.

**Tech Stack:** PHP 7.4-compatible syntax, Guzzle HTTP client, MikoPBX workers, standalone PHP test runner.

## Global Constraints

- Successful VK send results contain `ok=true`, the VK message ID, and the exact original message text.
- VK error responses continue through the existing error branch and are never normalized as success.
- Do not add fallback behavior to `ConnectorDB`.
- Do not change Telegram, VK edit, audio, filtering, or queue behavior.
- Do not expose messenger credentials in tests or logs.

---

### Task 1: Executable VK success contract

**Files:**
- Create: `Lib/VkResponseNormalizer.php`
- Create: `tests/VkResponseNormalizerTest.php`

**Interfaces:**
- Produces: `VkResponseNormalizer::sentMessage($messageId, string $messageText): array`.
- Consumes: the scalar `messages.send` response and the exact message originally submitted to VK.

- [ ] **Step 1: Write the failing test**

Create a standalone test that requires `Lib/VkResponseNormalizer.php` and
asserts the exact shape for integer ID `42`, text `Call text`, and empty text.

- [ ] **Step 2: Run RED**

Run: `php -n tests/VkResponseNormalizerTest.php`

Expected: failure stating that the normalizer implementation is missing.

- [ ] **Step 3: Implement the minimal normalizer**

Create a final class with one static method returning:

```php
[
    'ok' => true,
    'result' => [
        'message_id' => $messageId,
        'text' => $messageText,
    ],
]
```

- [ ] **Step 4: Run GREEN**

Run: `php -n tests/VkResponseNormalizerTest.php`

Expected: `VkResponseNormalizerTest: OK`.

### Task 2: Use the contract in Notifier

**Files:**
- Modify: `bin/Notifier.php:28-32,162-186`
- Modify: `tests/VkResponseNormalizerTest.php`

**Interfaces:**
- Consumes: `VkResponseNormalizer::sentMessage()` from Task 1.
- Produces: a successful `sendVkMessage()` response compatible with `ConnectorDB`.

- [ ] **Step 1: Add failing integration assertions**

Read `bin/Notifier.php` and assert it imports `VkResponseNormalizer`, retains
the `isset($body['error'])` branch, and calls `sentMessage($body['response'] ??
0, $messageText)` after that branch.

- [ ] **Step 2: Run RED**

Run: `php -n tests/VkResponseNormalizerTest.php`

Expected: failure reporting missing Notifier integration.

- [ ] **Step 3: Integrate the normalizer**

Import the class and replace only the successful VK return expression with
the tested normalizer call.

- [ ] **Step 4: Run GREEN and regression checks**

Run: `php -n tests/VkResponseNormalizerTest.php && php -n tests/CdrNumberFilterTest.php && php -n -l bin/Notifier.php && git diff --check`

Expected: both tests print `OK`, lint reports no errors, and the diff check is silent.

### Task 3: Deploy and live verify

**Files:**
- Deploy the verified module archive to `root@172.16.32.90` through `WorkerModuleInstaller`.

**Interfaces:**
- Consumes: Tasks 1-2 and existing test-PBX settings.
- Produces: runtime evidence for allowed and rejected CDR groups.

- [ ] **Step 1: Package and install**

Build the module ZIP excluding `.git`, local metadata, and tests; copy it and
settings JSON to the PBX; install only through `WorkerModuleInstaller`.

- [ ] **Step 2: Establish a safe test window**

Save the current filter and offset, set offset to the current CDR maximum, and
temporarily set the filter to `2001` to avoid processing historical backlog.

- [ ] **Step 3: Insert controlled CDR groups and reload**

Insert one group containing `dst_num=2001` and one group without matching
`src_num`/`dst_num`; restart module workers through `NotifierMain`.

- [ ] **Step 4: Verify outcomes**

Confirm the allowed group creates one `MessageData`, the rejected group creates
none and emits the filter-specific log, `ConnectorDB` remains running, and the
offset advances past both groups.

- [ ] **Step 5: Clean up**

Delete synthetic CDR and `MessageData` rows and restore the exact saved filter
and offset settings.
