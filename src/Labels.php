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
        ];
    }
}
