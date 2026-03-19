<?php
/**
 * Copyright © MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Alexey Portnov, 12 2019
 */


namespace Modules\ModuleNotifier\Lib;

use MikoPBX\Common\Models\PbxSettings;
use MikoPBX\Core\System\PBX;
use MikoPBX\Core\Workers\Cron\WorkerSafeScriptsCore;
use MikoPBX\Modules\Config\ConfigClass;
use MikoPBX\PBXCoreREST\Lib\PBXApiResult;
use Modules\ModuleNotifier\bin\ConnectorDB;
use Modules\ModuleNotifier\bin\Notifier;

class NotifierConf extends ConfigClass
{

    /**
     * Receive information about mikopbx main database changes
     *
     * @param $data
     */
    public function modelsEventChangeData($data): void
    {
        if ($data['model'] === Notifier::class) {
            $changedFields = count($data['changedFields']);
            $syncKeys = ['cdrOffset', 'referenceDate'];
            if ($changedFields === 1 && in_array($data['changedFields'][0],$syncKeys, true)) {
                return;
            }
            $templateMain = new NotifierMain();
            $templateMain->startAllServices(true);
        }
    }

    /**
     * Returns module workers to start it at WorkerSafeScriptCore
     *
     * @return array
     */
    public function getModuleWorkers(): array
    {
        return [
            [
                'type'   => WorkerSafeScriptsCore::CHECK_BY_BEANSTALK,
                'worker' => Notifier::class,
            ],
            [
                'type'   => WorkerSafeScriptsCore::CHECK_BY_BEANSTALK,
                'worker' => ConnectorDB::class,
            ],
        ];
    }

    /**
     * Кастомизация входящего контекста для конкретного маршрута.
     *
     * @param $rout_number
     *
     * @return string
     */
    public function generateIncomingRoutBeforeDial($rout_number): string
    {
        return "same => n,AGI({$this->moduleDir}/agi-bin/incoming-call.php)".PHP_EOL;
    }

    /**
     * Generate the outgoing route context for a specific route
     *
     * @param array $rout The route configuration array
     * @return string The generated outgoing route context
     */
    public function generateOutRoutContext(array $rout): string
    {
        return "same => n,AGI({$this->moduleDir}/agi-bin/outgoing-call.php,$rout[providerid])".PHP_EOL;
    }

    /**
     *  Process CoreAPI requests under root rights
     *
     * @param array $request
     *
     * @return PBXApiResult
     */
    public function moduleRestAPICallback(array $request): PBXApiResult
    {
        $res    = new PBXApiResult();
        $res->processor = __METHOD__;
        $action = strtoupper($request['action']);
        switch ($action) {
            case 'CHECK':
                $templateMain = new NotifierMain();
                $res          = $templateMain->checkModuleWorkProperly();
                break;
            case 'RELOAD':
                $templateMain = new NotifierMain();
                $templateMain->startAllServices(true);
                $res->success = true;
                break;
            default:
                $res->success    = false;
                $res->messages[] = 'API action not found in moduleRestAPICallback ModuleNotifier';
        }

        return $res;
    }

    /**
     * Process after enable action in web interface
     *
     * @return void
     */
    public function onAfterModuleEnable(): void
    {
        PBX::dialplanReload();
    }

    /**
     * Process after enable action in web interface
     *
     * @return void
     */
    public function onAfterModuleDisable(): void
    {
        $this->onAfterModuleEnable();
    }

}