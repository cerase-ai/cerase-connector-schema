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
}
