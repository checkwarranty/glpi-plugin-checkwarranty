<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginCheckwarrantyCron extends CommonDBTM
{
    public static function cronInfo($name): array
    {
        return ['description' => __('CheckWarranty automatic synchronization', 'checkwarranty')];
    }

    public static function cronCheckwarranty_sync(CronTask $task): int
    {
        global $DB;
        $config = PluginCheckwarrantyConfig::getConfig();
        if (empty($config['api_key'])) {
            return 0;
        }
        $rows = $DB->request([
            'FROM' => 'glpi_computers',
            'WHERE' => ['is_deleted' => 0, 'is_template' => 0, 'serial' => ['!=', ''], 'id' => ['>', (int) $config['sync_cursor']]],
            'ORDER' => 'id ASC', 'LIMIT' => 50,
        ]);
        $updated = 0;
        $visited = 0;
        $requests = 0;
        $deadline = microtime(true) + 35;
        foreach ($rows as $row) {
            $computer = new Computer();
            if (!$computer->getFromDB($row['id'])) {
                continue;
            }
            $status = PluginCheckwarrantySync::run($computer);
            $visited++;
            $updated += $status === 'completed' ? 1 : 0;
            $requests += in_array($status, ['completed', 'pending', 'error', 'not_found'], true) ? 1 : 0;
            $DB->update('glpi_plugin_checkwarranty_configs', ['sync_cursor' => (int) $row['id']], ['id' => 1]);
            if ($requests >= 4 || microtime(true) >= $deadline) {
                break;
            }
        }
        if ($visited === 0) {
            $DB->update('glpi_plugin_checkwarranty_configs', ['sync_cursor' => 0], ['id' => 1]);
        }
        $task->addVolume($updated);
        $task->log(sprintf('CheckWarranty: %d visited, %d updated.', $visited, $updated));
        return $visited > 0 ? 1 : 0;
    }
}
