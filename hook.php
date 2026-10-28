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
function plugin_checkwarranty_install(): bool {
    global $DB;

    $migration = new Migration(PLUGIN_CHECKWARRANTY_VERSION);

    // 1. Create plugin configuration table
    $table = 'glpi_plugin_checkwarranty_configs';
    if (!$DB->tableExists($table)) {
        $query = "CREATE TABLE IF NOT EXISTS `$table` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `api_key` varchar(255) DEFAULT NULL,
            `api_url` varchar(255) NOT NULL DEFAULT 'https://checkwarranty.info/api/v1',
            `recheck_days` int(11) NOT NULL DEFAULT 30,
            `sync_specs` tinyint(1) NOT NULL DEFAULT 1,
            `auto_detect` tinyint(1) NOT NULL DEFAULT 1,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        $DB->queryOrDie($query, $DB->error());

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

    // 2. Register CronTask (Automatic Action)
    CronTask::Register(
        'PluginCheckwarrantyCron',
        'checkwarranty_sync',
        DAY_TIMESTAMP, // Run daily
        [
            'comment'   => 'CheckWarranty automatic hardware warranty and specs synchronizer',
            'mode'      => CronTask::MODE_INTERNAL,
            'state'     => CronTask::STATE_DISABLE, // Disabled by default until API key is configured
            'hourmin'   => 2,
            'hourmax'   => 5,
        ]
    );

    $migration->executeMigration();
    return true;
}

/**
 * Uninstall hook: clean up database tables and unregister cron tasks
 *
 * @since 1.0.0
 *
 * @return bool
 */
function plugin_checkwarranty_uninstall(): bool {
    global $DB;

    // 1. Unregister Cron Task
    CronTask::Unregister('PluginCheckwarrantyCron');

    // 2. Drop plugin configuration table
    $table = 'glpi_plugin_checkwarranty_configs';
    if ($DB->tableExists($table)) {
        $DB->queryOrDie("DROP TABLE IF EXISTS `$table`;", $DB->error());
    }

    return true;
}
