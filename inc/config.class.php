<?php
/**
 * CheckWarranty Configuration Class for GLPI
 *
 * @package   PluginCheckwarranty
 * @author    CheckWarranty Team <support@checkwarranty.info>
 * @license   GPLv3+ https://www.gnu.org/licenses/gpl-3.0.html
 * @since     1.0.0
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginCheckwarrantyConfig extends CommonDBTM {

    /**
     * Get type name
     *
     * @since 1.0.0
     *
     * @param int $nb
     * @return string
     */
    public static function getTypeName($nb = 0): string {
        return __('CheckWarranty Configuration', 'checkwarranty');
    }

    /**
     * Retrieve current plugin configuration
     *
     * @since 1.0.0
     *
     * @return array
     */
    public static function getConfig(): array {
        global $DB;
        $table = 'glpi_plugin_checkwarranty_configs';
        if (!$DB->tableExists($table)) {
            return [
                'api_key'      => '',
                'api_url'      => 'https://checkwarranty.info/api/v1',
                'recheck_days' => 30,
                'sync_specs'   => 1,
                'auto_detect'  => 1,
            ];
        }

        $iterator = $DB->request([
            'FROM'  => $table,
            'WHERE' => ['id' => 1],
            'LIMIT' => 1,
        ]);

        foreach ($iterator as $data) {
            return $data;
        }

        return [
            'api_key'      => '',
            'api_url'      => 'https://checkwarranty.info/api/v1',
            'recheck_days' => 30,
            'sync_specs'   => 1,
            'auto_detect'  => 1,
        ];
    }

    /**
     * Save configuration
     *
     * @since 1.0.0
     *
     * @param array $input
     * @return bool
     */
    public static function saveConfig(array $input): bool {
        global $DB;
        $table = 'glpi_plugin_checkwarranty_configs';

        $data = [
            'api_key'      => trim($input['api_key'] ?? ''),
            'api_url'      => trim($input['api_url'] ?? 'https://checkwarranty.info/api/v1'),
            'recheck_days' => max(0, (int)($input['recheck_days'] ?? 30)),
            'sync_specs'   => isset($input['sync_specs']) ? 1 : 0,
            'auto_detect'  => isset($input['auto_detect']) ? 1 : 0,
            'date_mod'     => date('Y-m-d H:i:s'),
        ];

        return $DB->update($table, $data, ['id' => 1]);
    }

    /**
     * Display configuration form
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function showConfigForm(): void {
        if (!Session::haveRight('config', UPDATE)) {
            return;
        }

        $config = self::getConfig();

        echo "<form action='" . Plugin::getWebDir('checkwarranty') . "/front/config.form.php' method='post'>";
        echo "<div class='center' id='tabsbody'>";
        echo "<table class='tab_cadre_fixe'>";

        echo "<tr><th colspan='2'>" . __('CheckWarranty Settings', 'checkwarranty') . "</th></tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td><label for='api_key'>" . __('CheckWarranty API Key', 'checkwarranty') . "</label></td>";
        echo "<td>";
        echo "<input type='password' id='api_key' name='api_key' value='" . Html::cleanInputText($config['api_key']) . "' size='50' autocomplete='off' />";
        echo "<br><small>" . sprintf(__('Get your API key in the %sCheckWarranty Dashboard%s', 'checkwarranty'), "<a href='https://checkwarranty.info/dashboard/api-keys' target='_blank'>", "</a>") . "</small>";
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td><label for='api_url'>" . __('API URL', 'checkwarranty') . "</label></td>";
        echo "<td><input type='text' id='api_url' name='api_url' value='" . Html::cleanInputText($config['api_url']) . "' size='50' /></td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td><label for='recheck_days'>" . __('Smart Recheck Days', 'checkwarranty') . "</label></td>";
        echo "<td>";
        echo "<input type='number' id='recheck_days' name='recheck_days' value='" . (int)$config['recheck_days'] . "' min='0' max='365' />";
        echo "<br><small>" . __('Only refresh devices with expired warranty or expiring within N days (saves API credits)', 'checkwarranty') . "</small>";
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td><label for='sync_specs'>" . __('Sync Factory Specifications', 'checkwarranty') . "</label></td>";
        echo "<td>";
        echo "<input type='checkbox' id='sync_specs' name='sync_specs' value='1' " . ($config['sync_specs'] ? 'checked' : '') . " /> ";
        echo __('Append factory CPU, RAM, and storage specs to Infocom warranty notes', 'checkwarranty');
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td><label for='auto_detect'>" . __('Auto-Detect Brand', 'checkwarranty') . "</label></td>";
        echo "<td>";
        echo "<input type='checkbox' id='auto_detect' name='auto_detect' value='1' " . ($config['auto_detect'] ? 'checked' : '') . " /> ";
        echo __('Automatically infer brand from serial syntax and model name if manufacturer is empty', 'checkwarranty');
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_2'>";
        echo "<td colspan='2' class='center'>";
        echo "<input type='submit' name='update' value='" . _sx('button', 'Save') . "' class='btn btn-primary' />";
        echo "</td>";
        echo "</tr>";

        echo "</table>";
        Html::closeForm();
        echo "</div>";
    }
}
