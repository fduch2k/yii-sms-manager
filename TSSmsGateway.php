<?php

abstract class TSSmsGateway extends CComponent
{
    public $params = array();

    abstract public function send($phone, $text);
    abstract public function command($commands, $params);
    abstract public function getName();
}
