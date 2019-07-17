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
namespace PostFinanceCheckout\Payment\Controller\Checkout;

use Magento\Checkout\Model\Session\Proxy as CheckoutSession;
use Magento\Framework\DataObject;
use Magento\Framework\App\Action\Context;

/**
 * Frontend controller action to handle checkout failures.
 */
class Failure extends \PostFinanceCheckout\Payment\Controller\Checkout
{

    /**
     *
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     *
     * @param Context $context
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(Context $context, CheckoutSession $checkoutSession)
    {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
    }

    public function execute()
    {
        $this->checkoutSession->restoreQuote();

        $this->messageManager->addErrorMessage(\__('The payment could not be processed. Please try again.'));
        return $this->_redirect($this->getFailureRedirectionPath());
    }

    /**
     * Gets the path to redirect the customer to.
     *
     * @return string
     */
    private function getFailureRedirectionPath()
    {
        $response = new DataObject();
        $response->setPath('checkout/cart');
        $this->_eventManager->dispatch('postfinancecheckout_checkout_failure_redirection_path',
            [
                'response' => $response
            ]);
        return $response->getPath();
    }
}