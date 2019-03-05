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
namespace PostFinanceCheckout\Payment\Block;

class Checkout extends \Magento\Framework\View\Element\AbstractBlock
{

    protected function _construct()
    {
        /** @var \Magento\Framework\App\ObjectManager $om */
        $om = \Magento\Framework\App\ObjectManager::getInstance();
        /** @var \Magento\Framework\View\Page\Config $page */
        $page = $om->get('Magento\Framework\View\Page\Config');

        if ($this->_scopeConfig->getValue('amasty_checkout/general/enabled')) {
            $page->addPageAsset('PostFinanceCheckout_Payment::js/model/amasty-checkout.js');
        } elseif ($this->_scopeConfig->getValue('iwd_opc/general/enable')) {
            $page->addPageAsset('PostFinanceCheckout_Payment::js/model/iwd-opc-checkout.js');
        }
    }
}