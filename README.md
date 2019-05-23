Yii2 Wechat service provider
============================
基于Yii2的微信服务商扩展

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist c4ys/yii2-wx-service-provider "*"
```

or add

```
"c4ys/yii2-wx-service-provider": "*"
```

to the require section of your `composer.json` file.


用法
-----

config.php

```php
<?php
$config['wxProvider']=[
            'class' => c4ys\yii2wxserviceprovider\WxServiceProvider::class,
            'token'=>'',
            'encodingAesKey'=>'',
            'appId'=>'',
            'appSecret'=>'',
];

```


```php
<?php



```