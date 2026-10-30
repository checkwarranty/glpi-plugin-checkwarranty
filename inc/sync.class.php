<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

/** Persistent scan state prevents new paid jobs during polling/retries. */
class PluginCheckwarrantySync
{
    public const TABLE = 'glpi_plugin_checkwarranty_results';

    public static function get(int $computerId): array
    {
        global $DB;
        foreach ($DB->request(['FROM' => self::TABLE, 'WHERE' => ['computers_id' => $computerId], 'LIMIT' => 1]) as $row) {
            return $row;
        }
        return [];
    }

    public static function run(Computer $computer, bool $manual = false): string
    {
        global $DB;
        $id = (int) $computer->getID();
        $serial = trim($computer->fields['serial'] ?? '');
        $config = PluginCheckwarrantyConfig::getConfig();
        if ($serial === '' || empty($config['api_key'])) {
            return 'skipped';
        }
        $state = self::get($id);
        $sameSerial = ($state['serial'] ?? '') === $serial;
        $now = time();
        $last = $sameSerial ? strtotime($state['date_checked'] ?? '') : false;
        $retry = $sameSerial && in_array($state['state'] ?? '', ['pending', 'error', 'storage_error'], true);
        $stored = $sameSerial ? json_decode($state['payload'] ?? '', true) : null;
        if (!$manual && is_array($stored)) {
            $end = strtotime($stored['warranty']['endDate'] ?? '') ?: 0;
            if (!$retry && $end > $now + max(0, (int) $config['recheck_days']) * DAY_TIMESTAMP) {
                return 'cached';
            }
        }
        // A manual double click also uses the recent result; explicit refresh is daily.
        $cacheSeconds = $manual ? DAY_TIMESTAMP : max(1, (int) $config['recheck_days']) * DAY_TIMESTAMP;
        if ($last && !$retry && $now - $last < $cacheSeconds) {
            return 'cached';
        }
        if ($sameSerial && $now - (strtotime($state['date_attempt'] ?? '') ?: 0) < 60) {
            return 'pending';
        }
        $requestKey = $retry ? $state['request_key'] : bin2hex(random_bytes(16));
        $values = [
            'computers_id' => $id, 'serial' => $serial, 'request_key' => $requestKey,
            'batch_id' => $retry ? ($state['batch_id'] ?? '') : '',
            'state' => 'pending', 'date_attempt' => date('Y-m-d H:i:s'),
        ];
        if (!$sameSerial) {
            $values['payload'] = null;
            $values['date_checked'] = null;
        }
        if ($state) {
            // Optimistic lock: one caller owns this attempt, including after a crash.
            $DB->update(self::TABLE, $values, ['id' => $state['id'], 'date_attempt' => $state['date_attempt']]);
            if ($DB->affectedRows() !== 1) {
                return 'pending';
            }
        } elseif (!$DB->insert(self::TABLE, $values)) {
            return 'pending';
        }
        $manufacturer = new Manufacturer();
        $model = new ComputerModel();
        $mfg = $manufacturer->getFromDB((int) ($computer->fields['manufacturers_id'] ?? 0)) ? $manufacturer->fields['name'] : '';
        $mod = $model->getFromDB((int) ($computer->fields['computermodels_id'] ?? 0)) ? $model->fields['name'] : '';
        $brand = PluginCheckwarrantyApi::detectBrand($config['auto_detect'] ? $serial : '', $config['auto_detect'] ? $mod : '', $mfg);
        if (!$config['auto_detect'] && $brand === null) {
            self::save($id, ['state' => 'skipped', 'date_checked' => date('Y-m-d H:i:s')]);
            return 'skipped';
        }
        $storageRetry = ($state['state'] ?? '') === 'storage_error' && is_array($stored);
        $response = $storageRetry ? ['status' => 'completed'] : PluginCheckwarrantyApi::lookupDevice($config['api_key'], $config['api_url'], $serial, $brand, $requestKey, $values['batch_id'] ?: null);
        if (in_array($response['status'] ?? '', ['in_progress', 'pending'], true)) {
            self::save($id, ['state' => 'pending', 'batch_id' => $response['batchId'] ?? $values['batch_id']]);
            return 'pending';
        }
        if (in_array($response['status'] ?? '', ['error', 'failed'], true)) {
            self::save($id, ['state' => 'error']);
            return 'error';
        }
        $result = $storageRetry ? $stored : PluginCheckwarrantyResult::normalize($response);
        if (!$result) {
            self::save($id, ['state' => 'not_found', 'date_checked' => date('Y-m-d H:i:s'), 'payload' => null, 'batch_id' => '']);
            return 'not_found';
        }
        $infocom = new Infocom();
        $exists = $infocom->getFromDBforDevice('Computer', $id);
        $payload = PluginCheckwarrantyResult::infocomPayload($result, $exists ? $infocom->fields : [], (bool) $config['sync_specs']);
        if ($payload) {
            $saved = $exists ? $infocom->update(['id' => $infocom->getID()] + $payload)
                : $infocom->add(['itemtype' => 'Computer', 'items_id' => $id] + $payload);
            if (!$saved) {
                // Retain the paid response for retrying storage without a new lookup.
                self::save($id, ['state' => 'storage_error', 'payload' => json_encode($result)]);
                return 'error';
            }
        }
        self::save($id, ['state' => 'completed', 'date_checked' => date('Y-m-d H:i:s'), 'payload' => json_encode($result), 'batch_id' => '']);
        return 'completed';
    }

    public static function purge(CommonDBTM $computer): void
    {
        global $DB;
        $DB->delete(self::TABLE, ['computers_id' => (int) $computer->getID()]);
    }

    private static function save(int $id, array $values): void
    {
        global $DB;
        $DB->update(self::TABLE, $values, ['computers_id' => $id]);
    }
}
