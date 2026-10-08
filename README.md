# cerase-connector-schema

The shape of a Cerase connector descriptor, as a Composer library: the
Filament v5 form sections that edit it and the validation rules that check it.
Two applications use it, so the form a connector is published with and the
form a customer registers a custom connector with are the same component:

- the **Cerase Marketplace** publisher form (`PackageResource`, in
  `cerase-marketplace`), and
- the **control-plane** custom-connector form (`McpServerResource`, in
  `cerase-core`), whose registrar also runs the rules server-side.

It is a library and nothing else: no image, no service, nothing in any
compose file. It runs inside whichever application requires it — the
control-plane image on every appliance, and the Marketplace image on the
management plane.

## What it owns

- **Authentication section** — `auth_kind`, `auth_registration`,
  `auth_provider`, `auth_instructions`.
- **Install section** — `install_mode`, `install_remote_url`,
  `install_command`, `install_image`, `install_env_passthrough`,
  `credential_delivery`, `credential_env`, `credential_scope`, `scopes`,
  `credential_files`, and the two field lists `credential_parts` and
  `provider_fields`.
- **Rules** — the format rules for the install command and image reference,
  the cross-field rules, and the rules every declared field obeys, as pure PHP
  functions any server-side path can call.
- **Approval declaration** — the shape of a connector's `approval_display`
  block, which says how its calls read to the person asked to approve them
  (`ApprovalDeclaration`).
- **Batch limits** — the shape of a connector's `batch_limits` block, which
  says which argument of a tool is a list and the most items one call takes
  (`BatchLimits`).
- **Labels** in English and Italian (`Labels::LOCALES`).

The *Identity* section is not here: the Marketplace keys a package by
`type` / `name` / `git_url` / `tags`, the control-plane keys a connector by
`slug` / `display_name`, so each application keeps its own and appends the two
shared sections after it.

## Install

The package is not on Packagist. A consumer requires it from this repository
and a version tag:

```jsonc
"repositories": [
    { "type": "vcs", "url": "https://github.com/cerase-ai/cerase-connector-schema.git" }
],
"require": {
    "cerase-ai/cerase-connector-schema": "^0.4"
}
```

Requires PHP 8.3+ and `filament/filament` ^5.0.

## Usage

Build both sections from one `ConnectorSchemaConfig` and put them in the form
after your own Identity fields:

```php
use Cerase\ConnectorSchema\AuthSection;
use Cerase\ConnectorSchema\ConnectorSchemaConfig;
use Cerase\ConnectorSchema\InstallSection;
use Filament\Schemas\Components\Utilities\Get;

// Marketplace: English labels, all four install modes (`none` = clone the
// repo), cross-field rules on, sections shown only for connector packages.
$config = ConnectorSchemaConfig::make()
    ->locale('en')
    ->installModes(['remote_url', 'command', 'image', 'none'])
    ->strictCrossField()
    ->visibleWhen(fn (Get $get): bool => $get('type') === 'connector');

return $schema->components([
    // ... the app's own Identity section ...
    AuthSection::make($config),
    InstallSection::make($config),
]);
```

```php
// Control-plane: Italian labels, no `none` mode, cross-field rules on, and
// every descriptor field locked once the connector is created.
$config = ConnectorSchemaConfig::make()
    ->locale('it')
    ->withoutNoneMode()
    ->strictCrossField()
    ->disabledWhen(fn (string $operation): bool => $operation !== 'create');
```

Every setter returns a new instance, so a base config can be specialised
without side effects.

### Configuration

| Method | Purpose | Default |
| --- | --- | --- |
| `installModes(array $modes)` | Install modes offered, in this order | `['remote_url','command','image','none']` |
| `withoutNoneMode()` | Drop `none` (clone the repo) from the offered modes | — |
| `locale(string $locale)` | Label catalog: `en` or `it` | `en` |
| `visibleWhen(Closure $gate)` | Show both sections only when `fn (Get): bool` is true | always visible |
| `disabledWhen(Closure $gate)` | Disable every descriptor field when truthy; the closure goes straight to Filament's `->disabled()`, so it may inject `$operation`, `$get`, `$record` | editable |
| `strictCrossField(bool $on = true)` | Make the two cross-field rules required on the form | off |

`AuthSection::make($config)` and `InstallSection::make($config)` each return a
Filament `Section`. The Install section includes the two field-list repeaters,
also available on their own as `FieldsRepeater::credentialParts($config)` and
`FieldsRepeater::providerFields($config)`.

## Rules

```php
use Cerase\ConnectorSchema\Rules;

Rules::COMMAND_REGEX;              // '~^[A-Za-z0-9 \-_./:@%+]+$~'
Rules::IMAGE_REGEX;                // '~^[A-Za-z0-9._/:@\-+]+$~'
Rules::commandRule();              // 'regex:…' as a Laravel rule string
Rules::imageRule();
Rules::commandIsValid($string);    // bool
Rules::imageIsValid($string);      // bool

Rules::crossFieldViolations($descriptor);  // list<string> of codes, empty = valid
Rules::fieldViolations($descriptor);       // list of {code, list, key, detail}
Rules::fieldViolationMessage($violation, 'it');  // one violation as a sentence
```

The command rule allows no quoting, pipes, redirection, substitution or
chaining, because the command is handed to a sandboxed runner; the image rule
keeps an image reference from breaking out of the `docker` argument.

**Cross-field rules** (`crossFieldViolations`, and `->required()` on the form
when `strictCrossField()` is on):

- `auth_provider_required` — `auth_kind` is `oauth2` and `auth_provider` is
  blank;
- `credential_env_required` — `credential_delivery` is `env` and
  `credential_env` is blank.

They are off by default on the form; both applications turn them on.

## The fields a connector asks for

`credential_parts` are the values one account is made of, typed on the connect
form; `provider_fields` are the values of the connector's OAuth app beyond its
client ID and secret, typed once by an administrator. Each field is
`{key, label, help, secret, optional}`; `help` is one sentence saying where the
value is found in the product the connector talks to, and every form shows it
under the field.

```yaml
credential_parts:
  - key: INSTANCE_URL
    label: Instance URL
    secret: false
    help: The address you open the app at, for example https://acme.example.com.
  - key: DATABASE
    label: Database
    secret: false
    help: Shown on the app's login page, under the password.
    suggest:
      method: POST                      # GET (default) or POST
      url: "{INSTANCE_URL}/api/databases"
      body: {params: {}}                # POST only; its strings may name fields
      value_at: result                  # dot path into the JSON answer; * walks a list
  - key: API_KEY
    label: API key
    help: Profile → Security → New API key.
```

`secret` defaults to true and `optional` to false. A field of
`credential_parts` may declare `suggest`: a request the platform makes once the
fields it names are typed, and where in the answer the value sits. One value
fills the field, several are offered as a choice, none leaves it to the person.
It may name only fields of the same list declared `secret: false`, never its
own; `provider_fields` may not declare one.

`fieldViolations()` refuses a key that is not a valid variable name or is
repeated, a missing label or `help`, a non-boolean flag, a malformed
suggestion, and a connector with `auth_kind: bearer` that declares no
`credential_parts`. The repeaters run the per-field rules on save; the
bearer-without-fields rule is enforced by the server-side caller.
`CredentialFields::normalize($formState)` turns what a form or a YAML/JSON file
holds into the list as stored.

## The approval declaration

`approval_display` is the part of a descriptor that says how the connector's
calls read in an approval: per tool, the action in words (`sentences`), the
same action done (`outcomes`) and what approving does (`approving`), plus the
words for argument keys, choices, record names, recipients and attachments.
The class docblock of `src/ApprovalDeclaration.php` shows every key with an
example.

```php
use Cerase\ConnectorSchema\ApprovalDeclaration;

ApprovalDeclaration::violations($block);   // list<string>, empty = valid; null is valid
ApprovalDeclaration::missingFor($block, ['send_email', 'draft_email']);
                                           // the tools lacking a sentence or what approving does
ApprovalDeclaration::KEYS;                 // the keys a declaration may carry
ApprovalDeclaration::RECIPIENT_ROLES;      // to, cc, bcc
```

`violations()` returns one sentence per defect, each naming the key, so a
caller that refuses the block can throw with the first or show them all.
`missingFor()` takes the names of the tools that ask approval, that is every
write, and returns those the block does not cover with both a `sentences` and
an `approving` entry, in the order given. An entry keyed by the argument that
names the operation, as Twenty's `execute_tool: {toolName: {...}}`, covers its
tool when it words at least one operation.

`tests/fixtures/approval-declarations.yaml` is a copy of every declaration in
cerase-core's connector catalogue, and the suite requires each to be valid.

## The batch limits

`batch_limits` says, per tool, which arguments hold a list of records and the
most items one call of the tool takes. The control-plane copies it onto the
connector's row; the gateway splits a call over a limit into parts of that
size, puts them to the person as one approval listing the parts, and runs them
in order, stopping at the first that fails.

```yaml
batch_limits:
  manage_crm_objects:            # a tool of the connector
    createRequest.objects: 10    # a list, by its path in the call,
    updateRequest.objects: 10    # and the most items one call takes
```

```php
use Cerase\ConnectorSchema\BatchLimits;

BatchLimits::violations($block);   // list<string>, empty = valid; null is valid
```

A path is keys of the call's arguments joined by dots, never a position in a
list, and a limit is a whole number of at least 1. A call carrying items in two
of one tool's lists cannot be split, since every part would repeat the other
list, so the gateway refuses it before anybody is asked and says to send the
lists in separate calls.

## Tests

```bash
composer install
./run-tests.sh                 # everything CI can refuse a push on
./run-tests.sh phpunit [args]  # the suite only
./run-tests.sh tooling         # vendored scripts against their pin
./run-tests.sh docs            # docs parity
./run-tests.sh comments        # comment convention on the commits about to be pushed
./run-tests.sh secrets         # secrets guard
./run-tests.sh gitleaks        # secret scan of the history
```

The suite is plain PHPUnit with no Laravel or Filament boot. Without a host
PHP, `./run-tests.sh` builds and caches a `php:8.4-cli` image with `intl`
(name overridable with `CONNECTOR_SCHEMA_TEST_IMAGE`). `gitleaks` must be
installed for its tier; the runner prints the install command when it is not.

The section builders need a booted Filament app, so their behaviour is tested
in the consuming applications' form tests; this suite checks the rules, the
approval declaration, the batch limits, the config, and that each builder exposes a static
`make(ConnectorSchemaConfig)`.

`scripts/docs-parity.sh`, `scripts/comment-check.sh`, `scripts/secrets-guard.sh`,
their two helpers and `.github/workflows/dependabot-auto-merge.yml` are copies
of `cerase-core`'s, written by `cerase-core/scripts/sync-tooling.sh` and pinned
by `scripts/TOOLING.sha256`. Change them in `cerase-core`; a copy edited here
fails the pin check.

## CI and releases

`.github/workflows/ci.yml` runs on every push to `main`, every pull request and
every `v*` tag: the tooling pin, docs parity, comment convention, secrets
guard and PHPUnit on PHP 8.3 and 8.4, plus a `gitleaks` scan of the full
history that blocks the push. `dependabot-auto-merge.yml` merges Dependabot's
patch and minor updates once the other checks pass.

A release is a `vX.Y.Z` tag. The consumers resolve the package by tag through
Composer, so a change reaches them only after a new tag and a `composer update
cerase-ai/cerase-connector-schema` in each application.

## License

MIT — see [LICENSE](LICENSE).
