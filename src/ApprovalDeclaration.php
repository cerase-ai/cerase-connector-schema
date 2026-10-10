<?php

declare(strict_types=1);

namespace Cerase\ConnectorSchema;

/**
 * The shape of a connector's approval declaration: the `approval_display`
 * block saying how the connector's calls read to the person asked to approve
 * them. The control-plane reads it from its catalogue, a registry package and
 * a custom connector; the Marketplace stores and serves it. Both check it here.
 *
 *     approval_display:
 *       sentences:          # the action in words, per tool: a sentence, or
 *         send_email: Inviare un'email          # rules keyed by the argument
 *         execute_tool:                          # that names the operation
 *           toolName: {create_one_note: Aggiungere una nota, 'create_*': Creare un record}
 *       outcomes:           # the same action, done, in the same shape
 *       approving:          # what approving does, in the same shape
 *         draft_email: Salva una bozza nella tua casella Gmail (non viene inviata)
 *       labels:             # what a person reads in place of an argument's key
 *         stage: Fase
 *       unwrap: [arguments] # arguments whose own name is not part of what a person reads
 *       amounts_in_micros:  # an amount in millionths, and the field naming its currency
 *         amountMicros: currencyCode
 *       choices:            # what a choice's value reads as
 *         - fields: [stage]
 *           values: {NEW: Nuova}
 *       named_ids:          # fields holding the id of a record of a type
 *         companyId: company
 *       linked_to: [targetCompanyId]  # what a note or a task is attached to
 *       dates: [hs_timestamp]         # leaves holding an instant
 *       link: {type: objectType}      # the field naming the kind of record a link points to
 *       record_names:       # how the connector's answers name its records
 *         id: [id, recordId]
 *         name: ['{displayName}', '{name}']
 *         text_answers: [search_emails]
 *         group:            # what a long list of them is summarised by, first rule that fits
 *           - {label: Inviti del calendario, field: Subject, match: '^(invitation|invito)\s*:'}
 *           - {label: '{From}'}
 *       recipients:         # which arguments of a tool that sends a mail are To, Cc and Bcc,
 *         send_email: {to: to, cc: cc, bcc: bcc}   # by their path in the call; per tool, or
 *         execute_tool:                            # by the argument naming the operation
 *           toolName: {send_email: {to: arguments.recipients.to}}
 *       attachments:        # the argument of a tool that sends a mail naming the files it attaches
 *         send_email: attachments
 *       failures:           # the openings with which the connector's answers say a call
 *         - 'Error: '       # failed without marking them errors
 *
 * Every key is optional, and a key left empty is not declared. A connector
 * that declares nothing reads with the platform's generic sentences.
 */
final class ApprovalDeclaration
{
    /** The keys a declaration may carry. */
    public const KEYS = [
        'sentences', 'outcomes', 'approving', 'labels', 'unwrap', 'amounts_in_micros', 'choices',
        'named_ids', 'linked_to', 'dates', 'link', 'record_names', 'recipients',
        'attachments', 'failures',
    ];

    /** The roles a recipient can have, in the order a person reads them. */
    public const RECIPIENT_ROLES = ['to', 'cc', 'bcc'];

    /** The keys of `record_names`. */
    public const RECORD_NAME_KEYS = ['id', 'name', 'text_answers', 'group'];

    /** The keys of one rule of `record_names.group`. */
    public const GROUP_RULE_KEYS = ['label', 'field', 'match'];

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
            return ['approval_display must be a map, got '.get_debug_type($block)];
        }

        $found = [];
        $unknown = array_diff(array_keys($block), self::KEYS);
        if ($unknown !== []) {
            $found[] = 'approval_display has unknown key(s) ['.implode(',', $unknown).'] — it takes '.implode(', ', self::KEYS);
        }
        foreach (['sentences', 'outcomes', 'approving'] as $key) {
            if (isset($block[$key])) {
                self::rules($block[$key], "approval_display.{$key}", $found);
            }
        }
        foreach (['labels', 'named_ids', 'link'] as $key) {
            if (isset($block[$key])) {
                self::words($block[$key], "approval_display.{$key}", $found);
            }
        }
        foreach (['unwrap', 'linked_to', 'dates'] as $key) {
            if (isset($block[$key])) {
                self::names($block[$key], "approval_display.{$key}", $found);
            }
        }
        if (isset($block['amounts_in_micros'])) {
            self::amounts($block['amounts_in_micros'], $found);
        }
        if (isset($block['choices'])) {
            self::choices($block['choices'], $found);
        }
        if (isset($block['recipients'])) {
            self::recipients($block['recipients'], $found);
        }
        if (isset($block['attachments'])) {
            self::attachments($block['attachments'], $found);
        }
        if (isset($block['failures'])) {
            $failures = $block['failures'];
            if (! is_array($failures) || ! array_is_list($failures) || $failures === []
                || array_filter($failures, static fn (mixed $f): bool => ! is_string($f) || trim($f) === '') !== []) {
                $found[] = 'approval_display.failures must be a list of the openings of a failed answer';
            }
        }
        if (isset($block['record_names'])) {
            self::recordNames($block['record_names'], $found);
        }

        return array_values(array_unique($found));
    }

    /**
     * The tools among those given that the block does not cover with both the
     * action in words (`sentences`) and what approving does (`approving`), in
     * the order given, each once. These are the writes whose approval would
     * read with the generic sentence. An entry keyed by the argument naming
     * the operation covers its tool when it words at least one operation.
     *
     * @param  list<string>  $toolsAskingApproval  the names of the tools that ask approval
     * @return list<string>
     */
    public static function missingFor(mixed $block, array $toolsAskingApproval): array
    {
        $missing = [];
        foreach ($toolsAskingApproval as $tool) {
            $tool = (string) $tool;
            if (! self::worded($block, 'sentences', $tool) || ! self::worded($block, 'approving', $tool)) {
                $missing[$tool] = true;
            }
        }

        return array_map('strval', array_keys($missing));
    }

    /** Whether the block's `$key` holds words for the tool. */
    private static function worded(mixed $block, string $key, string $tool): bool
    {
        $byTool = is_array($block) ? ($block[$key] ?? null) : null;

        return is_array($byTool) && self::wordsSomething($byTool[$tool] ?? null);
    }

    /** A sentence that is not blank, or rules holding one. */
    private static function wordsSomething(mixed $entry): bool
    {
        if (is_string($entry)) {
            return trim($entry) !== '';
        }
        if (! is_array($entry)) {
            return false;
        }
        foreach ($entry as $rule) {
            if (self::wordsSomething($rule)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A tool's sentence: words, or rules read against the call's arguments.
     *
     * @param  list<string>  $found
     */
    private static function rules(mixed $map, string $where, array &$found): void
    {
        if (! is_array($map) || ($map !== [] && array_is_list($map))) {
            $found[] = "{$where} must map a tool to a sentence or to rules";

            return;
        }
        foreach ($map as $key => $rule) {
            if (is_string($rule)) {
                if (trim($rule) === '') {
                    $found[] = "{$where}.{$key} is empty";
                }

                continue;
            }
            if (! is_array($rule) || ($rule !== [] && array_is_list($rule))) {
                $found[] = "{$where}.{$key} must be a sentence or rules";

                continue;
            }
            self::rules($rule, "{$where}.{$key}", $found);
        }
    }

    /**
     * A map of a name to the words a person reads for it.
     *
     * @param  list<string>  $found
     */
    private static function words(mixed $map, string $where, array &$found): void
    {
        $valid = is_array($map) && ($map === [] || ! array_is_list($map))
            && array_filter($map, static fn (mixed $w): bool => ! is_string($w) || trim($w) === '') === [];
        if (! $valid) {
            $found[] = "{$where} must map a name to words";
        }
    }

    /**
     * A list of names.
     *
     * @param  list<string>  $found
     */
    private static function names(mixed $list, string $where, array &$found): void
    {
        $valid = is_array($list) && array_is_list($list)
            && array_filter($list, static fn (mixed $n): bool => ! is_string($n) || trim($n) === '') === [];
        if (! $valid) {
            $found[] = "{$where} must be a list of names";
        }
    }

    /**
     * Each amount field mapped to the field naming its currency, or to null.
     *
     * @param  list<string>  $found
     */
    private static function amounts(mixed $amounts, array &$found): void
    {
        $valid = is_array($amounts) && ! array_is_list($amounts);
        foreach ($valid ? $amounts : [] as $field => $currency) {
            if (! is_string($field) || $field === '' || ($currency !== null && (! is_string($currency) || $currency === ''))) {
                $valid = false;
            }
        }
        if (! $valid) {
            $found[] = 'approval_display.amounts_in_micros must map the amount field to its currency field';
        }
    }

    /**
     * A list of `{fields, values}`.
     *
     * @param  list<string>  $found
     */
    private static function choices(mixed $choices, array &$found): void
    {
        if (! is_array($choices) || ! array_is_list($choices)) {
            $found[] = 'approval_display.choices must be a list of {fields, values}';

            return;
        }
        foreach ($choices as $i => $choice) {
            if (! is_array($choice) || array_diff(array_keys($choice), ['fields', 'values']) !== []
                || ! isset($choice['fields'], $choice['values'])) {
                $found[] = "approval_display.choices.{$i} must be {fields, values}";

                continue;
            }
            self::names($choice['fields'], "approval_display.choices.{$i}.fields", $found);
            self::words($choice['values'], "approval_display.choices.{$i}.values", $found);
        }
    }

    /**
     * Per tool, its roles, or per value of the argument naming the operation,
     * that operation's roles.
     *
     * @param  list<string>  $found
     */
    private static function recipients(mixed $map, array &$found): void
    {
        if (! is_array($map) || $map === [] || array_is_list($map)) {
            $found[] = 'approval_display.recipients must map a tool to its recipients';

            return;
        }
        foreach ($map as $tool => $entry) {
            if (is_array($entry) && self::isRoleMap($entry)) {
                continue;
            }
            $byOperation = is_array($entry) && $entry !== [] && ! array_is_list($entry)
                && array_diff(array_keys($entry), self::RECIPIENT_ROLES) !== [];
            foreach ($byOperation ? $entry : [] as $operations) {
                if (! is_array($operations) || $operations === [] || array_is_list($operations)) {
                    $byOperation = false;

                    break;
                }
                foreach ($operations as $roles) {
                    if (! is_array($roles) || ! self::isRoleMap($roles)) {
                        $byOperation = false;

                        break 2;
                    }
                }
            }
            if (! $byOperation) {
                $found[] = "approval_display.recipients.{$tool} must map ".implode(', ', self::RECIPIENT_ROLES)
                    .' to the path of an argument, or name the argument that names the operation';
            }
        }
    }

    /**
     * A map of roles to the path of the argument holding them.
     *
     * @param  array<mixed>  $map
     */
    private static function isRoleMap(array $map): bool
    {
        if ($map === [] || array_is_list($map) || array_diff(array_keys($map), self::RECIPIENT_ROLES) !== []) {
            return false;
        }
        foreach ($map as $path) {
            if (! is_string($path) || trim($path) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Per tool, the argument naming the files it attaches.
     *
     * @param  list<string>  $found
     */
    private static function attachments(mixed $attachments, array &$found): void
    {
        if (! is_array($attachments) || $attachments === [] || array_is_list($attachments)) {
            $found[] = 'approval_display.attachments must map a tool to the argument naming its attachments';

            return;
        }
        foreach ($attachments as $tool => $argument) {
            if (! is_string($argument) || trim($argument) === '') {
                $found[] = "approval_display.attachments.{$tool} must name an argument of the tool";
            }
        }
    }

    /**
     * Where the connector's answers carry a record's id and its name, and
     * which tools answer in text.
     *
     * @param  list<string>  $found
     */
    private static function recordNames(mixed $names, array &$found): void
    {
        if (! is_array($names) || ($names !== [] && array_is_list($names))
            || array_diff(array_keys($names), self::RECORD_NAME_KEYS) !== []) {
            $found[] = 'approval_display.record_names takes '.implode(', ', self::RECORD_NAME_KEYS);

            return;
        }
        foreach (['id', 'name', 'text_answers'] as $key) {
            if (isset($names[$key])) {
                self::names($names[$key], "approval_display.record_names.{$key}", $found);
            }
        }
        if (isset($names['group'])) {
            self::groups($names['group'], $found);
        }
    }

    /**
     * What a long list of the connector's records is summarised by on an
     * approval: rules tried in order, the first that fits a record giving its
     * group. A rule is a label, filled from the record's fields; one with a
     * field and a pattern fits only a record whose field matches the pattern,
     * without regard to case, and its label may also name the pattern's named
     * groups. The gateway runs the pattern with Python's `re`, so it keeps to
     * the syntax PCRE and Python share, with named groups as `(?P<name>…)`.
     *
     * @param  list<string>  $found
     */
    private static function groups(mixed $rules, array &$found): void
    {
        if (! is_array($rules) || ! array_is_list($rules) || $rules === []) {
            $found[] = 'approval_display.record_names.group must be a list of rules, each a label with an optional field and pattern';

            return;
        }
        foreach ($rules as $i => $rule) {
            $where = "approval_display.record_names.group.{$i}";
            if (! is_array($rule) || ($rule !== [] && array_is_list($rule))
                || array_diff(array_keys($rule), self::GROUP_RULE_KEYS) !== []
                || ! is_string($rule['label'] ?? null) || trim($rule['label']) === '') {
                $found[] = "{$where} must be a label with an optional field and pattern";

                continue;
            }
            $field = $rule['field'] ?? null;
            $match = $rule['match'] ?? null;
            if (($field === null) !== ($match === null)) {
                $found[] = "{$where} names a pattern and a field together, or neither";

                continue;
            }
            if ($field !== null && (! is_string($field) || trim($field) === '')) {
                $found[] = "{$where}.field must name a field of the record";
            }
            if ($match !== null && (! is_string($match) || trim($match) === '' || @preg_match("\x01{$match}\x01iu", '') === false)) {
                $found[] = "{$where}.match is not a pattern";
            }
        }
    }
}
