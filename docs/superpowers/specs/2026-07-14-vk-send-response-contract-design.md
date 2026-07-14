# VK Send Response Contract Design

## Goal

Prevent `ConnectorDB` from terminating after a successful VK
`messages.send` request by making the VK adapter return the same successful
message fields consumed by the existing notification pipeline.

## Root cause

Telegram returns both `result.message_id` and `result.text`. The VK adapter
currently returns only `result.message_id`, while `ConnectorDB` persists both
fields. VK successfully sends the message, then `ConnectorDB` raises an
undefined-key exception while reading `result.text` before `MessageData` and
the CDR offset are saved.

The configured VK token is valid: read-only calls to `groups.getById` and
`messages.getConversations` both returned HTTP 200 responses.

## Design

`sendVkMessage()` remains responsible for adapting the VK API response to the
module's internal messenger contract. After it has rejected a VK `error`
response, it will normalize success to:

```php
[
    'ok' => true,
    'result' => [
        'message_id' => $body['response'] ?? 0,
        'text' => $messageText,
    ],
]
```

A dependency-free `VkResponseNormalizer` will build this value so the real
contract can be tested without booting MikoPBX workers or calling VK. The
normalizer will not process errors; `sendVkMessage()` continues to detect and
return VK errors before calling it.

No fallback is added to `ConnectorDB`, because accepting malformed success
responses there would hide adapter contract violations. Telegram, message
editing, audio upload, queues, and filtering remain unchanged.

## Verification

Automated tests will verify that normalization preserves the numeric VK
message ID and the exact original message text, including an empty string. A
source integration assertion will additionally verify that `sendVkMessage()`
uses the tested normalizer only after its VK error branch.

On the test PBX, a controlled live test will:

1. set the CDR offset to the current maximum to avoid old-call backlog;
2. activate filter `2001` temporarily;
3. insert one allowed and one rejected synthetic `linkedid` group;
4. verify that the allowed group creates `MessageData` without terminating
   `ConnectorDB`;
5. verify that the rejected group creates no message and emits the filter log;
6. remove synthetic CDRs and restore the prior filter and offset settings.
