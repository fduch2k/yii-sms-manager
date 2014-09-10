<?php

class TSDummyGateway extends TSSmsGateway
{

    public function getName()
    {
        return "DummySms";
    }

    private function getParams()
    {
        return Yii::app()->params['sms']['TSDummyGateway'][Yii::app()->language];
    }

    public function send($phones, $text)
    {
        return array();
    }

    public function command($operations, $params)
    {

    }

}
