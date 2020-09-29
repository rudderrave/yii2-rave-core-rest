<?php


namespace ravesoft\models;

use Yii;
use yii\httpclient\Client;

class RestClient {

    const HEADER_MODULE = 'module';
    const HEADER_KEY = 'key';

    /**
     * Url to Rest API
     *
     * @var string
     */
    public  $apiUrl = '';

    private $httpClient;
    /**
     * Header option in array. Insert in request
     *
     * @var array
     */
    private $header = [];

    /**
     * RestClient constructor.
     * @param null $apiUrl
     * @param null $header
     */
    public function __construct($apiUrl = null, $header = null) {

        if(isset($apiUrl)) {
            $this->apiUrl = $apiUrl;
        } else {
            $this->apiUrl = Yii::$app->rave->auth_link;
        }

        if(isset($header)) {
            $this->header = $header;
        } else {
            $this->header = [
                RestClient::HEADER_MODULE => Yii::$app->rave->auth_module,
                RestClient::HEADER_KEY => Yii::$app->rave->auth_key
            ];
        }
        $httpClient = null;
    }

    /**
     * @return \yii\httpclient\Request
     * @throws \yii\base\InvalidConfigException
     */
    public function CreateRequest()
    {
        if (!isset($this->httpClient)) {
            $this->httpClient = new Client(['baseUrl' => $this->apiUrl]);
        }

        $request = $this->httpClient->createRequest();
        $request->addHeaders([self::HEADER_MODULE => $this->header[self::HEADER_MODULE]]);
        $request->addHeaders([self::HEADER_KEY => $this->header[self::HEADER_KEY]]);

        return $request;
    }

}