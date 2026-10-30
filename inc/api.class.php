<?php

/**
 * CheckWarranty API Client for GLPI
 *
 * @package   PluginCheckwarranty
 * @author    CheckWarranty Team <support@checkwarranty.info>
 * @license   GPLv3+ https://www.gnu.org/licenses/gpl-3.0.html
 * @since     1.0.0
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginCheckwarrantyApi
{
    /**
     * Infer brand from manufacturer name, model name, or serial number
     *
     * @since 1.0.0
     *
     * @param string $serial
     * @param string $modelName
     * @param string $mfgName
     * @return string|null
     */
    public static function detectBrand(string $serial, string $modelName = '', string $mfgName = ''): ?string
    {
        $mfg = strtolower(trim($mfgName));
        $mod = strtolower(trim($modelName));
        $s   = strtoupper(trim($serial));

        // Check manufacturer
        if (str_contains($mfg, 'dell') || str_contains($mfg, 'poweredge')) {
            return 'dell';
        }
        if (str_contains($mfg, 'lenovo') || str_contains($mfg, 'think')) {
            return 'lenovo';
        }
        if (preg_match('/\b(hp|hpe|hewlett|probook|elitebook|zbook|proliant)\b/i', $mfg)) {
            return 'hp';
        }
        if (str_contains($mfg, 'apple') || str_contains($mfg, 'mac')) {
            return 'apple';
        }

        // Check model
        if (str_contains($mod, 'latitude') || str_contains($mod, 'optiplex') || str_contains($mod, 'precision') || str_contains($mod, 'poweredge')) {
            return 'dell';
        }
        if (str_contains($mod, 'thinkpad') || str_contains($mod, 'thinkcentre') || str_contains($mod, 'thinkstation') || str_contains($mod, 'thinksystem')) {
            return 'lenovo';
        }
        if (preg_match('/\b(hp|hpe|hewlett|probook|elitebook|zbook|proliant)\b/i', $mod)) {
            return 'hp';
        }
        if (str_contains($mod, 'macbook') || str_contains($mod, 'imac') || str_contains($mod, 'mac mini') || str_contains($mod, 'mac pro')) {
            return 'apple';
        }

        // Check serial syntax
        if (strlen($s) === 7 && ctype_alnum($s)) {
            return 'dell';
        }
        if (strlen($s) === 8 && ctype_alnum($s)) {
            return 'lenovo';
        }
        if (strlen($s) === 10 && (str_starts_with($s, '5CG') || str_starts_with($s, '5CD') || str_starts_with($s, 'CND') || str_starts_with($s, 'CZ'))) {
            return 'hp';
        }
        if (strlen($s) === 12 && str_starts_with($s, 'C02')) {
            return 'apple';
        }

        return null;
    }

    public static function validUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (!$parts || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            return false;
        }
        return ($parts['scheme'] ?? '') === 'https'
            || (defined('PLUGIN_CHECKWARRANTY_TESTING') && PLUGIN_CHECKWARRANTY_TESTING && ($parts['scheme'] ?? '') === 'http');
    }

    public static function lookupDevice(string $apiKey, string $apiUrl, string $serial, ?string $brand = null, ?string $requestKey = null, ?string $batchId = null): array
    {
        if (!self::validUrl($apiUrl)) {
            return ['status' => 'error'];
        }
        $base = rtrim($apiUrl, '/');
        $headers = ['Accept: application/json', 'Content-Type: application/json', 'X-API-Key: ' . trim($apiKey), 'User-Agent: CheckWarranty-GLPI/' . PLUGIN_CHECKWARRANTY_VERSION];
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
        ];
        if ($batchId !== null) {
            if (!preg_match('/^[a-z0-9-]{1,100}$/i', $batchId)) {
                return ['status' => 'error'];
            }
            $endpoint = $base . '/devices/scans/' . rawurlencode($batchId);
        } else {
            $endpoint = $base . ($brand ? '/devices/lookup' : '/devices/auto-detect');
            $payload = ['serialNumber' => $serial];
            if ($brand) {
                $payload['brand'] = $brand;
            }
            $headers[] = 'Idempotency-Key: ' . ($requestKey ?? bin2hex(random_bytes(16)));
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($payload);
        }
        $options[CURLOPT_HTTPHEADER] = $headers;
        $curl = curl_init($endpoint);
        curl_setopt_array($curl, $options);
        $body = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if (!is_string($body) || $code < 200 || $code >= 300) {
            return ['status' => 'error'];
        }
        $response = json_decode($body, true);
        if (!is_array($response)) {
            return ['status' => 'error'];
        }
        if ($batchId !== null && in_array($response['status'] ?? '', ['completed', 'partial', 'failed'], true)) {
            return isset($response['items'][0]) && is_array($response['items'][0]) ? $response['items'][0] : ['status' => 'error'];
        }
        return $response;
    }
}
