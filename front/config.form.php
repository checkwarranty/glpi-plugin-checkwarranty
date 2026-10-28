<?php
/**
 * CheckWarranty Configuration Form Controller for GLPI
 *
 * @package   PluginCheckwarranty
 * @author    CheckWarranty Team <support@checkwarranty.info>
 * @license   GPLv3+ https://www.gnu.org/licenses/gpl-3.0.html
 * @since     1.0.0
 */

include '../../../inc/includes.php';

Session::checkRight('config', UPDATE);

$config = new PluginCheckwarrantyConfig();

if (isset($_POST['update'])) {
    Session::checkCSRF($_POST);
    if (PluginCheckwarrantyConfig::saveConfig($_POST)) {
        Session::addMessageAfterRedirect(__('CheckWarranty settings saved successfully.', 'checkwarranty'), true, INFO);
    } else {
        Session::addMessageAfterRedirect(__('Failed to save CheckWarranty settings.', 'checkwarranty'), true, ERROR);
    }
    Html::back();
}

Html::header(
    __('CheckWarranty', 'checkwarranty'),
    $_SERVER['PHP_SELF'],
    'config',
    'plugin',
    'checkwarranty'
);

$config->showConfigForm();

Html::footer();
