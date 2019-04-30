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
namespace PostFinanceCheckout\Payment\Model\Webhook\Listener\Refund;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\CreditmemoManagementInterface;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Magento\Sales\Model\Order\Invoice;
use PostFinanceCheckout\Payment\Api\RefundJobRepositoryInterface;
use PostFinanceCheckout\Payment\Helper\Data as Helper;
use PostFinanceCheckout\Payment\Model\Service\LineItemReductionService;
use PostFinanceCheckout\Payment\Model\Service\Order\TransactionService;
use PostFinanceCheckout\Sdk\Model\LineItemType;
use PostFinanceCheckout\Sdk\Model\Refund;
use PostFinanceCheckout\Sdk\Model\TransactionInvoiceState;

/**
 * Webhook listener command to handle successful refunds.
 */
class SuccessfulCommand extends AbstractCommand
{

    /**
     *
     * @var RefundJobRepositoryInterface
     */
    private $refundJobRepository;

    /**
     *
     * @var CreditmemoRepositoryInterface
     */
    private $creditmemoRepository;

    /**
     *
     * @var CreditmemoFactory
     */
    private $creditmemoFactory;

    /**
     *
     * @var CreditmemoManagementInterface
     */
    private $creditmemoManagement;

    /**
     *
     * @var InvoiceRepositoryInterface
     */
    private $invoiceRepository;

    /**
     *
     * @var LineItemReductionService
     */
    private $lineItemReductionService;

    /**
     *
     * @var TransactionService
     */
    private $transactionService;

    /**
     *
     * @var Helper
     */
    private $helper;

    /**
     *
     * @param RefundJobRepositoryInterface $refundJobRepository
     * @param CreditmemoRepositoryInterface $creditmemoRepository
     * @param CreditmemoFactory $creditmemoFactory
     * @param CreditmemoManagementInterface $creditmemoManagement
     * @param InvoiceRepositoryInterface $invoiceRepository
     * @param LineItemReductionService $lineItemReductionService
     * @param TransactionService $transactionService
     * @param Helper $helper
     */
    public function __construct(RefundJobRepositoryInterface $refundJobRepository,
        CreditmemoRepositoryInterface $creditmemoRepository, CreditmemoFactory $creditmemoFactory,
        CreditmemoManagementInterface $creditmemoManagement, InvoiceRepositoryInterface $invoiceRepository,
        LineItemReductionService $lineItemReductionService, TransactionService $transactionService, Helper $helper)
    {
        parent::__construct($refundJobRepository);
        $this->refundJobRepository = $refundJobRepository;
        $this->creditmemoRepository = $creditmemoRepository;
        $this->creditmemoFactory = $creditmemoFactory;
        $this->creditmemoManagement = $creditmemoManagement;
        $this->invoiceRepository = $invoiceRepository;
        $this->lineItemReductionService = $lineItemReductionService;
        $this->transactionService = $transactionService;
        $this->helper = $helper;
    }

    /**
     *
     * @param Refund $entity
     * @param Order $order
     */
    public function execute($entity, Order $order)
    {
        if ($this->isDerecognizedInvoice($entity, $order)) {
            return;
        }

        /** @var \Magento\Sales\Model\Order\Creditmemo $creditmemo */
        $creditmemo = $this->creditmemoRepository->create()->load($entity->getExternalId(),
            'postfinancecheckout_external_id');
        if (! $creditmemo->getId()) {
            $this->registerRefund($entity, $order);
        }
        $this->deleteRefundJob($entity);
    }

    private function isDerecognizedInvoice(Refund $refund, Order $order)
    {
        $transactionInvoice = $this->transactionService->getTransactionInvoice($order);
        if ($transactionInvoice->getState() == TransactionInvoiceState::DERECOGNIZED) {
            return true;
        } else {
            return false;
        }
    }

    private function registerRefund(Refund $refund, Order $order)
    {
        $creditmemoData = $this->collectCreditmemoData($refund, $order);
        try {
            $refundJob = $this->refundJobRepository->getByOrderId($order->getId());
            $invoice = $this->invoiceRepository->get($refundJob->getInvoiceId());
            $creditmemo = $this->creditmemoFactory->createByInvoice($invoice, $creditmemoData);
        } catch (NoSuchEntityException $e) {
            $paidInvoices = $order->getInvoiceCollection()->addFieldToFilter('state', Invoice::STATE_PAID);
            if ($paidInvoices->count() == 1) {
                $creditmemo = $this->creditmemoFactory->createByInvoice($paidInvoices->getFirstItem(), $creditmemoData);
            } else {
                $creditmemo = $this->creditmemoFactory->createByOrder($order, $creditmemoData);
            }
        }
        $creditmemo->setPaymentRefundDisallowed(false);
        $creditmemo->setAutomaticallyCreated(true);
        $creditmemo->addComment(\__('The credit memo has been created automatically.'));
        $creditmemo->setPostfinancecheckoutExternalId($refund->getExternalId());
        $this->creditmemoManagement->refund($creditmemo);
    }

    private function collectCreditmemoData(Refund $refund, Order $order)
    {
        $orderItemMap = [];
        foreach ($order->getAllItems() as $orderItem) {
            $orderItemMap[$orderItem->getQuoteItemId()] = $orderItem;
        }

        $lineItems = [];
        foreach ($refund->getTransaction()->getLineItems() as $lineItem) {
            $lineItems[$lineItem->getUniqueId()] = $lineItem;
        }

        $baseLineItems = [];
        foreach ($this->lineItemReductionService->getBaseLineItems($order->getPostfinancecheckoutSpaceId(),
            $refund->getTransaction()
                ->getId(), $refund) as $lineItem) {
            $baseLineItems[$lineItem->getUniqueId()] = $lineItem;
        }

        $refundQuantities = [];
        foreach ($order->getAllItems() as $orderItem) {
            $refundQuantities[$orderItem->getQuoteItemId()] = 0;
        }

        $creditmemoAmount = 0;
        $shippingAmount = 0;
        foreach ($refund->getReductions() as $reduction) {
            $lineItem = $lineItems[$reduction->getLineItemUniqueId()];
            switch ($lineItem->getType()) {
                case LineItemType::PRODUCT:
                    if ($reduction->getQuantityReduction() > 0) {
                        $refundQuantities[$orderItemMap[$reduction->getLineItemUniqueId()]->getId()] = $reduction->getQuantityReduction();
                        $creditmemoAmount += $reduction->getQuantityReduction() *
                            ($orderItemMap[$reduction->getLineItemUniqueId()]->getRowTotal() +
                            $orderItemMap[$reduction->getLineItemUniqueId()]->getTaxAmount() -
                            $orderItemMap[$reduction->getLineItemUniqueId()]->getDiscountAmount() +
                            $orderItemMap[$reduction->getLineItemUniqueId()]->getDiscountTaxCompensationAmount()) /
                            $orderItemMap[$reduction->getLineItemUniqueId()]->getQtyOrdered();
                    }
                    break;
                case LineItemType::FEE:
                case LineItemType::DISCOUNT:
                    break;
                case LineItemType::SHIPPING:
                    if ($reduction->getQuantityReduction() > 0) {
                        $shippingAmount = $baseLineItems[$reduction->getLineItemUniqueId()]->getAmountIncludingTax();
                    } elseif ($reduction->getUnitPriceReduction() > 0) {
                        $shippingAmount = $reduction->getUnitPriceReduction();
                    } else {
                        $shippingAmount = 0;
                    }

                    if ($shippingAmount <= $order->getShippingInclTax() - $order->getShippingRefunded()) {
                        $creditmemoAmount += $shippingAmount;
                    } else {
                        $shippingAmount = 0;
                    }

                    if ($order->getShippingDiscountAmount() > 0) {
                        $shippingAmount += ($shippingAmount / $order->getShippingAmount()) *
                            $order->getShippingDiscountAmount();
                    }
                    break;
            }
        }

        $roundedCreditmemoAmount = $this->helper->roundAmount($creditmemoAmount,
            $refund->getTransaction()
                ->getCurrency());

        $positiveAdjustment = 0;
        $negativeAdjustment = 0;
        if ($roundedCreditmemoAmount > $refund->getAmount()) {
            $negativeAdjustment = $roundedCreditmemoAmount - $refund->getAmount();
        } elseif ($roundedCreditmemoAmount < $refund->getAmount()) {
            $positiveAdjustment = $refund->getAmount() - $roundedCreditmemoAmount;
        }

        return [
            'qtys' => $refundQuantities,
            'shipping_amount' => $shippingAmount,
            'adjustment_positive' => $positiveAdjustment,
            'adjustment_negative' => $negativeAdjustment
        ];
    }
}