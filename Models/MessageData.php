<?php
/**
 * Copyright © MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Alexey Portnov, 2 2019
 */

namespace Modules\ModuleNotifier\Models;

use MikoPBX\Modules\Models\ModulesModelsBase;
use Modules\ModuleNotifier\Lib\Providers\CdrDbProvider;

/**
 * Class MessageData
 *
 * @package MikoPBX\Common\Models
 *
 * @Indexes(
 *     [name='messageId', columns=['messageId'], type=''],
 *     [name='linkedId', columns=['linkedId'], type='']
 * )
 */
class MessageData extends ModulesModelsBase
{

    /**
     * @Primary
     * @Identity
     * @Column(type="integer", nullable=false)
     */
    public $id;

    /**
     * @Column(type="string", nullable=true)
     */
    public $messageId;

    /**
     * @Column(type="string", nullable=true)
     */
    public $linkedId;

    /**
     * @Column(type="string", nullable=true)
     */
    public $messageText;


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
        $this->setSource('messageData');
        parent::initialize();
        $this->useDynamicUpdate(true);
        if(!$this->di->has(CdrDbProvider::SERVICE_NAME)){
            $this->di->register(new CdrDbProvider());
        }
        $this->setConnectionService(CdrDbProvider::SERVICE_NAME);
    }
}