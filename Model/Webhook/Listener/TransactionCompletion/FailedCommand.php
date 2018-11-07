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
namespace PostFinanceCheckout\Payment\Model\Webhook\Listener\TransactionCompletion;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;

/**
 * Webhook listener command to handle failed transaction completions.
 */
class FailedCommand extends AbstractCommand
{

    /**
     *
     * @param \PostFinanceCheckout\Sdk\Model\TransactionCompletion $entity
     * @param Order $order
     */
    public function execute($entity, Order $order)
    {
        $transaction = $entity->getLineItemVersion()->getTransaction();
        $invoice = $this->getInvoiceForTransaction($transaction, $order);
        if ($invoice instanceof Invoice && $invoice->getPostfinancecheckoutCapturePending() &&
            $invoice->getState() == Invoice::STATE_OPEN) {
            $invoice->setPostfinancecheckoutCapturePending(false);

            /** @var \Magento\Sales\Model\Order\Payment $payment */
            $payment = $order->getPayment();
            $authTransaction = $payment->getAuthorizationTransaction();
            $authTransaction->setIsClosed(false);

            $order->addRelatedObject($invoice);
            $order->addRelatedObject($authTransaction);
            $this->_orderRepository->save($order);
        }
    }
}