<?php

namespace Fondy\Api\Payment;

use Fondy\Api\Api;

class Rectoken extends Api
{
    private $url = '/recurring/';
    /**
     * Minimal required params to get checkout
     * @var array
     */
    private $requiredParams = [
        'merchant_id' => 'integer',
        'order_id' => 'string',
        'order_desc' => 'string',
        'currency' => 'string',
        'amount' => 'integer',
        'rectoken' => 'string'
    ];

    /**
     * @param $data
     * @param array $headers
     * @return mixed
     * @throws \Fondy\Exeption\ApiExeption
     */
    public function get($data, $headers = [])
    {
        $requestData = $this->prepareParams($data);
        $this->validate($requestData, $this->requiredParams);
        return $this->Request($method = 'POST', $this->url, $headers, $requestData);
    }
}