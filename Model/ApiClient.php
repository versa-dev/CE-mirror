<?php
/**
 * PostFinance Checkout Magento 2
 *
 * This Magento 2 extension enables to process payments with PostFinance Checkout (https://www.postfinance.ch/).
 *
 * @package PostFinanceCheckout_Payment
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */
namespace PostFinanceCheckout\Payment\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;

/**
 * Service to provide PostFinance Checkout API client.
 */
class ApiClient
{

    /**
     *
     * @var ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     *
     * @var EncryptorInterface
     */
    protected $_encrypter;

    /**
     *
     * @var \PostFinanceCheckout\Sdk\ApiClient
     */
    private $apiClient;

    /**
     * List of shared service instances
     *
     * @var array
     */
    private $sharedInstances = [];

    /**
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param EncryptorInterface $encrypter
     */
    public function __construct(ScopeConfigInterface $scopeConfig, EncryptorInterface $encrypter)
    {
        $this->_scopeConfig = $scopeConfig;
        $this->_encrypter = $encrypter;
    }

    /**
     * Retrieve cached service instance.
     *
     * @param string $type
     */
    public function getService($type)
    {
        $type = \ltrim($type, '\\');
        if (! isset($this->sharedInstances[$type])) {
            $this->sharedInstances[$type] = new $type($this->getApiClient());
        }
        return $this->sharedInstances[$type];
    }

    /**
     * Gets the gateway API client.
     *
     * @throws \PostFinanceCheckout\Payment\Model\ApiClientException
     * @return \PostFinanceCheckout\Sdk\ApiClient
     */
    public function getApiClient()
    {
        if ($this->apiClient == null) {
            $userId = $this->_scopeConfig->getValue('postfinancecheckout_payment/general/api_user_id');
            $applicationKey = $this->_scopeConfig->getValue('postfinancecheckout_payment/general/api_user_secret');
            if (! empty($userId) && ! empty($applicationKey)) {
                $client = new \PostFinanceCheckout\Sdk\ApiClient($userId, $this->_encrypter->decrypt($applicationKey));
                $client->setBasePath($this->getBaseGatewayUrl() . '/api');
                $this->apiClient = $client;
            } else {
                throw new \PostFinanceCheckout\Payment\Model\ApiClientException('The PostFinance Checkout API user data are incomplete.');
            }
        }
        return $this->apiClient;
    }

    /**
     * Gets whether the required data to connect to the gateway are provided.
     *
     * @return boolean
     */
    public function checkApiClientData()
    {
        $userId = $this->_scopeConfig->getValue('postfinancecheckout_payment/general/api_user_id');
        $applicationKey = $this->_scopeConfig->getValue('postfinancecheckout_payment/general/api_user_secret');
        if (! empty($userId) && ! empty($applicationKey)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Gets the base URL to the gateway.
     *
     * @return string
     */
    protected function getBaseGatewayUrl()
    {
        return \rtrim($this->_scopeConfig->getValue('postfinancecheckout_payment/general/base_gateway_url'), '/');
    }
}