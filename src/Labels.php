<?php

declare(strict_types=1);

namespace Cerase\ConnectorSchema;

/**
 * Locale catalogs for the connector-descriptor form. The English catalog is
 * byte-for-byte the copy the marketplace publisher form has shipped (so the
 * marketplace can adopt the package with zero visible change); the Italian
 * catalog is for the control-plane custom-connector form.
 *
 * Keys are dotted strings; a handful resolve to option maps (value => label).
 */
final class Labels
{
    /**
     * The supported locales.
     *
     * @var list<string>
     */
    public const LOCALES = ['en', 'it'];

    /**
     * Return the flat label catalog for a locale (falls back to English for an
     * unknown locale so a mis-configured app never renders blank labels).
     *
     * @return array<string, string|array<string, string>>
     */
    public static function for(string $locale): array
    {
        return match ($locale) {
            'it' => self::italian(),
            default => self::english(),
        };
    }

    /**
     * @return array<string, string|array<string, string>>
     */
    private static function english(): array
    {
        return [
            'auth.title' => 'Authentication',
            'auth.description' => 'How users connect this connector — shown on the listing. The repo\'s cerase.json remains the source of truth at install.',
            'auth.kind.label' => 'Auth kind',
            'auth.kind.options' => [
                'none' => 'None',
                'bearer' => 'API key / token (bearer)',
                'oauth2' => 'OAuth 2.0',
            ],
            'auth.registration.label' => 'OAuth client registration',
            'auth.registration.options' => [
                'preregistered' => 'Pre-registered (operator supplies client credentials)',
                'dynamic' => 'Dynamic (RFC 7591 — zero operator setup)',
            ],
            'auth.provider.label' => 'OAuth provider slug',
            'auth.provider.helper' => 'e.g. notion, linear, google — matches the provider this connector authenticates against.',
            'auth.instructions.label' => 'Connect instructions (Markdown)',
            'auth.instructions.helper' => 'Connector-specific notes shown in the connect flow, separate from the description.',

            'install.title' => 'Install',
            'install.description' => 'How the control-plane installs this connector — directly from the registry, no git clone. Pick a mode; the matching field appears.',
            'install.mode.label' => 'Install mode',
            'install.mode.options' => [
                'remote_url' => 'Remote MCP (hosted URL — nothing to install)',
                'command' => 'Command (stdio MCP, e.g. npx -y …)',
                'image' => 'Container image (GHCR)',
                'none' => 'None / clone the repo (legacy / content)',
            ],
            'install.mode.helper' => 'A self-describing connector installs with no repo — leave Git URL empty.',
            'install.remote_url.label' => 'Remote MCP URL',
            'install.command.label' => 'Install command',
            'install.command.helper' => 'e.g. npx -y airtable-mcp-server',
            'install.command.regex_message' => 'Only letters, digits, spaces and - _ . / : @ % + are allowed (no shell metacharacters).',
            'install.image.label' => 'Container image',
            'install.image.helper' => 'e.g. ghcr.io/cerase-ai/cerase-memory-mcp:latest',
            'install.image.regex_message' => 'Must be a valid image reference (letters, digits and . _ / : @ - + only).',
            'install.env_passthrough.label' => 'Env passthrough',
            'install.env_passthrough.helper' => 'Env vars the runner forwards to the connector process.',
            'credential.delivery.label' => 'Credential delivery',
            'credential.delivery.options' => [
                'header' => 'HTTP header (per-call Authorization)',
                'env' => 'Environment variable (per-connection runner)',
                'file' => 'Token file',
            ],
            'credential.env.label' => 'Credential env var',
            'credential.env.helper' => 'e.g. AIRTABLE_API_KEY — where the connector reads its credential.',
            'credential.scope.label' => 'Credential scope',
            'credential.scope.options' => [
                'user' => 'Personal (per user / assistant)',
                'tenant' => 'Organization (shared)',
            ],
            'scopes.label' => 'OAuth scopes',
            'scopes.helper' => 'e.g. read, write',
            'credential.files.label' => 'Credential files',
            'credential.files.helper' => 'Token files written into the connector runner (e.g. Google Drive).',
            'credential.files.path.label' => 'Path',
            'credential.files.template.label' => 'Template',

            'fields.parts.label' => 'Fields to fill in when connecting',
            'fields.parts.helper' => 'One per value an account needs. A connector authenticating with a key declares at least the key itself.',
            'fields.provider.label' => 'OAuth app fields',
            'fields.provider.helper' => 'Values of the OAuth app beyond its client ID and secret, typed once by an administrator.',
            'fields.add' => 'Add field',
            'fields.key.label' => 'Key',
            'fields.key.helper' => 'e.g. INSTANCE_URL — the variable the connector reads.',
            'fields.label.label' => 'Label',
            'fields.help.label' => 'Where the value is found',
            'fields.help.helper' => 'One sentence, shown under the field: where in the product the person finds this value.',
            'fields.secret.label' => 'Secret (masked, never sent to build a suggestion)',
            'fields.optional.label' => 'Optional',
            'fields.suggest.label' => 'Suggestion (optional)',
            'fields.suggest.description' => 'A request made once the fields it names are typed, whose answer fills this one. It may name only fields that are not secret, as {KEY}.',
            'fields.suggest.method.label' => 'Method',
            'fields.suggest.url.label' => 'URL',
            'fields.suggest.url.helper' => 'e.g. {INSTANCE_URL}/api/databases',
            'fields.suggest.body.label' => 'JSON body (POST)',
            'fields.suggest.value_at.label' => 'Where the value is in the answer',
            'fields.suggest.value_at.helper' => 'A dot path, e.g. result or data.*.name.',

            'violation.credential_fields_required' => 'A connector that authenticates with a key declares the fields the person types (credential_parts), each with the sentence saying where its value is found.',
            'violation.field_key_invalid' => ':list › :key: the key must be a variable name (letters, digits and _, not starting with a digit).',
            'violation.field_key_duplicate' => ':list › :key: two fields have this key.',
            'violation.field_label_required' => ':list › :key: the field has no label.',
            'violation.field_help_required' => ':list › :key: the field has no sentence saying where its value is found (help).',
            'violation.field_flag_invalid' => ':list › :key: :detail must be true or false.',
            'violation.field_suggestion_invalid' => ':list › :key: the suggestion is not valid — :detail.',
            'violation.field_suggestion_not_allowed' => ':list › :key: only a field of credential_parts may declare a suggestion.',
        ];
    }

    /**
     * @return array<string, string|array<string, string>>
     */
    private static function italian(): array
    {
        return [
            'auth.title' => 'Autenticazione',
            'auth.description' => 'Come gli utenti si collegano a questo connettore — mostrato nella scheda. Il file cerase.json del repo resta la fonte di verità all\'installazione.',
            'auth.kind.label' => 'Tipo di autenticazione',
            'auth.kind.options' => [
                'none' => 'Nessuna',
                'bearer' => 'Chiave API / token (bearer)',
                'oauth2' => 'OAuth 2.0',
            ],
            'auth.registration.label' => 'Registrazione client OAuth',
            'auth.registration.options' => [
                'preregistered' => 'Pre-registrata (l\'operatore fornisce le credenziali client)',
                'dynamic' => 'Dinamica (RFC 7591 — nessuna configurazione operatore)',
            ],
            'auth.provider.label' => 'Slug del provider OAuth',
            'auth.provider.helper' => 'es. notion, linear, google — corrisponde al provider verso cui il connettore si autentica.',
            'auth.instructions.label' => 'Istruzioni di collegamento (Markdown)',
            'auth.instructions.helper' => 'Note specifiche del connettore mostrate nel flusso di collegamento, separate dalla descrizione.',

            'install.title' => 'Installazione',
            'install.description' => 'Come il control-plane installa questo connettore — direttamente dal registro, senza clonare il repo. Scegli una modalità; compare il campo corrispondente.',
            'install.mode.label' => 'Modalità di installazione',
            'install.mode.options' => [
                'remote_url' => 'MCP remoto (URL ospitato — niente da installare)',
                'command' => 'Comando (MCP stdio, es. npx -y …)',
                'image' => 'Immagine container (GHCR)',
                'none' => 'Nessuna / clona il repo (legacy / contenuti)',
            ],
            'install.mode.helper' => 'Un connettore auto-descrittivo si installa senza repo — lascia vuoto il Git URL.',
            'install.remote_url.label' => 'URL MCP remoto',
            'install.command.label' => 'Comando di installazione',
            'install.command.helper' => 'es. npx -y airtable-mcp-server',
            'install.command.regex_message' => 'Sono ammessi solo lettere, cifre, spazi e - _ . / : @ % + (nessun metacarattere di shell).',
            'install.image.label' => 'Immagine container',
            'install.image.helper' => 'es. ghcr.io/cerase-ai/cerase-memory-mcp:latest',
            'install.image.regex_message' => 'Deve essere un riferimento immagine valido (lettere, cifre e . _ / : @ - + soltanto).',
            'install.env_passthrough.label' => 'Variabili d\'ambiente inoltrate',
            'install.env_passthrough.helper' => 'Variabili d\'ambiente che il runner inoltra al processo del connettore.',
            'credential.delivery.label' => 'Consegna delle credenziali',
            'credential.delivery.options' => [
                'header' => 'Header HTTP (Authorization per chiamata)',
                'env' => 'Variabile d\'ambiente (runner per connessione)',
                'file' => 'File del token',
            ],
            'credential.env.label' => 'Variabile d\'ambiente della credenziale',
            'credential.env.helper' => 'es. AIRTABLE_API_KEY — dove il connettore legge la sua credenziale.',
            'credential.scope.label' => 'Ambito della credenziale',
            'credential.scope.options' => [
                'user' => 'Personale (per utente / assistente)',
                'tenant' => 'Organizzazione (condivisa)',
            ],
            'scopes.label' => 'Scope OAuth',
            'scopes.helper' => 'es. read, write',
            'credential.files.label' => 'File delle credenziali',
            'credential.files.helper' => 'File di token scritti nel runner del connettore (es. Google Drive).',
            'credential.files.path.label' => 'Percorso',
            'credential.files.template.label' => 'Template',

            'fields.parts.label' => 'Campi da compilare per collegare un account',
            'fields.parts.helper' => 'Uno per ogni valore che serve a un account. Un connettore che si autentica con una chiave dichiara almeno la chiave.',
            'fields.provider.label' => 'Campi dell\'app OAuth',
            'fields.provider.helper' => 'Valori dell\'app OAuth oltre a client ID e secret, inseriti una volta da un amministratore.',
            'fields.add' => 'Aggiungi campo',
            'fields.key.label' => 'Chiave',
            'fields.key.helper' => 'es. INSTANCE_URL — la variabile che il connettore legge.',
            'fields.label.label' => 'Etichetta',
            'fields.help.label' => 'Dove si trova il valore',
            'fields.help.helper' => 'Una frase, mostrata sotto il campo: dove, nel prodotto, la persona trova questo valore.',
            'fields.secret.label' => 'Segreto (mascherato, mai usato per un suggerimento)',
            'fields.optional.label' => 'Facoltativo',
            'fields.suggest.label' => 'Suggerimento (facoltativo)',
            'fields.suggest.description' => 'Una richiesta fatta appena sono compilati i campi che nomina, la cui risposta compila questo. Può nominare solo campi non segreti, come {CHIAVE}.',
            'fields.suggest.method.label' => 'Metodo',
            'fields.suggest.url.label' => 'URL',
            'fields.suggest.url.helper' => 'es. {INSTANCE_URL}/api/databases',
            'fields.suggest.body.label' => 'Corpo JSON (POST)',
            'fields.suggest.value_at.label' => 'Dove si trova il valore nella risposta',
            'fields.suggest.value_at.helper' => 'Un percorso a punti, es. result oppure data.*.name.',

            'violation.credential_fields_required' => 'Un connettore che si autentica con una chiave dichiara i campi che la persona compila (credential_parts), ciascuno con la frase che dice dove si trova il suo valore.',
            'violation.field_key_invalid' => ':list › :key: la chiave dev\'essere un nome di variabile (lettere, cifre e _, senza cifra iniziale).',
            'violation.field_key_duplicate' => ':list › :key: due campi hanno questa chiave.',
            'violation.field_label_required' => ':list › :key: il campo non ha un\'etichetta.',
            'violation.field_help_required' => ':list › :key: il campo non ha la frase che dice dove si trova il suo valore (help).',
            'violation.field_flag_invalid' => ':list › :key: :detail dev\'essere true o false.',
            'violation.field_suggestion_invalid' => ':list › :key: il suggerimento non è valido — :detail.',
            'violation.field_suggestion_not_allowed' => ':list › :key: solo un campo di credential_parts può dichiarare un suggerimento.',
        ];
    }
}
