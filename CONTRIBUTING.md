# Contributing to cerase-connector-schema

This library is maintained by Guidance Studio S.r.l. as part of the Cerase
platform. Bug reports and pull requests are welcome. Report a suspected
vulnerability privately, as [`SECURITY.md`](SECURITY.md) describes, never in an
issue or a pull request.

## Before you open a pull request

- Run `./run-tests.sh`. It runs what CI can refuse a change on (the tooling pin,
  docs parity, the comment convention, the secrets guard, the secret scan and
  PHPUnit) and needs Docker, not PHP.
- Add or change a test in `tests/` for the rule or builder you change.
- Leave the files pinned by `scripts/TOOLING.sha256` alone. They are copies of
  another Cerase repository's, and CI fails when one differs.
- A comment says what the code does and why. When and under which ticket it
  changed belongs in the commit message, and `scripts/comment-check.sh` refuses
  it in a comment.

## How a change lands

The maintainers commit to `main`, and every push and pull request runs
`.github/workflows/ci.yml`. A pull request is merged by a maintainer once its
checks pass. A change reaches the two applications only after a new `vX.Y.Z`
tag and a Composer update in each of them.

## Licence

A contribution is licensed under the MIT licence in [`LICENSE`](LICENSE), the
licence of the rest of the repository.
