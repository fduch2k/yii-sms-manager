<?php

//http://www.massmailsoftware.com/bulksmsandpager/smsapi.htm

class TSAtomParkGateway extends TSSmsGateway
{

    public $username;
    public $password;
    public $sender;

    const SEND = 'SEND';
    const GETSTATUS = 'GETSTATUS';
    const BALANCE = 'BALANCE';
    const CREDITPRICE = 'CREDITPRICE';

    private $_body = "<?xml version=\"1.0\" encoding=\"UTF-8\"?><SMS>\n{body}\n</SMS>";
    private $_operations = "<operations>\n{operations}\n</operations>\n";
    private $_operation = '<operation>{operation}</operation>';
    private $_auth = "<authentification>\n<username>{username}</username>\n<password>{password}</password>\n</authentification>\n";

    // SEND
    private $_sms = "<message>\n<sender>{sender}</sender>\n<text><![CDATA[{text}]]></text>\n</message>\n";
    private $_numbers = "<numbers>\n{numbers}</numbers>";
    private $_number = "<number messageID=\"{messageid}\" variables=\"{variables}\">{number}</number>\n";

    // GETSTATUS
    //private $_statistics = "<statistics>\n{messages}</statistics>";
    //private $_messageid = "<messageid>{messageid}</messageid>\n";

    public function getName()
    {
        return "Atompark";
    }

    private function doRequest($postData)
    {
        $curl = curl_init();
        $curlOptions = array(
            CURLOPT_URL => 'http://atompark.com/members/sms/xml.php',
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_POST => true,
            CURLOPT_HEADER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 100,
            CURLOPT_POSTFIELDS => array('XML' => $postData),
        );
        curl_setopt_array($curl, $curlOptions);

        if (false === ($response = curl_exec($curl))) {
            throw new Exception('Http request failed with code ' . CVarDumper::dumpAsString($response));
        }
        curl_close($curl);

        return $response;
    }

    public function send($phones, $text)
    {
        $operations = strtr($this->_operations, array('{operations}' => strtr($this->_operation, array('{operation}' => self::SEND))));
        $sms = strtr($this->_sms, array('{text}' => $text, '{sender}' => $this->sender));
        if (is_array($phones) == false) {
            $phones = array($phones => array());
        }

        $number = '';
        foreach ($phones as $phone => $value) {
            $number .= strtr($this->_number, array(
                    '{number}' => $phone,
                    '{variables}' => implode(';', CPropertyValue::ensureArray($value)) . ';',
                    '{messageid}' => md5($phone),
                ));
        }

        $numbers = strtr($this->_numbers, array('{numbers}' => $number));
        $result = strtr($this->_body, array('{body}' => $operations . $this->auth . $sms . $numbers));

        $response = $this->doRequest($result);

        preg_match('/<status>(.+)<\/status>.*<credits>(.+)<\/credits>/ism', $response, $match);

        $status = $match[1];
        $ok = (bool) ($status > 0);
        $needSmsAlert = ($ok == false) && ($status != -4);

        $operations = strtr($this->_operations, array('{operations}' => strtr($this->_operation, array('{operation}' => self::BALANCE))));
        $result = strtr($this->_body, array('{body}' => $operations . $this->auth));
        $balanceResponse = $this->doRequest($result);

        $match = null;
        preg_match('/<status>(.+)<\/status>.*<credits>(.+)<\/credits>/ism', $balanceResponse, $match);
        $credits = $match[2];

        return array('status' => $status, 'ok' => $ok, 'responseText' => $response, 'credits' => $credits,
            'needSmsAlert' => $needSmsAlert);
    }

    protected function getAuth()
    {
        $result = strtr($this->_auth, array('{username}' => $this->username, '{password}' => $this->password));
        return $result;
    }

    public function command($operations, $params)
    {

    }
}
