#!/usr/bin/php
<?php
use MikoPBX\Core\Asterisk\AGI;
use MikoPBX\Core\System\SystemMessages;
use Modules\ModuleNotifier\bin\ConnectorDB;
use Modules\ModuleNotifier\Models\CallHistory;
require_once 'Globals.php';

try {
    $agi      = new AGI();
    $number = $agi->get_variable('src_number',true);
    if(empty($number)){
        $number = $agi->get_variable('number',true);
    }
    $params = [
        'did'            => $agi->get_variable('FROM_DID',true),
        'line'           => $argv[1]??'',
        'src'            => $agi->request['agi_callerid'],
        'dst'            => $number,
        'linkedid'       => $agi->get_variable('CHANNEL(linkedid)',true),
        'answered'       => 1,
        'typeCall'       => CallHistory::CALL_TYPE_OUTGOING
    ];
    ConnectorDB::invoke('sendEditMessage', [$params], false);
} catch (Throwable $e) {
    SystemMessages::sysLogMsg('Notifier', $e->getMessage(), LOG_ERR);
}