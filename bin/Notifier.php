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
    private Client $httpClient;
    private string $chatId;
    private string $botApiKey;
    private string $messageTemplate;
    private string $messengerType = 'telegram';
    private string $vkToken = '';
    private string $vkPeerId = '';

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
        unset($this->httpClient);
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
        $this->messageTemplate = $settings->messageTemplate;
        $this->messengerType   = $settings->messengerType ?: 'telegram';

        try {
            if ($this->messengerType === 'vk') {
                $this->vkToken   = $settings->vkToken;
                $this->vkPeerId  = $settings->vkPeerId;
                $this->httpClient = new Client(['timeout' => 15.0]);
                $this->logger->writeInfo('Initialized VK messenger');
            } else {
                $this->chatId    = $settings->chatId;
                $this->httpClient = new Client(['base_uri' => "https://api.telegram.org/bot$settings->botApiKey/"]);
                $this->logger->writeInfo('Initialized Telegram messenger');
            }
        } catch (Throwable $e) {
            $this->logger->writeError('Fail init messenger: ' . $e->getMessage());
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
        if ($this->messengerType === 'vk') {
            return $this->sendVkMessage($messageText);
        }
        return $this->sendTelegramMessage($messageText);
    }

    /**
     * @param string $messageText
     * @return array
     */
    private function sendTelegramMessage(string $messageText):array
    {
        $response = [];
        $data = [
            'chat_id' => $this->chatId,
            'text' => $messageText
        ];
        try {
            $responseHttp = $this->httpClient->request('POST', 'sendMessage', [
                'form_params' => $data
            ]);
        }catch (GuzzleException $e){
            $response['error'] = "Fail sendMessage $messageText...";
            $this->logger->writeInfo("Fail sendMessage $messageText...");
            return $response;
        }

        try {
            $response = json_decode($responseHttp->getBody(), true);
        }catch (\JsonException $e){
            $response['error'] = "Fail decode sendMessage response $messageText...";
            $this->logger->writeInfo("Fail decode sendMessage response $messageText...");
        }
        return $response;
    }

    /**
     * Send message via VK API.
     * @param string $messageText
     * @return array
     */
    private function sendVkMessage(string $messageText):array
    {
        $data = [
            'peer_id'      => $this->vkPeerId,
            'message'      => $messageText,
            'random_id'    => random_int(1, PHP_INT_MAX),
            'access_token' => $this->vkToken,
            'v'            => '5.199',
        ];
        try {
            $responseHttp = $this->httpClient->request('POST', 'https://api.vk.com/method/messages.send', [
                'form_params' => $data
            ]);
            $body = json_decode($responseHttp->getBody(), true);
            if (isset($body['error'])) {
                $errorMsg = $body['error']['error_msg'] ?? 'unknown';
                $this->logger->writeError('VK API error: ' . $errorMsg);
                return ['error' => $errorMsg];
            }
            return ['ok' => true, 'result' => ['message_id' => $body['response'] ?? 0]];
        } catch (\Throwable $e) {
            $this->logger->writeError("VK send failed: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * @param $messageId
     * @param $messageText
     * @return string[]
     */
    public function editMessageText($messageId, $messageText):array
    {
        $this->logger->writeInfo('editMessageText: '.$messageText. ', messageId: ' .$messageId);
        if ($this->messengerType === 'vk') {
            return $this->editVkMessage($messageId, $messageText);
        }
        return $this->editTelegramMessageText($messageId, $messageText);
    }

    /**
     * @param $messageId
     * @param $messageText
     * @return array
     */
    private function editTelegramMessageText($messageId, $messageText):array
    {
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
            $responseHttp = $this->httpClient->request('POST', 'editMessageText', [
                'form_params' => $data
            ]);
        }catch (GuzzleException $e){
            $this->logger->writeInfo("Fail editMessageText $messageText...");
            return $response;
        }

        try {
            $response['data'] = json_decode($responseHttp->getBody(), true);
        }catch (\JsonException $e){
            $this->logger->writeInfo( "Fail decode editMessageText response $messageText...");
        }
        return $response;
    }

    /**
     * Edit message via VK API. Falls back to sending new message if too old (~24h).
     * @param $messageId
     * @param string $messageText
     * @return array
     */
    private function editVkMessage($messageId, string $messageText):array
    {
        $response = ['message' => '', 'error' => '', 'data' => []];
        $data = [
            'peer_id'      => $this->vkPeerId,
            'message_id'   => $messageId,
            'message'      => $messageText,
            'access_token' => $this->vkToken,
            'v'            => '5.199',
        ];
        try {
            $responseHttp = $this->httpClient->request('POST', 'https://api.vk.com/method/messages.edit', [
                'form_params' => $data
            ]);
            $body = json_decode($responseHttp->getBody(), true);
            if (isset($body['error'])) {
                $errorCode = $body['error']['error_code'] ?? 0;
                // Error 909 = message too old to edit (~24h limit)
                if ($errorCode === 909) {
                    $this->logger->writeInfo('VK message too old to edit, sending new');
                    $newResult = $this->sendVkMessage($messageText);
                    $response['data'] = $newResult;
                } else {
                    $response['error'] = $body['error']['error_msg'] ?? 'unknown';
                    $this->logger->writeError('VK edit error: ' . $response['error']);
                }
            } else {
                $response['data'] = ['ok' => true, 'result' => ['message_id' => $messageId, 'text' => $messageText]];
            }
        } catch (\Throwable $e) {
            $response['error'] = $e->getMessage();
            $this->logger->writeError("VK edit failed: " . $e->getMessage());
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
        if ($this->messengerType === 'vk') {
            return $this->sendVkAudio($messageText, $audioFile, $title, $replyToMessageId);
        }
        return $this->sendTelegramAudio($messageText, $audioFile, $title, $replyToMessageId);
    }

    /**
     * @param string $messageText
     * @param string $audioFile
     * @param string $title
     * @param int $replyToMessageId
     * @return array
     */
    private function sendTelegramAudio(string $messageText, string $audioFile, string $title, int $replyToMessageId): array
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
            $responseHttp = $this->httpClient->request('POST', 'sendAudio', [
                'multipart' => $data
            ]);
        }catch (GuzzleException $e){
            $response['error'] = "Fail sendAudio $audioFile...";
            $this->logger->writeInfo("Fail sendAudio $audioFile...".$e->getMessage());
            return $response;
        }
        try {
            $response['data'] = json_decode($responseHttp->getBody(), true);
        }catch (\JsonException $e){
            $response['error'] = "Fail decode sendAudio response $audioFile...";
            $this->logger->writeInfo("Fail decode sendAudio response $audioFile...");
        }
        return $response;
    }

    /**
     * Send audio file via VK API (upload as document + send with attachment).
     * @param string $messageText
     * @param string $audioFile
     * @param string $title
     * @param int $replyToMessageId
     * @return array
     */
    private function sendVkAudio(string $messageText, string $audioFile, string $title, int $replyToMessageId): array
    {
        $response = ['message' => '', 'error' => '', 'data' => []];
        if (!file_exists($audioFile)) {
            $this->logger->writeInfo("File $audioFile not found");
            return $response;
        }

        // Convert to OGG for VK audio_message (voice message with player)
        $oggFile = '';
        $soxPath = trim(shell_exec('which sox 2>/dev/null'));
        if (!empty($soxPath)) {
            $oggFile = tempnam('/tmp', 'vk_audio_') . '.ogg';
            $cmd = sprintf('%s %s %s 2>&1', $soxPath, escapeshellarg($audioFile), escapeshellarg($oggFile));
            exec($cmd, $output, $exitCode);
            if ($exitCode !== 0 || !file_exists($oggFile)) {
                $this->logger->writeError('sox conversion failed: ' . implode(' ', $output));
                $oggFile = '';
            }
        }
        $uploadFile = !empty($oggFile) ? $oggFile : $audioFile;
        $uploadType = !empty($oggFile) ? 'audio_message' : 'doc';

        try {
            // Step 1: get upload URL
            $uploadServerResp = $this->httpClient->request('POST', 'https://api.vk.com/method/docs.getMessagesUploadServer', [
                'form_params' => [
                    'peer_id'      => $this->vkPeerId,
                    'type'         => $uploadType,
                    'access_token' => $this->vkToken,
                    'v'            => '5.199',
                ]
            ]);
            $uploadData = json_decode($uploadServerResp->getBody(), true);
            if (isset($uploadData['error'])) {
                $response['error'] = 'VK upload server error: ' . ($uploadData['error']['error_msg'] ?? 'unknown');
                $this->logger->writeError($response['error']);
                if (!empty($oggFile) && file_exists($oggFile)) { unlink($oggFile); }
                return $response;
            }
            $uploadUrl = $uploadData['response']['upload_url'] ?? '';

            // Step 2: upload file
            $uploadFilename = !empty($oggFile) ? 'recording.ogg' : preg_replace('/\.\w+$/', '.dat', basename($audioFile));
            $uploadResp = $this->httpClient->request('POST', $uploadUrl, [
                'multipart' => [
                    ['name' => 'file', 'contents' => fopen($uploadFile, 'r'), 'filename' => $uploadFilename]
                ]
            ]);
            $uploadResult = json_decode($uploadResp->getBody(), true);
            $fileParam = $uploadResult['file'] ?? '';

            // Step 3: save document
            $saveResp = $this->httpClient->request('POST', 'https://api.vk.com/method/docs.save', [
                'form_params' => [
                    'file'         => $fileParam,
                    'title'        => $title ?: basename($audioFile),
                    'access_token' => $this->vkToken,
                    'v'            => '5.199',
                ]
            ]);
            $saveData = json_decode($saveResp->getBody(), true);
            if (isset($saveData['error'])) {
                $response['error'] = 'VK save error: ' . ($saveData['error']['error_msg'] ?? 'unknown');
                $this->logger->writeError($response['error']);
                if (!empty($oggFile) && file_exists($oggFile)) { unlink($oggFile); }
                return $response;
            }

            // Build attachment string
            $docType = $saveData['response']['type'] ?? 'doc';
            $doc = $saveData['response'][$docType] ?? [];
            $attachment = sprintf('doc%s_%s_%s', $doc['owner_id'] ?? '', $doc['id'] ?? '', $doc['access_key'] ?? '');

            // Step 4: send message with attachment
            $sendData = [
                'peer_id'      => $this->vkPeerId,
                'message'      => $messageText,
                'attachment'    => $attachment,
                'random_id'    => random_int(1, PHP_INT_MAX),
                'access_token' => $this->vkToken,
                'v'            => '5.199',
            ];
            if ($replyToMessageId > 0) {
                $sendData['reply_to'] = $replyToMessageId;
            }
            $sendResp = $this->httpClient->request('POST', 'https://api.vk.com/method/messages.send', [
                'form_params' => $sendData
            ]);
            $sendBody = json_decode($sendResp->getBody(), true);
            if (isset($sendBody['error'])) {
                $response['error'] = $sendBody['error']['error_msg'] ?? 'unknown';
                $this->logger->writeError('VK sendAudio error: ' . $response['error']);
            } else {
                $response['data'] = ['ok' => true, 'result' => ['message_id' => $sendBody['response'] ?? 0]];
            }
        } catch (\Throwable $e) {
            $response['error'] = $e->getMessage();
            $this->logger->writeError("VK sendAudio failed: " . $e->getMessage());
        }
        // Cleanup temp OGG file
        if (!empty($oggFile) && file_exists($oggFile)) {
            unlink($oggFile);
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