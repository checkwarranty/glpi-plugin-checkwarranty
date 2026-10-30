<?php

use Glpi\Application\View\TemplateRenderer;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginCheckwarrantyComputer extends CommonGLPI
{
    public static function getTypeName($nb = 0): string
    {
        return __('Warranty (CheckWarranty)', 'checkwarranty');
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): string
    {
        return $item instanceof Computer && $item->can($item->getID(), READ)
            && Session::haveRight('infocom', READ) ? self::getTypeName() : '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
    {
        if (!$item instanceof Computer || !$item->can($item->getID(), READ) || !Session::haveRight('infocom', READ)) {
            return false;
        }
        $id = (int) $item->getID();
        $infocom = new Infocom();
        $exists = $infocom->getFromDBforDevice('Computer', $id);
        if ($exists && !$infocom->can($infocom->getID(), READ)) {
            return false;
        }
        $state = PluginCheckwarrantySync::get($id);
        $result = ($state['serial'] ?? '') === ($item->fields['serial'] ?? '') ? json_decode($state['payload'] ?? '', true) : null;
        $labels = [
            'completed' => __('Completed', 'checkwarranty'),
            'pending' => __('Pending', 'checkwarranty'),
            'not_found' => __('No data', 'checkwarranty'),
            'error' => __('Transport error', 'checkwarranty'),
            'failed' => __('Lookup failed', 'checkwarranty'),
            'storage_error' => __('Storage retry pending', 'checkwarranty'),
            'skipped' => __('Skipped', 'checkwarranty'),
        ];
        TemplateRenderer::getInstance()->display('@checkwarranty/computer.html.twig', [
            'computer' => $item->fields,
            'result' => $result,
            'state' => $labels[$state['state'] ?? ''] ?? __('Not recorded', 'checkwarranty'),
            'can_refresh' => $item->can($id, UPDATE) && ($exists ? $infocom->can($infocom->getID(), UPDATE) : Session::haveRight('infocom', CREATE)),
            'action' => Plugin::getWebDir('checkwarranty') . '/front/computer.form.php',
        ]);
        return true;
    }
}
