<?php

declare(strict_types=1);

namespace Cerase\ConnectorSchema\Tests;

use Cerase\ConnectorSchema\ApprovalDeclaration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * A connector's approval declaration is read in one shape by the control-plane
 * and the Marketplace, and a check anywhere can tell which of its writes would
 * read with the generic sentence.
 */
final class ApprovalDeclarationTest extends TestCase
{
    /**
     * The declarations of the connector catalogue, by slug, parsed the way the
     * control-plane parses the catalogue itself.
     *
     * @return array<string, mixed>
     */
    private static function catalogue(): array
    {
        $doc = Yaml::parseFile(__DIR__.'/fixtures/approval-declarations.yaml');

        return array_column($doc['entries'], 'approval_display', 'slug');
    }

    public function test_every_declaration_in_the_connector_catalogue_is_accepted(): void
    {
        $catalogue = self::catalogue();

        self::assertSame(['google-workspace', 'gmail', 'twenty', 'cerase-email'], array_keys($catalogue));
        foreach ($catalogue as $slug => $block) {
            self::assertSame([], ApprovalDeclaration::violations($block), $slug);
        }
    }

    public function test_a_connector_that_declares_nothing_is_valid(): void
    {
        self::assertSame([], ApprovalDeclaration::violations(null));
        self::assertSame([], ApprovalDeclaration::violations([]));
    }

    public function test_every_key_in_its_shape_is_accepted(): void
    {
        self::assertSame([], ApprovalDeclaration::violations([
            'sentences' => [
                'send_email' => 'Inviare un\'email',
                'write' => ['operation' => ['create' => ['model' => ['res.partner' => 'Creare un contatto', '*' => 'Creare un record']]]],
            ],
            'outcomes' => ['send_email' => 'L\'email è stata inviata'],
            'approving' => ['execute_tool' => ['toolName' => ['create_*' => 'Crea subito un record', '*' => 'Esegue subito l\'operazione']]],
            'labels' => ['stage' => 'Fase'],
            'unwrap' => ['arguments'],
            'amounts_in_micros' => ['amountMicros' => 'currencyCode', 'budgetMicros' => null],
            'choices' => [['fields' => ['stage'], 'values' => ['NEW' => 'Nuova']]],
            'named_ids' => ['companyId' => 'company'],
            'linked_to' => ['targetCompanyId'],
            'dates' => ['hs_timestamp'],
            'link' => ['type' => 'objectType'],
            'record_names' => ['id' => ['id'], 'name' => ['{name}'], 'text_answers' => ['search_emails']],
            'recipients' => [
                'send_email' => ['to' => 'to', 'cc' => 'cc', 'bcc' => 'bcc'],
                'execute_tool' => ['toolName' => ['send_email' => ['to' => 'arguments.recipients.to']]],
            ],
            'attachments' => ['send_email' => 'attachments'],
            'failures' => ['Error: '],
        ]));
    }

    public function test_a_key_left_empty_is_read_as_not_declared(): void
    {
        self::assertSame([], ApprovalDeclaration::violations(['sentences' => null, 'recipients' => null]));
    }

    /**
     * One block per rule, and the sentence that names what is wrong with it.
     *
     * @return array<string, array{mixed, string}>
     */
    public static function malformedBlocks(): array
    {
        return [
            'a list, not a map' => [['a', 'b'], 'approval_display must be a map, got array'],
            'a sentence, not a map' => ['Inviare', 'approval_display must be a map, got string'],
            'an unknown key' => [['wording' => []],
                'approval_display has unknown key(s) [wording] — it takes sentences, outcomes, approving, labels, unwrap, '
                .'amounts_in_micros, choices, named_ids, linked_to, dates, link, record_names, recipients, attachments, failures'],
            'sentences a list' => [['sentences' => ['Inviare']], 'approval_display.sentences must map a tool to a sentence or to rules'],
            'an empty sentence' => [['sentences' => ['send_email' => ' ']], 'approval_display.sentences.send_email is empty'],
            'what approving does as a list' => [['approving' => ['draft_email' => ['x', 'y']]], 'approval_display.approving.draft_email must be a sentence or rules'],
            'an empty outcome under an operation' => [['outcomes' => ['execute_tool' => ['toolName' => ['send_email' => '']]]],
                'approval_display.outcomes.execute_tool.toolName.send_email is empty'],
            'a label that is not words' => [['labels' => ['stage' => ['x']]], 'approval_display.labels must map a name to words'],
            'named ids as a list' => [['named_ids' => ['company']], 'approval_display.named_ids must map a name to words'],
            'a blank link' => [['link' => ['type' => ' ']], 'approval_display.link must map a name to words'],
            'unwrap as a map' => [['unwrap' => ['arguments' => true]], 'approval_display.unwrap must be a list of names'],
            'a blank name linked to' => [['linked_to' => ['targetCompanyId', '']], 'approval_display.linked_to must be a list of names'],
            'a date that is not a name' => [['dates' => [7]], 'approval_display.dates must be a list of names'],
            'an amount without its currency field' => [['amounts_in_micros' => ['amountMicros']],
                'approval_display.amounts_in_micros must map the amount field to its currency field'],
            'an amount with a blank currency field' => [['amounts_in_micros' => ['amountMicros' => '']],
                'approval_display.amounts_in_micros must map the amount field to its currency field'],
            'choices as a map' => [['choices' => ['stage' => ['NEW' => 'Nuova']]], 'approval_display.choices must be a list of {fields, values}'],
            'a choice without values' => [['choices' => [['fields' => ['stage']]]], 'approval_display.choices.0 must be {fields, values}'],
            'a choice with another key' => [['choices' => [['fields' => ['stage'], 'values' => ['NEW' => 'Nuova'], 'default' => 'NEW']]],
                'approval_display.choices.0 must be {fields, values}'],
            'a choice whose fields are not names' => [['choices' => [['fields' => 'stage', 'values' => ['NEW' => 'Nuova']]]],
                'approval_display.choices.0.fields must be a list of names'],
            'a choice whose values are not words' => [['choices' => [['fields' => ['stage'], 'values' => ['NEW' => 1]]]],
                'approval_display.choices.0.values must map a name to words'],
            'record names under another key' => [['record_names' => ['fields' => ['id']]], 'approval_display.record_names takes id, name, text_answers'],
            'record names as a list' => [['record_names' => [['id']]], 'approval_display.record_names takes id, name, text_answers'],
            'a record name that is not a list' => [['record_names' => ['name' => '{name}']], 'approval_display.record_names.name must be a list of names'],
            'recipients as a list' => [['recipients' => ['to']], 'approval_display.recipients must map a tool to its recipients'],
            'a recipient role nobody reads' => [['recipients' => ['send_email' => ['from' => 'from']]],
                'approval_display.recipients.send_email must map to, cc, bcc to the path of an argument, or name the argument that names the operation'],
            'a recipient path that is not a name' => [['recipients' => ['send_email' => ['to' => ['to']]]],
                'approval_display.recipients.send_email must map to, cc, bcc to the path of an argument, or name the argument that names the operation'],
            'an operation with no roles' => [['recipients' => ['execute_tool' => ['toolName' => ['send_email' => 'to']]]],
                'approval_display.recipients.execute_tool must map to, cc, bcc to the path of an argument, or name the argument that names the operation'],
            'attachments as a list' => [['attachments' => ['attachments']], 'approval_display.attachments must map a tool to the argument naming its attachments'],
            'attachments naming no argument' => [['attachments' => ['email_send' => ' ']], 'approval_display.attachments.email_send must name an argument of the tool'],
            'failures as a map' => [['failures' => ['error' => 'Error: ']], 'approval_display.failures must be a list of the openings of a failed answer'],
            'a blank failure' => [['failures' => ['Error: ', '']], 'approval_display.failures must be a list of the openings of a failed answer'],
        ];
    }

    #[DataProvider('malformedBlocks')]
    public function test_a_declaration_nobody_could_read_is_refused_naming_the_key(mixed $block, string $message): void
    {
        self::assertSame([$message], ApprovalDeclaration::violations($block));
    }

    public function test_every_defect_is_reported_not_only_the_first(): void
    {
        self::assertSame([
            'approval_display has unknown key(s) [wording,display] — it takes '.implode(', ', ApprovalDeclaration::KEYS),
            'approval_display.sentences.send_email is empty',
            'approval_display.approving.draft_email must be a sentence or rules',
            'approval_display.labels must map a name to words',
            'approval_display.choices.1 must be {fields, values}',
            'approval_display.recipients.send_email must map to, cc, bcc to the path of an argument, or name the argument that names the operation',
            'approval_display.recipients.draft_email must map to, cc, bcc to the path of an argument, or name the argument that names the operation',
        ], ApprovalDeclaration::violations([
            'wording' => [],
            'display' => [],
            'sentences' => ['send_email' => '', 'draft_email' => 'Preparare una bozza'],
            'approving' => ['draft_email' => ['x']],
            'labels' => ['to' => 'A', 'cc' => ['Cc'], 'bcc' => ''],
            'choices' => [['fields' => ['stage'], 'values' => ['NEW' => 'Nuova']], ['fields' => ['status']]],
            'recipients' => ['send_email' => ['from' => 'from'], 'draft_email' => ['to' => '']],
        ]));
    }

    public function test_the_two_consumers_read_the_keys_and_the_roles_from_here(): void
    {
        self::assertSame([
            'sentences', 'outcomes', 'approving', 'labels', 'unwrap', 'amounts_in_micros', 'choices',
            'named_ids', 'linked_to', 'dates', 'link', 'record_names', 'recipients', 'attachments', 'failures',
        ], ApprovalDeclaration::KEYS);
        self::assertSame(['to', 'cc', 'bcc'], ApprovalDeclaration::RECIPIENT_ROLES);
    }

    public function test_a_write_is_covered_by_its_sentence_and_what_approving_it_does(): void
    {
        $block = [
            'sentences' => ['send_email' => 'Inviare un\'email', 'draft_email' => 'Preparare una bozza', 'delete_email' => 'Eliminare un\'email'],
            'outcomes' => ['delete_email' => 'L\'email è stata eliminata', 'create_label' => 'L\'etichetta è stata creata'],
            'approving' => ['send_email' => 'Invia subito un\'email', 'create_label' => 'Crea un\'etichetta'],
        ];

        self::assertSame(
            ['draft_email', 'delete_email', 'create_label', 'update_label'],
            ApprovalDeclaration::missingFor($block, ['send_email', 'draft_email', 'delete_email', 'create_label', 'update_label']),
        );
    }

    public function test_an_entry_keyed_by_the_argument_naming_the_operation_covers_its_tool(): void
    {
        $block = [
            'sentences' => ['execute_tool' => ['toolName' => ['send_email' => 'Inviare un\'email', '*' => 'Eseguire un\'operazione']]],
            'approving' => ['execute_tool' => ['toolName' => ['send_email' => 'Invia subito un\'email']]],
        ];

        self::assertSame([], ApprovalDeclaration::missingFor($block, ['execute_tool']));
    }

    public function test_an_entry_that_words_nothing_covers_nothing(): void
    {
        $block = [
            'sentences' => ['send_email' => '  ', 'execute_tool' => ['toolName' => ['send_email' => 'Inviare un\'email']]],
            'approving' => ['send_email' => 'Invia subito un\'email', 'execute_tool' => ['toolName' => []]],
        ];

        self::assertSame(['send_email', 'execute_tool'], ApprovalDeclaration::missingFor($block, ['send_email', 'execute_tool']));
    }

    public function test_a_connector_that_declares_nothing_leaves_every_write_uncovered_once(): void
    {
        self::assertSame(['send_email', 'draft_email'], ApprovalDeclaration::missingFor(null, ['send_email', 'draft_email', 'send_email']));
        self::assertSame(['send_email'], ApprovalDeclaration::missingFor('Inviare', ['send_email']));
        self::assertSame(['send_email'], ApprovalDeclaration::missingFor(['sentences' => 'Inviare', 'approving' => ['Invia']], ['send_email']));
        self::assertSame([], ApprovalDeclaration::missingFor(null, []));
    }
}
