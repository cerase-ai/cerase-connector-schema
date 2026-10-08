<?php

declare(strict_types=1);

namespace Cerase\ConnectorSchema\Tests;

use Cerase\ConnectorSchema\BatchLimits;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * A connector's batch limits are read in one shape by the control-plane, the
 * Marketplace and, through the column they are copied onto, the gateway that
 * splits a call over one.
 */
final class BatchLimitsTest extends TestCase
{
    /** HubSpot's block as cerase-core's connector catalogue carries it. */
    private const HUBSPOT = <<<'YAML'
        batch_limits:
          manage_crm_objects:
            createRequest.objects: 10
            updateRequest.objects: 10
        YAML;

    public function test_hubspots_declaration_in_the_connector_catalogue_is_accepted(): void
    {
        $block = Yaml::parse(self::HUBSPOT)['batch_limits'];

        self::assertSame([], BatchLimits::violations($block));
    }

    public function test_a_connector_that_declares_nothing_is_valid(): void
    {
        self::assertSame([], BatchLimits::violations(null));
        self::assertSame([], BatchLimits::violations([]));
    }

    public function test_a_path_of_one_key_and_a_limit_of_one_are_accepted(): void
    {
        self::assertSame([], BatchLimits::violations(['batch_update' => ['records' => 1]]));
        self::assertSame([], BatchLimits::violations(['upsert-rows' => ['body.rows_v2' => 500]]));
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function malformed(): iterable
    {
        yield 'a list instead of a map' => [
            [['manage_crm_objects' => ['objects' => 10]]],
            'batch_limits must map a tool to the lists it takes, got array',
        ];
        yield 'a scalar' => [
            10,
            'batch_limits must map a tool to the lists it takes, got int',
        ];
        yield 'a tool with nothing under it' => [
            ['manage_crm_objects' => []],
            'batch_limits.manage_crm_objects must map the path of a list in the call to the most items one call takes',
        ];
        yield 'a tool holding a number' => [
            ['manage_crm_objects' => 10],
            'batch_limits.manage_crm_objects must map the path of a list in the call to the most items one call takes',
        ];
        yield 'a tool holding a list of paths' => [
            ['manage_crm_objects' => ['createRequest.objects']],
            'batch_limits.manage_crm_objects must map the path of a list in the call to the most items one call takes',
        ];
        yield 'a path with an index into a list' => [
            ['manage_crm_objects' => ['requests[0].objects' => 10]],
            'batch_limits.manage_crm_objects names «requests[0].objects», which is not a path of keys joined by dots',
        ];
        yield 'a path with an empty key' => [
            ['manage_crm_objects' => ['updateRequest..objects' => 10]],
            'batch_limits.manage_crm_objects names «updateRequest..objects», which is not a path of keys joined by dots',
        ];
        yield 'a limit of zero' => [
            ['manage_crm_objects' => ['updateRequest.objects' => 0]],
            'batch_limits.manage_crm_objects.updateRequest.objects must be a whole number of at least 1, got 0',
        ];
        yield 'a limit written as text' => [
            ['manage_crm_objects' => ['updateRequest.objects' => '10']],
            'batch_limits.manage_crm_objects.updateRequest.objects must be a whole number of at least 1, got "10"',
        ];
        yield 'a fractional limit' => [
            ['manage_crm_objects' => ['updateRequest.objects' => 2.5]],
            'batch_limits.manage_crm_objects.updateRequest.objects must be a whole number of at least 1, got 2.5',
        ];
        yield 'a tool without a name' => [
            ['' => ['objects' => 10]],
            'batch_limits has a tool without a name',
        ];
    }

    #[DataProvider('malformed')]
    public function test_a_malformed_block_is_refused_naming_the_key(mixed $block, string $message): void
    {
        self::assertContains($message, BatchLimits::violations($block));
    }

    public function test_every_defect_is_reported_and_none_twice(): void
    {
        $violations = BatchLimits::violations([
            'manage_crm_objects' => ['createRequest.objects' => 0, 'updateRequest.objects' => 'ten'],
            'manage_segment' => [],
        ]);

        self::assertCount(3, $violations);
        self::assertSame($violations, array_values(array_unique($violations)));
    }
}
