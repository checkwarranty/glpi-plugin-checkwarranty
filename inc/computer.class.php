<?php
/**
 * CheckWarranty Computer Tab Integration for GLPI
 *
 * @package   PluginCheckwarranty
 * @author    CheckWarranty Team <support@checkwarranty.info>
 * @license   GPLv3+ https://www.gnu.org/licenses/gpl-3.0.html
 * @since     1.0.0
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginCheckwarrantyComputer extends CommonGLPI {

    /**
     * Return type name for the tab
     *
     * @since 1.0.0
     *
     * @param int $nb Number of items
     * @return string
     */
    public static function getTypeName($nb = 0): string {
        return __('Warranty (CheckWarranty)', 'checkwarranty');
    }

    /**
     * Get tab name for item
     *
     * @since 1.0.0
     *
     * @param CommonGLPI $item
     * @param int        $withtemplate
     * @return string
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): string {
        if ($item->getType() === 'Computer') {
            return self::getTypeName();
        }
        return '';
    }

    /**
     * Display tab content for item
     *
     * @since 1.0.0
     *
     * @param CommonGLPI $item
     * @param int        $tabnum
     * @param int        $withtemplate
     * @return bool
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool {
        if ($item->getType() !== 'Computer') {
            return false;
        }

        $computerId = (int)$item->getID();
        $serial = trim($item->fields['serial'] ?? '');

        // Fetch current Infocom details
        $infocom = new Infocom();
        $hasInfocom = $infocom->getFromDBforDevice('Computer', $computerId);

        echo "<div class='center' id='tabsbody'>";
        echo "<table class='tab_cadre_fixe'>";

        echo "<tr><th colspan='2'>" . __('Hardware Warranty Status', 'checkwarranty') . "</th></tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td style='width: 30%;'>" . __('Serial Number', 'checkwarranty') . "</td>";
        echo "<td><strong>" . ($serial ? Html::cleanInputText($serial) : "<em>" . __('No serial number defined', 'checkwarranty') . "</em>") . "</strong></td>";
        echo "</tr>";

        if ($hasInfocom) {
            $warrantyDate = $infocom->fields['warranty_date'] ?? null;
            $buyDate = $infocom->fields['buy_date'] ?? null;
            $duration = (int)($infocom->fields['warranty_duration'] ?? 0);
            $info = $infocom->fields['warranty_info'] ?? '';

            $isExpired = false;
            if ($warrantyDate) {
                $isExpired = (strtotime($warrantyDate) < time());
            }

            echo "<tr class='tab_bg_1'>";
            echo "<td>" . __('Warranty Expiration Date', 'checkwarranty') . "</td>";
            echo "<td>";
            if ($warrantyDate) {
                $badgeClass = $isExpired ? 'badge bg-danger' : 'badge bg-success';
                $badgeText = $isExpired ? __('Expired', 'checkwarranty') : __('Active', 'checkwarranty');
                echo Html::convDate($warrantyDate) . " &nbsp;<span class='$badgeClass'>$badgeText</span>";
            } else {
                echo "<em>" . __('Not recorded', 'checkwarranty') . "</em>";
            }
            echo "</td>";
            echo "</tr>";

            echo "<tr class='tab_bg_1'>";
            echo "<td>" . __('Purchase / Factory Ship Date', 'checkwarranty') . "</td>";
            echo "<td>" . ($buyDate ? Html::convDate($buyDate) : "<em>" . __('Not recorded', 'checkwarranty') . "</em>") . "</td>";
            echo "</tr>";

            echo "<tr class='tab_bg_1'>";
            echo "<td>" . __('Warranty Duration', 'checkwarranty') . "</td>";
            echo "<td>" . ($duration ? sprintf(__('%d months', 'checkwarranty'), $duration) : "<em>" . __('Not recorded', 'checkwarranty') . "</em>") . "</td>";
            echo "</tr>";

            echo "<tr class='tab_bg_1'>";
            echo "<td>" . __('Support Tier & Factory Specs', 'checkwarranty') . "</td>";
            echo "<td>" . ($info ? nl2br(Html::cleanInputText($info)) : "<em>" . __('No notes recorded', 'checkwarranty') . "</em>") . "</td>";
            echo "</tr>";
        } else {
            echo "<tr class='tab_bg_1'>";
            echo "<td colspan='2' class='center'><em>" . __('No financial/warranty record (Infocom) found for this computer yet.', 'checkwarranty') . "</em></td>";
            echo "</tr>";
        }

        if ($serial) {
            $config = PluginCheckwarrantyConfig::getConfig();
            $actionUrl = Plugin::getWebDir('checkwarranty') . '/front/computer.form.php';

            echo "<tr class='tab_bg_2'>";
            echo "<td colspan='2' class='center'>";
            echo "<form method='post' action='$actionUrl'>";
            echo Html::hidden('computer_id', ['value' => $computerId]);
            echo Html::hidden('check_single', ['value' => 1]);
            echo "<button type='submit' class='btn btn-primary' " . (empty($config['api_key']) ? 'disabled' : '') . ">";
            echo "<i class='fas fa-sync-alt'></i> " . __('Check / Refresh Warranty with CheckWarranty', 'checkwarranty');
            echo "</button>";

            if (empty($config['api_key'])) {
                echo "<br><small class='text-danger'>" . __('Please configure your CheckWarranty API Key in Setup > Plugins > CheckWarranty first.', 'checkwarranty') . "</small>";
            }

            Html::closeForm();
            echo "</td>";
            echo "</tr>";
        }

        echo "</table>";
        echo "</div>";

        return true;
    }
}
