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
namespace PostFinanceCheckout\Payment\Plugin\Payment\Model\Method;

use Magento\Framework\ObjectManagerInterface;
use Magento\Payment\Gateway\Config\Config;
use Magento\Payment\Gateway\Config\ConfigValueHandler;
use PostFinanceCheckout\Payment\Model\Payment\Gateway\Config\ValueHandlerPool;
use PostFinanceCheckout\Payment\Model\Payment\Method\Adapter;

/**
 * Interceptor to provide the payment method adapters for the PostFinance Checkout payment methods.
 */
class Factory
{

    /**
     *
     * @var ObjectManagerInterface
     */
    protected $_objectManager = null;

    /**
     *
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(ObjectManagerInterface $objectManager)
    {
        $this->_objectManager = $objectManager;
    }

    public function beforeCreate(\Magento\Payment\Model\Method\Factory $subject, $classname, $data = [])
    {
        if (strpos($classname, 'postfinancecheckout_payment::') === 0) {
            $configurationId = \substr($classname, \strlen('postfinancecheckout_payment::'));
            $data['code'] = 'postfinancecheckout_payment_' . $configurationId;
            $data['paymentMethodConfigurationId'] = $configurationId;
            $data['valueHandlerPool'] = $this->getValueHandlerPool($configurationId);
            $data['commandPool'] = $this->_objectManager->get('PostFinanceCheckoutPaymentGatewayCommandPool');
            $data['validatorPool'] = $this->_objectManager->get('PostFinanceCheckoutPaymentGatewayValidatorPool');
            return [
                Adapter::class,
                $data
            ];
        } else {
            return null;
        }
    }

    protected function getValueHandlerPool($configurationId)
    {
        $configInterface = $this->_objectManager->create(Config::class,
            [
                'methodCode' => 'postfinancecheckout_payment_' . $configurationId
            ]);
        $valueHandler = $this->_objectManager->create(ConfigValueHandler::class,
            [
                'configInterface' => $configInterface
            ]);
        return $this->_objectManager->create(ValueHandlerPool::class,
            [
                'handler' => $valueHandler
            ]);
    }
}