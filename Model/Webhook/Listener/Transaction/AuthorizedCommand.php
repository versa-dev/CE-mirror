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
namespace PostFinanceCheckout\Payment\Model\Webhook\Listener\Transaction;

use Magento\Sales\Model\Order;
use PostFinanceCheckout\Sdk\Model\TransactionState;

/**
 * Webhook listener command to handle authorized transactions.
 */
class AuthorizedCommand extends AbstractCommand
{

    /**
     *
     * @param \PostFinanceCheckout\Sdk\Model\Transaction $entity
     * @param Order $order
     */
    public function execute($entity, Order $order)
    {
        if ($order->getPostfinancecheckoutAuthorized()) {
            // In case the order is already authorized.
            return;
        }

        /** @var \Magento\Sales\Model\Order\Payment $payment */
        $payment = $order->getPayment();
        $payment->setTransactionId($entity->getLinkedSpaceId() . '_' . $entity->getId());
        $payment->setIsTransactionClosed(false);
        $payment->registerAuthorizationNotification($entity->getAuthorizationAmount());

        if ($entity->getState() != TransactionState::FULFILL) {
            $order->setState(Order::STATE_PROCESSING);
            $order->addStatusToHistory('processing_postfinancecheckout',
                \__('The order should not be fulfilled yet, as the payment is not guaranteed.'));
        }

        $order->setPostfinancecheckoutAuthorized(true);
        $this->_orderRepository->save($order);

        $this->sendOrderEmail($order);
    }
}