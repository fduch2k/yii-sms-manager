<?php

/**
 * SMS Manager
 *
 * @link https://github.com/fduch2k/yii-sms-manager
 * @author Alexander Hramov <alexander.hramov@gmail.com>
 * @author Victor Tyurin
 * @copyright Copyright (c) 2012-2014, TagShake Ltd.
 * @license http://opensource.org/licenses/MIT
 */

class TSSmsManager extends CApplicationComponent
{

    public $alertRepeatInterval = '00:10:00';
    public $maxBlockTime = '00:10:00';
    public $alertEmail = '';
    public $alertPhone = '';
    public $gateways = array();
    public $gatewayMinCredit = 20;

    public function init()
    {
        $this->maxBlockTime = $this->getTimeInterval($this->maxBlockTime);
        $this->alertRepeatInterval = $this->getTimeInterval($this->alertRepeatInterval);
        $this->gatewayMinCredit = CPropertyValue::ensureFloat($this->gatewayMinCredit);
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
        $textHash = md5($alertText);

        $id = "{$gateway->getName()}-lastAlert-$textHash";
        $lastAlertTime = Yii::app()->getGlobalState($id, 0);

        $currentTime = time();
        $this->log('time = ' . $currentTime . ' lastAlertTime = ' . $lastAlertTime . ' alertRepeatInterval = ' . $this->alertRepeatInterval);
        if (($currentTime - $lastAlertTime) > $this->alertRepeatInterval) {
            Yii::app()->setGlobalState($id, $currentTime);
            foreach ($this->gateways as $gateway) {
                $result = $gateway->send(array($this->alertPhone => array()), $alertText);
                $ok = $result['ok'];
                if ($ok) {
                    break;
                }
            }

            $this->log('Send alert email');
            mail($this->alertEmail, Yii::t('TSSmsManager', 'SMS Manager alert'), $alertText);
        } else {
            $this->log('Skip alert: ' . $alertText . ' unblock time for this alert: ' . date("h:i:s", $lastAlertTime + $this->alertRepeatInterval));
        }
    }

    private function sendCreditsAlert($gateway, $credits)
    {
        $text = Yii::t(
            'TSSmsManager',
            'Мало денег на счету смс-провайдера {gatewayName}: {credits}. Для стабильной работы нужно хотя бы {minCredits}',
            array(
                '{gatewayName}' => $gateway->getName(),
                '{credits}' => $credits,
                '{minCredits}' => $this->gatewayMinCredit
            )
        );

        $this->sendAlertLow($gateway, $text);
    }

    private function sendAlert($gateway, $result)
    {
        $messages = array(
            'Не получилось послать смс через {gatewayName}. Код результата (статус): {status}. Ответ от сервера: {response}',
            'Не получилось послать смс через {gatewayName}. Денег на счету: {credits}. Код результата (статус): {status}. Ответ от сервера: {response}',
        );

        $text = Yii::t(
            'TSSmsManager',
            $messages[is_numeric($result['credits'])],
            array(
                '{gatewayName}' => $gateway->getName(),
                '{credits}' => $result['credits'],
                '{status}' => $result['status'],
                '{response}' => $result['responseText']
            )
        );

        $this->sendAlertLow($gateway, $text);
    }

    private function isGatewayBlocked($index)
    {
        $id = "gatewayBlockTime$index";
        $blockTime = Yii::app()->getGlobalState($id, 0);

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
            Yii::app()->setGlobalState($id, 0);
            $this->log("Gateway unblocked - time expired");
        }

        return $isBlocked;
    }

    private function blockGateway($index)
    {
        $id = "gatewayBlockTime$index";
        Yii::app()->setGlobalState($id, time());
    }

    private function getNextSmsGateway($index)
    {
        $indexStart = null;
        $gateway = null;

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

            $minCredit = $gateway->minCredit;

            $this->log("Sending sms using {$gateway->getName()}, minCredits = $minCredit");
            $result = $gateway->send($phones, $text);
            $credits = floatval($result['credits']);

            $this->log("Response text {$result['responseText']}");
            if ($result['ok']) {
                $this->log("Sent. Credits on account: $credits, minCredits: $minCredit");

                if ($credits <= $minCredit) {
                    $this->log("Send credits alert");
                    $this->sendCreditsAlert($gateway, $credits);
                }

                break;
            } else {
                $this->log("Bad send result: {$result['status']}. Sending gateway alert");
                $this->sendAlert($gateway, $result);
                $this->log("Block gateway {$gateway->getName()}");

                $this->blockGateway($index);
            }
        }

        return $result;
    }
}
