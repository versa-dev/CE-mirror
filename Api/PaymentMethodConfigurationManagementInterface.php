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
namespace PostFinanceCheckout\Payment\Api;

/**
 * Payment method configuration management interface.
 *
 * @api
 */
interface PaymentMethodConfigurationManagementInterface
{

    /**
     * Synchronizes the payment method configurations from PostFinance Checkout.
     */
    public function synchronize();

    /**
     * Updates the payment method configuration information.
     *
     * @param \PostFinanceCheckout\Sdk\Model\PaymentMethodConfiguration $configuration
     */
    public function update(\PostFinanceCheckout\Sdk\Model\PaymentMethodConfiguration $configuration);
}