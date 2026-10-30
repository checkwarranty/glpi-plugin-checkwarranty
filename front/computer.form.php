<?php

include '../../../inc/includes.php';

Session::checkRight('computer', UPDATE);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['check_single'])) {
    Html::back();
    exit;
}
$computer = new Computer();
$computer->check((int) ($_POST['computer_id'] ?? 0), UPDATE);
$infocom = new Infocom();
if ($infocom->getFromDBforDevice('Computer', (int) $computer->getID())) {
    $infocom->check($infocom->getID(), UPDATE);
} else {
    Session::checkRight('infocom', CREATE);
}
$status = PluginCheckwarrantySync::run($computer, true);
$messages = [
    'completed' => __('Warranty refreshed.', 'checkwarranty'),
    'cached' => __('Recent result reused; no new scan requested.', 'checkwarranty'),
    'pending' => __('Scan pending. Refresh later; no duplicate scan is created.', 'checkwarranty'),
    'not_found' => __('No useful device data returned.', 'checkwarranty'),
    'error' => __('Lookup failed. Retry later; completed data is preserved.', 'checkwarranty'),
    'skipped' => __('Serial, manufacturer or API key is missing.', 'checkwarranty'),
];
Session::addMessageAfterRedirect($messages[$status], true, in_array($status, ['error', 'not_found', 'skipped'], true) ? WARNING : INFO);
Html::back();
