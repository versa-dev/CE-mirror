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
namespace PostFinanceCheckout\Payment\Controller\Transaction;

use Magento\Checkout\Model\Session\SuccessValidator;
use Magento\Framework\DataObject;
use Magento\Framework\App\Action\Context;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use PostFinanceCheckout\Payment\Model\Service\Order\TransactionService;
use PostFinanceCheckout\Sdk\Model\TransactionState;

/**
 * Frontend controller action to handle successful payments.
 */
class Success extends \PostFinanceCheckout\Payment\Controller\Transaction
{

    /**
     *
     * @var SuccessValidator
     */
    private $successValidator;

    /**
     *
     * @var TransactionService
     */
    private $transactionService;

    /**
     *
     * @param Context $context
     * @param OrderRepositoryInterface $orderRepository
     * @param SuccessValidator $successValidator
     * @param TransactionService $transactionService
     */
    public function __construct(Context $context, OrderRepositoryInterface $orderRepository,
        SuccessValidator $successValidator, TransactionService $transactionService)
    {
        parent::__construct($context, $orderRepository);
        $this->successValidator = $successValidator;
        $this->transactionService = $transactionService;
    }

    public function execute()
    {
        $order = $this->getOrder();

        $this->transactionService->waitForTransactionState($order,
            [
                TransactionState::AUTHORIZED,
                TransactionState::COMPLETED,
                TransactionState::FULFILL
            ], 5);

        if (! $this->successValidator->isValid()) {
            $this->messageManager->addErrorMessage(
                \__(
                    'There seems to have been a problem with your order. ' .
                    'However, the payment was successful. Please contact us.'));
            return $this->_redirect('checkout/cart');
        }

        return $this->_redirect($this->getSuccessRedirectionPath($order));
    }

    /**
     * Gets the path to redirect the customer to.
     *
     * @param Order $order
     * @return string
     */
    private function getSuccessRedirectionPath(Order $order)
    {
        $response = new DataObject();
        $response->setPath('checkout/onepage/success');
        $this->_eventManager->dispatch('postfinancecheckout_success_redirection_path',
            [
                'order' => $order,
                'response' => $response
            ]);
        return $response->getPath();
    }
}