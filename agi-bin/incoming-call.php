#!/usr/bin/php
<?php
use MikoPBX\Core\Asterisk\AGI;
use MikoPBX\Core\System\SystemMessages;
use Modules\ModuleNotifier\bin\ConnectorDB;
use Modules\ModuleNotifier\Models\CallHistory;
require_once 'Globals.php';

try {
    $agi      = new AGI();
    $params = [
        'did'            => $agi->get_variable('FROM_DID',true),
        'line'           => $agi->get_variable('FROM_PEER',true),
        'src'            => $agi->request['agi_callerid'],
        'linkedid'       => $agi->get_variable('CHANNEL(linkedid)',true),
        'answered'       => 1,
        'typeCall'       => CallHistory::CALL_TYPE_INCOMING
    ];
    ConnectorDB::invoke('sendEditMessage', [$params], false);
} catch (Throwable $e) {
    SystemMessages::sysLogMsg('Notifier', $e->getMessage(), LOG_ERR);
}