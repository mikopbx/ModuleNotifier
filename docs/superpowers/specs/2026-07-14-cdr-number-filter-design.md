# CDR Number Filter Design

## Goal

Add an optional notification filter that processes a call only when at least
one CDR row belonging to that call contains a configured number in `src_num`
or `dst_num`.

## Call boundary

A phone call is the complete group of CDR rows sharing one `linkedid`. The
filter must evaluate the completed group returned by `HistoryParser`, not
individual database rows. A match in any row admits the entire group for the
existing notification workflow.

For example, a filter containing `2001` admits a group when one of its rows has
`dst_num=2001`, even if the other rows contain different endpoints. A separate
group without `2001` in either `src_num` or `dst_num` is skipped.

## Configuration and UI

The `ModuleNotifier` settings model gains a nullable text property named
`numberFilter`. The module form exposes it as a multiline field labelled in
Russian and English with the meaning “Notify only when a number participates
in the call.” Supporting text states that entries may be separated by spaces
or line breaks and that an empty field disables filtering.

The existing generic controller save loop persists the property along with the
other model fields. The setting applies to both VKontakte and Telegram because
filtering happens before dispatch to `Notifier`.

## Normalization and matching

The configured value is split on whitespace. Every resulting token is
normalized by removing all non-digit characters. Empty normalized tokens are
discarded and duplicate numbers are collapsed.

Each CDR row contributes only its `src_num` and `dst_num` values. Those values
are normalized with the same non-digit removal rule. A call matches only when
a normalized CDR value exactly equals a normalized configured number. Partial
and substring matches are not allowed.

If the configured value is empty or normalization produces no numbers, the
filter is disabled and all calls retain the current behavior.

## Processing flow

`ConnectorDB` loads and normalizes `numberFilter` when it refreshes module
settings. During `syncCdrData()`, immediately after `HistoryParser` returns the
groups and before `sendEditMessage()` or persistence/audio processing, it asks
a focused predicate whether the complete CDR group is allowed.

An allowed group follows the existing flow unchanged: create or edit the text
notification, save its call history rows, and send available recordings. A
rejected group performs none of those notification actions. Its CDR position
is still consumed by the normal synchronization offset logic, so it is not
reconsidered indefinitely.

Every rejected group writes one informational log entry containing its
`linkedid` and the reason that neither `src_num` nor `dst_num` matched the
configured number filter. The entry must not include endpoint numbers, the
configured list, messenger credentials, or the complete CDR payload.

## Error handling and compatibility

Malformed input cannot make filtering fail: non-digits are removed and empty
tokens are ignored. Missing `src_num` or `dst_num` values are treated as empty.
Existing installations have an empty value by default, which keeps all current
notifications enabled.

No VK API, Telegram API, queue protocol, or CDR query changes are required.

## Tests

Automated tests will verify:

- an empty configuration admits every call;
- a match in `src_num` admits the group;
- a match in `dst_num` in any one of several rows admits the whole group;
- a group with no match is rejected;
- configuration and CDR values ignore non-digit characters;
- duplicate and empty configured entries do not alter behavior;
- matching is exact, so `2001` does not match `12001`.
- a rejected group logs its `linkedid` and a filter-specific reason before it
  is skipped.

Form/model checks will verify that `numberFilter` is represented as a
multiline setting and has Russian and English labels/help text.
