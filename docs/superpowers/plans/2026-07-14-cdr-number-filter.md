# CDR Number Filter Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an optional, UI-configurable number list that admits a complete `linkedid` CDR group only when one row exactly matches a configured number in `src_num` or `dst_num`.

**Architecture:** Put normalization and group matching in a dependency-free `CdrNumberFilter` value object so it can be tested without booting MikoPBX. `ConnectorDB` loads the configured filter and applies it to each grouped result before any message, history, or audio work. The existing annotation-driven settings model and Phalcon form persist and display the raw list.

**Tech Stack:** PHP 7.4, MikoPBX/Phalcon module models and forms, Volt templates, standalone PHP assertion test runner.

## Global Constraints

- A call is the complete set of rows sharing one `linkedid`.
- Inspect only `src_num` and `dst_num`.
- Split configured entries on whitespace, remove all non-digits from each entry, discard empty entries, and remove duplicates.
- Compare normalized values exactly; never use substring matching.
- An empty normalized list disables filtering and admits all calls.
- Apply the same filter to Telegram and VKontakte before notification dispatch.
- Preserve unrelated untracked files already present in the worktree.

---

### Task 1: Dependency-free CDR number matcher

**Files:**
- Create: `Lib/CdrNumberFilter.php`
- Create: `tests/CdrNumberFilterTest.php`

**Interfaces:**
- Produces: `CdrNumberFilter::__construct(string $rawNumbers)` and `CdrNumberFilter::allows(array $cdrGroup): bool`.
- Consumes: A grouped CDR array containing `rows`, whose members may contain `src_num` and `dst_num`.

- [ ] **Step 1: Write the failing behavior tests**

Create a standalone test runner that requires `Lib/CdrNumberFilter.php`, exercises empty input, a match in any group row, non-matches, punctuation normalization, duplicate/empty entries, exact matching, and missing endpoint fields, and exits non-zero on assertion failure.

- [ ] **Step 2: Run the test to verify RED**

Run: `php tests/CdrNumberFilterTest.php`

Expected: failure because `Modules\ModuleNotifier\Lib\CdrNumberFilter` does not exist.

- [ ] **Step 3: Implement the minimal matcher**

Implement a final class that parses whitespace-separated tokens once in the constructor and checks exact normalized endpoint membership in `allows()`.

- [ ] **Step 4: Run the test to verify GREEN**

Run: `php tests/CdrNumberFilterTest.php`

Expected: `CdrNumberFilterTest: OK` and exit code 0.

### Task 2: ConnectorDB integration

**Files:**
- Modify: `bin/ConnectorDB.php:29-53,80-113,258-283`
- Modify: `tests/CdrNumberFilterTest.php`

**Interfaces:**
- Consumes: `CdrNumberFilter::__construct()` and `allows()` from Task 1.
- Produces: filtering before `sendEditMessage()` and before history/audio processing.

- [ ] **Step 1: Add a failing source integration check**

Extend the standalone test runner to assert that `ConnectorDB.php` imports and constructs `CdrNumberFilter` from `numberFilter`, and calls `allows($cdr)` before `sendEditMessage($cdr)`.

- [ ] **Step 2: Run the test to verify RED**

Run: `php tests/CdrNumberFilterTest.php`

Expected: failure reporting missing `ConnectorDB` filter integration.

- [ ] **Step 3: Integrate the matcher**

Add a `CdrNumberFilter` property, refresh it from `$settings->numberFilter ?? ''` in `updateSettings()`, and `continue` rejected groups immediately after assigning their `linkedid`.

- [ ] **Step 4: Run the test to verify GREEN**

Run: `php tests/CdrNumberFilterTest.php`

Expected: `CdrNumberFilterTest: OK` and exit code 0.

### Task 3: Persisted setting and web form

**Files:**
- Modify: `Models/ModuleNotifier.php:64-75`
- Modify: `App/Forms/ModuleNotifierForm.php:42-48`
- Modify: `App/Views/index.volt:53-64`
- Modify: `Messages/ru.php:21-28`
- Modify: `Messages/en.php:21-28`
- Modify: `tests/CdrNumberFilterTest.php`

**Interfaces:**
- Produces: nullable string setting `ModuleNotifier::$numberFilter`, form element `numberFilter`, and translation keys `module_notifier_numberFilter` and `module_notifier_numberFilter_help`.
- Consumes: The controller's existing model-property save loop.

- [ ] **Step 1: Add failing setting/UI checks**

Extend the test runner to inspect the model, form, Volt template, and both translation files for the exact property, textarea render, label, help key, and empty-filter explanation.

- [ ] **Step 2: Run the test to verify RED**

Run: `php tests/CdrNumberFilterTest.php`

Expected: failure reporting absent `numberFilter` model/form/UI wiring.

- [ ] **Step 3: Implement model and UI wiring**

Add the annotated model property, a two-row `TextArea`, a messenger-independent field below the channel-specific settings, and concise Russian/English label and help translations.

- [ ] **Step 4: Run the test to verify GREEN**

Run: `php tests/CdrNumberFilterTest.php`

Expected: `CdrNumberFilterTest: OK` and exit code 0.

### Task 4: Full verification

**Files:**
- Verify all modified PHP and Volt files.

**Interfaces:**
- Consumes: Tasks 1-3 complete.
- Produces: syntax-clean, regression-checked feature ready for deployment.

- [ ] **Step 1: Run focused behavior tests**

Run: `php tests/CdrNumberFilterTest.php`

Expected: `CdrNumberFilterTest: OK`.

- [ ] **Step 2: Run PHP syntax checks**

Run: `find App Lib Models Messages bin tests -name '*.php' -print0 | xargs -0 -n1 php -l`

Expected: every file reports `No syntax errors detected`.

- [ ] **Step 3: Check patch hygiene**

Run: `git diff --check && git status --short`

Expected: no whitespace errors; status lists only the planned feature files plus pre-existing untracked user files.

- [ ] **Step 4: Review the final diff against the design**

Confirm that filtering occurs once per complete group, before both text and audio paths, and that empty configuration remains backward compatible.
