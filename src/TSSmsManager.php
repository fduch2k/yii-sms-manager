<?php

class TSSmsManager extends CApplicationComponent
{

    public $alertRepeatInterval = '00:10:00';
    public $maxBlockTime = '00:51:00';
    public $alertEmail = '';
    public $alertPhone = '';
    public $gateways = array();
    public $gatewayMinCredit = 20;

    const SMS_INTERVAL = 180;// 3 minutes

    public function init()
    {
        $this->maxBlockTime = $this->getTimeInterval($this->maxBlockTime);
        $this->alertRepeatInterval = $this->getTimeInterval($this->alertRepeatInterval);
        $gateways = array();
        foreach ($this->gateways as $gateway) {
            $gateways[] = Yii::createComponent($gateway);
        }
        $this->gateways = $gateways;
    }

    private function getTimeInterval($timeStr)
    {
        return strtotime($timeStr) - strtotime('00:00:00');
    }

    private function log($text)
    {
        Yii::log($text, 'debug.SmsManager');
    }

    private function sendAlertLow($gateway, $alertText)
    {
        $email = $this->alertEmail;
        $phone = $this->alertPhone;

        $className = get_class($gateway);
        $textHash = md5($alertText);

        $id = $className . '-lastAlert-' . $textHash;
        $lastAlertTime = Yii::app()->cache->get($id);

        if ($lastAlertTime === false) {
            $lastAlertTime = 0;
        }

        $this->log('time = ' . time() . ' lastAlertTime = ' . $lastAlertTime . ' alertRepeatInterval = ' . $this->alertRepeatInterval);
        //Сверяем время последней отправки этого текста для этого гейта
        if ((time() - $lastAlertTime) > $this->alertRepeatInterval) {
            Yii::app()                          ->cache->set($id, time());
            //Пробуем через смс-гейты отправить
            $ok = false;
            foreach ($this->gateways as $gateway) {
                $result = $gateway->send(array($phone => array()), $alertText);
                $ok = $result['ok'];
                if ($ok) {
                    break;
                }
            }

            $this->log('Send alert email');
            //Ещё емайл до кучи пошлём
            Yii::app()->mailer->sendMail($this->alertEmail, array($email), 'SMS Manager alert', $alertText);
        } else {
            $this->log('Skip alert: ' . $alertText . ' unblock time for this alert: ' . date("h:i:s", $lastAlertTime + $this->alertRepeatInterval));
        }
    }

    private function sendCreditsAlert($gateway, $credits)
    {
        $minCredit = floatval($this->gatewayMinCredit);
        $gatewayName = $gateway->getName();

        $text = 'Мало денег на счету смс-провайдера {gatewayName}: {credits}. Для стабильной работы нужно хотя бы {minCredits}';
        $text = Yii::t('TSSmsManager', $text, array('{gatewayName}' => $gatewayName, '{credits}' => $credits, '{minCredits}' => $minCredit));

        $this->sendAlertLow($gateway, $text);
    }

    private function sendAlert($gateway, $result)
    {
        $gatewayName = $gateway->getName();
        $credits = $result['credits'];
        $status = $result['status'];
        $response = $result['responseText'];

        if (is_numeric($credits)) {
            $text = 'Не получилось послать смс через {gatewayName}. Денег на счету: {credits}. Код результата (статус): {status}. Ответ от сервера: {response}';
        } else {

            $text = 'Не получилось послать смс через {gatewayName}. Код результата (статус): {status}. Ответ от сервера: {response}';
        }

        $text = Yii::t('TSSmsManager', $text, array('{gatewayName}' => $gatewayName, '{credits}' => $credits,
                'status' => $status, '{response}' => $response));

        $this->sendAlertLow($gateway, $text);
    }

    private function isGatewayBlocked($index)
    {
        $id = 'gatewayBlockTime' . $index;
        $blockTime = Yii::app()->cache->get($id);

        $this->log(
            "isGatewayBlocked: block time for gateway index $index = " .
            ($blockTime === false ? 'false' : date("h:i:s", $blockTime)) .
            ", unblock time = " .
            ($blockTime === false ? 'false' : date("h:i:s", $blockTime + $this->maxBlockTime))
        );

        if ($blockTime === false) {
            return false;
        }

        $isBlocked = (time() - $blockTime) < $this->maxBlockTime;

        if ($isBlocked) {
            $this->log("Gateway still blocked");
        } else {
            Yii::app()->cache->set($id, false);
            $this->log("Gateway unblocked - time expired");
        }

        return $isBlocked;
    }

    private function blockGateway($index)
    {
        $id = 'gatewayBlockTime' . $index;
        $blockTime = time();
        Yii::app()->cache->set($id, $blockTime);
    }

    private function getNextSmsGateway($index)
    {
        $indexStart = null;

        while (true) {
            $index++;
            if ($index >= count($this->gateways)) {
                $index = 0;
            }

            if ($index === $indexStart) {
                break;
            }

            if ($indexStart === null) {
                $indexStart = $index;
            }

            /////////

            $this->log("Gateway index to send: $index");

            if ($this->isGatewayBlocked($index)) {
                $this->log("Gateway with index = $index is blocked. Try next gateway");
            } else {
                $gateway = $this->gateways[$index];
                break;
            }
        };

        if ($gateway == null) {
            $this->log("All gateways are blocked");

            //Все движки блокированы - берём просто следующий
            if ($index < count($this->gateways) - 1) {
                $index = $indexStart + 1;
            } else {

                $index = 0;
            }

            $gateway = $index < count($this->gateways) ? $this->gateways[$index] : null;
        }

        if ($gateway === null) {
            $this->log("No gateway class found!");
        }

        return array('gateway' => $gateway, 'index' => $index);
    }

    public function send($phones, $text)
    {
        $result = null;
        $index = -1;
        $i = -1;
        while ($i++ < count($this->gateways)) {
            $res = $this->getNextSmsGateway($index);

            $gateway = $res['gateway'];
            $index = $res['index'];
            if ($gateway == null) {
                $this->log("Gateway = null. Exit from send function, index = $index");
                break;
            }

            $minCredit = floatval($gateway->minCredit);

            $this->log("Sending sms using {$gateway->getName()}, minCredit = $minCredit");
            $result = $gateway->send($phones, $text);
            $credits = floatval($result['credits']);

            if ($result['ok']) {
                $this->log("Send ok. Credits on account: $credits, min credits: $minCredit");

                if ($credits <= $minCredit) {
                    $this->log("Send credits alert");
                    $this->sendCreditsAlert($gateway, $credits);
                }

                break;
            } else {
                $this->log("Bad send result: " . $result['status'] . ". Sending gateway alert");
                $this->sendAlert($gateway, $result);
                $this->log("Block gateway " . get_class($gateway));

                $this->blockGateway($index);
            }
        }

        return $result;
    }
}
