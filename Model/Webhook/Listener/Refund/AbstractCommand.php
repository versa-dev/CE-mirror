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

use Magento\Framework\DB\TransactionFactory as DBTransactionFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Email\Sender\OrderSender as OrderEmailSender;
use PostFinanceCheckout\Payment\Api\RefundJobRepositoryInterface;
use PostFinanceCheckout\Payment\Model\Webhook\Listener\AbstractOrderRelatedCommand;
use PostFinanceCheckout\Sdk\Model\Refund;

/**
 * Abstract webhook listener command to handle refunds.
 */
abstract class AbstractCommand extends AbstractOrderRelatedCommand
{

    /**
     *
     * @var RefundJobRepositoryInterface
     */
    protected $_refundJobRepository;

    /**
     *
     * @param DBTransactionFactory $dbTransactionFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderEmailSender $orderEmailSender
     * @param RefundJobRepositoryInterface $refundJobRepository
     */
    public function __construct(DBTransactionFactory $dbTransactionFactory, OrderRepositoryInterface $orderRepository,
        OrderEmailSender $orderEmailSender, RefundJobRepositoryInterface $refundJobRepository)
    {
        parent::__construct($dbTransactionFactory, $orderRepository, $orderEmailSender);
        $this->_refundJobRepository = $refundJobRepository;
    }

    /**
     * Deletes the refund job of the given refund if existing.
     *
     * @param Refund $refund
     */
    protected function deleteRefundJob(Refund $refund)
    {
        try {
            $refundJob = $this->_refundJobRepository->getByExternalId($refund->getExternalId());
            $this->_refundJobRepository->delete($refundJob);
        } catch (NoSuchEntityException $e) {}
    }
}