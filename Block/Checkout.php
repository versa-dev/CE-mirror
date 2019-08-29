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
namespace PostFinanceCheckout\Payment\Block;

use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\View\Page\Config as ViewPageConfig;

class Checkout extends \Magento\Framework\View\Element\AbstractBlock
{

    protected function _construct()
    {
        /** @var \Magento\Framework\App\ObjectManager $om */
        $om = \Magento\Framework\App\ObjectManager::getInstance();
        /** @var ModuleManager $moduleManager */
        $moduleManager = $om->get(ModuleManager::class);
        /** @var ViewPageConfig $page */
        $page = $om->get(ViewPageConfig::class);

        if ($moduleManager->isEnabled('Amasty_Checkout') &&
            $this->_scopeConfig->getValue('amasty_checkout/general/enabled')) {
            $page->addPageAsset('PostFinanceCheckout_Payment::js/model/amasty-checkout.js');
        } elseif ($moduleManager->isEnabled('IWD_Opc') && $this->_scopeConfig->getValue('iwd_opc/general/enable')) {
            $page->addPageAsset('PostFinanceCheckout_Payment::js/model/iwd-opc-checkout.js');
        }
    }
}