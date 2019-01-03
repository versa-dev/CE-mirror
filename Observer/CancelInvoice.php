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

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use PostFinanceCheckout\Payment\Model\Payment\Method\Adapter;
use PostFinanceCheckout\Payment\Model\Service\Order\TransactionService;
use PostFinanceCheckout\Sdk\Model\TransactionState;

/**
 * Observer to validate the cancellation of an invoice.
 */
class CancelInvoice implements ObserverInterface
{

    /**
     *
     * @var TransactionService
     */
    private $transactionService;

    /**
     *
     * @param TransactionService $transactionService
     */
    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    public function execute(Observer $observer)
    {
        /** @var \Magento\Sales\Model\Order\Invoice $invoice */
        $invoice = $observer->getInvoice();
        $order = $invoice->getOrder();

        if ($order->getPayment()->getMethodInstance() instanceof Adapter) {
            if ($invoice->getPostfinancecheckoutCapturePending()) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    \__('The invoice cannot be cancelled as its capture has already been requested.'));
            }

            if (! $order->getPostfinancecheckoutInvoiceAllowManipulation()) {
                // The invoice can only be cancelled by the merchant if the transaction is in state 'AUTHORIZED'.
                $transaction = $this->transactionService->getTransaction($order->getPostfinancecheckoutSpaceId(),
                    $order->getPostfinancecheckoutTransactionId());
                if ($transaction->getState() != TransactionState::AUTHORIZED) {
                    throw new \Magento\Framework\Exception\LocalizedException(\__('The invoice cannot be cancelled.'));
                }
            }
        }
    }
}