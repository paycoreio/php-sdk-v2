<?php

namespace Fondy\Api;

use Fondy\Configuration;
use Fondy\Exeption\ApiExeption;
use Fondy\Helper as Helper;

class Api
{
    /**
     * @var string request client
     */
    protected $client;
    /**
     * @var string
     */
    protected $version;
    /**
     * @var int
     */
    protected $mid;
    /**
     * @var string
     */
    protected $secretKey;
    /**
     * @var string
     */
    protected $requestType;

    /**
     * @param $data
     * @throws ApiExeption
     */
    public function __construct($type = '')
    {
        $this->version = Configuration::getApiVersion();
        $this->mid = Configuration::getMerchantId();
        $this->setKeyByOperationType($type);
        if (empty($this->mid) or !is_numeric($this->mid))
            throw new ApiExeption('Merchant ID is empty or invalid.');
        if (empty($this->secretKey))
            throw new ApiExeption('Secret Key is empty or invalid.');
        $this->client = Configuration::getHttpClient();
        $this->requestType = Configuration::getRequestType();
    }

    /**
     * @param $method
     * @param $url
     * @param $headers
     * @param $data
     * @return mixed
     * @throws ApiExeption
     */
    public function Request($method, $url, $headers, $data)
    {
        $url = $this->createUrl($url);
        $data = $this->getDataByVersion($data);
        $headers = Helper\RequestHelper::parseHeadres($headers, $this->requestType);
        $response = $this->client->request($method, $url, $headers, $data);
        if (!$response)
            throw new ApiExeption('Unknown error.');

        return $response;

    }

    /**
     * @param $data
     * @return string or array
     */
    protected function converDataV1($data)
    {
        if (!isset($data['signature']))
            $data['signature'] = Helper\ApiHelper::generateSignature($data, $this->secretKey, $this->version);
        switch ($this->requestType) {
            case 'xml':
                $convertedData = Helper\ApiHelper::toXML(['request' => $data]);
                break;
            case 'form':
                $convertedData = Helper\ApiHelper::toFormData($data);
                break;
            case 'json':
                $convertedData = Helper\ApiHelper::toJSON(['request' => $data]);
                break;
        }

        return $convertedData;
    }

    /**
     * @param $data
     * @return string
     */
    protected function converDataV2($data)
    {
        if (isset($data['signature']))
            unset($data['signature']);
        $convertedData = [
            "version" => "2.0",
            "data" => base64_encode(Helper\ApiHelper::toJSON(['order' => $data]))
        ];

        $convertedData["signature"] = Helper\ApiHelper::generateSignature($convertedData["data"], $this->secretKey, $this->version);

        return Helper\ApiHelper::toJSON(['request' => $convertedData]);
    }

    /**
     * @param $params
     * @return mixed
     */
    protected function prepareParams($params)
    {
        $prepared_params = $params;

        if (!isset($prepared_params['merchant_id'])) {
            $prepared_params['merchant_id'] = $this->mid;
        }

        if (!isset($prepared_params['order_id'])) {
            $prepared_params['order_id'] = Helper\ApiHelper::generateOrderID($this->mid);
        }

        if (!isset($prepared_params['order_desc'])) {
            $prepared_params['order_desc'] = Helper\ApiHelper::generateOrderDesc($prepared_params['order_id']);
        }

        if (isset($prepared_params['merchant_data']) && is_array($prepared_params['merchant_data'])) {
            $prepared_params['merchant_data'] = Helper\ApiHelper::toJSON($prepared_params['merchant_data']);
        }

        if (isset($prepared_params['recurring_data']) && $this->version === '1.0')
            throw new \InvalidArgumentException('Reccuring_data allowed only for api version \'2.0\'');

        if (isset($prepared_params['reservation_data']['products'])) {
            $prepared_params['reservation_data'] = base64_encode(Helper\ApiHelper::toJSON($prepared_params['reservation_data']));
        }

        return $prepared_params;
    }

    /**
     * @param $data
     * @return string
     * @throws ApiExeption
     */
    protected function getDataByVersion($data)
    {
        if (!$this->version)
            throw new ApiExeption('Unknown api version');

        switch ($this->version) {
            case '1.0':
                $convertedData = $this->converDataV1($data);
                break;
            case '2.0':
                if ($this->requestType != 'json') {
                    throw new ApiExeption('Invalid request type. In protocol \'2.0\' only \'json\' allowed.');
                }
                $convertedData = $this->converDataV2($data);
                break;
        }
        return $convertedData;
    }

    protected function validate($params, $required)
    {
        Helper\ValidationHelper::validateRequiredParams($params, $required);
    }

    /**
     * @param $url
     * @return string
     */
    protected function createUrl($url)
    {
        return Configuration::getApiUrl() . $url;
    }

    /**
     * setting secret key by operation type
     * @param $type
     */
    protected function setKeyByOperationType($type = '')
    {
        if ($type === 'credit') {
            $this->secretKey = Configuration::getCreditKey();
        } else {
            $this->secretKey = Configuration::getSecretKey();
        }
    }
}