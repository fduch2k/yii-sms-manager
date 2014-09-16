<?php

/**
 * SMS Gateway base class
 *
 * @link https://github.com/fduch2k/yii-sms-manager
 * @author Alexander Hramov <alexander.hramov@gmail.com>
 * @author Victor Tyurin
 * @copyright Copyright (c) 2012-2014, TagShake Ltd.
 * @license http://opensource.org/licenses/MIT
 */

abstract class TSSmsGateway extends CComponent
{
    public $username;
    public $password;
    public $sender;
    public $minCredit = 20;

    abstract public function send($phone, $text);
    abstract public function command($commands, $params);
    abstract public function getName();

    public function init()
    {
        $this->minCredit = CPropertyValue::ensureFloat($this->minCredit);
    }
}
