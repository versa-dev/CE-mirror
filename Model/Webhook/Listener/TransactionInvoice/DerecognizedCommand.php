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

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;

/**
 * Webhook listener command to handle derecognized transaction invoices.
 */
class DerecognizedCommand extends AbstractCommand
{

    /**
     *
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     *
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(OrderRepositoryInterface $orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }

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
            $invoice->setPostfinancecheckoutCapturePending(false);
            $invoice->setPostfinancecheckoutDerecognized(true);
            $order->addRelatedObject($invoice);
            $this->orderRepository->save($order);
        }
    }
}