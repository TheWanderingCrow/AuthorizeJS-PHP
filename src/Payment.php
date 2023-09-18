<?php

namespace thewanderingcrow;

use Academe\AuthorizeNet;
use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;

class Payment
{

    private string $api_login_id;
    private string $transaction_key;
    private string $callbackUrl;
    private string $buttonText;
    private bool $isTest;
    private string $style;
    private $formTemplate = <<<HEREDOC
    
    <form id="paymentForm"
        method="POST"
        action="{{callbackUrl}}">
        <input type="hidden" name="dataValue" id="dataValue" />
        <input type="hidden" name="dataDescriptor" id="dataDescriptor" />
        <button type="button"
            class="AcceptUI"
            data-billingAddressOptions='{"show":true, "required":false}' 
            data-apiLoginID="{{apiId}}" 
            data-clientKey="{{publicKey}}"
            data-acceptUIFormBtnTxt="Submit" 
            data-acceptUIFormHeaderTxt="Payment Information" 
            data-responseHandler="responseHandler"
            style="{{style}}"
            >{{buttonText}}
        </button>
    </form>

    <script type="text/javascript"
        src="https://jstest.authorize.net/v3/AcceptUI.js"
        charset="utf-8">
    </script>
    
    <script type="text/javascript">
    
    function responseHandler(response) {
        if (response.messages.resultCode === "Error") {
            var i = 0;
            while (i < response.messages.message.length) {
                console.log(
                    response.messages.message[i].code + ": " +
                    response.messages.message[i].text
                );
                i = i + 1;
            }
        } else {
            paymentFormUpdate(response.opaqueData);
        }
    }
    
    
    function paymentFormUpdate(opaqueData) {
        document.getElementById("dataDescriptor").value = opaqueData.dataDescriptor;
        document.getElementById("dataValue").value = opaqueData.dataValue;
        document.getElementById("paymentForm").submit();
    }
    </script>
    HEREDOC;

    /**
     * Construct a Payment Object
     * 
     * @param String $api_login_id Authorize.NET login id
     * @param String $transaction_key Authorize.NET transaction key
     * @param ?bool $isTest false uses Authorize.NET production servers; true uses the sandbox servers
     * 
     */
    public function __construct(string $api_login_id, string $transaction_key, ?bool $isTest = false)
    {
        $this->api_login_id = $api_login_id;
        $this->transaction_key = $transaction_key;
        $this->isTest = $isTest;
    }

    /**
     * Generates the HTML/JS required for a payment button.  
     * @param String $callbackUrl URL that credit card capture will call to finish processing
     * @param ?String $buttonText default is "Pay Now"
     * @param ?String $style Inline CSS styling for button
     * 
     * @return String
     */
    public function insertPaymentButton(string $callbackUrl, ?string $buttonText = "Pay Now", ?string $style = "")
    {
        $form = $this->bind_to_template([
            'apiId' => $this->api_login_id,
            'publicKey' => $this->getPublicKey(),
            'callbackUrl' => $callbackUrl,
            'buttonText' => $buttonText,
            'style'=>$style
        ], $this->formTemplate);

        echo $form;
    }

    /**
     * Attempts to apply an amount to a previously captured card.
     * 
     * @param Array $authData The data returned by the callback event
     * @param String $amount The amount to charge to the card
     * @param String $customerId The customer ID that will be recorded in this transaction
     * @param String $currency defaults to 'USD'
     * @param $customerType see Authorize.NET documentation.  defaults to \Academe\AuthorizeNet\Request\Model\Customer::CUSTOMER_TYPE_INDIVIDUAL
     * 
     * @return Array attempt response
     */
    public function processCard($authData, $amount, $customerId, $currency = 'USD', $customerType = \Academe\AuthorizeNet\Request\Model\Customer::CUSTOMER_TYPE_INDIVIDUAL)
    {
        $gateway = \Omnipay\Omnipay::create('AuthorizeNetApi_Api');

        $gateway->setAuthName($this->api_login_id);
        $gateway->setTransactionKey($this->transaction_key);
        $gateway->setTestMode($this->isTest);

        // this is left blank because we are using tokenized card data
        $creditCard = new \Omnipay\Common\CreditCard([
            // Swiped tracks can be provided instead, if the card is present.
            'number' => '',
            'expiryMonth' => '',
            'expiryYear' => '',
            'cvv' => '',
            // Billing and shipping details can be added here.
        ]);

        // Generate a unique merchant site transaction ID.
        $transactionId = rand(100000000, 999999999);

        $response = $gateway->purchase([
            'amount' => $amount,
            'currency' => $currency,
            'transactionId' => $transactionId,
            'card' => $creditCard,
            // Additional optional attributes:
            'customerId' => $customerId,
            'customerType' => $customerType,
            'opaqueDataDescriptor' => $authData['dataDescriptor'],
            'opaqueDataValue' => $authData['dataValue'],
        ])->send();

        return $response->getData();
    }
    
    /**
     * 
     */
    private function getPublicKey()
    {
        /* Create a merchantAuthenticationType object with authentication details
       retrieved from the constants file */
        $merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
        $merchantAuthentication->setName($this->api_login_id);
        $merchantAuthentication->setTransactionKey($this->transaction_key);

        // Set the transaction's refId
        $refId = 'ref' . time();

        $request = new AnetAPI\GetMerchantDetailsRequest();
        $request->setMerchantAuthentication($merchantAuthentication);

        $controller = new AnetController\GetMerchantDetailsController($request);

        if ($this->isTest) {
            $response = $controller->executeWithApiResponse(\net\authorize\api\constants\ANetEnvironment::SANDBOX);
        } else {
            $response = $controller->executeWithApiResponse(\net\authorize\api\constants\ANetEnvironment::PRODUCTION);
        }

        if (($response != null) && ($response->getMessages()->getResultCode() == "Ok")) {
            return $response->getPublicClientKey();
        } else {
            $errorMessages = $response->getMessages()->getMessage();
            throw new \Exception("Response : " . $errorMessages[0]->getCode() . "  " . $errorMessages[0]->getText() . "\n");
        }
    }

    /**
     * 
     */
    private function bind_to_template($replacements, $template)
    {
        return preg_replace_callback(
            '/{{(.+?)}}/',
            function ($matches) use ($replacements) {
                return $replacements[$matches[1]];
            },
            $template
        );
    }
}
