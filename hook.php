<?php

/**
 * CheckWarranty Plugin for GLPI - Lifecycle Hooks
 *
 * @package   PluginCheckwarranty
 * @author    CheckWarranty Team <support@checkwarranty.info>
 * @license   GPLv3+ https://www.gnu.org/licenses/gpl-3.0.html
 * @since     1.0.0
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

/**
 * Install hook: creates configuration tables and registers cron task
 *
 * @since 1.0.0
 *
 * @return bool
 */
function plugin_checkwarranty_install(): bool
{
    global $DB;

    $migration = new Migration(PLUGIN_CHECKWARRANTY_VERSION);

    // 1. Create plugin configuration table
    $table = 'glpi_plugin_checkwarranty_configs';
    if (!$DB->tableExists($table)) {
        $query = "CREATE TABLE IF NOT EXISTS `$table` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `api_key` text DEFAULT NULL,
            `api_url` varchar(255) NOT NULL DEFAULT 'https://checkwarranty.info/api/v1',
            `recheck_days` int(11) NOT NULL DEFAULT 30,
            `sync_specs` tinyint(1) NOT NULL DEFAULT 1,
            `auto_detect` tinyint(1) NOT NULL DEFAULT 1,
            `date_mod` timestamp NULL DEFAULT NULL,
            `sync_cursor` int unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        if (!$DB->doQuery($query)) {
            return false;
        }

        // Insert initial default configuration row
        $DB->insert($table, [
            'id'           => 1,
            'api_key'      => '',
            'api_url'      => 'https://checkwarranty.info/api/v1',
            'recheck_days' => 30,
            'sync_specs'   => 1,
            'auto_detect'  => 1,
            'date_mod'     => date('Y-m-d H:i:s'),
        ]);
    }

    $migration->addField($table, 'sync_cursor', 'integer', ['value' => 0]);
    $migration->changeField($table, 'api_key', 'api_key', 'text');
    $results = 'glpi_plugin_checkwarranty_results';
    if (!$DB->tableExists($results)) {
        $charset = DBConnection::getDefaultCharset();
        $collation = DBConnection::getDefaultCollation();
        $query = "CREATE TABLE `$results` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `computers_id` int unsigned NOT NULL,
            `serial` varchar(255) NOT NULL,
            `request_key` varchar(64) NOT NULL,
            `batch_id` varchar(100) NOT NULL DEFAULT '',
            `state` varchar(30) NOT NULL,
            `date_attempt` timestamp DEFAULT NULL,
            `date_checked` timestamp DEFAULT NULL,
            `payload` mediumtext DEFAULT NULL,
            PRIMARY KEY (`id`), UNIQUE KEY `computers_id` (`computers_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=$charset COLLATE=$collation";
        if (!$DB->doQuery($query)) {
            return false;
        }
    }

    // 2. Register CronTask (Automatic Action)
    CronTask::Register(
        'PluginCheckwarrantyCron',
        'checkwarranty_sync',
        300, // Bounded batches; disabled until configured
        [
            'comment'   => 'CheckWarranty automatic hardware warranty and specs synchronizer',
            'mode'      => CronTask::MODE_EXTERNAL,
            'state'     => CronTask::STATE_DISABLE, // Disabled by default until API key is configured
        ]
    );

    $migration->executeMigration();
    foreach ($DB->request(['FROM' => 'glpi_plugin_checkwarranty_configs']) as $row) {
        $key = $row['api_key'] ?? '';
        if ($key !== '' && !str_starts_with($key, 'enc:')) {
            $encrypted = (new GLPIKey())->encrypt($key);
            if ($encrypted === '') {
                return false;
            }
            $DB->update('glpi_plugin_checkwarranty_configs', ['api_key' => 'enc:' . $encrypted], ['id' => $row['id']]);
        }
    }
    return true;
}

/**
 * Uninstall hook: clean up database tables and unregister cron tasks
 *
 * @since 1.0.0
 *
 * @return bool
 */
function plugin_checkwarranty_uninstall(): bool
{
    global $DB;

    // 1. Unregister Cron Task
    CronTask::unregister('checkwarranty');

    foreach (['glpi_plugin_checkwarranty_configs', 'glpi_plugin_checkwarranty_results'] as $table) {
        if ($DB->tableExists($table) && !$DB->doQuery("DROP TABLE `$table`")) {
            return false;
        }
    }
    return true;
}
