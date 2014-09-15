<?php

abstract class TSSmsGateway extends CComponent
{
    public $username;
    public $password;
    public $sender;
    public $minCredit = 20;

    abstract public function send($phone, $text);
    abstract public function command($commands, $params);
    abstract public function getName();
}
