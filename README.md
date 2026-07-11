# cerase-connector-schema

Shared **connector-descriptor form schema** for the Cerase platform — one
source of truth for the connector *Authentication* and *Install* sections
rendered by two Filament v5 apps:

- the **Cerase Marketplace** publisher form (`PackageResource`), and
- the **control-plane** custom-connector form (`McpServerResource`).

Before this package the control-plane form was a hand-copied subset of the
marketplace form that had silently drifted (it had lost section descriptions,
the `url()` rule on the remote URL, the shell-metachar rule on the command, the
OCI-ref rule on the image, per-mode `required`s, and more). This package makes
the two components **identical by construction**: change the schema once, both
apps change together.

## What it is (and isn't)

It owns the parts of the descriptor that are genuinely shared:

- **Authentication** — `auth_kind`, `auth_registration`, `auth_provider`,
  `auth_instructions`.
- **Install** — `install_mode`, `install_remote_url`, `install_command`,
  `install_image`, `install_env_passthrough`, `credential_delivery`,
  `credential_env`, `credential_scope`, `scopes`, `credential_files`.

It does **not** own the *Identity* section — that legitimately diverges
between apps (the marketplace keys packages by `type` / `name` / `git_url` /
`tags`; the control-plane keys connectors by `slug` / `display_name` / …), so
each app keeps its own Identity fields and simply appends the shared sections.

## Install

VCS-pinned (like `guidance-studio/filament-tenant-members`) until it is on
Packagist. In the consuming app's `composer.json`:

```jsonc
"repositories": [
    { "type": "vcs", "url": "https://github.com/cerase-ai/cerase-connector-schema.git" }
],
"require": {
    "cerase-ai/cerase-connector-schema": "^0.1"
}
```

## Usage

Both apps build the two sections from a fluent `ConnectorSchemaConfig` and drop
them into their form after their own Identity fields:

```php
use Cerase\ConnectorSchema\AuthSection;
use Cerase\ConnectorSchema\ConnectorSchemaConfig;
use Cerase\ConnectorSchema\InstallSection;
use Filament\Schemas\Components\Utilities\Get;

// Marketplace: English labels, all four install modes (incl. clone/none),
// sections shown only for connector packages.
$config = ConnectorSchemaConfig::make()
    ->locale('en')
    ->installModes(['remote_url', 'command', 'image', 'none'])
    ->visibleWhen(fn (Get $get): bool => $get('type') === 'connector');

return $schema->components([
    // ... the app's own Identity section ...
    AuthSection::make($config),
    InstallSection::make($config),
    // ... the app's own content tabs ...
]);
```

```php
// Control-plane: Italian labels, three install modes (no repo clone), sections
// always visible (every record is a connector), and the whole descriptor
// LOCKED after creation (a custom connector is immutable post-install). See
// M-CONN-PKG-2.
$config = ConnectorSchemaConfig::make()
    ->locale('it')
    ->withoutNoneMode()
    ->disabledWhen(fn (string $operation): bool => $operation !== 'create');
```

### Configuration

| Method | Purpose | Default |
| --- | --- | --- |
| `installModes(array $modes)` | Ordered install-mode keys to offer | `['remote_url','command','image','none']` |
| `withoutNoneMode()` | Drop the `none` (clone-the-repo) mode | — |
| `locale(string $locale)` | Label catalog: `en` or `it` | `en` |
| `visibleWhen(Closure $gate)` | Section-level visibility predicate `fn (Get): bool` | always visible |
| `disabledWhen(Closure $gate)` | Disable EVERY descriptor field when truthy (passed straight to Filament's `->disabled()`, so it may inject `$operation`/`$get`/`$record`) — the control-plane locks a custom connector after creation | not disabled |
| `strictCrossField(bool $on = true)` | Enable the OPTIONAL cross-field requireds | `false` (off) |

### Standalone validation rules

The format rules are exposed independently of Filament so server-side paths
(API / maintainer) can reuse them:

```php
use Cerase\ConnectorSchema\Rules;

Rules::COMMAND_REGEX;              // '~^[A-Za-z0-9 \-_./:@%+]+$~'
Rules::IMAGE_REGEX;               // '~^[A-Za-z0-9._/:@\-+]+$~'
Rules::commandRule();             // 'regex:~^[A-Za-z0-9 \-_./:@%+]+$~'  (Laravel rule)
Rules::imageRule();               // 'regex:~^[A-Za-z0-9._/:@\-+]+$~'
Rules::commandIsValid($string);   // bool
Rules::imageIsValid($string);     // bool

// The strict cross-field rules as a PURE function (server-side enforcement).
Rules::crossFieldViolations($descriptor); // list<string> of violated codes
Rules::VIOLATION_CREDENTIAL_ENV_REQUIRED; // 'credential_env_required'
Rules::VIOLATION_AUTH_PROVIDER_REQUIRED;  // 'auth_provider_required'
```

### Strict cross-field rules (opt-in, OFF by default)

Two rules are available but **disabled by default** so that adopting the
package changes no app's behavior:

- `auth_provider` required when `auth_kind === 'oauth2'`;
- `credential_env` required when `credential_delivery === 'env'`.

Enable them on the **form** with `->strictCrossField()` (turned on by
`M-CONN-GUARD-1`). For **server-side** enforcement (an app's registrar / API
path), evaluate the identical rules as a pure function via
`Rules::crossFieldViolations($descriptor)` — it returns the list of violated
codes (empty = valid), so the form and the server can never drift.

## Tests

The package suite is pure PHP (rules + config) and runs with plain PHPUnit — no
Laravel/Filament boot:

```bash
composer install
vendor/bin/phpunit
```

The Filament `Section` builders are behaviorally covered by the **consuming
apps'** suites (the marketplace publisher-form tests prove byte-for-byte
parity); the package suite only guarantees the builders parse and expose the
expected `make(ConnectorSchemaConfig): Section` entrypoint.

## License

MIT — see [LICENSE](LICENSE).
