<?php

declare(strict_types=1);

namespace Cerase\ConnectorSchema\Tests;

use Cerase\ConnectorSchema\AuthSection;
use Cerase\ConnectorSchema\ConnectorSchemaConfig;
use Cerase\ConnectorSchema\Labels;
use Cerase\ConnectorSchema\Rules;
use Closure;
use PHPUnit\Framework\TestCase;
use ReflectionFunction;
use ReflectionMethod;

/**
 * The fourth auth kind, `browser`, is declared but not served: a publisher can
 * say its connector needs a login the person makes in the connector's own
 * browser, and the schema refuses it because no consumer can connect such a
 * login yet.
 */
final class AuthKindTest extends TestCase
{
    public function test_the_declared_and_served_auth_kinds_are_pinned(): void
    {
        self::assertSame(['none', 'bearer', 'oauth2', 'browser'], Rules::AUTH_KINDS);
        self::assertSame(['none', 'bearer', 'oauth2'], Rules::AUTH_KINDS_SERVED);
    }

    public function test_every_locale_offers_every_auth_kind_in_order(): void
    {
        foreach (Labels::LOCALES as $locale) {
            $options = Labels::for($locale)['auth.kind.options'];

            self::assertIsArray($options, "auth.kind.options in {$locale}");
            self::assertSame(
                Rules::AUTH_KINDS,
                array_keys($options),
                "auth.kind.options must list every kind in order in {$locale}",
            );

            foreach ($options as $kind => $label) {
                self::assertIsString($label, "auth.kind.options.{$kind} in {$locale}");
                self::assertNotSame('', trim($label), "auth.kind.options.{$kind} is empty in {$locale}");
            }
        }
    }

    public function test_the_browser_auth_kind_is_refused(): void
    {
        self::assertSame('auth_kind_not_served', Rules::VIOLATION_AUTH_KIND_NOT_SERVED);
        self::assertSame(
            [Rules::VIOLATION_AUTH_KIND_NOT_SERVED],
            Rules::crossFieldViolations(['auth_kind' => 'browser']),
        );
    }

    public function test_served_auth_kinds_and_a_missing_kind_are_not_flagged(): void
    {
        foreach (Rules::AUTH_KINDS_SERVED as $kind) {
            $descriptor = ['auth_kind' => $kind];
            if ($kind === 'oauth2') {
                $descriptor['auth_provider'] = 'example';
            }

            self::assertSame([], Rules::crossFieldViolations($descriptor), "auth_kind {$kind}");
        }

        self::assertSame([], Rules::crossFieldViolations([]));
    }

    public function test_cross_field_violation_messages_come_from_the_locale_catalog(): void
    {
        $codes = [
            Rules::VIOLATION_CREDENTIAL_ENV_REQUIRED,
            Rules::VIOLATION_AUTH_PROVIDER_REQUIRED,
            Rules::VIOLATION_AUTH_KIND_NOT_SERVED,
        ];

        foreach ($codes as $code) {
            $messages = [];

            foreach (Labels::LOCALES as $locale) {
                $expected = Labels::for($locale)['violation.'.$code] ?? null;

                self::assertIsString($expected, "violation.{$code} is missing in {$locale}");
                self::assertNotSame('', trim($expected), "violation.{$code} is empty in {$locale}");

                $message = Rules::crossFieldViolationMessage($code, $locale);

                self::assertSame($expected, $message, "violation.{$code} in {$locale}");
                self::assertNotSame('', trim($message), "violation.{$code} is empty in {$locale}");
                $messages[$locale] = $message;
            }

            self::assertNotSame(
                $messages['en'],
                $messages['it'],
                "violation.{$code} must have a distinct Italian sentence",
            );
        }

        self::assertStringContainsString(
            'browser',
            Rules::crossFieldViolationMessage(Rules::VIOLATION_AUTH_KIND_NOT_SERVED, 'en'),
        );
        self::assertStringContainsString(
            'browser',
            Rules::crossFieldViolationMessage(Rules::VIOLATION_AUTH_KIND_NOT_SERVED, 'it'),
        );
    }

    public function test_the_auth_kind_rule_refuses_browser_in_every_locale(): void
    {
        foreach (Labels::LOCALES as $locale) {
            $rule = Rules::authKindRule($locale);

            self::assertInstanceOf(Closure::class, $rule, "authKindRule({$locale}) must return a Closure");

            $reflection = new ReflectionFunction($rule);
            self::assertCount(3, $reflection->getParameters());
            self::assertSame('string', (string) $reflection->getParameters()[0]->getType());
            self::assertSame('mixed', (string) $reflection->getParameters()[1]->getType());
            self::assertSame(Closure::class, (string) $reflection->getParameters()[2]->getType());
            self::assertSame('void', (string) $reflection->getReturnType());

            $failures = [];
            $fail = function (string $message) use (&$failures): void {
                $failures[] = $message;
            };

            $rule('auth_kind', 'browser', $fail);

            self::assertSame(
                [Rules::crossFieldViolationMessage(Rules::VIOLATION_AUTH_KIND_NOT_SERVED, $locale)],
                $failures,
                "browser must be refused exactly once in {$locale}",
            );

            foreach (Rules::AUTH_KINDS_SERVED as $kind) {
                $failures = [];
                $rule('auth_kind', $kind, $fail);

                self::assertSame([], $failures, "auth_kind {$kind} must not be refused in {$locale}");
            }

            $failures = [];
            $rule('auth_kind', null, $fail);

            self::assertSame([], $failures, "a missing auth_kind must not be refused in {$locale}");
        }
    }

    public function test_the_auth_section_builder_still_exposes_make(): void
    {
        self::assertTrue(class_exists(AuthSection::class), 'AuthSection did not load');

        $make = new ReflectionMethod(AuthSection::class, 'make');
        self::assertTrue($make->isStatic(), 'AuthSection::make must be static');
        self::assertTrue($make->isPublic(), 'AuthSection::make must be public');

        $params = $make->getParameters();
        self::assertCount(1, $params, 'AuthSection::make takes exactly the config');
        self::assertSame(
            ConnectorSchemaConfig::class,
            (string) $params[0]->getType(),
            'AuthSection::make must accept a ConnectorSchemaConfig',
        );
    }
}
