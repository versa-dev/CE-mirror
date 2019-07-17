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
namespace PostFinanceCheckout\Payment\Model\Webhook\Listener\DeliveryIndication;

use PostFinanceCheckout\Payment\Model\Webhook\Listener\AbstractOrderRelatedCommand;

/**
 * Abstract webhook listener command to handle delivery indications.
 */
abstract class AbstractCommand extends AbstractOrderRelatedCommand
{
}