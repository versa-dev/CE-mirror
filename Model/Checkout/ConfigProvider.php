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
namespace PostFinanceCheckout\Payment\Model\Checkout;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Payment\Helper\Data as PaymentHelper;
use Psr\Log\LoggerInterface;
use PostFinanceCheckout\Payment\Api\PaymentMethodConfigurationRepositoryInterface;
use PostFinanceCheckout\Payment\Api\Data\PaymentMethodConfigurationInterface;
use PostFinanceCheckout\Payment\Model\PaymentMethodConfiguration;
use PostFinanceCheckout\Payment\Model\Payment\Method\Adapter;
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
     * @var LoggerInterface
     */
    private $logger;

    /**
     *
     * @var PaymentHelper
     */
    private $paymentHelper;

    /**
     *
     * @param PaymentMethodConfigurationRepositoryInterface $paymentMethodConfigurationRepository
     * @param TransactionService $transactionService
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param FilterBuilder $filterBuilder
     * @param FilterGroupBuilder $filterGroupBuilder
     * @param CheckoutSession $checkoutSession
     * @param LoggerInterface $logger
     * @param PaymentHelper $paymentHelper
     */
    public function __construct(PaymentMethodConfigurationRepositoryInterface $paymentMethodConfigurationRepository,
        TransactionService $transactionService, SearchCriteriaBuilder $searchCriteriaBuilder,
        FilterBuilder $filterBuilder, FilterGroupBuilder $filterGroupBuilder, CheckoutSession $checkoutSession,
        LoggerInterface $logger, PaymentHelper $paymentHelper)
    {
        $this->paymentMethodConfigurationRepository = $paymentMethodConfigurationRepository;
        $this->transactionService = $transactionService;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->filterBuilder = $filterBuilder;
        $this->filterGroupBuilder = $filterGroupBuilder;
        $this->checkoutSession = $checkoutSession;
        $this->logger = $logger;
        $this->paymentHelper = $paymentHelper;
    }

    public function getConfig()
    {
        $config = [
            'payment' => [],
            'postfinancecheckout' => []
        ];

        /* @var \Magento\Quote\Model\Quote $quote */
        $quote = $this->checkoutSession->getQuote();
        // Make sure that the quote's totals are collected before generating javascript and payment page URLs.
        $quote->collectTotals();
        try {
            $config['postfinancecheckout']['javascriptUrl'] = $this->transactionService->getJavaScriptUrl($quote);
        } catch (\Exception $e) {
            $this->logger->critical($e);
        }

        try {
            $config['postfinancecheckout']['paymentPageUrl'] = $this->transactionService->getPaymentPageUrl($quote);
        } catch (\Exception $e) {
            $this->logger->critical($e);
        }

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
            $methodCode = 'postfinancecheckout_payment_' . $configuration->getEntityId();
            $methodInstance = $this->getPaymentMethodInstance($methodCode);
            if ($methodInstance != null) {
                $config['payment'][$methodCode] = [
                    'isActive' => true,
                    'configurationId' => $configuration->getConfigurationId(),
                    'description' => $methodInstance->getDescription(),
                    'showDescription' => $methodInstance->isShowDescription(),
                    'imageUrl' => $methodInstance->getImageUrl(),
                    'showImage' => $methodInstance->isShowImage()
                ];
            }
        }

        return $config;
    }

    /**
     * @param string $methodCode
     * @return Adapter
     */
    private function getPaymentMethodInstance($methodCode) {
        try {
            $instance = $this->paymentHelper->getMethodInstance($methodCode);
            if ($instance instanceof Adapter) {
                return $instance;
            } else {
                return null;
            }
        } catch (\UnexpectedValueException $e) {
            return null;
        }
    }
}