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

    private function createOperationsElement(DOMDocument $doc, Array $operations)
    {
        $operations = new DOMElement('operations');
        foreach ($operations as $operation) {
            $operations->appendChild($doc->createElement('operation', $operation));
        }
        return $operations;
    }

    private function createAuthElement(DOMDocument $doc)
    {
        $authentification = $doc->createElement('authentification');
        $authentification->appendChild($doc->createElement('username', $this->username));
        $authentification->appendChild($doc->createElement('password', $this->password));
        return $authentification;
    }

    private function parseResponse($responseText)
    {
        $result = false;
        $response = new DOMDocument('1.0', 'UTF-8');
        if ($response->loadXML($responseText)) {
            $statuses = $response->getElementsByTagName('status');
            $status = $statuses->length > 0 ? intval($statuses->item(0)->nodeValue) : '';
            $credits = $response->getElementsByTagName('credits');
            $credit = $credits->length > 0 ? floatval($credits->item(0)->nodeValue) : '';
            $result = array('status'=>$status, 'credits'=>$credit);
        }
        return $result;
    }

    public function send($phones, $text)
    {
        $request = new DOMDocument("1.0", "UTF-8");
        $requestBody = $request->createElement('SMS');
        $requestBody->appendChild($this->createOperationsElement(array(self::SEND)));
        $requestBody->appendChild($this->createAuthElement());
        $request->appendChild($requestBody);

        $sms = $request->createElement('message');
        $sms->appendChild($request->createElement('sender', $this->sender));
        $textElement = $request->createElement('text');
        $textElement->appendChild(new DOMCdataSection($text));
        $sms->appendChild($textElement);
        $requestBody->appendChild($sms);

        if (is_array($phones) == false) {
            $phones = array($phones => array());
        }

        $numbers = $request->createElement('numbers');
        foreach ($phones as $phone => $value) {
            $variables = implode(';', CPropertyValue::ensureArray($value)) . ';';
            $number = $request->createElement('number', $phone);
            $number->setAttribute('messageid', md5($phone.date().$variables));
            $number->setAttribute('variables', $variables);
            $numbers->appendChild($number);
        }
        $requestBody->appendChild($numbers);

        $responseText = $this->doRequest($request->saveXML());
        $result = $this->parseResponse($responseText);

        $status = $result['status'];
        $ok = (bool)($status > 0);
        $needSmsAlert = ($ok == false) && ($status != -4);

        $credits = $this->balance();

        return array('status' => $status, 'ok' => $ok, 'responseText' => $responseText, 'credits' => $credits,
            'needSmsAlert' => $needSmsAlert);
    }

    public function balance()
    {
        $request = new DOMDocument("1.0", "UTF-8");
        $requestBody = $request->createElement('SMS');
        $requestBody->appendChild($this->createOperationsElement(array(self::BALANCE)));
        $requestBody->appendChild($this->createAuthElement());
        $request->appendChild($requestBody);

        $responseText = $this->doRequest($request->saveXML());
        $result = $this->parseResponse($responseText);
        if ($result['status'] === 0) {
            return $result['credits'];
        }
        return false;
    }

    public function command($operations, $params)
    {

    }
}
