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
namespace PostFinanceCheckout\Payment\Plugin\Sales\Model\Service;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;
use Psr\Log\LoggerInterface;
use PostFinanceCheckout\Payment\Api\RefundJobRepositoryInterface;
use PostFinanceCheckout\Payment\Api\Data\RefundJobInterface;
use PostFinanceCheckout\Payment\Model\ApiClient;
use PostFinanceCheckout\Payment\Model\RefundJobFactory;
use PostFinanceCheckout\Payment\Model\Payment\Method\Adapter as PaymentMethodAdapter;
use PostFinanceCheckout\Payment\Model\Service\LineItemReductionService;
use PostFinanceCheckout\Sdk\Model\RefundCreate;
use PostFinanceCheckout\Sdk\Model\RefundType;
use PostFinanceCheckout\Sdk\Service\RefundService;

/**
 * Interceptor to handle refund jobs when a refund is triggered.
 */
class CreditmemoService
{

    /**
     *
     * @var LoggerInterface
     */
    private $logger;

    /**
     *
     * @var LineItemReductionService
     */
    private $lineItemReductionService;

    /**
     *
     * @var RefundJobFactory
     */
    private $refundJobFactory;

    /**
     *
     * @var RefundJobRepositoryInterface
     */
    private $refundJobRepository;

    /**
     *
     * @var ApiClient
     */
    private $apiClient;

    /**
     *
     * @param LoggerInterface $logger
     * @param LineItemReductionService $lineItemReductionService
     * @param RefundJobFactory $refundJobFactory
     * @param RefundJobRepositoryInterface $refundJobRepository
     * @param ApiClient $apiClient
     */
    public function __construct(LoggerInterface $logger, LineItemReductionService $lineItemReductionService,
        RefundJobFactory $refundJobFactory, RefundJobRepositoryInterface $refundJobRepository, ApiClient $apiClient)
    {
        $this->logger = $logger;
        $this->lineItemReductionService = $lineItemReductionService;
        $this->refundJobFactory = $refundJobFactory;
        $this->refundJobRepository = $refundJobRepository;
        $this->apiClient = $apiClient;
    }

    public function aroundRefund(\Magento\Sales\Model\Service\CreditmemoService $subject, callable $proceed,
        \Magento\Sales\Api\Data\CreditmemoInterface $creditmemo, $offlineRequested = false)
    {
        try {
            return $proceed($creditmemo, $offlineRequested);
        } catch (\Exception $e) {
            if ($creditmemo->getPostfinancecheckoutKeepRefundJob() !== true) {
                try {
                    $this->refundJobRepository->delete(
                        $this->refundJobRepository->getByOrderId($creditmemo->getOrderId()));
                } catch (NoSuchEntityException $e) {}
            }
            throw $e;
        }
    }

    public function beforeRefund(\Magento\Sales\Model\Service\CreditmemoService $subject,
        \Magento\Sales\Api\Data\CreditmemoInterface $creditmemo, $offlineRequested = false)
    {
        if ($offlineRequested || ! $creditmemo->getInvoice()) {
            return null;
        }

        if ($creditmemo->getOrder()
            ->getPayment()
            ->getMethodInstance() instanceof PaymentMethodAdapter &&
            $creditmemo->getPostfinancecheckoutExternalId() == null) {
            try {
                $this->handleExistingRefundJob($creditmemo->getOrder());

                $refundCreate = $this->createRefund($creditmemo);
                $this->createRefundJob($creditmemo->getInvoice(), $refundCreate);
            } catch (\Exception $e) {
                throw new \Magento\Framework\Exception\LocalizedException(\__($e->getMessage()));
            }
        }
    }

    /**
     * Checks if there is an existing refund job for the given order and trys to send to refund to the gateway again.
     *
     * @param Order $order
     * @throws \Exception
     */
    private function handleExistingRefundJob(Order $order)
    {
        try {
            $existingRefundJob = $this->refundJobRepository->getByOrderId($order->getId());
            try {
                $this->apiClient->getService(RefundService::class)->refund(
                    $order->getPostfinancecheckoutSpaceId(), $existingRefundJob->getRefund());
            } catch (\Exception $e) {
                $this->logger->critical($e);
            }

            throw new \Magento\Framework\Exception\LocalizedException(
                \__('As long as there is an open creditmemo for the order, no new creditmemo can be created.'));
        } catch (NoSuchEntityException $e) {}
    }

    /**
     * Creates a new refund job for the given invoice and refund.
     *
     * @param Invoice $invoice
     * @param RefundCreate $refund
     * @return \PostFinanceCheckout\Payment\Model\RefundJob
     */
    private function createRefundJob(Invoice $invoice, RefundCreate $refund)
    {
        $entity = $this->refundJobFactory->create();
        $entity->setData(RefundJobInterface::ORDER_ID, $invoice->getOrderId());
        $entity->setData(RefundJobInterface::INVOICE_ID, $invoice->getId());
        $entity->setData(RefundJobInterface::SPACE_ID, $invoice->getOrder()
            ->getPostfinancecheckoutSpaceId());
        $entity->setData(RefundJobInterface::EXTERNAL_ID, $refund->getExternalId());
        $entity->setData(RefundJobInterface::REFUND, $refund);
        return $this->refundJobRepository->save($entity);
    }

    /**
     * Creates a refund creation model for the given creditmemo.
     *
     * @param Creditmemo $creditmemo
     * @return RefundCreate
     */
    private function createRefund(Creditmemo $creditmemo)
    {
        $refund = new RefundCreate();
        $refund->setExternalId(\uniqid($creditmemo->getOrderId() . '-'));
        $refund->setReductions($this->lineItemReductionService->convertCreditmemo($creditmemo));
        $refund->setTransaction($creditmemo->getOrder()
            ->getPostfinancecheckoutTransactionId());
        $refund->setType(RefundType::MERCHANT_INITIATED_ONLINE);
        return $refund;
    }
}