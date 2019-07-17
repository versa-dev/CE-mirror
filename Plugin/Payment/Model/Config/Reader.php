<?php
/**
 * PostFinance Checkout Magento 2
 *
 * This Magento 2 extension enables to process payments with PostFinance Checkout (https://www.postfinance.ch/checkout/).
 *
 * @package PostFinanceCheckout_Payment
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */
namespace PostFinanceCheckout\Payment\Plugin\Payment\Model\Config;

use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;
use PostFinanceCheckout\Payment\Api\PaymentMethodConfigurationRepositoryInterface;
use PostFinanceCheckout\Payment\Api\Data\PaymentMethodConfigurationInterface;
use PostFinanceCheckout\Payment\Model\PaymentMethodConfiguration;

/**
 * Interceptor to dynamically extend the payment configuration with the PostFinance Checkout payment method data.
 */
class Reader
{

    /**
     *
     * @var PaymentMethodConfigurationRepositoryInterface
     */
    private $paymentMethodConfigurationRepository;

    /**
     *
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     *
     * @var FilterBuilder
     */
    private $filterBuilder;

    /**
     *
     * @var FilterGroupBuilder
     */
    private $filterGroupBuilder;

    /**
     *
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     *
     * @var ResourceConnection
     */
    private $resourceConnection;

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
        $this->paymentMethodConfigurationRepository = $paymentMethodConfigurationRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->storeManager = $storeManager;
        $this->filterBuilder = $filterBuilder;
        $this->filterGroupBuilder = $filterGroupBuilder;
        $this->resourceConnection = $resourceConnection;
    }

    public function afterRead(\Magento\Payment\Model\Config\Reader $subject, $result)
    {
        if (! $this->isTableExists()) {
            return $result;
        }

        if (isset($result['methods'])) {
            $stateFilter = $this->filterBuilder->setConditionType('in')
                ->setField(PaymentMethodConfigurationInterface::STATE)
                ->setValue([
                PaymentMethodConfiguration::STATE_ACTIVE,
                PaymentMethodConfiguration::STATE_INACTIVE
            ])
                ->create();
            $filterGroup = $this->filterGroupBuilder->setFilters([
                $stateFilter
            ])->create();
            $searchCriteria = $this->searchCriteriaBuilder->setFilterGroups([
                $filterGroup
            ])->create();

            $configurations = $this->paymentMethodConfigurationRepository->getList($searchCriteria)->getItems();
            foreach ($configurations as $configuration) {
                $result['methods'][$this->getPaymentMethodId($configuration)] = $this->generateConfig();
            }
        }
        return $result;
    }

    private function getPaymentMethodId(PaymentMethodConfigurationInterface $configuration)
    {
        return 'postfinancecheckout_payment_' . $configuration->getEntityId();
    }

    private function generateConfig()
    {
        return [
            'allow_multiple_address' => '1'
        ];
    }

    /**
     * Gets whether the payment method configuration database table exists.
     *
     * @return boolean
     */
    private function isTableExists()
    {
        return $this->resourceConnection->getConnection()->isTableExists(
            $this->resourceConnection->getTableName('postfinancecheckout_payment_method_configuration'));
    }
}