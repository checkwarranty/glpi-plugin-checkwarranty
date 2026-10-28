<?php
/**
 * CheckWarranty Single Computer Refresh Controller for GLPI
 *
 * @package   PluginCheckwarranty
 * @author    CheckWarranty Team <support@checkwarranty.info>
 * @license   GPLv3+ https://www.gnu.org/licenses/gpl-3.0.html
 * @since     1.0.0
 */

include '../../../inc/includes.php';

Session::checkRight('computer', UPDATE);

if (isset($_POST['check_single']) && !empty($_POST['computer_id'])) {
    Session::checkCSRF($_POST);

    $computerId = (int)$_POST['computer_id'];
    $computer = new Computer();

    if (!$computer->getFromDB($computerId)) {
        Session::addMessageAfterRedirect(__('Computer not found.', 'checkwarranty'), true, ERROR);
        Html::back();
    }

    $serial = trim($computer->fields['serial'] ?? '');
    if (empty($serial)) {
        Session::addMessageAfterRedirect(__('This computer does not have a serial number.', 'checkwarranty'), true, ERROR);
        Html::back();
    }

    $config = PluginCheckwarrantyConfig::getConfig();
    $apiKey = trim($config['api_key'] ?? '');
    $apiUrl = trim($config['api_url'] ?? 'https://checkwarranty.info/api/v1');

    if (empty($apiKey)) {
        Session::addMessageAfterRedirect(__('CheckWarranty API key is not configured in Setup > Plugins.', 'checkwarranty'), true, ERROR);
        Html::back();
    }

    // Resolve manufacturer & model names
    $mfg = new Manufacturer();
    $mfgId = (int)($computer->fields['manufacturers_id'] ?? 0);
    $mfgName = ($mfgId > 0 && $mfg->getFromDB($mfgId)) ? $mfg->fields['name'] : '';

    $model = new ComputerModel();
    $modelId = (int)($computer->fields['computermodels_id'] ?? 0);
    $modelName = ($modelId > 0 && $model->getFromDB($modelId)) ? $model->fields['name'] : '';

    $brand = PluginCheckwarrantyApi::detectBrand($serial, $modelName, $mfgName);

    $res = PluginCheckwarrantyApi::lookupDevice($apiKey, $apiUrl, $serial, $brand);

    if (!$res || ($res['status'] ?? '') === 'not_found') {
        Session::addMessageAfterRedirect(sprintf(__('Serial number %s not found in vendor database.', 'checkwarranty'), $serial), true, WARNING);
        Html::back();
    }

    $warranty = $res['data']['warranty'] ?? [];
    $specs = $res['data']['specs'] ?? [];

    $endDateStr = !empty($warranty['endDate']) ? substr($warranty['endDate'], 0, 10) : null;
    $startDateStr = !empty($warranty['startDate']) ? substr($warranty['startDate'], 0, 10) : null;
    $coverageType = $warranty['coverageType'] ?? ($warranty['description'] ?? '');

    // Duration in months
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

    $infoParts = [];
    if (!empty($coverageType)) {
        $infoParts[] = substr($coverageType, 0, 80);
    }
    if ($config['sync_specs'] && !empty($specs)) {
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
    if ($infocom->getFromDBforDevice('Computer', $computerId)) {
        $infocomData['id'] = $infocom->fields['id'];
        $infocom->update($infocomData);
    } else {
        $infocomData['itemtype'] = 'Computer';
        $infocomData['items_id'] = $computerId;
        $infocom->add($infocomData);
    }

    Session::addMessageAfterRedirect(
        sprintf(__('Warranty refreshed: Expiration %s (%s).', 'checkwarranty'), $endDateStr ?: 'N/A', $coverageType ?: 'Standard'),
        true,
        INFO
    );
    Html::back();
}

Html::back();
