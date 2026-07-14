<?php
/**
 * Copyright (C) MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Nikolay Beketov, 6 2018
 *
 */

return [
	'repModuleNotifier'         => 'Уведомления о звонках - %repesent%',
	'mo_ModuleModuleNotifier'   => 'Уведомления о звонках',
    'BreadcrumbModuleNotifier'  => 'Уведомления о звонках',
    'SubHeaderModuleNotifier'   => 'Модуль позволяет отправлять уведомления в Telegram или VK группу',
    'module_template_AddNewRecord'  => 'Добавить',

    'module_notifierbotApiKey'          => 'Telegram Bot Token',
    'module_notifierchatId'             => 'Telegram Chat ID',
    'module_messageTemplate'            => 'Шаблон оповещения о входящем',

    'module_notifier_messengerType'     => 'Канал уведомлений',
    'module_notifier_vkToken'           => 'VK Access Token (токен сообщества)',
    'module_notifier_vkPeerId'          => 'VK Peer ID (ID беседы)',
    'module_notifier_GetChats'          => 'Получить беседы',
    'module_notifier_SelectChat'        => 'Выберите беседу',
    'module_notifier_SendTest'          => 'Отправить тестовое сообщение',
    'module_notifier_numberFilter'      => 'Оповещать, только если в звонке участвует номер',
    'module_notifier_numberFilter_help' => 'Номера разделяются пробелами или переводами строк. Спецсимволы игнорируются. Пустой список разрешает уведомления по всем звонкам.',

    'module_notifier_CALL_TYPE_INCOMING'     => 'Входящий звонок с номера: %src% на %dst%, did: %did%',
    'module_notifier_CALL_TYPE_OUTGOING'     => 'Исходящий звонок с номера: %src% на %dst% через %line%',
    'module_notifier_CALL_TYPE_OUTGOING_FAIL'=> 'Разговор с номера: %src% на %dst% через %line% не состоялся',
    'module_notifier_CALL_TYPE_MISSED'       => 'Разговор с номера с номера: %src% на %dst% не состоялся, did: %did%',
    'module_notifier_CALL_AUDIO'       => 'Запись разговора с номера: %src% на %dst%',
];
