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
namespace PostFinanceCheckout\Payment\Model\Config\Source;

/**
 * Provides the integration methods as array options.
 */
class IntegrationMethod implements \Magento\Framework\Option\ArrayInterface
{

    const IFRAME = 'iframe';
    const LIGHTBOX = 'lightbox';

    public function toOptionArray()
    {
        return [
            [
                'value' => self::IFRAME,
                'label' => \__('Iframe')
            ],
            [
                'value' => self::LIGHTBOX,
                'label' => \__('Lightbox')
            ]
        ];
    }
}