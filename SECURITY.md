# Security Policy

`cerase-connector-schema` is the shape of a Cerase connector descriptor: the
form sections that edit it and the validation rules that check it. The Cerase
control-plane runs those rules on every connector a customer registers, and the
Cerase Marketplace runs them on every connector a publisher submits. A rule that
accepts what it should refuse, such as an install command, an image reference
or a declared host, can let a connector run what nobody approved.

It is maintained by Guidance Studio S.r.l.

## Supported versions

The release unit is a `vX.Y.Z` git tag, which the two applications resolve
through Composer.

| Version | Gets security fixes |
|---|---|
| the latest tag | yes |
| any earlier tag | no |

A fix ships as a new tag, and reaches the applications when they update to it.
No earlier tag is patched.

## Reporting a vulnerability

Email **tech@guidance.studio** with `cerase-connector-schema` in the subject.
That mailbox reaches the maintainers.

Do not open an issue, a discussion or a pull request for a suspected
vulnerability. This repository is public, so any of those is a disclosure.

Include as much of this as you have:

- what the defect is, and which rule, field or builder it is in
- the tag or commit you tested
- the descriptor that shows it, or steps to reproduce
- what an attacker gains, and what access they need to begin

Reports in English or Italian are equally welcome.

## What happens after you report

We confirm the report arrived, tell you whether we reproduced it and how we
rate it, and tell you which tag carries the fix once it is published.

We do not publish a response-time commitment. If you hear nothing, treat it as a
mail that did not arrive and send it again.

## Disclosure

Please hold details until a fixed tag is published and both applications run
it. We will tell you when that happens, and we will credit you unless you
prefer otherwise.

There is no paid bug bounty.
