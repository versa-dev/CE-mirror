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
use PostFinanceCheckout\Sdk\Service\LabelDescriptionService;

/**
 * Provider of label descriptor information from the gateway.
 */
class LabelDescriptorProvider extends AbstractProvider
{

    /**
     *
     * @param FrontendInterface $cache
     * @param ApiClient $apiClient
     */
    public function __construct(FrontendInterface $cache, ApiClient $apiClient)
    {
        parent::__construct($cache, $apiClient, 'postfinancecheckout_payment_label_descriptors');
    }

    /**
     * Gets the label descriptor by the given id.
     *
     * @param int $id
     * @return \PostFinanceCheckout\Sdk\Model\LabelDescriptor
     */
    public function find($id)
    {
        return parent::find($id);
    }

    /**
     * Gets a list of label descriptors.
     *
     * @return \PostFinanceCheckout\Sdk\Model\LabelDescriptor[]
     */
    public function getAll()
    {
        return parent::getAll();
    }

    protected function fetchData()
    {
        return $this->_apiClient->getService(LabelDescriptionService::class)->all();
    }

    protected function getId($entry)
    {
        /** @var \PostFinanceCheckout\Sdk\Model\LabelDescriptor $entry */
        return $entry->getId();
    }
}