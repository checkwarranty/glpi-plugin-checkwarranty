<?php

use Glpi\Application\View\TemplateRenderer;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginCheckwarrantyConfig extends CommonDBTM
{
    public static function getTypeName($nb = 0): string
    {
        return __('CheckWarranty Settings', 'checkwarranty');
    }

    public static function getConfig(): array
    {
        global $DB;
        $config = ['api_key' => '', 'api_url' => 'https://checkwarranty.info/api/v1', 'recheck_days' => 30, 'sync_specs' => 1, 'auto_detect' => 1, 'sync_cursor' => 0];
        $table = 'glpi_plugin_checkwarranty_configs';
        if ($DB->tableExists($table)) {
            foreach ($DB->request(['FROM' => $table, 'WHERE' => ['id' => 1]]) as $row) {
                $config = $row + $config;
            }
        }
        if (str_starts_with($config['api_key'] ?? '', 'enc:')) {
            $ciphertext = substr($config['api_key'], 4);
            $plain = (new GLPIKey())->decrypt($ciphertext);
            $config['api_key'] = $plain !== $ciphertext ? ($plain ?? '') : '';
        }
        return $config;
    }

    public static function saveConfig(array $input): bool
    {
        global $DB;
        if (!Session::haveRight('config', UPDATE)) {
            return false;
        }
        $url = trim($input['api_url'] ?? '');
        $key = trim($input['api_key'] ?? '');
        if (!PluginCheckwarrantyApi::validUrl($url) || preg_match('/[\r\n]/', $key)) {
            return false;
        }
        $values = [
            'api_url' => $url,
            'recheck_days' => max(1, min(365, (int) ($input['recheck_days'] ?? 30))),
            'sync_specs' => isset($input['sync_specs']) ? 1 : 0,
            'auto_detect' => isset($input['auto_detect']) ? 1 : 0,
            'date_mod' => date('Y-m-d H:i:s'),
        ];
        if ($key !== '') {
            $encrypted = (new GLPIKey())->encrypt($key);
            if ($encrypted === '') {
                return false;
            }
            $values['api_key'] = 'enc:' . $encrypted;
        } elseif (isset($input['clear_key'])) {
            $values['api_key'] = '';
        }
        return $DB->update('glpi_plugin_checkwarranty_configs', $values, ['id' => 1]);
    }

    public function showConfigForm(): void
    {
        Session::checkRight('config', UPDATE);
        $config = self::getConfig();
        $configured = $config['api_key'] !== '';
        unset($config['api_key']);
        TemplateRenderer::getInstance()->display('@checkwarranty/config.html.twig', [
            'config' => $config, 'configured' => $configured,
            'action' => Plugin::getWebDir('checkwarranty') . '/front/config.form.php',
        ]);
    }
}
