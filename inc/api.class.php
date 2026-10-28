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

class PluginCheckwarrantyApi {

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
    public static function detectBrand(string $serial, string $modelName = '', string $mfgName = ''): ?string {
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

    /**
     * Query CheckWarranty REST API for device warranty and hardware specifications
     *
     * @since 1.0.0
     *
     * @param string      $apiKey
     * @param string      $apiUrl
     * @param string      $serial
     * @param string|null $brand
     * @return array|null
     */
    public static function lookupDevice(string $apiKey, string $apiUrl, string $serial, ?string $brand = null): ?array {
        $baseUrl = rtrim(trim($apiUrl), '/');
        $endpoint = $brand ? "$baseUrl/devices/lookup" : "$baseUrl/devices/auto-detect";

        $payload = ['serialNumber' => $serial];
        if ($brand) {
            $payload['brand'] = $brand;
        }

        $idempotencyKey = sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-API-Key: ' . trim($apiKey),
            "Idempotency-Key: $idempotencyKey",
            'User-Agent: CheckWarranty-GLPI-Plugin/' . PLUGIN_CHECKWARRANTY_VERSION,
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 404 || !$response) {
            return null;
        }

        $res = json_decode($response, true);
        if (!$res) {
            return null;
        }

        // If scan is queued asynchronously, poll batch endpoint
        if (in_array($res['status'] ?? '', ['in_progress', 'pending']) && !empty($res['batchId'])) {
            return self::pollBatch($apiKey, $baseUrl, $res['batchId']);
        }

        return $res;
    }

    /**
     * Poll batch scan endpoint
     *
     * @since 1.0.0
     *
     * @param string $apiKey
     * @param string $baseUrl
     * @param string $batchId
     * @param int    $retries
     * @return array|null
     */
    private static function pollBatch(string $apiKey, string $baseUrl, string $batchId, int $retries = 5): ?array {
        $url = "$baseUrl/devices/scans/$batchId";
        $headers = [
            'Accept: application/json',
            'X-API-Key: ' . trim($apiKey),
            'User-Agent: CheckWarranty-GLPI-Plugin/' . PLUGIN_CHECKWARRANTY_VERSION,
        ];

        for ($i = 0; $i < $retries; $i++) {
            sleep(4);
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 20,
            ]);
            $response = curl_exec($ch);
            curl_close($ch);

            if (!$response) {
                continue;
            }

            $res = json_decode($response, true);
            if (($res['status'] ?? '') === 'completed') {
                return !empty($res['items'][0]) ? $res['items'][0] : $res;
            }
            if (in_array($res['status'] ?? '', ['failed', 'error', 'not_found'])) {
                return $res;
            }
        }

        return null;
    }
}
