<?php

class TSSmsFeedBackGateway extends TSSmsGateway
{
    const SEND_URL = 'http://json.api.smsfeedback.ru/send/';
    const CREDITS_URL = 'http://json.api.smsfeedback.ru/credits/';

    private function getUrl($url, $args)
    {
        $argsJson = CJSON::encode($args);

        $curl = curl_init();
        $curlOptions = array(
            CURLOPT_URL => $url,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_POST => true,
            CURLOPT_HEADER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 100,
            CURLOPT_POSTFIELDS => $argsJson,
        );
        curl_setopt_array($curl, $curlOptions);

        if (false === ($response = curl_exec($curl))) {
            throw new Exception('Http request failed with code ' . CVarDumper::dumpAsString($response));
        }
        curl_close($curl);

        return $response;
    }

    public function getName()
    {
        return "Sms feedback";
    }

    public function send($phones, $text)
    {
        $sender = $this->sender;

        $result = array();
        foreach ($phones as $phone => $variables) {
            $variables = CPropertyValue::ensureArray($variables);
            $textMsg = $text;

            $i = 1;
            foreach ($variables as $variable) {
                $textMsg = str_replace('%' . $i . '%', $variable, $textMsg);
                $i++;
            }

            $args = array('messages' => array(array(
                        'clientId' => $sender,
                        'phone' => $phone,
                        'text' => $text,
                        'username' => $this->username,
                        'password' => $this->password,
                    )));

            $responseText = $this->getUrl(self::SEND_URL, $args);
            $response = CJSON::decode($responseText);

            $resultStatus = false;
            $credits = 0;

            $status = isset($response['status']) ? $response['status'] : 'no status';
            if ($status == 'ok') {
                if (isset($response['messages']) == false || count($response['messages']) == 0) {
                    throw new Exception('No messages in response');
                }

                $message = $response['messages'][0];
                $status = isset($message['status']) ? $message['status'] : 'no status';

                $resultStatus = $status == 'accepted';

                $response = $this->getUrl(self::CREDITS_URL, array());

                if (isset($response['credits'])) {
                    $credits = $response['credits'];
                } else {

                    throw new Exception('No credits in response');
                }
            }

            $result = array('ok' => $status == 'ok',
                'status' => $status,
                'responseText' => $responseText,
                'credits' => $credits,
                'needSmsAlert' => true);
        }

        return $result;
    }

    public function command($commands, $params)
    {
    }
}
