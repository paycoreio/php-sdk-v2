<?php

namespace Cloudipsp\Response;

use Cloudipsp\Configuration;
use Cloudipsp\Exception\ApiException;
use Cloudipsp\Helper\ResponseHelper;
use Cloudipsp\Helper\ResultHelper;

class Response
{
    /**
     * @var string
     */
    protected $orderID;
    /**
     * @var string
     */
    protected $requestType;
    /**
     * @var array
     */
    protected $response;
    /**
     * @var string
     */
    protected $apiVersion;

    /**
     * Response constructor.
     * @param $data
     * @throws ApiException
     */
    public function __construct($data)
    {
        if (isset($data['order_id']))
            $this->orderID = $data['order_id'];
        $data = $data['response'];
        $this->requestType = Configuration::getRequestType();
        $this->apiVersion = Configuration::getApiVersion();
        switch ($this->requestType) {
            case 'xml':
                $response = ResponseHelper::xmlToArray($data);
                break;
            case 'form':
                $response['response'] = ResponseHelper::formToArray($data);
                break;
            case 'json':
                $response = ResponseHelper::jsonToArray($data);
                break;
        }

        $this->checkResponse($response);

        $this->response = $response;

    }

    /**
     * Check response on errors
     * @param $response
     * @return mixed
     * @throws ApiException
     */
    private function checkResponse($response)
    {
        if (isset($response['response']['response_status']) && $response['response']['response_status'] == 'failure')
            throw new ApiException('Request is incorrect.', 200, $response);
        if (isset($response['response']['error_code']))
            throw new ApiException('Request is incorrect.', 200, $response);
        return $response;
    }

    /**
     * Redirect to checkout
     */
    public function toCheckout()
    {
        $url = $this->getData()['checkout_url'];
        if (isset($url)) {
            header(sprintf('location: %s', $url));
            exit;
        }
    }

    /**
     * @return bool|string
     */
    public function getUrl()
    {
        if (isset($this->response['response']['checkout_url'])) {
            return $this->response['response']['checkout_url'];
        }
        if (isset($this->getData()['checkout_url'])) {
            return $this->getData()['checkout_url'];
        } else {
            return false;
        }
    }

    /**
     * @return mixed
     */
    public function getData()
    {
        if ($this->apiVersion === '2.0') {
            return ResponseHelper::getBase64Data($this->response);
        } else {
            return $this->response['response'];
        }
    }

    protected function buildVerifyData()
    {
        if ($this->apiVersion === '2.0') {
            $data = ResponseHelper::getBase64Data($this->response);
            $data['encodedData'] = $this->response['response']['data'];
            $data['signature'] = $this->response['response']['signature'];
        } else {
            $data = $this->getData();
        }
        return $data;
    }

    /**
     * @return mixed
     */
    public function getOrderID()
    {
        return $this->orderID ? $this->orderID : false;
    }

    /**
     * @return bool
     */
    public function isApproved()
    {
        $data = $this->buildVerifyData();
        return ResultHelper::isPaymentApproved($data, '', $this->apiVersion);
    }

    /**
     * @return bool
     */
    public function isValid()
    {
        $data = $this->buildVerifyData();
        return ResultHelper::isPaymentValid($data, '', $this->apiVersion);
    }
}