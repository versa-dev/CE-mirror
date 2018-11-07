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
use PostFinanceCheckout\Sdk\Service\PaymentMethodService;

/**
 * Provider of payment method information from the gateway.
 */
class PaymentMethodProvider extends AbstractProvider
{

    /**
     *
     * @param FrontendInterface $cache
     * @param ApiClient $apiClient
     */
    public function __construct(FrontendInterface $cache, ApiClient $apiClient)
    {
        parent::__construct($cache, $apiClient, 'postfinancecheckout_payment_methods');
    }

    /**
     * Gets the payment method by the given id.
     *
     * @param int $id
     * @return \PostFinanceCheckout\Sdk\Model\PaymentMethod
     */
    public function find($id)
    {
        return parent::find($id);
    }

    /**
     * Gets a list of payment methods.
     *
     * @return \PostFinanceCheckout\Sdk\Model\PaymentMethod[]
     */
    public function getAll()
    {
        return parent::getAll();
    }

    protected function fetchData()
    {
        return $this->_apiClient->getService(PaymentMethodService::class)->all();
    }

    protected function getId($entry)
    {
        /** @var \PostFinanceCheckout\Sdk\Model\PaymentMethod $entry */
        return $entry->getId();
    }
}