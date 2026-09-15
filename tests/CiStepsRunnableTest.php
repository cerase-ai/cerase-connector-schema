<?php

declare(strict_types=1);

namespace Cerase\ConnectorSchema\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Every check CI can refuse a push on must be reachable from ./run-tests.sh.
 *
 * The failure this closes is a shape rather than one bug. A job whose name a
 * developer recognises can contain a step that no local tier runs, so the first
 * sign of a refusal is a red push. This repo had no local runner at all: four
 * of the five things its workflow can refuse a push on were unreachable here,
 * and the fifth, PHPUnit, was reachable only by knowing to type it.
 *
 * The list is DERIVED from the workflow rather than enumerated here, so a step
 * added to ci.yml tomorrow reds on the day it lands. An enumeration goes stale
 * silently, which is the defect it was meant to catch.
 */
final class CiStepsRunnableTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__);
    }

    private function workflow(): string
    {
        $yaml = file_get_contents($this->root().'/.github/workflows/ci.yml');
        self::assertIsString($yaml, 'the workflow is unreadable, so this test would assert nothing');

        return $yaml;
    }

    private function runner(): string
    {
        $path = $this->root().'/run-tests.sh';
        self::assertFileExists($path, 'there is no local runner, so CI is the only place this repo can be tested');
        self::assertFileIsReadable($path);
        $sh = file_get_contents($path);
        self::assertIsString($sh);

        return $sh;
    }

    public function test_the_runner_runs_every_guard_script_the_workflow_runs(): void
    {
        preg_match_all('#bash scripts/([a-z0-9_-]+\.sh)#', $this->workflow(), $m);
        $scripts = array_unique($m[1]);

        self::assertNotEmpty(
            $scripts,
            'no guard script was found in the workflow, so this test would pass by looking at nothing'
        );

        // The INVOCATION, never the name on its own. Written as a substring
        // check this test passed on a runner whose only remaining occurrence of
        // the guard was the word in a comment — the mention rather than the
        // use, which is the exact way a guard is made to assert nothing.
        $runner = $this->runner();
        foreach ($scripts as $script) {
            self::assertMatchesRegularExpression(
                '/^[^#\n]*bash[^\n]*'.preg_quote($script, '/').'/m',
                $runner,
                "CI refuses a push on scripts/{$script} and no local tier runs it"
            );
        }
    }

    public function test_the_runner_runs_the_secret_scan_that_hard_blocks_the_publish(): void
    {
        self::assertStringContainsString('gitleaks detect', $this->workflow());
        self::assertStringContainsString('gitleaks detect', $this->runner());
    }

    public function test_the_runner_runs_the_test_suite_the_workflow_runs(): void
    {
        self::assertStringContainsString('phpunit', $this->workflow());
        self::assertStringContainsString('phpunit', $this->runner());
    }

    /**
     * The guard scripts are cerase-core's, copied here and pinned by
     * scripts/TOOLING.sha256. Without a check against the pin, a copy edited in
     * this repo decides the push while cerase-core's decides every other repo,
     * and nothing in this repo's CI says so.
     *
     * The scripts are read from the pin itself, so a row added to it tomorrow
     * is covered on the day it lands.
     */
    public function test_the_workflow_checks_the_vendored_tooling_against_its_pin_before_running_any_of_it(): void
    {
        $body = $this->withoutComments($this->workflow());

        $check = strpos($body, 'sha256sum --check scripts/TOOLING.sha256');
        self::assertNotFalse(
            $check,
            'CI runs the vendored scripts without checking them against scripts/TOOLING.sha256'
        );

        $firstRun = null;
        foreach ($this->pinnedScripts() as $script) {
            $at = strpos($body, $script);
            if ($at !== false && ($firstRun === null || $at < $firstRun)) {
                $firstRun = $at;
            }
        }
        self::assertNotNull(
            $firstRun,
            'the workflow runs none of the pinned scripts, so the order below would compare nothing'
        );
        self::assertLessThan($firstRun, $check, 'a pinned script runs before its copy is checked against the pin');
    }

    public function test_the_runner_checks_the_vendored_tooling_against_the_same_pin(): void
    {
        self::assertMatchesRegularExpression(
            '/^[^#\n]*sha256sum --check scripts\/TOOLING\.sha256/m',
            $this->runner(),
            'CI refuses a push on a drifted copy of the vendored tooling and no local tier checks it'
        );
    }

    /** @return list<string> */
    private function pinnedScripts(): array
    {
        $pin = file_get_contents($this->root().'/scripts/TOOLING.sha256');
        self::assertIsString($pin, 'the tooling pin is unreadable, so this test would assert nothing');
        preg_match_all('#^[0-9a-f]{64}\s+(scripts/[A-Za-z0-9_.-]+\.sh)$#m', $pin, $m);
        self::assertNotEmpty($m[1], 'the tooling pin lists no script');

        return $m[1];
    }

    private function withoutComments(string $text): string
    {
        return implode("\n", array_filter(
            explode("\n", $text),
            static fn (string $line): bool => preg_match('/^\s*#/', $line) !== 1,
        ));
    }
}
