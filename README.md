# AuthorizeJS-PHP
## How to install
Add the following to your composer.json
```
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/TheWanderingCrow/AuthorizeJS-PHP"
    }
],
"require": {
    "thewanderingcrow/authorize-js-php": "dev-main"
},
```
## How to use
Create a new payment object with required parameters
```php
<?php

require __DIR__ . "/vendor/autoload.php";

$api_login_id = "test";
$transaction_key = "test";

$payment = new thewanderingcrow\Payment(api_login_id: $api_login_id, transaction_key: $transaction_key);
```

You may then insert a payment button into your template with
```php
$callbackUrl = "https://example.com/callback";

$payment->insertPaymentButton(callbackUrl: $callbackUrl);
```

The data passed via $_POST to callback should be processed with `$payment->processCard($authData)`

Optional Parameters are:
isTest: set this to `true` if you are using the testing endpoint
buttonText: default is "Pay Now"
style: default is "", accepts any valid inline css styling
