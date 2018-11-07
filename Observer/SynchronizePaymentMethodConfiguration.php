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
use PostFinanceCheckout\Payment\Api\PaymentMethodConfigurationManagementInterface;

/**
 * Observer to synchronize the payment method configurations.
 */
class SynchronizePaymentMethodConfiguration implements ObserverInterface
{

    /**
     *
     * @var PaymentMethodConfigurationManagementInterface
     */
    protected $_paymentMethodConfigurationManagement;

    /**
     *
     * @param PaymentMethodConfigurationManagementInterface $paymentMethodConfigurationManagement
     */
    public function __construct(PaymentMethodConfigurationManagementInterface $paymentMethodConfigurationManagement)
    {
        $this->_paymentMethodConfigurationManagement = $paymentMethodConfigurationManagement;
    }

    public function execute(Observer $observer)
    {
        $this->_paymentMethodConfigurationManagement->synchronize();
    }
}