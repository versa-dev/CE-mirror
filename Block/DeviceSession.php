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

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\ScopeInterface;

class DeviceSession extends \Magento\Framework\View\Element\Template
{

    /**
     *
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     *
     * @var CookieManagerInterface
     */
    private $cookieManager;

    /**
     *
     * @var CookieMetadataFactory
     */
    private $cookieMetadataFactory;

    /**
     *
     * @param Context $context
     * @param ScopeConfigInterface $scopeConfig
     * @param CookieManagerInterface $cookieManager
     * @param CookieMetadataFactory $cookieMetadataFactory
     * @param array $data
     */
    public function __construct(Context $context, ScopeConfigInterface $scopeConfig,
        CookieManagerInterface $cookieManager, CookieMetadataFactory $cookieMetadataFactory,
        array $data = [])
    {
        parent::__construct($context, $data);
        $this->scopeConfig = $scopeConfig;
        $this->cookieManager = $cookieManager;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
    }

    /**
     *
     * @return string
     */
    public function getSessionIdentifierUrl()
    {
        return $this->getUrl('postfinancecheckout_payment/checkout/deviceSession', ['_secure' => $this->getRequest()->isSecure()]);
    }

    /**
     *
     * @return string
     */
    public function getScriptUrl()
    {
        $baseUrl = \rtrim($this->scopeConfig->getValue('postfinancecheckout_payment/general/base_gateway_url'), '/');
        $spaceId = $this->scopeConfig->getValue('postfinancecheckout_payment/general/space_id',
            ScopeInterface::SCOPE_STORE, $this->_storeManager->getStore());
        if (! empty($spaceId)) {
            return $baseUrl . '/s/' . $spaceId . '/payment/device.js?sessionIdentifier=';
        }
    }
}