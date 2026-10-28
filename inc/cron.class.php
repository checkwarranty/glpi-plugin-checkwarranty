<?php
/**
 * CheckWarranty Background Scheduled Task (Cron) for GLPI
 *
 * @package   PluginCheckwarranty
 * @author    CheckWarranty Team <support@checkwarranty.info>
 * @license   GPLv3+ https://www.gnu.org/licenses/gpl-3.0.html
 * @since     1.0.0
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginCheckwarrantyCron extends CommonDBTM {

    /**
     * Automatic Action entrypoint: synchronizes warranty status for computers
     *
     * @since 1.0.0
     *
     * @param CronTask $task
     * @return int 0 on failure/idle, 1 on success
     */
    public static function cronCheckwarranty_sync(CronTask $task): int {
        global $DB;

        $config = PluginCheckwarrantyConfig::getConfig();
        $apiKey = trim($config['api_key'] ?? '');
        $apiUrl = trim($config['api_url'] ?? 'https://checkwarranty.info/api/v1');
        $recheckDays = max(0, (int)($config['recheck_days'] ?? 30));
        $syncSpecs = (bool)($config['sync_specs'] ?? true);

        if (empty($apiKey)) {
            $task->log('CheckWarranty API key is not configured. Task aborted.');
            return 0;
        }

        // Query computers with non-empty serial numbers
        $iterator = $DB->request([
            'SELECT'    => [
                'glpi_computers.id',
                'glpi_computers.name',
                'glpi_computers.serial',
                'glpi_manufacturers.name AS mfg_name',
                'glpi_computermodels.name AS model_name',
                'glpi_infocoms.id AS infocom_id',
                'glpi_infocoms.warranty_date',
            ],
            'FROM'      => 'glpi_computers',
            'LEFT JOIN' => [
                'glpi_manufacturers' => [
                    'FKEY' => [
                        'glpi_computers'     => 'manufacturers_id',
                        'glpi_manufacturers' => 'id',
                    ],
                ],
                'glpi_computermodels' => [
                    'FKEY' => [
                        'glpi_computers'      => 'computermodels_id',
                        'glpi_computermodels' => 'id',
                    ],
                ],
                'glpi_infocoms' => [
                    'ON' => [
                        'glpi_infocoms'  => 'items_id',
                        'glpi_computers' => 'id',
                        ['AND' => ['glpi_infocoms.itemtype' => 'Computer']],
                    ],
                ],
            ],
            'WHERE'     => [
                'glpi_computers.is_deleted' => 0,
                'NOT' => ['glpi_computers.serial' => ['', null]],
            ],
            'LIMIT'     => 50, // Batch limit per cron run
        ]);

        $processed = 0;
        $updated = 0;
        $now = time();

        foreach ($iterator as $row) {
            $computerId = (int)$row['id'];
            $serial = trim($row['serial']);
            $mfgName = $row['mfg_name'] ?? '';
            $modelName = $row['model_name'] ?? '';
            $existingWarrantyDate = $row['warranty_date'] ?? null;
            $infocomId = (int)($row['infocom_id'] ?? 0);

            // Smart recheck check
            if (!empty($existingWarrantyDate)) {
                $expTimestamp = strtotime($existingWarrantyDate);
                $daysRemaining = ($expTimestamp - $now) / 86400;
                if ($daysRemaining > $recheckDays) {
                    continue; // Skip: warranty is active and valid beyond threshold
                }
            }

            $brand = PluginCheckwarrantyApi::detectBrand($serial, $modelName, $mfgName);
            $processed++;

            $res = PluginCheckwarrantyApi::lookupDevice($apiKey, $apiUrl, $serial, $brand);
            if (!$res || ($res['status'] ?? '') === 'not_found') {
                continue;
            }

            $warranty = $res['data']['warranty'] ?? [];
            $specs = $res['data']['specs'] ?? [];

            $endDateStr = !empty($warranty['endDate']) ? substr($warranty['endDate'], 0, 10) : null;
            $startDateStr = !empty($warranty['startDate']) ? substr($warranty['startDate'], 0, 10) : null;
            $coverageType = $warranty['coverageType'] ?? ($warranty['description'] ?? '');

            if (!$endDateStr && !$startDateStr && empty($specs)) {
                continue;
            }

            // Calculate duration in months
            $durationMonths = null;
            if ($startDateStr && $endDateStr) {
                $d1 = strtotime($startDateStr);
                $d2 = strtotime($endDateStr);
                if ($d2 > $d1) {
                    $durationMonths = max(1, (int)round(($d2 - $d1) / (30.4375 * 86400)));
                } else {
                    $durationMonths = 0;
                }
            }

            // Build warranty notes
            $infoParts = [];
            if (!empty($coverageType)) {
                $infoParts[] = substr($coverageType, 0, 80);
            }
            if ($syncSpecs && !empty($specs)) {
                if (!empty($specs['cpu'])) {
                    $infoParts[] = 'CPU: ' . $specs['cpu'];
                }
                if (!empty($specs['ram'])) {
                    $infoParts[] = 'RAM: ' . $specs['ram'];
                }
                if (!empty($specs['storage'])) {
                    $infoParts[] = 'Disk: ' . $specs['storage'];
                }
            }

            $infocomData = [];
            if ($endDateStr) {
                $infocomData['warranty_date'] = $endDateStr;
            }
            if ($startDateStr) {
                $infocomData['buy_date'] = $startDateStr;
            }
            if ($durationMonths !== null) {
                $infocomData['warranty_duration'] = $durationMonths;
            }
            if (!empty($infoParts)) {
                $infocomData['warranty_info'] = substr(implode(' | ', $infoParts), 0, 255);
            }

            $infocom = new Infocom();
            if ($infocomId > 0) {
                $infocomData['id'] = $infocomId;
                $infocom->update($infocomData);
            } else {
                $infocomData['itemtype'] = 'Computer';
                $infocomData['items_id'] = $computerId;
                $infocom->add($infocomData);
            }

            $updated++;
        }

        $task->addVolume($updated);
        $task->log(sprintf('CheckWarranty Cron: %d processed, %d updated.', $processed, $updated));
        return 1;
    }
}
