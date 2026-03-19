<?php
/**
 * Copyright © MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Alexey Portnov, 2 2019
 */

/*
 * https://docs.phalcon.io/4.0/en/db-models
 *
 */

namespace Modules\ModuleNotifier\Models;

use MikoPBX\Common\Models\ModelsBase;
use MikoPBX\Modules\Models\ModulesModelsBase;

class ModuleNotifier extends ModulesModelsBase
{

    /**
     * @Primary
     * @Identity
     * @Column(type="integer", nullable=false)
     */
    public $id;

    /**
     * Text field example
     *
     * @Column(type="string", nullable=true)
     */
    public $botApiKey;

    /**
     * Text field example
     *
     * @Column(type="string", nullable=true)
     */
    public $chatId;

    /**
     * Text field example
     *
     * @Column(type="string", nullable=true)
     */
    public $messageTemplate;


    /**
     * @Column(type="integer", default=0, nullable=true)
     */
    public $cdrOffset = 0;

    /**
     * @Column(type="string", nullable=true)
     */
    public $referenceDate;


    /**
     * Text field example
     *
     * @Column(type="string", nullable=true)
     */
    public $text_field;

    /**
     * TextArea field example
     *
     * @Column(type="string", nullable=true)
     */
    public $text_area_field;

    /**
     * Password field example
     *
     * @Column(type="string", nullable=true)
     */
    public $password_field;

    /**
     * Integer field example
     *
     * @Column(type="integer", default="1", nullable=true)
     */
    public $integer_field;

    /**
     * CheckBox
     *
     * @Column(type="integer", default="1", nullable=true)
     */
    public $checkbox_field;

    /**
     * Toggle
     *
     * @Column(type="integer", default="1", nullable=true)
     */
    public $toggle_field;

    /**
     * Dropdown menu
     *
     * @Column(type="string", nullable=true)
     */
    public $dropdown_field;

    /**
     * Returns dynamic relations between module models and common models
     * MikoPBX check it in ModelsBase after every call to keep data consistent
     *
     * There is example to describe the relation between Providers and ModuleNotifier models
     *
     * It is important to duplicate the relation alias on message field after Models\ word
     *
     * @param $calledModelObject
     *
     * @return void
     */
    public static function getDynamicRelations(&$calledModelObject): void
    {

    }

    public function initialize(): void
    {
        $this->setSource('m_ModuleNotifier');
        parent::initialize();
    }

    public static function getSettings(){
        $parameters = [
            'cache' => [
                'key' => ModelsBase::makeCacheKey(self::class, 'getSettings'),
                'lifetime' => 300,
            ],
        ];
        return self::findFirst($parameters);
    }

}