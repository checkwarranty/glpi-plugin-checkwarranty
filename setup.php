<?php

/**
 * CheckWarranty Plugin for GLPI
 *
 * @package   PluginCheckwarranty
 * @author    CheckWarranty Team <support@checkwarranty.info>
 * @copyright 2026 CheckWarranty Team
 * @license   GPLv3+ https://www.gnu.org/licenses/gpl-3.0.html
 * @link      https://checkwarranty.info
 * @since     1.0.0
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

define('PLUGIN_CHECKWARRANTY_VERSION', '1.0.1');
define('PLUGIN_CHECKWARRANTY_MIN_GLPI', '10.0.0');
define('PLUGIN_CHECKWARRANTY_MAX_GLPI', '12.0.0');

/**
 * Initialize the plugin: register classes, hooks, and tabs
 *
 * @since 1.0.0
 *
 * @return void
 */
function plugin_init_checkwarranty(): void
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['checkwarranty'] = true;

    // Load localization domain & register plugin classes
    Plugin::registerClass('PluginCheckwarrantyConfig');

    Plugin::registerClass('PluginCheckwarrantyComputer', [
        'addtabon' => 'Computer',
    ]);

    // Register config page in Setup > Plugins
    if (Session::haveRight('config', UPDATE)) {
        $PLUGIN_HOOKS['config_page']['checkwarranty'] = 'front/config.form.php';
    }

    $PLUGIN_HOOKS['item_purge']['checkwarranty'] = ['Computer' => ['PluginCheckwarrantySync', 'purge']];
}

/**
 * Define plugin version and metadata for GLPI Marketplace
 *
 * @since 1.0.0
 *
 * @return array
 */
function plugin_version_checkwarranty(): array
{
    return [
        'name'           => 'CheckWarranty',
        'version'        => PLUGIN_CHECKWARRANTY_VERSION,
        'author'         => 'CheckWarranty Team <support@checkwarranty.info>',
        'license'        => 'GPLv3+',
        'homepage'       => 'https://checkwarranty.info',
        'requirements'   => [
            'glpi' => [
                'min' => PLUGIN_CHECKWARRANTY_MIN_GLPI,
                'max' => PLUGIN_CHECKWARRANTY_MAX_GLPI,
            ],
            'php'  => [
                'min'  => '8.0.0',
                'exts' => ['curl', 'json'],
            ],
        ],
    ];
}

/**
 * Check prerequisites before installation or activation
 *
 * @since 1.0.0
 *
 * @return bool
 */
function plugin_checkwarranty_check_prerequisites(): bool
{
    // Check PHP version
    if (version_compare(PHP_VERSION, '8.0.0', '<')) {
        echo sprintf(__('PHP >= %s required', 'checkwarranty'), '8.0.0');
        return false;
    }

    // Check cURL extension
    if (!extension_loaded('curl')) {
        echo __('cURL extension is required for CheckWarranty', 'checkwarranty');
        return false;
    }

    // Check JSON extension
    if (!extension_loaded('json')) {
        echo __('JSON extension is required for CheckWarranty', 'checkwarranty');
        return false;
    }

    return true;
}

/**
 * Check if plugin can be activated
 *
 * @since 1.0.0
 *
 * @return bool
 */
function plugin_checkwarranty_check_config($verbose = false): bool
{
    return plugin_checkwarranty_check_prerequisites();
}
