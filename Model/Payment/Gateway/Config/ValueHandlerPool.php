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
namespace PostFinanceCheckout\Payment\Model\Payment\Gateway\Config;

use Magento\Payment\Gateway\Config\ValueHandlerInterface;
use Magento\Payment\Gateway\Config\ValueHandlerPoolInterface;

/**
 * Handler to provide payment gateway configuration values.
 */
class ValueHandlerPool implements ValueHandlerPoolInterface
{

    /**
     *
     * @var ValueHandlerInterface
     */
    private $handler;

    /**
     *
     * @param ValueHandlerInterface $handler
     */
    public function __construct(ValueHandlerInterface $handler)
    {
        $this->handler = $handler;
    }

    /**
     * Retrieves the configuration value handler
     *
     * @param string $field
     * @return ValueHandlerInterface
     */
    public function get($field)
    {
        return $this->handler;
    }
}
