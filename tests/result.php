<?php
define('GLPI_ROOT', __DIR__);
require dirname(__DIR__) . '/inc/result.class.php';

function expect($actual, $expected, string $message): void
{
    if ($actual !== $expected) {
        throw new RuntimeException($message . ': ' . var_export($actual, true));
    }
}

$result = PluginCheckwarrantyResult::normalize([
    'status' => 'completed',
    'warranty' => ['status' => 'unknown', 'endDate' => '2027-01-05', 'details' => ['processor' => 'Xeon']],
]);
expect($result['specs']['cpu'], 'Xeon', 'Specs-only public DTO');
$payload = PluginCheckwarrantyResult::infocomPayload($result, ['warranty_info' => 'Manual note']);
expect(isset($payload['buy_date']), false, 'Purchase date preserved');
expect(isset($payload['warranty_date']), false, 'Dell start remains unknown');
expect(str_contains($payload['warranty_info'], 'expiry=2027-01-05'), true, 'Exact expiry retained');
expect(str_contains($payload['warranty_info'], 'Manual note'), true, 'Manual notes retained');
expect(PluginCheckwarrantyResult::normalize(['status' => 'failed']), null, 'Errors rejected');
expect(PluginCheckwarrantyResult::normalize(['status' => 'completed', 'warranty' => ['status' => 'not_found']]), null, 'Not found rejected');
expect(PluginCheckwarrantyResult::date('2026-02-31'), null, 'Invalid date rejected');
$exact = PluginCheckwarrantyResult::normalize(['status' => 'completed', 'warranty' => ['startDate' => '2024-01-01', 'endDate' => '2027-01-01']]);
$payload = PluginCheckwarrantyResult::infocomPayload($exact, []);
expect($payload['warranty_date'], '2024-01-01', 'Native warranty commencement');
expect($payload['warranty_duration'], 36, 'Exact calendar duration');
$exact['warranty']['startDateSource'] = 'estimated';
expect(isset(PluginCheckwarrantyResult::infocomPayload($exact, [])['warranty_date']), false, 'Estimated start excluded');
echo "Result contract tests passed\n";
