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
namespace PostFinanceCheckout\Payment\Plugin\Checkout\Block\Checkout;

use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;
use PostFinanceCheckout\Payment\Api\PaymentMethodConfigurationRepositoryInterface;
use PostFinanceCheckout\Payment\Api\Data\PaymentMethodConfigurationInterface;
use PostFinanceCheckout\Payment\Model\PaymentMethodConfiguration;

/**
 * Interceptor to dynamically extend the layout configuration with the PostFinance Checkout payment method data.
 */
class LayoutProcessor
{

    /**
     *
     * @var PaymentMethodConfigurationRepositoryInterface
     */
    protected $_paymentMethodConfigurationRepository;

    /**
     *
     * @var SearchCriteriaBuilder
     */
    protected $_searchCriteriaBuilder;

    /**
     *
     * @var FilterBuilder
     */
    protected $_filterBuilder;

    /**
     *
     * @var FilterGroupBuilder
     */
    protected $_filterGroupBuilder;

    /**
     *
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     *
     * @var ResourceConnection
     */
    protected $_resourceConnection;

    /**
     *
     * @param PaymentMethodConfigurationRepositoryInterface $paymentMethodConfigurationRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param FilterBuilder $filterBuilder
     * @param FilterGroupBuilder $filterGroupBuilder
     * @param StoreManagerInterface $storeManager
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(PaymentMethodConfigurationRepositoryInterface $paymentMethodConfigurationRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder, FilterBuilder $filterBuilder,
        FilterGroupBuilder $filterGroupBuilder, StoreManagerInterface $storeManager,
        ResourceConnection $resourceConnection)
    {
        $this->_paymentMethodConfigurationRepository = $paymentMethodConfigurationRepository;
        $this->_searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->_filterBuilder = $filterBuilder;
        $this->_filterGroupBuilder = $filterGroupBuilder;
        $this->_storeManager = $storeManager;
        $this->_resourceConnection = $resourceConnection;
    }

    public function beforeProcess(\Magento\Checkout\Block\Checkout\LayoutProcessor $subject, $jsLayout)
    {
        if (! $this->isTableExists()) {
            return [
                $jsLayout
            ];
        }

        if (isset(
            $jsLayout['components']['checkout']['children']['steps']['children']['billing-step']['children']['payment']['children']['renders']['children']['postfinancecheckout_payment']['methods'])) {
            $stateFilter = $this->_filterBuilder->setConditionType('in')
                ->setField(PaymentMethodConfigurationInterface::STATE)
                ->setValue(
                [
                    PaymentMethodConfiguration::STATE_ACTIVE,
                    PaymentMethodConfiguration::STATE_INACTIVE
                ])
                ->create();
            $filterGroup = $this->_filterGroupBuilder->setFilters([
                $stateFilter
            ])->create();
            $searchCriteria = $this->_searchCriteriaBuilder->setFilterGroups(
                [
                    $filterGroup
                ])->create();

            $configurations = $this->_paymentMethodConfigurationRepository->getList($searchCriteria)->getItems();
            foreach ($configurations as $configuration) {
                $jsLayout['components']['checkout']['children']['steps']['children']['billing-step']['children']['payment']['children']['renders']['children']['postfinancecheckout_payment']['methods']['postfinancecheckout_payment_' .
                    $configuration->getEntityId()] = $this->getMethodData($configuration);
            }
        }

        return [
            $jsLayout
        ];
    }

    protected function getMethodData(PaymentMethodConfigurationInterface $configuration)
    {
        return [
            'isBillingAddressRequired' => true
        ];
    }

    /**
     * Gets whether the payment method configuration database table exists.
     *
     * @return boolean
     */
    protected function isTableExists()
    {
        return $this->_resourceConnection->getConnection()->isTableExists(
            $this->_resourceConnection->getTableName('postfinancecheckout_payment_method_configuration'));
    }
}