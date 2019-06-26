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
namespace PostFinanceCheckout\Payment\Observer;

use Magento\Framework\DB\TransactionFactory as DBTransactionFactory;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use PostFinanceCheckout\Payment\Api\TransactionInfoManagementInterface;
use PostFinanceCheckout\Payment\Api\TransactionInfoRepositoryInterface;
use PostFinanceCheckout\Payment\Helper\Data as Helper;
use PostFinanceCheckout\Payment\Model\ApiClient;
use PostFinanceCheckout\Payment\Model\Service\Order\TransactionService;
use PostFinanceCheckout\Sdk\Model\TransactionState;
use PostFinanceCheckout\Sdk\Service\ChargeFlowService;

/**
 * Observer to create an invoice and confirm the transaction when the quote is submitted.
 */
class SubmitQuote implements ObserverInterface
{

    /**
     *
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     *
     * @var DBTransactionFactory
     */
    private $dbTransactionFactory;

    /**
     *
     * @var Helper
     */
    private $helper;

    /**
     *
     * @var TransactionService
     */
    private $transactionService;

    /**
     *
     * @var TransactionInfoManagementInterface
     */
    private $transactionInfoManagement;

    /**
     *
     * @var TransactionInfoRepositoryInterface
     */
    private $transactionInfoRepository;

    /**
     *
     * @var ApiClient
     */
    private $apiClient;

    /**
     *
     * @param OrderRepositoryInterface $orderRepository
     * @param DBTransactionFactory $dbTransactionFactory
     * @param Helper $helper
     * @param TransactionService $transactionService
     * @param TransactionInfoManagementInterface $transactionInfoManagement
     * @param TransactionInfoRepositoryInterface $transactionInfoRepository
     * @param ApiClient $apiClient
     */
    public function __construct(OrderRepositoryInterface $orderRepository, DBTransactionFactory $dbTransactionFactory,
        Helper $helper, TransactionService $transactionService,
        TransactionInfoManagementInterface $transactionInfoManagement,
        TransactionInfoRepositoryInterface $transactionInfoRepository, ApiClient $apiClient)
    {
        $this->orderRepository = $orderRepository;
        $this->dbTransactionFactory = $dbTransactionFactory;
        $this->helper = $helper;
        $this->transactionService = $transactionService;
        $this->transactionInfoManagement = $transactionInfoManagement;
        $this->transactionInfoRepository = $transactionInfoRepository;
        $this->apiClient = $apiClient;
    }

    public function execute(Observer $observer)
    {
        /** @var Order $order */
        $order = $observer->getOrder();

        $transactionId = $order->getPostfinancecheckoutTransactionId();
        if (! empty($transactionId)) {
            if (! $this->checkTransactionInfo($order)) {
                $this->cancelOrder($order);
                return;
            }

            $transaction = $this->transactionService->getTransaction($order->getPostfinancecheckoutSpaceId(),
                $order->getPostfinancecheckoutTransactionId());
            $this->transactionInfoManagement->update($transaction, $order);

            $invoice = $this->createInvoice($order);

            $transaction = $this->transactionService->confirmTransaction($transaction, $order, $invoice,
                $this->helper->isAdminArea(), $order->getPostfinancecheckoutToken());
            $this->transactionInfoManagement->update($transaction, $order);
        }

        if ($order->getPostfinancecheckoutChargeFlow() && $this->helper->isAdminArea()) {
            $this->apiClient->getService(ChargeFlowService::class)->applyFlow(
                $order->getPostfinancecheckoutSpaceId(), $order->getPostfinancecheckoutTransactionId());

            if ($order->getPostfinancecheckoutToken() != null) {
                $this->transactionService->waitForTransactionState($order,
                    [
                        TransactionState::AUTHORIZED,
                        TransactionState::COMPLETED,
                        TransactionState::FULFILL
                    ], 3);
            }
        }
    }

    /**
     * Checks whether the transaction info for the transaction linked to the order is already linked to another order.
     *
     * @param Order $order
     * @return boolean
     */
    private function checkTransactionInfo(Order $order)
    {
        try {
            $info = $this->transactionInfoRepository->getByTransactionId($order->getPostfinancecheckoutSpaceId(),
                $order->getPostfinancecheckoutTransactionId());

            if ($info->getOrderId() != $order->getId()) {
                return false;
            }
        } catch (NoSuchEntityException $e) {}
        return true;
    }

    /**
     * Creates an invoice for the order.
     *
     * @param int $spaceId
     * @param int $transactionId
     * @param Order $order
     * @return Order\Invoice
     */
    private function createInvoice(Order $order)
    {
        $invoice = $order->prepareInvoice();
        $invoice->register();
        $invoice->setTransactionId(
            $order->getPostfinancecheckoutSpaceId() . '_' . $order->getPostfinancecheckoutTransactionId());

        $this->dbTransactionFactory->create()
            ->addObject($order)
            ->addObject($invoice)
            ->save();
        return $invoice;
    }

    /**
     * Cancels the given order and invoice linked to the transaction.
     *
     * @param Order $order
     */
    private function cancelOrder(Order $order)
    {
        $invoice = $this->getInvoiceForTransaction($order);
        if ($invoice) {
            $order->setPostfinancecheckoutInvoiceAllowManipulation(true);
            $invoice->cancel();
            $order->addRelatedObject($invoice);
        }
        $order->registerCancellation(null, false);
        $this->orderRepository->save($order);
    }

    /**
     * Gets the invoice linked to the given transaction.
     *
     * @param Order $order
     * @return Invoice
     */
    private function getInvoiceForTransaction(Order $order)
    {
        foreach ($order->getInvoiceCollection() as $invoice) {
            /** @var Invoice $invoice */
            if (\strpos($invoice->getTransactionId(),
                $order->getPostfinancecheckoutSpaceId() . '_' . $order->getPostfinancecheckoutTransactionId()) ===
                0 && $invoice->getState() != Invoice::STATE_CANCELED) {
                $invoice->load($invoice->getId());
                return $invoice;
            }
        }
    }
}