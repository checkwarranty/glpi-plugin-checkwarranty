<?php
/** Run only in the isolated acceptance compose project. */
define('PLUGIN_CHECKWARRANTY_TESTING', true);
require '/var/www/glpi/inc/includes.php';

function verify($condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: $message\n";
}

$auth = new Auth();
$auth->login('glpi', 'glpi');
verify($auth->auth_succeded, 'Isolated GLPI administrator authenticated');
verify(PluginCheckwarrantyConfig::saveConfig([
    'api_key' => 'acceptance-sandbox-only',
    'api_url' => 'http://mock:8000/api/v1',
    'recheck_days' => 30, 'sync_specs' => 1, 'auto_detect' => 1,
]), 'Configuration saved');
foreach ($DB->request(['FROM' => 'glpi_plugin_checkwarranty_configs', 'WHERE' => ['id' => 1]]) as $config) {
    verify(str_starts_with($config['api_key'], 'enc:') && !str_contains($config['api_key'], 'sandbox'), 'API key encrypted at rest');
}
verify(PluginCheckwarrantyConfig::getConfig()['api_key'] === 'acceptance-sandbox-only', 'Encrypted key decrypts');

$ids = [];
foreach (['ENDONLY', 'FULL', 'SPECS', 'NOTFOUND', 'ASYNC', 'ERROR'] as $kind) {
    $computer = new Computer();
    $id = $computer->add(['name' => 'Acceptance ' . $kind, 'serial' => 'TEST-' . $kind, 'entities_id' => 0]);
    verify($id > 0, 'Create test Computer ' . $kind);
    $ids[$kind] = $id;
    $infocom = new Infocom();
    $infocom->add(['itemtype' => 'Computer', 'items_id' => $id, 'buy_date' => '2020-03-01', 'warranty_info' => 'Manual note']);
    $status = PluginCheckwarrantySync::run($computer, true);
    $expected = ['NOTFOUND' => 'not_found', 'ASYNC' => 'pending', 'ERROR' => 'error'][$kind] ?? 'completed';
    verify($status === $expected, $kind . ' returns ' . $expected);
    $infocom->getFromDBforDevice('Computer', $id);
    verify($infocom->fields['buy_date'] === '2020-03-01', $kind . ' preserves purchase');
    if ($kind === 'ENDONLY') {
        verify(empty($infocom->fields['warranty_date']), 'Dell start stays absent');
        verify(str_contains($infocom->fields['warranty_info'], 'expiry=2027-01-05'), 'Exact Dell expiration retained');
        verify(str_contains($infocom->fields['warranty_info'], 'Manual note'), 'Manual notes retained');
        ob_start();
        PluginCheckwarrantyComputer::displayTabContentForItem($computer);
        $html = ob_get_clean();
        verify(!str_contains($html, '<script>') && str_contains($html, '&lt;script&gt;'), 'Twig escapes provider markup');
        verify(PluginCheckwarrantySync::run($computer, true) === 'cached', 'Double refresh reuses paid result');
    }
    if ($kind === 'FULL') {
        verify($infocom->fields['warranty_date'] === '2024-01-01' && (int) $infocom->fields['warranty_duration'] === 36, 'Native warranty interval is exact');
    }
    if ($kind === 'ASYNC') {
        $DB->update(PluginCheckwarrantySync::TABLE, ['date_attempt' => date('Y-m-d H:i:s', time() - 120)], ['computers_id' => $id]);
        verify(PluginCheckwarrantySync::run($computer, true) === 'completed', 'Pending scan resumes with GET');
    }
    if ($kind === 'ERROR') {
        $before = PluginCheckwarrantySync::get($id)['request_key'];
        $DB->update(PluginCheckwarrantySync::TABLE, ['date_attempt' => date('Y-m-d H:i:s', time() - 120)], ['computers_id' => $id]);
        PluginCheckwarrantySync::run($computer, true);
        verify(PluginCheckwarrantySync::get($id)['request_key'] === $before, 'Transport retry retains idempotency key');
    }
}

$computer = new Computer();
$computer->getFromDB($ids['ENDONLY']);
$savedProfile = $_SESSION['glpiactiveprofile'];
$_SESSION['glpiactiveprofile']['infocom'] = 0;
verify((new PluginCheckwarrantyComputer())->getTabNameForItem($computer) === '', 'Financial data hidden without READ rights');
$_SESSION['glpiactiveprofile'] = $savedProfile;

$entity = (new Entity())->add(['name' => 'Acceptance other tenant', 'entities_id' => 0]);
$other = new Computer();
$otherId = $other->add(['name' => 'Other tenant', 'serial' => 'TEST-OTHER', 'entities_id' => $entity]);
$activeEntities = $_SESSION['glpiactiveentities'];
$_SESSION['glpiactiveentities'] = [0];
verify(!(new Computer())->can($otherId, UPDATE), 'Cross-entity update denied');
$_SESSION['glpiactiveentities'] = $activeEntities;
verify(!Session::validateCSRF(['_glpi_csrf_token' => 'invalid']), 'Invalid CSRF token rejected');

ob_start();
(new PluginCheckwarrantyConfig())->showConfigForm();
$html = ob_get_clean();
verify(!str_contains($html, 'acceptance-sandbox-only'), 'Secret never rendered in form');
verify(str_contains($html, '_glpi_csrf_token'), 'Form includes CSRF token');

for ($i = 0; $i < 61; $i++) {
    (new Computer())->add(['name' => 'Cursor ' . $i, 'serial' => 'TEST-NOTFOUND-' . $i, 'entities_id' => 0]);
}
$task = new CronTask();
$task->getFromDBByCrit(['itemtype' => 'PluginCheckwarrantyCron', 'name' => 'checkwarranty_sync']);
for ($i = 0; $i < 22; $i++) {
    PluginCheckwarrantyCron::cronCheckwarranty_sync($task);
}
$count = 0;
foreach ($DB->request(['FROM' => PluginCheckwarrantySync::TABLE]) as $row) {
    $count++;
}
verify($count >= 67, 'Cron progresses beyond first 50 computers');

$computer->getFromDB($ids['ENDONLY']);
$computer->delete(['id' => $ids['ENDONLY']], true);
verify(PluginCheckwarrantySync::get($ids['ENDONLY']) === [], 'Purge removes plugin scan state');
echo 'GLPI runtime acceptance passed: ' . GLPI_VERSION . "\n";
