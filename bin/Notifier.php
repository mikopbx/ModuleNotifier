<?php
/*
 * MikoPBX - free phone system for small business
 * Copyright © 2017-2022 Alexey Portnov and Nikolay Beketov
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see <https://www.gnu.org/licenses/>.
 */

namespace Modules\ModuleNotifier\bin;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use MikoPBX\Core\System\BeanstalkClient;
use MikoPBX\Core\System\Processes;
use MikoPBX\Core\System\SystemMessages;
use MikoPBX\Core\System\Util;
use MikoPBX\Core\Workers\WorkerBase;
use MikoPBX\PBXCoreREST\Lib\PBXApiResult;
use Modules\ModuleNotifier\Lib\Logger;
use Modules\ModuleNotifier\Lib\MikoPBXVersion;
use Modules\ModuleNotifier\Models\ModuleNotifier;
use Throwable;

require_once('Globals.php');

class Notifier extends WorkerBase
{
    private Logger $logger;

    private int $countReq = 0;
    private float $counterStartTime = 0;
    private Client $telegram;
    private string $chatId;
    private string $botApiKey;
    private string $messageTemplate;

    private const MAX_REQUEST = 2;
    public  const ACTION_SEND_MESSAGE       = 'sendMessage';
    public  const ACTION_EDIT_MESSAGE       = 'editMessageText';
    public  const ACTION_SEND_AUDIO         = 'sendAudio';
    public  const ACTION_SEND_PARAM_MESSAGE = 'sendParamMessage';

    public array $params = [];
    /**
     * Handles the received signal.
     *
     * @param int $signal The signal to handle.
     *
     * @return void
     */
    public function signalHandler(int $signal): void
    {
        parent::signalHandler($signal);
        cli_set_process_title('SHUTDOWN_'.cli_get_process_title());
        unset($this->telegram);
    }

    /**
     * Старт работы листнера.
     *
     * @param $argv
     */
    public function start($argv):void
    {
        $this->logger   = new Logger('Notifier', 'ModuleNotifier');
        $this->logger->writeInfo('Starting...');

        /** @var ModuleNotifier $settings */
        $settings = ModuleNotifier::findFirst();
        if(!$settings){
            $this->logger->writeInfo('Settings not found');
            exit();
        }
        $this->chatId          = $settings->chatId;
        $this->messageTemplate = $settings->messageTemplate;
        try {
            $this->telegram = new Client(['base_uri' => "https://api.telegram.org/bot$settings->botApiKey/"]);
        }catch (Throwable $e){
            $this->logger->writeError('Fail init telegram');
            die();
        }
        $beanstalk      = new BeanstalkClient(self::class);
        $beanstalk->subscribe(self::class, [$this, 'onEvents']);
        $beanstalk->subscribe($this->makePingTubeName(self::class), [$this, 'pingCallBack']);
        while ($this->needRestart === false) {
            $beanstalk->wait();
            $this->logger->rotate();
        }
    }

    /**
     * @param $messageText
     * @return string[]
     */
    public function sendMessage($messageText):array
    {
        $this->logger->writeInfo('sendMessage: '.$messageText);
        $response = [];
        $data = [
            'chat_id' => $this->chatId,
            'text' => $messageText
        ];
        try {
            $responseHttp = $this->telegram->request('POST', 'sendMessage', [
                'form_params' => $data
            ]);
        }catch (GuzzleException $e){
            $response['error'] = "Fail sendMessage $messageText...";
            $this->logger->writeInfo("Fail sendMessage $messageText...");
        }

        try {
            $response = json_decode($responseHttp->getBody(), true);
        }catch (\JsonException $e){
            $response['error'] = "Fail decode sendMessage response $messageText...";
            $this->logger->writeInfo('TelegramBot', "Fail decode sendMessage response $messageText...");
        }
        return $response;
    }

    /**
     * @param $messageId
     * @param $messageText
     * @return string[]
     */
    public function editMessageText($messageId, $messageText):array
    {
        $this->logger->writeInfo('editMessageText: '.$messageText. ', messageId: ' .$messageId);

        $response = [
            'message' => '',
            'error' => '',
            'data' => []
        ];
        $data = [
            'chat_id' => $this->chatId,
            'message_id' => $messageId,
            'text' => $messageText
        ];
        try {
            $responseHttp = $this->telegram->request('POST', 'editMessageText', [
                'form_params' => $data
            ]);
        }catch (GuzzleException $e){
            $this->logger->writeInfo("Fail editMessageText $messageText...");
        }

        try {
            $response['data'] = json_decode($responseHttp->getBody(), true);
        }catch (\JsonException $e){
            $this->logger->writeInfo( "Fail decode editMessageText response $messageText...");
        }
        return $response;
    }

    /**
     * @param string $messageText
     * @param string $title
     * @param string $audioFile
     * @return array
     */
    function sendAudio(string $messageText, string $audioFile, string $title = '', int $replyToMessageId = 0): array
    {
        $response = [
            'message' => '',
            'error' => '',
            'data' => []
        ];
        if(!file_exists($audioFile)){
            $this->logger->writeInfo("File $audioFile not found...");
            return $response;
        }
        $data = [
            ['name' => 'chat_id', 'contents' => $this->chatId],
            ['name' => 'caption', 'contents' => $messageText],
            ['name' => 'audio',   'contents' => fopen($audioFile, 'r')]
        ];
        if(!empty($title)){
            $data[] =  ['name' => 'title', 'contents' => $title];
        }
        if ($replyToMessageId > 0) {
            $data[] = ['name' => 'reply_to_message_id', 'contents' => $replyToMessageId];
        }
        try {
            $responseHttp = $this->telegram->request('POST', 'sendAudio', [
                'multipart' => $data
            ]);
        }catch (GuzzleException $e){
            $response['error'] = "Fail sendAudio $audioFile...";
            $this->logger->writeInfo("Fail sendAudio $audioFile...".$e->getMessage());
        }
        try {
            $response['data'] = json_decode($responseHttp->getBody(), true);
        }catch (JsonException $e){
            $response['error'] = "Fail decode sendAudio response $audioFile...";
            $this->logger->writeInfo("Fail decode sendAudio response $audioFile...");
        }
        return $response;
    }

    /**
     * @param $params
     * @return string[]
     */
    public function sendParamMessage($params):array
    {
        $this->params = $params;
        $pattern = '/<([A-Z_]+)(?:\(([^)]+)\))?>/';
        $message  = preg_replace_callback($pattern, [$this, 'replaceVariables'], $this->messageTemplate);
        return $this->sendMessage($message);
    }

    public function replaceVariables($matches) {
        $variable_name = $matches[1]??"";
        $new_variable_name = preg_replace('/^VAR_/', '', $variable_name);
        if($variable_name === $new_variable_name){
            return $matches[0];
        }
        return $this->params[$new_variable_name]??'';
    }

    /**
     * Получение запросов на идентификацию номера телефона.
     * @param $tube
     * @return void
     */
    public function onEvents($tube): void
    {
        try {
            $data = json_decode($tube->getBody(), true);
        }catch (\Throwable $e){
            return;
        }
        $res_data = '';
        $funcName = $data['function']??'';
        if(method_exists($this, $funcName)){
            $this->needSleep();
            $this->logger->rotate();
            if(empty($data['args'])){
                $res_data = $this->$funcName();
            }else{
                $res_data = $this->$funcName(...$data['args']);
            }
            $res_data = json_encode($res_data);
            $res_data = $this->saveResultInTmpFile($res_data);
        }
        if(isset($data['need-ret'])){
            $tube->reply($res_data);
        }
    }

    /**
     * Сериализует данные и сохраняет их во временный файл.
     * @param $data
     * @return string
     */
    private function saveResultInTmpFile($data):string
    {
        try {
            $res_data = json_encode($data, JSON_THROW_ON_ERROR);
        }catch (\JsonException $e){
            return '';
        }
        $tmpDir = '/tmp/';
        $dirsConfig = $this->di->getShared('config');
        $tmoDirName = $dirsConfig->path('core.tempDir') . '/Notifier';
        Util::mwMkdir($tmoDirName);
        chown($tmoDirName, 'www');
        if (file_exists($tmoDirName)) {
            $tmpDir = $tmoDirName;
        }

        $downloadCacheDir = $dirsConfig->path('www.downloadCacheDir');
        if (!file_exists($downloadCacheDir)) {
            $downloadCacheDir = '';
        }
        $fileBaseName = md5(microtime(true));
        // "temp-" in the filename is necessary for the file to be automatically deleted after 5 minutes.
        $filename = $tmpDir . '/temp-' . $fileBaseName;
        file_put_contents($filename, $res_data);
        if (!empty($downloadCacheDir)) {
            $linkName = $downloadCacheDir . '/' . $fileBaseName;
            // For automatic file deletion.
            // A file with such a symlink will be deleted after 5 minutes by cron.
            Util::createUpdateSymlink($filename, $linkName, true);
        }
        chown($filename, 'www');
        return $filename;
    }

    /**
     * Выполнение меодов worker, запущенного в другом процессе.
     * @param string $function
     * @param array $args
     * @param bool $retVal
     * @return PBXApiResult
     */
    public static function invoke(string $function, array $args = [], bool $retVal = true):PBXApiResult
    {
        $object             = new PBXApiResult();
        $req = [
            'action'   => 'invoke',
            'function' => $function,
            'args'     => $args
        ];
        $client = new BeanstalkClient(self::class);
        try {
            if($retVal){
                $req['need-ret'] = true;
                [$object->success, $result] = $client->sendRequest(json_encode($req, JSON_THROW_ON_ERROR), 20);
            }else{
                $client->publish(json_encode($req, JSON_THROW_ON_ERROR));
                $object->success    = false;
                return $object;
            }
            if($object->success){
                $object->data = json_decode(json_decode(file_get_contents($result)), true);
            }
        } catch (\Throwable $e) {
            $object->success    = false;
            $object->messages[] = $e->getMessage();
        }
        $di = MikoPBXVersion::getDefaultDi();
        if($di){
            $tmpDir = $di->getShared('config')->path('core.tempDir') . '/Notifier';
            Processes::mwExecBg(Util::which('find')." $tmpDir -mmin +1 -type f -delete");
        }
        return $object;
    }

    /**
     * Разерешо только MAX_REQUEST запросов в секунду. Принудительное ожидание.
     * @return void
     */
    public function needSleep():void{
        $nowTime   = microtime(true);
        $deltaTime = $nowTime - $this->counterStartTime;
        if( $deltaTime > 1 ){
            $this->countReq = 0;
            $deltaTime = 0;
            $this->counterStartTime = $nowTime;
        }
        $this->countReq++;
        if($deltaTime > 0 && $this->countReq > self::MAX_REQUEST){
            usleep(round($deltaTime * 1000000));
            $this->countReq = 0;
        }
    }

}

if(isset($argv) && count($argv) !== 1
    && Util::getFilePathByClassName(Notifier::class) === $argv[0]){
    Notifier::startWorker($argv??[]);
}