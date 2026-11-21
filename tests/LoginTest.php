<?php

declare(strict_types=1);

namespace Cerase\ConnectorSchema\Tests;

use Cerase\ConnectorSchema\Login;
use Cerase\ConnectorSchema\Rules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * How a sign-in made in the connector's own browser runs: only a `browser`
 * connector declares it, in a block of arguments for its own image, an
 * optional viewer port, the steps a person follows and how long a code it
 * prints lasts; the login is kept in the connector's state.
 */
final class LoginTest extends TestCase
{
    private const NON_BROWSER = 'auth_login: only a connector whose sign-in is made in its own browser declares how it runs.';

    private const REQUIRED = 'auth_login: a connector whose sign-in is made in its own browser declares how it runs.';

    private const NOT_A_MAP = 'auth_login must be a mapping of args, port, steps and code_seconds.';

    private const ARGS_INVALID = 'auth_login: args must be a list of 1 to 8 arguments.';

    private const PORT_INVALID = 'auth_login: port must be a whole number from 1024 to 65535, other than 3000 and 3001.';

    private const STEPS_NOT_A_MAP = 'auth_login: steps must be a mapping from a language to its list of steps.';

    private const STEPS_IT_REQUIRED = 'auth_login › steps: it is required, since the console shows the steps in Italian.';

    private const CODE_SECONDS_INVALID = 'auth_login: code_seconds must be a whole number from 10 to 900.';

    private const CODE_SECONDS_WITH_PORT = 'auth_login: code_seconds is declared only by a sign-in that prints a code, which has no port.';

    private const WITHOUT_IMAGE = 'auth_login: a sign-in in the connector\'s own browser runs in its own image.';

    private const WITHOUT_STATE = 'auth_login: the sign-in is kept in the connector\'s state, so a connector whose sign-in is made in its own browser declares one.';

    private const IT_WITHOUT_IMAGE = 'auth_login: un accesso nel browser del connettore si svolge nella sua propria immagine.';

    private const IT_CODE_SECONDS_INVALID = 'auth_login: code_seconds dev\'essere un numero intero da 10 a 900.';

    private const IT_STEPS_IT_REQUIRED = 'auth_login › steps: it è obbligatoria, perché la console mostra i passi in italiano.';

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private static function descriptor(array $overrides = []): array
    {
        return array_replace([
            'auth_kind' => 'browser',
            'install_mode' => 'image',
            'install_image' => 'ghcr.io/example/browser:1',
            'install_state' => ['path' => '/home/node/.login', 'max_mb' => 128],
            'auth_login' => self::login(),
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private static function login(array $overrides = []): array
    {
        return array_replace(['args' => ['--login']], $overrides);
    }

    private static function unknownKey(string $key): string
    {
        return "auth_login: {$key} is not one of args, port, steps and code_seconds.";
    }

    private static function unknownStepLanguage(string $language): string
    {
        return "auth_login › steps: {$language} is not one of en and it.";
    }

    private static function invalidStepList(string $language): string
    {
        return "auth_login › steps › {$language}: a list of 1 to 8 steps.";
    }

    private static function invalidStep(string $language, int $position): string
    {
        return "auth_login › steps › {$language} › {$position}: a step is one line of 1 to 200 characters of plain text, with no < and no control character.";
    }

    private static function badArg(mixed $arg): string
    {
        $shown = is_string($arg) ? $arg : (string) json_encode($arg);

        return "auth_login › args › {$shown}: an argument is 1 to 100 characters of letters, digits and _ . / : = @ % + -.";
    }

    public function test_the_constants_are_the_descriptor_contract(): void
    {
        self::assertSame('auth_login', Login::KEY);
    }

    public function test_a_browser_login_with_args_only_is_accepted(): void
    {
        self::assertSame([], Login::violations(self::descriptor()));
    }

    public function test_a_browser_login_with_args_and_a_port_is_accepted(): void
    {
        self::assertSame([], Login::violations(self::descriptor([
            'auth_login' => self::login(['port' => 6080]),
        ])));
    }

    public function test_a_browser_login_accepts_the_port_bounds(): void
    {
        foreach ([1024, 65535] as $port) {
            self::assertSame([], Login::violations(self::descriptor([
                'auth_login' => self::login(['port' => $port]),
            ])), "port {$port}");
        }
    }

    public function test_an_argument_may_use_the_image_argument_alphabet(): void
    {
        self::assertSame([], Login::violations(self::descriptor([
            'auth_login' => self::login([
                'args' => ['a_b./c:d=e@f%g+h-i', str_repeat('a', 100)],
            ]),
        ])));
    }

    public function test_a_browser_descriptor_passes_the_served_kind_rule(): void
    {
        self::assertSame([], Rules::crossFieldViolations(['auth_kind' => 'browser']));
    }

    public function test_a_non_browser_login_is_refused(): void
    {
        self::assertSame(
            [self::NON_BROWSER],
            Login::violations(self::descriptor([
                'auth_kind' => 'none',
                'auth_login' => self::login(['port' => 6080]),
            ])),
        );
    }

    public function test_a_non_browser_descriptor_may_leave_the_login_absent(): void
    {
        self::assertSame([], Login::violations(self::descriptor([
            'auth_kind' => 'oauth2',
            'auth_login' => null,
        ])));

        self::assertSame([], Login::violations([
            'auth_kind' => 'bearer',
            'install_mode' => 'image',
            'install_image' => 'ghcr.io/example/browser:1',
            'install_state' => ['path' => '/home/node/.login', 'max_mb' => 128],
        ]));
    }

    public function test_a_browser_login_is_required(): void
    {
        self::assertSame(
            [self::REQUIRED],
            Login::violations(self::descriptor(['auth_login' => null])),
        );
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function notMaps(): iterable
    {
        yield 'a string' => ['--login'];
        yield 'a number' => [42];
        yield 'a list' => [['--login', 6080]];
    }

    #[DataProvider('notMaps')]
    public function test_a_login_that_is_not_a_mapping_is_refused(mixed $login): void
    {
        self::assertSame(
            [self::NOT_A_MAP],
            Login::violations(self::descriptor(['auth_login' => $login])),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unknownKeys(): iterable
    {
        yield 'extra' => ['extra'];
        yield 'mode' => ['mode'];
        yield 'viewer' => ['viewer'];
    }

    #[DataProvider('unknownKeys')]
    public function test_an_unknown_key_is_refused_and_named(string $key): void
    {
        self::assertSame(
            [self::unknownKey($key)],
            Login::violations(self::descriptor([
                'auth_login' => self::login([$key => 'x']),
            ])),
        );
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidArgs(): iterable
    {
        yield 'missing' => [['port' => 6080]];
        yield 'empty' => [['args' => []]];
        yield 'a string' => [['args' => '--login']];
        yield 'a map' => [['args' => ['login' => '--login']]];
        yield 'too many' => [['args' => array_fill(0, 9, '--login')]];
    }

    /**
     * @param  array<string, mixed>  $login
     */
    #[DataProvider('invalidArgs')]
    public function test_args_must_be_a_list_of_one_to_eight(array $login): void
    {
        self::assertSame(
            [self::ARGS_INVALID],
            Login::violations(self::descriptor(['auth_login' => $login])),
        );
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function badArgs(): iterable
    {
        yield 'a space' => ['--log in'];
        yield 'a shell character' => ['--login;rm'];
        yield 'a quote' => ['--login"'];
        yield 'longer than 100 characters' => [str_repeat('a', 101)];
        yield 'a number' => [42];
    }

    #[DataProvider('badArgs')]
    public function test_a_bad_argument_is_refused_and_named(mixed $arg): void
    {
        self::assertSame(
            [self::badArg($arg)],
            Login::violations(self::descriptor([
                'auth_login' => self::login(['args' => ['--login', $arg]]),
            ])),
        );
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidPorts(): iterable
    {
        yield 'a numeric string' => ['6080'];
        yield 'a float' => [6080.5];
        yield 'a boolean' => [true];
        yield 'below the range' => [1023];
        yield 'above the range' => [65536];
        yield 'the first platform port' => [3000];
        yield 'the second platform port' => [3001];
    }

    #[DataProvider('invalidPorts')]
    public function test_a_port_is_a_whole_number_from_1024_to_65535_other_than_the_platform_ports(mixed $port): void
    {
        self::assertSame(
            [self::PORT_INVALID],
            Login::violations(self::descriptor([
                'auth_login' => self::login(['port' => $port]),
            ])),
        );
    }

    public function test_a_browser_login_with_steps_and_a_code_is_accepted(): void
    {
        self::assertSame([], Login::violations(self::descriptor([
            'auth_login' => [
                'args' => ['--login'],
                'steps' => [
                    'it' => [
                        "Apri l'app Esempio sul telefono.",
                        'Tocca Impostazioni → Dispositivi → Collega un dispositivo.',
                        'Inquadra il codice qui sotto.',
                    ],
                    'en' => [
                        'Open the Esempio app on your phone.',
                        'Tap Settings → Devices → Link a device.',
                        'Scan the code below.',
                    ],
                ],
                'code_seconds' => 160,
            ],
        ])));
    }

    public function test_a_browser_login_accepts_only_italian_steps(): void
    {
        self::assertSame([], Login::violations(self::descriptor([
            'auth_login' => self::login([
                'steps' => ['it' => ["Apri l'app Esempio."]],
            ]),
        ])));
    }

    public function test_a_browser_login_accepts_a_step_of_exactly_two_hundred_characters(): void
    {
        foreach ([str_repeat('a', 200), str_repeat('è', 200)] as $step) {
            self::assertSame([], Login::violations(self::descriptor([
                'auth_login' => self::login(['steps' => ['it' => [$step]]]),
            ])));
        }
    }

    public function test_a_browser_login_accepts_eight_steps(): void
    {
        self::assertSame([], Login::violations(self::descriptor([
            'auth_login' => self::login([
                'steps' => ['it' => array_fill(0, 8, "Apri l'app Esempio.")],
            ]),
        ])));
    }

    public function test_a_step_may_carry_plain_text_marks_and_accents(): void
    {
        self::assertSame([], Login::violations(self::descriptor([
            'auth_login' => self::login([
                'steps' => ['it' => [
                    'Tocca Impostazioni → Dispositivi → Collega «Esempio» & altro.',
                    'Perché è così: > & «» sono ammessi.',
                ]],
            ]),
        ])));
    }

    public function test_a_browser_login_accepts_the_code_seconds_bounds(): void
    {
        foreach ([10, 900] as $seconds) {
            self::assertSame([], Login::violations(self::descriptor([
                'auth_login' => self::login(['code_seconds' => $seconds]),
            ])), "code_seconds {$seconds}");
        }

        self::assertSame([], Login::violations(self::descriptor([
            'auth_login' => self::login(['port' => null, 'code_seconds' => 160]),
        ])));
    }

    public function test_null_steps_and_code_seconds_declare_nothing(): void
    {
        self::assertSame([], Login::violations(self::descriptor([
            'auth_login' => self::login(['steps' => null, 'code_seconds' => null]),
        ])));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidStepsBlocks(): iterable
    {
        yield 'a string' => ["Apri l'app Esempio."];
        yield 'a number' => [42];
        yield 'a list' => [['Apri', 'Inquadra']];
        yield 'an empty array' => [[]];
    }

    #[DataProvider('invalidStepsBlocks')]
    public function test_steps_must_be_a_mapping_from_a_language_to_its_list(mixed $steps): void
    {
        self::assertSame(
            [self::STEPS_NOT_A_MAP],
            Login::violations(self::descriptor([
                'auth_login' => self::login(['steps' => $steps]),
            ])),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unknownStepLanguages(): iterable
    {
        yield 'fr' => ['fr'];
        yield 'de' => ['de'];
    }

    #[DataProvider('unknownStepLanguages')]
    public function test_a_step_language_must_be_english_or_italian(string $language): void
    {
        self::assertSame(
            [self::unknownStepLanguage($language)],
            Login::violations(self::descriptor([
                'auth_login' => self::login([
                    'steps' => [
                        'it' => ["Apri l'app Esempio."],
                        $language => ['Ouvre l\'application Esempio.'],
                    ],
                ]),
            ])),
        );
    }

    public function test_steps_require_italian(): void
    {
        self::assertSame(
            [self::STEPS_IT_REQUIRED],
            Login::violations(self::descriptor([
                'auth_login' => self::login([
                    'steps' => ['en' => ['Open the Esempio app on your phone.']],
                ]),
            ])),
        );
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidStepLists(): iterable
    {
        yield 'a string' => [['it' => "Apri l'app Esempio."]];
        yield 'a map' => [['it' => ['first' => "Apri l'app Esempio."]]];
        yield 'an empty list' => [['it' => []]];
        yield 'nine steps' => [['it' => array_fill(0, 9, "Apri l'app Esempio.")]];
    }

    /**
     * @param  array<string, mixed>  $steps
     */
    #[DataProvider('invalidStepLists')]
    public function test_a_step_language_holds_a_list_of_one_to_eight(array $steps): void
    {
        self::assertSame(
            [self::invalidStepList('it')],
            Login::violations(self::descriptor([
                'auth_login' => self::login(['steps' => $steps]),
            ])),
        );
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function badSteps(): iterable
    {
        yield 'an empty string' => [''];
        yield 'only spaces' => ['   '];
        yield 'longer than two hundred characters' => [str_repeat('a', 201)];
        yield 'a line break' => ["a\nb"];
        yield 'a tab' => ["a\tb"];
        yield 'markup' => ['<b>x</b>'];
        yield 'a less-than sign' => ['a < b'];
        yield 'a number' => [42];
    }

    #[DataProvider('badSteps')]
    public function test_a_step_is_one_line_of_plain_text(mixed $step): void
    {
        self::assertSame(
            [self::invalidStep('it', 2)],
            Login::violations(self::descriptor([
                'auth_login' => self::login([
                    'steps' => ['it' => ["Apri l'app Esempio.", $step]],
                ]),
            ])),
        );
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidCodeSeconds(): iterable
    {
        yield 'below the range' => [9];
        yield 'above the range' => [901];
        yield 'zero' => [0];
        yield 'a numeric string' => ['160'];
        yield 'a float' => [160.0];
        yield 'a boolean' => [true];
    }

    #[DataProvider('invalidCodeSeconds')]
    public function test_code_seconds_is_a_whole_number_from_10_to_900(mixed $seconds): void
    {
        self::assertSame(
            [self::CODE_SECONDS_INVALID],
            Login::violations(self::descriptor([
                'auth_login' => self::login(['code_seconds' => $seconds]),
            ])),
        );
    }

    public function test_code_seconds_is_declared_only_without_a_port(): void
    {
        self::assertSame(
            [self::CODE_SECONDS_WITH_PORT],
            Login::violations(self::descriptor([
                'auth_login' => self::login([
                    'port' => 6080,
                    'code_seconds' => 160,
                ]),
            ])),
        );
    }

    public function test_the_new_violations_read_as_sentences_in_italian(): void
    {
        self::assertSame(
            [self::IT_CODE_SECONDS_INVALID],
            Login::violations(self::descriptor([
                'auth_login' => self::login(['code_seconds' => 5]),
            ]), 'it'),
        );

        self::assertSame(
            [self::IT_STEPS_IT_REQUIRED],
            Login::violations(self::descriptor([
                'auth_login' => self::login([
                    'steps' => ['en' => ['Open the Esempio app on your phone.']],
                ]),
            ]), 'it'),
        );
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function notOwnImage(): iterable
    {
        yield 'a remote connector' => [[
            'install_mode' => 'remote_url',
            'install_remote_url' => 'https://mcp.example.com',
        ]];

        yield 'a command connector' => [[
            'install_mode' => 'command',
            'install_command' => 'npx -y example-mcp@1',
        ]];

        yield 'a command connector that also carries an image' => [[
            'install_mode' => 'command',
            'install_command' => 'npx -y example-mcp@1',
            'install_image' => 'ghcr.io/example/browser:1',
        ]];

        yield 'a connector with no mode and no image' => [[
            'install_mode' => null,
            'install_image' => null,
            'install_command' => 'npx -y example-mcp@1',
        ]];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('notOwnImage')]
    public function test_a_browser_login_runs_its_own_image(array $overrides): void
    {
        self::assertSame(
            [self::WITHOUT_IMAGE],
            Login::violations(self::descriptor($overrides)),
        );
    }

    public function test_a_browser_login_with_no_mode_but_an_image_runs_its_own_image(): void
    {
        self::assertSame([], Login::violations(self::descriptor([
            'install_mode' => null,
            'install_image' => 'ghcr.io/example/browser:1',
        ])));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function withoutState(): iterable
    {
        yield 'null' => [['install_state' => null]];
        yield 'empty' => [['install_state' => []]];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('withoutState')]
    public function test_a_browser_login_declares_a_state(array $overrides): void
    {
        self::assertSame(
            [self::WITHOUT_STATE],
            Login::violations(self::descriptor($overrides)),
        );
    }

    public function test_a_browser_login_without_a_state_key_is_refused(): void
    {
        $descriptor = self::descriptor();
        unset($descriptor['install_state']);

        self::assertSame([self::WITHOUT_STATE], Login::violations($descriptor));
    }

    public function test_a_violation_reads_as_a_sentence_in_italian(): void
    {
        self::assertSame(
            [self::IT_WITHOUT_IMAGE],
            Login::violations(self::descriptor([
                'install_mode' => 'remote_url',
                'install_remote_url' => 'https://mcp.example.com',
            ]), 'it'),
        );
    }

    public function test_a_forms_login_is_stored_as_the_descriptor_reads_it(): void
    {
        self::assertSame(
            ['args' => ['--login'], 'port' => null],
            Login::normalize(['args' => [' --login ', '', '   ']]),
        );

        self::assertSame(
            ['args' => ['--login', '--login-viewer'], 'port' => 6080],
            Login::normalize([
                'args' => [' --login ', '', '--login-viewer', '  '],
                'port' => '6080',
            ]),
        );

        self::assertSame(
            ['args' => ['--login'], 'port' => 6080],
            Login::normalize(['args' => ['--login'], 'port' => 6080]),
        );
    }

    public function test_a_forms_login_without_new_keys_keeps_its_shape(): void
    {
        self::assertSame(
            ['args' => ['--login'], 'port' => null],
            Login::normalize([
                'args' => ['--login'],
                'port' => null,
                'steps' => null,
                'code_seconds' => null,
            ]),
        );
    }

    public function test_a_forms_steps_textarea_becomes_a_list_per_language(): void
    {
        self::assertSame(
            [
                'args' => ['--login'],
                'port' => null,
                'steps' => ['it' => ["Apri l'app Esempio.", 'Inquadra il codice.']],
            ],
            Login::normalize([
                'args' => ['--login'],
                'steps' => [
                    'it' => " Apri l'app Esempio. \n\n Inquadra il codice. \n",
                    'en' => '  ',
                ],
            ]),
        );
    }

    public function test_a_forms_steps_list_is_trimmed_and_blank_languages_dropped(): void
    {
        self::assertSame(
            [
                'args' => ['--login'],
                'port' => null,
                'steps' => ['it' => ['Apri', 'Inquadra']],
            ],
            Login::normalize([
                'args' => ['--login'],
                'steps' => [
                    'it' => [' Apri ', '', '  Inquadra  '],
                    'en' => ['  ', ''],
                ],
            ]),
        );

        self::assertSame(
            ['args' => ['--login'], 'port' => null],
            Login::normalize([
                'args' => ['--login'],
                'steps' => ['it' => ['  '], 'en' => []],
            ]),
        );
    }

    public function test_a_forms_steps_string_is_split_on_any_line_break(): void
    {
        self::assertSame(
            [
                'args' => ['--login'],
                'port' => null,
                'steps' => ['it' => ['Apri', 'Inquadra']],
            ],
            Login::normalize([
                'args' => ['--login'],
                'steps' => ['it' => "Apri\r\nInquadra"],
            ]),
        );
    }

    public function test_a_forms_login_orders_steps_before_code_seconds(): void
    {
        self::assertSame(
            [
                'args' => ['--login'],
                'port' => null,
                'steps' => ['it' => ['Apri']],
                'code_seconds' => 160,
            ],
            Login::normalize([
                'args' => ['--login'],
                'steps' => ['it' => 'Apri'],
                'code_seconds' => '160',
            ]),
        );
    }

    public function test_a_forms_code_seconds_becomes_an_int(): void
    {
        self::assertSame(
            ['args' => ['--login'], 'port' => null, 'code_seconds' => 160],
            Login::normalize([
                'args' => ['--login'],
                'port' => '',
                'code_seconds' => ' 160 ',
            ]),
        );

        self::assertSame(
            ['args' => ['--login'], 'port' => null, 'code_seconds' => 160],
            Login::normalize(['args' => ['--login'], 'code_seconds' => 160]),
        );
    }

    public function test_a_forms_code_seconds_that_is_not_a_number_is_omitted(): void
    {
        foreach (['  ', 'abc', 160.5] as $seconds) {
            self::assertSame(
                ['args' => ['--login'], 'port' => null],
                Login::normalize(['args' => ['--login'], 'code_seconds' => $seconds]),
            );
        }
    }

    public function test_a_blank_form_login_with_new_keys_declares_nothing(): void
    {
        self::assertNull(Login::normalize([
            'steps' => ['it' => ["Apri l'app Esempio."]],
            'code_seconds' => 160,
        ]));

        self::assertNull(Login::normalize([
            'args' => [],
            'port' => '',
            'steps' => ['it' => "Apri\nInquadra"],
            'code_seconds' => '160',
        ]));
    }

    public function test_a_normalized_form_login_with_steps_and_code_seconds_is_accepted(): void
    {
        $login = Login::normalize([
            'args' => [' --login '],
            'port' => '',
            'steps' => [
                'it' => " Apri l'app Esempio. \n Inquadra il codice. ",
                'en' => ' Open the Esempio app on your phone. ',
            ],
            'code_seconds' => ' 160 ',
        ]);

        self::assertSame(
            [
                'args' => ['--login'],
                'port' => null,
                'steps' => [
                    'it' => ["Apri l'app Esempio.", 'Inquadra il codice.'],
                    'en' => ['Open the Esempio app on your phone.'],
                ],
                'code_seconds' => 160,
            ],
            $login,
        );

        self::assertSame([], Login::violations(self::descriptor(['auth_login' => $login])));
    }

    public function test_the_steps_reader_returns_the_steps_of_the_requested_language(): void
    {
        $login = [
            'args' => ['--login'],
            'steps' => [
                'it' => ["Apri l'app Esempio.", 'Inquadra il codice.'],
                'en' => ['Open the Esempio app on your phone.', 'Scan the code below.'],
            ],
        ];

        self::assertSame(["Apri l'app Esempio.", 'Inquadra il codice.'], Login::steps($login, 'it'));
        self::assertSame(['Open the Esempio app on your phone.', 'Scan the code below.'], Login::steps($login, 'en'));

        self::assertSame(
            ['Apri', 'Inquadra'],
            Login::steps([
                'args' => ['--login'],
                'steps' => ['it' => " Apri \n Inquadra "],
            ], 'it'),
        );
    }

    public function test_the_steps_reader_falls_back_to_italian(): void
    {
        $login = [
            'args' => ['--login'],
            'steps' => ['it' => ["Apri l'app Esempio."]],
        ];

        self::assertSame(["Apri l'app Esempio."], Login::steps($login, 'en'));
        self::assertSame(["Apri l'app Esempio."], Login::steps($login, 'de'));
    }

    public function test_the_steps_reader_shows_nothing_for_an_invalid_declaration(): void
    {
        self::assertSame([], Login::steps([
            'args' => ['--login'],
            'steps' => ['it' => ['Apri <b>Esempio</b>.']],
        ], 'it'));

        self::assertSame([], Login::steps(null, 'it'));
        self::assertSame([], Login::steps('--login', 'it'));
        self::assertSame([], Login::steps(['args' => ['--login']], 'it'));
    }

    public function test_the_code_seconds_reader_returns_a_valid_lifetime(): void
    {
        self::assertSame(160, Login::codeSeconds(['args' => ['--login'], 'code_seconds' => 160]));
        self::assertSame(160, Login::codeSeconds(['args' => ['--login'], 'code_seconds' => '160']));
        self::assertSame(160, Login::codeSeconds(['args' => ['--login'], 'code_seconds' => ' 160 ']));
        self::assertNull(Login::codeSeconds(['args' => ['--login']]));
        self::assertNull(Login::codeSeconds(['args' => ['--login'], 'code_seconds' => 5]));
        self::assertNull(Login::codeSeconds([
            'args' => ['--login'],
            'port' => 6080,
            'code_seconds' => 160,
        ]));
        self::assertNull(Login::codeSeconds(null));
    }

    public function test_a_blank_form_login_declares_nothing(): void
    {
        self::assertNull(Login::normalize(null));
        self::assertNull(Login::normalize([]));
        self::assertNull(Login::normalize(['args' => [], 'port' => '']));
        self::assertNull(Login::normalize(['args' => ['  '], 'port' => '  ']));
        self::assertNull(Login::normalize('--login'));
    }

    public function test_a_normalized_form_login_is_accepted(): void
    {
        $login = Login::normalize([
            'args' => [' --login ', '', '--login-viewer'],
            'port' => '6080',
        ]);

        self::assertSame([], Login::violations(self::descriptor(['auth_login' => $login])));

        // A form that leaves the port empty stores it as null, which declares no viewer.
        $qr = Login::normalize(['args' => ['--login'], 'port' => '']);

        self::assertSame([], Login::violations(self::descriptor(['auth_login' => $qr])));
    }
}
