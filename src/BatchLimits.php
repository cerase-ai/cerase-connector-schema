<?php

declare(strict_types=1);

namespace Cerase\ConnectorSchema;

/**
 * The shape of a connector's batch limits: per tool, the arguments holding a
 * list of records that the tool takes at most so many of in one call. The
 * control-plane copies the block onto the connector's row and the gateway reads
 * it there: a call over a limit is split into parts of that size, put to the
 * person as one approval listing the parts, and run part after part, stopping
 * at the first that fails.
 *
 *     batch_limits:
 *       manage_crm_objects:            # a tool of the connector
 *         createRequest.objects: 10    # a list, by its path in the call,
 *         updateRequest.objects: 10    # and the most items one call takes
 *
 * A path names keys of the call's arguments joined by dots, never a position
 * in a list. A call carrying items in two of a tool's lists cannot be split,
 * since every part would repeat the other list, and the gateway refuses it
 * before anybody is asked.
 */
final class BatchLimits
{
    /** Keys of the call's arguments joined by dots. */
    public const PATH_REGEX = '~^[A-Za-z0-9_-]+(\.[A-Za-z0-9_-]+)*$~';

    /**
     * Every reason the block cannot be read, one sentence each, naming the
     * key. Empty when the block is valid; null declares nothing and is valid.
     *
     * @return list<string>
     */
    public static function violations(mixed $block): array
    {
        if ($block === null) {
            return [];
        }
        if (! is_array($block) || ($block !== [] && array_is_list($block))) {
            return ['batch_limits must map a tool to the lists it takes, got '.get_debug_type($block)];
        }

        $found = [];
        foreach ($block as $tool => $lists) {
            $tool = (string) $tool;
            if (trim($tool) === '') {
                $found[] = 'batch_limits has a tool without a name';

                continue;
            }
            if (! is_array($lists) || $lists === [] || array_is_list($lists)) {
                $found[] = "batch_limits.{$tool} must map the path of a list in the call to the most items one call takes";

                continue;
            }
            foreach ($lists as $path => $most) {
                $path = (string) $path;
                if (preg_match(self::PATH_REGEX, $path) !== 1) {
                    $found[] = "batch_limits.{$tool} names «{$path}», which is not a path of keys joined by dots";

                    continue;
                }
                if (! is_int($most) || $most < 1) {
                    $shown = json_encode($most, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    $found[] = "batch_limits.{$tool}.{$path} must be a whole number of at least 1, got "
                        .(is_string($shown) ? $shown : get_debug_type($most));
                }
            }
        }

        return array_values(array_unique($found));
    }
}
