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
namespace PostFinanceCheckout\Payment\Model\Checkout;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use PostFinanceCheckout\Payment\Api\PaymentMethodConfigurationRepositoryInterface;
use PostFinanceCheckout\Payment\Api\Data\PaymentMethodConfigurationInterface;
use PostFinanceCheckout\Payment\Model\PaymentMethodConfiguration;
use PostFinanceCheckout\Payment\Model\Service\Quote\TransactionService;

/**
 * Class to provide information that allow to checkout using the PostFinance Checkout payment methods.
 */
class ConfigProvider implements ConfigProviderInterface
{

    /**
     *
     * @var PaymentMethodConfigurationRepositoryInterface
     */
    protected $_paymentMethodConfigurationRepository;

    /**
     *
     * @var TransactionService
     */
    protected $_transactionService;

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
     * @var CheckoutSession
     */
    protected $_checkoutSession;

    /**
     *
     * @param PaymentMethodConfigurationRepositoryInterface $paymentMethodConfigurationRepository
     * @param TransactionService $transactionService
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param FilterBuilder $filterBuilder
     * @param FilterGroupBuilder $filterGroupBuilder
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(PaymentMethodConfigurationRepositoryInterface $paymentMethodConfigurationRepository,
        TransactionService $transactionService, SearchCriteriaBuilder $searchCriteriaBuilder,
        FilterBuilder $filterBuilder, FilterGroupBuilder $filterGroupBuilder, CheckoutSession $checkoutSession)
    {
        $this->_paymentMethodConfigurationRepository = $paymentMethodConfigurationRepository;
        $this->_transactionService = $transactionService;
        $this->_searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->_filterBuilder = $filterBuilder;
        $this->_filterGroupBuilder = $filterGroupBuilder;
        $this->_checkoutSession = $checkoutSession;
    }

    public function getConfig()
    {
        $config = [
            'payment' => [],
            'postfinancecheckout' => []
        ];

        try {
            $config['postfinancecheckout']['javascriptUrl'] = $this->_transactionService->getJavaScriptUrl(
                $this->_checkoutSession->getQuote());
        } catch (\Exception $e) {}

        try {
            $config['postfinancecheckout']['paymentPageUrl'] = $this->_transactionService->getPaymentPageUrl(
                $this->_checkoutSession->getQuote());
        } catch (\Exception $e) {}

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
        $searchCriteria = $this->_searchCriteriaBuilder->setFilterGroups([
            $filterGroup
        ])->create();

        $configurations = $this->_paymentMethodConfigurationRepository->getList($searchCriteria)->getItems();
        foreach ($configurations as $configuration) {
            $config['payment']['postfinancecheckout_payment_' . $configuration->getEntityId()] = [
                'isActive' => true,
                'configurationId' => $configuration->getConfigurationId()
            ];
        }

        return $config;
    }
}