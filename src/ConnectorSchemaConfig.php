<?php

declare(strict_types=1);

namespace Cerase\ConnectorSchema;

use Closure;

/**
 * Immutable, fluent configuration for the connector-descriptor form schema.
 *
 * It lets each app tune the shared Authentication + Install sections without
 * forking them:
 *
 *   - which install modes to offer (marketplace: 4 incl. 'none';
 *     control-plane: 3) and in which order;
 *   - the label locale (en / it);
 *   - a section-level visibility gate (marketplace shows the sections only when
 *     `type === 'connector'`; the control-plane, whose records are always
 *     connectors, leaves it open);
 *   - whether the OPTIONAL strict cross-field rules are enforced. They are OFF
 *     by default, so adopting the package changes no app's behavior; an app
 *     opts in.
 *
 * Every setter returns a fresh instance, so a shared base config can be
 * specialised without side effects.
 */
final class ConnectorSchemaConfig
{
    /**
     * @param  list<string>  $installModes
     * @param  (Closure(mixed): bool)|null  $sectionVisibility
     * @param  Closure|null  $disabledWhen
     */
    private function __construct(
        private array $installModes = ['remote_url', 'command', 'image', 'none'],
        private string $locale = 'en',
        private ?Closure $sectionVisibility = null,
        private bool $strictCrossField = false,
        private ?Closure $disabledWhen = null,
    ) {}

    public static function make(): self
    {
        return new self();
    }

    /**
     * Replace the offered install modes (order is preserved as given).
     *
     * @param  list<string>  $modes
     */
    public function installModes(array $modes): self
    {
        return $this->with(installModes: array_values($modes));
    }

    /**
     * Convenience: offer every mode EXCEPT 'none' (the control-plane default —
     * it never clones a repo).
     */
    public function withoutNoneMode(): self
    {
        return $this->with(installModes: array_values(array_filter(
            $this->installModes,
            static fn (string $mode): bool => $mode !== 'none',
        )));
    }

    public function locale(string $locale): self
    {
        return $this->with(locale: $locale);
    }

    /**
     * Gate the whole Authentication + Install sections behind a predicate. The
     * closure receives Filament's `Get` utility (typed loosely so the package
     * needs no Filament import here) and returns a bool.
     *
     * @param  Closure(mixed): bool  $callback
     */
    public function visibleWhen(Closure $callback): self
    {
        return $this->with(sectionVisibility: $callback);
    }

    public function strictCrossField(bool $enabled = true): self
    {
        return $this->with(strictCrossField: $enabled);
    }

    /**
     * Disable EVERY field of the Authentication + Install sections when the
     * predicate is truthy. The control-plane locks a custom connector's
     * descriptor after creation (the fields are immutable post-install), so it
     * passes `fn (string $operation): bool => $operation !== 'create'`. The
     * closure is handed straight to Filament's `->disabled()`, so it may inject
     * `$operation` / `$get` / `$record` like any Filament callback.
     *
     * OFF by default — the marketplace keeps every descriptor field editable,
     * so adopting the package changes no app's behavior.
     *
     * @param  Closure  $callback
     */
    public function disabledWhen(Closure $callback): self
    {
        return $this->with(disabledWhen: $callback);
    }

    // ── getters ──────────────────────────────────────────────────────────────

    /**
     * @return list<string>
     */
    public function getInstallModes(): array
    {
        return $this->installModes;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * @return (Closure(mixed): bool)|null
     */
    public function getSectionVisibility(): ?Closure
    {
        return $this->sectionVisibility;
    }

    public function isStrictCrossField(): bool
    {
        return $this->strictCrossField;
    }

    /**
     * The predicate that disables every descriptor field, or null when the
     * fields are always editable (the default / marketplace behavior).
     */
    public function getDisabledWhen(): ?Closure
    {
        return $this->disabledWhen;
    }

    // ── computed ─────────────────────────────────────────────────────────────

    public function offersNoneMode(): bool
    {
        return in_array('none', $this->installModes, true);
    }

    /**
     * The install-mode Select options: the localized label map filtered and
     * ordered to exactly the offered modes.
     *
     * @return array<string, string>
     */
    public function installModeOptions(): array
    {
        /** @var array<string, string> $all */
        $all = $this->catalog()['install.mode.options'];

        $options = [];
        foreach ($this->installModes as $mode) {
            if (array_key_exists($mode, $all)) {
                $options[$mode] = $all[$mode];
            }
        }

        return $options;
    }

    /**
     * The resolved label catalog for the configured locale.
     *
     * @return array<string, string|array<string, string>>
     */
    public function catalog(): array
    {
        return Labels::for($this->locale);
    }

    /**
     * Convenience string accessor into the catalog.
     */
    public function label(string $key): string
    {
        $value = $this->catalog()[$key] ?? '';

        return is_string($value) ? $value : '';
    }

    /**
     * Convenience option-map accessor into the catalog.
     *
     * @return array<string, string>
     */
    public function options(string $key): array
    {
        $value = $this->catalog()[$key] ?? [];

        return is_array($value) ? $value : [];
    }

    // ── internal ─────────────────────────────────────────────────────────────

    /**
     * @param  list<string>|null  $installModes
     * @param  (Closure(mixed): bool)|null  $sectionVisibility
     * @param  Closure|null  $disabledWhen
     */
    private function with(
        ?array $installModes = null,
        ?string $locale = null,
        ?Closure $sectionVisibility = null,
        ?bool $strictCrossField = null,
        ?Closure $disabledWhen = null,
    ): self {
        $clone = new self(
            $installModes ?? $this->installModes,
            $locale ?? $this->locale,
            $this->sectionVisibility,
            $strictCrossField ?? $this->strictCrossField,
            $this->disabledWhen,
        );

        // A null closure means "unchanged" (we can't distinguish "clear it"
        // from "leave it" through a nullable param, and no caller needs to
        // clear it), so carry the existing closures unless a new one is
        // explicitly supplied.
        if ($sectionVisibility !== null) {
            $clone->sectionVisibility = $sectionVisibility;
        }
        if ($disabledWhen !== null) {
            $clone->disabledWhen = $disabledWhen;
        }

        return $clone;
    }
}
