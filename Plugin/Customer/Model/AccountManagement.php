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
namespace PostFinanceCheckout\Payment\Plugin\Customer\Model;

use Magento\Checkout\Model\Session\Proxy as CheckoutSession;

class AccountManagement
{

    /**
     *
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     *
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(CheckoutSession $checkoutSession)
    {
        $this->checkoutSession = $checkoutSession;
    }

    public function beforeIsEmailAvailable(\Magento\Customer\Model\AccountManagement $subject, $customerEmail,
        $websiteId = null)
    {
        $this->checkoutSession->setPostFinanceCheckoutCheckoutEmailAddress($customerEmail);
    }
}