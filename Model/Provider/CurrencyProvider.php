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
namespace PostFinanceCheckout\Payment\Model\Provider;

use Magento\Framework\Cache\FrontendInterface;
use PostFinanceCheckout\Payment\Model\ApiClient;
use PostFinanceCheckout\Sdk\Service\CurrencyService;

/**
 * Provider of currency information from the gateway.
 */
class CurrencyProvider extends AbstractProvider
{

    /**
     *
     * @param FrontendInterface $cache
     * @param ApiClient $apiClient
     */
    public function __construct(FrontendInterface $cache, ApiClient $apiClient)
    {
        parent::__construct($cache, $apiClient, 'postfinancecheckout_payment_currencies');
    }

    /**
     * Gets the currency by the given code.
     *
     * @param string $code
     * @return \PostFinanceCheckout\Sdk\Model\RestCurrency
     */
    public function find($code)
    {
        return parent::find($code);
    }

    /**
     * Gets a list of currencies.
     *
     * @return \PostFinanceCheckout\Sdk\Model\RestCurrency[]
     */
    public function getAll()
    {
        return parent::getAll();
    }

    protected function fetchData()
    {
        return $this->_apiClient->getService(CurrencyService::class)->all();
    }

    protected function getId($entry)
    {
        /** @var \PostFinanceCheckout\Sdk\Model\RestCurrency $entry */
        return $entry->getCurrencyCode();
    }
}