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
namespace PostFinanceCheckout\Payment\Model\Webhook\Listener\TransactionInvoice;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use PostFinanceCheckout\Sdk\Model\Transaction;
use PostFinanceCheckout\Sdk\Model\TransactionState;

/**
 * Webhook listener command to handle captured transaction invoices.
 */
class CaptureCommand extends AbstractCommand
{

    /**
     *
     * @param \PostFinanceCheckout\Sdk\Model\TransactionInvoice $entity
     * @param Order $order
     */
    public function execute($entity, Order $order)
    {
        $transaction = $entity->getCompletion()
            ->getLineItemVersion()
            ->getTransaction();
        $invoice = $this->getInvoiceForTransaction($transaction, $order);
        if (! ($invoice instanceof Invoice) || $invoice->getState() == Invoice::STATE_OPEN) {
            $isOrderInReview = ($order->getState() == Order::STATE_PAYMENT_REVIEW);

            if (! ($invoice instanceof Invoice)) {
                $order->setPostfinancecheckoutInvoiceAllowManipulation(true);
            }

            if (! ($invoice instanceof Invoice) || $invoice->getState() == Invoice::STATE_OPEN) {
                /** @var \Magento\Sales\Model\Order\Payment $payment */
                $payment = $order->getPayment();
                $payment->registerCaptureNotification($entity->getAmount());
                if (! ($invoice instanceof Invoice)) {
                    $invoice = $payment->getCreatedInvoice();
                }
                $invoice->setPostfinancecheckoutCapturePending(false);
                $order->addRelatedObject($invoice);
            }

            if ($transaction->getState() == TransactionState::COMPLETED) {
                $order->setStatus('processing_postfinancecheckout');
            }

            if ($isOrderInReview) {
                $order->setState(Order::STATE_PAYMENT_REVIEW);
                $order->addStatusToHistory(true);
            }

            $this->_orderRepository->save($order);
            $this->sendOrderEmail($order);
        }
    }

    protected function createInvoice(Transaction $transaction, Order $order)
    {
        $invoice = $order->prepareInvoice();
        $invoice->register();
        $invoice->setTransactionId(
            $order->getPostfinancecheckoutSpaceId() . '_' . $order->getPostfinancecheckoutTransactionId());
        $order->addRelatedObject($invoice);
        return $invoice;
    }
}