<?php
/**
 * Copyright © MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Alexey Portnov, 11 2018
 */
namespace Modules\ModuleNotifier\App\Controllers;
use MikoPBX\AdminCabinet\Controllers\BaseController;
use MikoPBX\Common\Models\CallQueues;
use MikoPBX\Common\Models\Extensions;
use MikoPBX\Modules\PbxExtensionUtils;
use Modules\ModuleNotifier\App\Forms\ModuleNotifierForm;
use Modules\ModuleNotifier\Models\ModuleNotifier;
use MikoPBX\Common\Models\Providers;
use Modules\ModuleNotifier\Models\PhoneBook;

class ModuleNotifierController extends BaseController
{
    private $moduleUniqueID = 'ModuleNotifier';
    private $moduleDir;

    /**
     * Basic initial class
     */
    public function initialize(): void
    {
        $this->moduleDir = PbxExtensionUtils::getModuleDir($this->moduleUniqueID);
        $this->view->logoImagePath = "{$this->url->get()}assets/img/cache/{$this->moduleUniqueID}/logo.svg";
        $this->view->submitMode = null;
        parent::initialize();
    }

    public function getTablesDescriptionAction(): void
    {
        $this->view->data = $this->getTablesDescription();
    }

    public function getNewRecordsAction(): void
    {
        $currentPage                 = $this->request->getPost('draw');
        $table                       = $this->request->get('table');
        $this->view->draw            = $currentPage;
        $this->view->recordsTotal    = 0;
        $this->view->recordsFiltered = 0;
        $this->view->data            = [];

        $descriptions = $this->getTablesDescription();
        if(!isset($descriptions[$table])){
            return;
        }
        $className = $this->getClassName($table);
        if(!empty($className)){
            $filter = [];
            if(isset($descriptions[$table]['cols']['priority'])){
                $filter = ['order' => 'priority'];
            }
            $allRecords = $className::find($filter)->toArray();
            $records    = [];
            $emptyRow   = [
                'rowIcon'  =>  $descriptions[$table]['cols']['rowIcon']['icon']??'',
                'DT_RowId' => 'TEMPLATE'
            ];
            foreach ($descriptions[$table]['cols'] as $key => $metadata) {
                if('rowIcon' !== $key){
                    $emptyRow[$key] = '';
                }
            }
            $records[] = $emptyRow;
            foreach ($allRecords as $rowData){
                $tmpData = [];
                $tmpData['DT_RowId'] =  $rowData['id'];
                foreach ($descriptions[$table]['cols'] as $key => $metadata){
                    if('rowIcon' === $key){
                        $tmpData[$key] = $metadata['icon']??'';
                    }elseif('delButton' === $key){
                        $tmpData[$key] = '';
                    }elseif(isset($rowData[$key])){
                        $tmpData[$key] =  $rowData[$key];
                    }
                }
                $records[] = $tmpData;
            }
            $this->view->data      = $records;
        }
    }

    /**
     * Index page controller
     */
    public function indexAction(): void
    {
        $footerCollection = $this->assets->collection('footerJS');
        $footerCollection->addJs('js/pbx/main/form.js', true);
        $footerCollection->addJs('js/vendor/datatable/dataTables.semanticui.js', true);
        $footerCollection->addJs("js/cache/{$this->moduleUniqueID}/module-notifier-index.js", true);
        $footerCollection->addJs('js/vendor/jquery.tablednd.min.js', true);

        $headerCollectionCSS = $this->assets->collection('headerCSS');
        $headerCollectionCSS->addCss("css/cache/{$this->moduleUniqueID}/module-notifier.css", true);
        $headerCollectionCSS->addCss('css/vendor/datatable/dataTables.semanticui.min.css', true);

        $settings = ModuleNotifier::findFirst();
        if ($settings === null) {
            $settings = new ModuleNotifier();
        }

        // For example we add providers list on the form
        $providers = Providers::find();
        $providersList = [];
        foreach ($providers as $provider){
            $providersList[ $provider->uniqid ] = $provider->getRepresent();
        }
        $options['providers']=$providersList;

        $this->view->form = new ModuleNotifierForm($settings, $options);
        $this->view->pick("{$this->moduleDir}/App/Views/index");

        // Список выбора очередей.
        $this->view->queues = CallQueues::find(['columns' => ['id', 'name']]);
        $this->view->users  = Extensions::find(["type = 'SIP'", 'columns' => ['number', 'callerid']]);
    }

    /**
     * Save settings AJAX action
     */
    public function saveAction() :void
    {
        $data       = $this->request->getPost();
        $record = ModuleNotifier::findFirst();
        if ($record === null) {
            $record = new ModuleNotifier();
        }
        $this->db->begin();
        foreach ($record as $key => $value) {
            switch ($key) {
                case 'id':
                    break;
                case 'checkbox_field':
                case 'toggle_field':
                    if (array_key_exists($key, $data)) {
                        $record->$key = ($data[$key] === 'on') ? '1' : '0';
                    } else {
                        $record->$key = '0';
                    }
                    break;
                default:
                    if (array_key_exists($key, $data)) {
                        $record->$key = $data[$key];
                    } else {
                        $record->$key = '';
                    }
            }
        }

        if ($record->save() === FALSE) {
            $errors = $record->getMessages();
            $this->flash->error(implode('<br>', $errors));
            $this->view->success = false;
            $this->db->rollback();
            return;
        }

        $this->flash->success($this->translation->_('ms_SuccessfulSaved'));
        $this->view->success = true;
        $this->db->commit();
    }

    /**
     * Delete phonebook record
     */
    public function deleteAction(): void
    {
        $table     = $this->request->get('table');
        $className = $this->getClassName($table);
        if(empty($className)) {
            $this->view->success = false;
            return;
        }
        $id     = $this->request->get('id');
        $record = $className::findFirstById($id);
        if ($record !== null && ! $record->delete()) {
            $this->flash->error(implode('<br>', $record->getMessages()));
            $this->view->success = false;
            return;
        }
        $this->view->success = true;
    }

    /**
     * Возвращает метаданные таблицы.
     * @return array
     */
    private function getTablesDescription():array
    {
        $description['PhoneBook'] = [
            'cols' => [
                'rowIcon'    => ['header' => '',                        'class' => 'collapsing', 'icon' => 'user'],
                'priority'   => ['header' => '',                        'class' => 'collapsing'],
                'call_id'    => ['header' => 'Представление абонента',  'class' => 'ten wide'],
                'number_rep' => ['header' => 'Номер телефона',          'class' => 'four wide'],
                'queueId'    => ['header' => 'Номер числом',            'class' => 'collapsing', 'select' => 'queues-list'],
                'delButton'  => ['header' => '',                        'class' => 'collapsing']
            ],
            'ajaxUrl' => '/getNewRecords',
            'icon' => 'user',
            'needDelButton' => true
        ];
        return $description;
    }

    /**
     * Обновление данных в таблице.
     */
    public function saveTableDataAction():void
    {
        $data       = $this->request->getPost();
        $tableName  = $data['pbx-table-id']??'';

        $className = $this->getClassName($tableName);
        if(empty($className)){
            return;
        }
        $rowId      = $data['pbx-row-id']??'';
        if(empty($rowId)){
            $this->view->success = false;
            return;
        }
        $this->db->begin();
        /** @var PhoneBook $rowData */
        $rowData = $className::findFirst('id="'.$rowId.'"');
        if(!$rowData){
            $rowData = new $className();
        }
        foreach ($rowData as $key => $value) {
            if($key === 'id'){
                continue;
            }
            if (array_key_exists($key, $data)) {
                $rowData->writeAttribute($key, $data[$key]);
            }
        }
        // save action
        if ($rowData->save() === FALSE) {
            $errors = $rowData->getMessages();
            $this->flash->error(implode('<br>', $errors));
            $this->view->success = false;
            $this->db->rollback();
            return;
        }
        $this->view->data = ['pbx-row-id'=>$rowId, 'newId'=>$rowData->id, 'pbx-table-id' => $data['pbx-table-id']];
        $this->view->success = true;
        $this->db->commit();

    }

    /**
     * Получение имени класса по имени таблицы
     * @param $tableName
     * @return string
     */
    private function getClassName($tableName):string
    {
        if(empty($tableName)){
            return '';
        }
        $className = "Modules\ModuleNotifier\Models\\$tableName";
        if(!class_exists($className)){
            $className = '';
        }
        return $className;
    }

    /**
     * Get VK conversations list via VK API
     */
    public function getVkConversationsAction(): void
    {
        $vkToken = $this->request->getPost('vkToken', 'string', '');
        if (empty($vkToken)) {
            $this->view->success = false;
            $this->view->message = 'VK Token is empty';
            return;
        }
        $ch = curl_init('https://api.vk.com/method/messages.getConversations');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_POSTFIELDS     => http_build_query([
                'access_token' => $vkToken,
                'v'            => '5.199',
                'count'        => 50,
            ]),
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        $body = json_decode($response, true);
        if (isset($body['error'])) {
            $this->view->success = false;
            $this->view->message = $body['error']['error_msg'] ?? 'VK API error';
            return;
        }
        $conversations = [];
        foreach ($body['response']['items'] ?? [] as $item) {
            $peer = $item['conversation']['peer'] ?? [];
            if ($peer['type'] !== 'chat') {
                continue;
            }
            $title = $item['conversation']['chat_settings']['title'] ?? '';
            $membersCount = $item['conversation']['chat_settings']['members_count'] ?? 0;
            $conversations[] = [
                'peer_id' => (string)$peer['id'],
                'title'   => $title,
                'members' => $membersCount,
            ];
        }
        $this->view->success = true;
        $this->view->data    = $conversations;
    }

    /**
     * Send test message to VK or Telegram
     */
    public function sendTestMessageAction(): void
    {
        $messengerType = $this->request->getPost('messengerType', 'string', 'telegram');
        $message = 'Тестовое сообщение ModuleNotifier ' . date('Y-m-d H:i:s');

        if ($messengerType === 'vk') {
            $vkToken  = $this->request->getPost('vkToken', 'string', '');
            $vkPeerId = $this->request->getPost('vkPeerId', 'string', '');
            if (empty($vkToken) || empty($vkPeerId)) {
                $this->view->success = false;
                $this->view->message = 'VK Token or Peer ID is empty';
                return;
            }
            $ch = curl_init('https://api.vk.com/method/messages.send');
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_POSTFIELDS     => http_build_query([
                    'peer_id'      => $vkPeerId,
                    'message'      => $message,
                    'random_id'    => random_int(1, PHP_INT_MAX),
                    'access_token' => $vkToken,
                    'v'            => '5.199',
                ]),
            ]);
            $response = curl_exec($ch);
            curl_close($ch);
            $body = json_decode($response, true);
            if (isset($body['error'])) {
                $this->view->success = false;
                $this->view->message = $body['error']['error_msg'] ?? 'VK API error';
                return;
            }
            $this->view->success = true;
            $this->view->message = 'message_id=' . ($body['response'] ?? '?');
        } else {
            $botApiKey = $this->request->getPost('botApiKey', 'string', '');
            $chatId    = $this->request->getPost('chatId', 'string', '');
            if (empty($botApiKey) || empty($chatId)) {
                $this->view->success = false;
                $this->view->message = 'Bot Token or Chat ID is empty';
                return;
            }
            $ch = curl_init("https://api.telegram.org/bot{$botApiKey}/sendMessage");
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_POSTFIELDS     => http_build_query([
                    'chat_id' => $chatId,
                    'text'    => $message,
                ]),
            ]);
            $response = curl_exec($ch);
            curl_close($ch);
            $body = json_decode($response, true);
            if (!($body['ok'] ?? false)) {
                $this->view->success = false;
                $this->view->message = $body['description'] ?? 'Telegram API error';
                return;
            }
            $this->view->success = true;
            $this->view->message = 'message_id=' . ($body['result']['message_id'] ?? '?');
        }
    }

    /**
     * Changes rules priority
     *
     */
    public function changePriorityAction(): void
    {
        $this->view->disable();
        $result = true;

        if ( ! $this->request->isPost()) {
            return;
        }
        $priorityTable = $this->request->getPost();
        $tableName     = $this->request->get('table');
        $className = $this->getClassName($tableName);
        if(empty($className)){
            echo "table not found -- ы$tableName --";
            return;
        }
        $rules = $className::find();
        foreach ($rules as $rule){
            if (array_key_exists ( $rule->id, $priorityTable)){
                $rule->priority = $priorityTable[$rule->id];
                $result         .= $rule->update();
            }
        }
        echo json_encode($result);
    }
}