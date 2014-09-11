<?php

class TSDummyGateway extends TSSmsGateway
{

    public function getName()
    {
        return "DummySms";
    }

    public function send($phones, $text)
    {
        return array();
    }

    public function command($operations, $params)
    {

    }

}
