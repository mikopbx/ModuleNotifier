<?php
/**
 * Copyright (C) MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Nikolay Beketov, 9 2018
 *
 */
namespace Modules\ModuleNotifier\App\Forms;

use Phalcon\Forms\Form;
use Phalcon\Forms\Element\Text;
use Phalcon\Forms\Element\Numeric;
use Phalcon\Forms\Element\Password;
use Phalcon\Forms\Element\Check;
use Phalcon\Forms\Element\TextArea;
use Phalcon\Forms\Element\Hidden;
use Phalcon\Forms\Element\Select;


class ModuleNotifierForm extends Form
{

    public function initialize($entity = null, $options = null) :void
    {

        // id
        $this->add(new Hidden('id', ['value' => $entity->id]));
        $this->add(new Text('botApiKey'));
        $this->add(new Text('chatId'));

        // Messenger type selector
        $messengerTypes = [
            'telegram' => 'Telegram',
            'vk'       => 'VKontakte',
        ];
        $this->add(new Select('messengerType', $messengerTypes, [
            'useEmpty' => false,
            'class'    => 'ui selection dropdown',
        ]));

        // VK fields
        $this->add(new Text('vkToken'));
        $this->add(new Text('vkPeerId'));
        $this->add(new TextArea('numberFilter', ['rows' => 3]));

        $rows = max(round(strlen($entity->messageTemplate) / 95), 2);
        $this->add(new TextArea('messageTemplate', ['rows' => $rows]));

        // text_field
        $this->add(new Text('text_field'));
        // text_area_field
        $rows = max(round(strlen($entity->text_area_field) / 95), 2);
        $this->add(new TextArea('text_area_field', ['rows' => $rows]));
        // password_field
        $this->add(new Password('password_field'));
        // integer_field
        $this->add(new Numeric('integer_field', [
            'maxlength'    => 2,
            'style'        => 'width: 80px;',
            'defaultValue' => 3,
        ]));
        // checkbox_field
        $checkAr = ['value' => null];
        if ($entity->checkbox_field) {
            $checkAr = ['checked' => '1'];
        }
        $this->add(new Check('checkbox_field', $checkAr));

        // toggle_field
        $checkAr = ['value' => null];
        if ($entity->toggle_field) {
            $checkAr = ['checked' => '1'];
        }
        $this->add(new Check('toggle_field', $checkAr));

        // dropdown_field
        $providers = new Select('dropdown_field', $options['providers'], [
            'using'    => [
                'id',
                'name',
            ],
            'useEmpty' => false,
            'class'    => 'ui selection dropdown provider-select',
        ]);
        $this->add($providers);
    }
}
