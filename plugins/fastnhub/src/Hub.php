<?php
namespace GlpiPlugin\Fastnhub;

use CommonGLPI;
use Session;

class Hub extends CommonGLPI
{
    public static string $rightname = 'config';

    public static function getTypeName($nb = 0)
    {
        return 'fastn Integrations';
    }

    public static function getIcon()
    {
        return 'ti ti-plug-connected';
    }

    public static function getMenuContent()
    {
        if (!Session::haveRight('config', READ)) {
            return false;
        }
        return [
            'title' => self::getTypeName(),
            'page'  => '/plugins/fastnhub/front/hub.php',
            'icon'  => self::getIcon(),
        ];
    }

    /** Settings: environment variables win, then config.php next to this plugin. */
    public static function settings(): array
    {
        $file = [];
        $path = dirname(__DIR__) . '/config.php';
        if (is_readable($path)) {
            $loaded = include $path;
            if (is_array($loaded)) {
                $file = $loaded;
            }
        }
        $get = function (string $env, string $key, $default = '') use ($file) {
            $v = getenv($env);
            return ($v !== false && $v !== '') ? $v : ($file[$key] ?? $default);
        };
        return [
            'api_key'       => $get('FASTN_API_KEY', 'api_key'),
            'customer_id'   => $get('FASTN_CUSTOMER_ID', 'customer_id', 'glpi-internal'),
            'customer_name' => $get('FASTN_CUSTOMER_NAME', 'customer_name', 'GLPI (internal)'),
            'api_base'      => rtrim($get('FASTN_API_BASE', 'api_base', 'https://api.fastn.dev'), '/'),
            // Service account used so users never type GLPI credentials into the widget.
            'glpi'          => is_array($file['glpi'] ?? null) ? $file['glpi'] : [],
        ];
    }

    private const GLPI_CONNECTOR_ID  = '5c1ad59b-03de-487c-95f7-e08da847ea46';
    private const GLPI_AUTH_METHOD   = 'd3e02dbb-8d8b-465c-b74d-321d63f70a1e';
    private const GLPI_CONNECTION    = 'glpi-service-account';

    /**
     * Makes sure fastn already holds a GLPI connection, so the user is not asked for GLPI credentials.
     * Safe to call repeatedly: it only creates the connection when it is missing.
     */
    private static function ensureGlpiConnection(array $s): void
    {
        $g = $s['glpi'];
        foreach (['baseUrl', 'clientId', 'clientSecret', 'username', 'password'] as $key) {
            if (empty($g[$key])) {
                return; // not configured: fall back to the normal widget form
            }
        }
        $hdr  = ['x-org-id: ' . $s['customer_id']];
        $list = self::call($s, 'GET', '/api/v1/connections', [], $hdr);
        foreach (($list['json']['data'] ?? []) as $c) {
            if (($c['name'] ?? '') === self::GLPI_CONNECTION && ($c['connectorId'] ?? '') === self::GLPI_CONNECTOR_ID) {
                return;
            }
        }
        self::call($s, 'POST', '/api/v1/connections', [
            'connectorId' => self::GLPI_CONNECTOR_ID,
            'authMethodId' => self::GLPI_AUTH_METHOD,
            'name'        => self::GLPI_CONNECTION,
            'credentials' => [
                'baseUrl'      => rtrim($g['baseUrl'], '/'),
                'clientId'     => $g['clientId'],
                'clientSecret' => $g['clientSecret'],
                'username'     => $g['username'],
                'password'     => $g['password'],
            ],
        ], $hdr);
    }

    private static function call(array $s, string $method, string $path, array $body, array $extraHeaders = []): array
    {
        $headers = array_merge([
            'Authorization: Bearer ' . $s['api_key'],
            'Content-Type: application/json',
            'Accept: application/json',
        ], $extraHeaders);
        if (strpos($s['api_key'], 'fsk_test_') === 0) {
            $headers[] = 'X-fastn-Test-Mode: true';
        }
        $ch = curl_init($s['api_base'] . $path);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
        ]);
        if ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
        $raw    = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);
        curl_close($ch);
        return ['status' => $status, 'json' => json_decode((string) $raw, true), 'raw' => (string) $raw, 'error' => $err];
    }

    /**
     * Returns a fastn embed token for this GLPI user (cached in the PHP session ~7h).
     * The API key never leaves the server.
     */
    public static function embedToken(): string
    {
        $cached = $_SESSION['plugin_fastnhub_token'] ?? null;
        if (is_array($cached) && ($cached['exp'] ?? 0) > time()) {
            return $cached['token'];
        }
        $s = self::settings();
        if ($s['api_key'] === '') {
            throw new \RuntimeException('No fastn API key configured. Set FASTN_API_KEY or fill in plugins/fastnhub/config.php.');
        }
        // Register the customer once (a 409 means it already exists).
        self::call($s, 'POST', '/api/v1/orgs', ['name' => $s['customer_name'], 'orgId' => $s['customer_id']]);

        try {
            self::ensureGlpiConnection($s);
        } catch (\Throwable $e) {
            // Never block the widget; the user can still connect GLPI by hand.
        }

        $userId = 'glpi-user-' . (int) Session::getLoginUserID();
        $r = self::call($s, 'POST', '/api/v1/embed/token', ['userId' => $userId], ['x-org-id: ' . $s['customer_id']]);
        $token = $r['json']['data']['token'] ?? ($r['json']['token'] ?? null);
        if (!$token) {
            throw new \RuntimeException('fastn refused the embed token (HTTP ' . $r['status'] . '): ' . substr($r['raw'] ?: $r['error'], 0, 300));
        }
        $_SESSION['plugin_fastnhub_token'] = ['token' => $token, 'exp' => time() + 7 * 3600];
        return $token;
    }
}
