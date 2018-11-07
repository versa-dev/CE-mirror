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
namespace PostFinanceCheckout\Payment\Gateway\Command;

use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Psr\Log\LoggerInterface;
use PostFinanceCheckout\Payment\Api\RefundJobRepositoryInterface;
use PostFinanceCheckout\Payment\Helper\Locale as LocaleHelper;
use PostFinanceCheckout\Payment\Model\ApiClient;
use PostFinanceCheckout\Payment\Model\RefundJobFactory;
use PostFinanceCheckout\Payment\Model\Service\LineItemReductionService;
use PostFinanceCheckout\Sdk\Model\RefundState;
use PostFinanceCheckout\Sdk\Service\RefundService;

/**
 * Payment gateway command to refund a payment.
 */
class RefundCommand implements CommandInterface
{

    /**
     *
     * @var LoggerInterface
     */
    protected $_logger;

    /**
     *
     * @var LocaleHelper
     */
    protected $_localeHelper;

    /**
     *
     * @var LineItemReductionService
     */
    protected $_lineItemReductionService;

    /**
     *
     * @var RefundJobFactory
     */
    protected $_refundJobFactory;

    /**
     *
     * @var RefundJobRepositoryInterface
     */
    protected $_refundJobRepository;

    /**
     *
     * @var ApiClient
     */
    protected $_apiClient;

    /**
     *
     * @param LoggerInterface $logger
     * @param LocaleHelper $localeHelper
     * @param LineItemReductionService $lineItemReductionService
     * @param RefundJobFactory $refundJobFactory
     * @param RefundJobRepositoryInterface $refundJobRepository
     * @param ApiClient $apiClient
     */
    public function __construct(LoggerInterface $logger, LocaleHelper $localeHelper,
        LineItemReductionService $lineItemReductionService, RefundJobFactory $refundJobFactory,
        RefundJobRepositoryInterface $refundJobRepository, ApiClient $apiClient)
    {
        $this->_logger = $logger;
        $this->_localeHelper = $localeHelper;
        $this->_lineItemReductionService = $lineItemReductionService;
        $this->_refundJobFactory = $refundJobFactory;
        $this->_refundJobRepository = $refundJobRepository;
        $this->_apiClient = $apiClient;
    }

    public function execute(array $commandSubject)
    {
        /** @var \Magento\Sales\Model\Order\Payment $payment */
        $payment = SubjectReader::readPayment($commandSubject)->getPayment();
        $creditmemo = $payment->getCreditmemo();

        if ($creditmemo->getPostfinancecheckoutExternalId() == null) {
            $refundJob = $this->_refundJobRepository->getByOrderId($payment->getOrder()
                ->getId());
            try {
                $refund = $this->_apiClient->getService(RefundService::class)->refund(
                    $creditmemo->getOrder()
                        ->getPostfinancecheckoutSpaceId(), $refundJob->getRefund());
            } catch (\PostFinanceCheckout\Sdk\ApiException $e) {
                if ($e->getResponseObject() instanceof \PostFinanceCheckout\Sdk\Model\ClientError) {
                    $this->_refundJobRepository->delete($refundJob);
                    throw new \Magento\Framework\Exception\LocalizedException(
                        \__($e->getResponseObject()->getMessage()));
                } else {
                    $creditmemo->setPostfinancecheckoutKeepRefundJob(true);
                    $this->_logger->critical($e);
                    throw new \Magento\Framework\Exception\LocalizedException(
                        \__('There has been an error while sending the refund to the gateway.'));
                }
            } catch (\Exception $e) {
                $creditmemo->setPostfinancecheckoutKeepRefundJob(true);
                $this->_logger->critical($e);
                throw new \Magento\Framework\Exception\LocalizedException(
                    \__('There has been an error while sending the refund to the gateway.'));
            }

            if ($refund->getState() == RefundState::FAILED) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    $this->_localeHelper->translate($refund->getFailureReason()
                        ->getDescription()));
            } elseif ($refund->getState() == RefundState::PENDING || $refund->getState() == RefundState::MANUAL_CHECK) {
                $creditmemo->setPostfinancecheckoutKeepRefundJob(true);
                throw new \Magento\Framework\Exception\LocalizedException(
                    \__('The refund was requested successfully, but is still pending on the gateway.'));
            }

            $creditmemo->setPostfinancecheckoutExternalId($refund->getExternalId());
            $this->_refundJobRepository->delete($refundJob);
        }
    }
}