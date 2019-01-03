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
use Magento\Checkout\Model\Session\Proxy as CheckoutSession;
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
    private $paymentMethodConfigurationRepository;

    /**
     *
     * @var TransactionService
     */
    private $transactionService;

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
     * @var CheckoutSession
     */
    private $checkoutSession;

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
        $this->paymentMethodConfigurationRepository = $paymentMethodConfigurationRepository;
        $this->transactionService = $transactionService;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->filterBuilder = $filterBuilder;
        $this->filterGroupBuilder = $filterGroupBuilder;
        $this->checkoutSession = $checkoutSession;
    }

    public function getConfig()
    {
        $config = [
            'payment' => [],
            'postfinancecheckout' => []
        ];

        try {
            $config['postfinancecheckout']['javascriptUrl'] = $this->transactionService->getJavaScriptUrl(
                $this->checkoutSession->getQuote());
        } catch (\Exception $e) {}

        try {
            $config['postfinancecheckout']['paymentPageUrl'] = $this->transactionService->getPaymentPageUrl(
                $this->checkoutSession->getQuote());
        } catch (\Exception $e) {}

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
            $config['payment']['postfinancecheckout_payment_' . $configuration->getEntityId()] = [
                'isActive' => true,
                'configurationId' => $configuration->getConfigurationId()
            ];
        }

        return $config;
    }
}