# SMS Manager for Yii 1.x framework with balancing, balance watching and notifications

This extension allows you setup several sms gateway with built-in balance watching and low balance or sending problems notification. 

## Instalation

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist fduch2k/yii-sms-manager "*"
```

or add

```json
"fduch2k/yii-sms-manager": "*"
```

to the require section of your composer.json.


## Configuration

To use this extension, you have to configure the Connection class in your application configuration:

```php
return array(
    //....
    'components' => array(
        'sms' => array(
            'class'=>'TSSmsManager',
            'alertPhone'=>'mobile phone number for sending sms with notification',
            'alertEmail'=>'email for sending notification',
            'gateways'=>array(
                array(
                    'class'=>'TSAtomParkGateway',
                    'sender'=>'senderid1',
                    'username'=>YOUR_USERNAME,
                    'password'=>YOUR_PASSWORD,
                ),
                array(
                    'class'=>'TSSmsFeedBackGateway',
                    'sender'=>'senderid2',
                    'username'=>YOUR_USERNAME,
                    'password'=>YOUR_PASSWORD,
                ),
            ),
        ),
    )
);
```

## Usage

This code will send 2 sms to $phoneNumber1 - 'Hello, Alex. Your password is qwerty' and $phoneNumber2 - 'Hello, Peter! Your password is 123456'
```php
Yii::app()->sms->send(array(array($phoneNumber1=>array('Alex', 'qwerty'), $phoneNumber2=>array('Peter', '123456')))), 'Hello, %1%! Your password is %2%');
```

