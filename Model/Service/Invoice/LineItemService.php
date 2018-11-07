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
namespace PostFinanceCheckout\Payment\Model\Service\Invoice;

use Magento\Customer\Model\GroupRegistry as CustomerGroupRegistry;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Sales\Model\Order\Invoice;
use Magento\Tax\Helper\Data as TaxHelper;
use Magento\Tax\Model\Calculation as TaxCalculation;
use Magento\Tax\Model\TaxClass\Repository as TaxClassRepository;
use PostFinanceCheckout\Payment\Helper\Data as Helper;
use PostFinanceCheckout\Payment\Helper\LineItem as LineItemHelper;
use PostFinanceCheckout\Payment\Model\Service\AbstractLineItemService;
use PostFinanceCheckout\Sdk\Model\LineItemAttributeCreate;

/**
 * Service to handle line items in invoice context.
 */
class LineItemService extends AbstractLineItemService
{

    /**
     *
     * @param Helper $helper
     * @param LineItemHelper $lineItemHelper
     * @param ScopeConfigInterface $scopeConfig
     * @param TaxClassRepository $taxClassRepository
     * @param TaxHelper $taxHelper
     * @param TaxCalculation $taxCalculation
     * @param CustomerGroupRegistry $groupRegistry
     * @param EventManagerInterface $eventManager
     */
    public function __construct(Helper $helper, LineItemHelper $lineItemHelper, ScopeConfigInterface $scopeConfig,
        TaxClassRepository $taxClassRepository, TaxHelper $taxHelper, TaxCalculation $taxCalculation,
        CustomerGroupRegistry $groupRegistry, EventManagerInterface $eventManager)
    {
        parent::__construct($helper, $lineItemHelper, $scopeConfig, $taxClassRepository, $taxHelper, $taxCalculation,
            $groupRegistry, $eventManager);
    }

    /**
     * Convers the invoice's items to line items.
     *
     * @param Invoice $invoice
     * @param float $expectedAmount
     * @return \PostFinanceCheckout\Sdk\Model\LineItemCreate[]
     */
    public function convertInvoiceLineItems(Invoice $invoice, $expectedAmount)
    {
        return $this->_lineItemHelper->reduceAmount($this->convertLineItems($invoice), $expectedAmount);
    }

    /**
     * Gets the attributes for the given invoice item.
     *
     * @param Invoice\Item $entityItem
     * @return LineItemAttributeCreate[]
     */
    protected function getAttributes($entityItem)
    {
        $attributes = [];
        $options = $entityItem->getOrderItem()->getProductOptions();
        if (isset($options['attributes_info'])) {
            foreach ($options['attributes_info'] as $option) {
                $value = $option['value'];
                if (\is_array($value)) {
                    $value = \current($value);
                }

                $attribute = new LineItemAttributeCreate();
                $attribute->setLabel($this->_helper->fixLength($this->_helper->getFirstLine($option['label']), 512));
                $attribute->setValue($this->_helper->fixLength($this->_helper->getFirstLine($value), 512));
                $attributes[$this->getAttributeKey($option)] = $attribute;
            }
        }
        return $attributes;
    }

    /**
     * Converts the invoice's shipping information to a line item.
     *
     * @param Invoice $invoice
     * @return \PostFinanceCheckout\Sdk\Model\LineItemCreate
     */
    protected function convertShippingLineItem($invoice)
    {
        return $this->convertShippingLineItemInner($invoice, $invoice->getShippingInclTax(),
            $invoice->getOrder()
                ->getShippingDescription());
    }

    /**
     * Gets the shipping tax for the given entity.
     *
     * @param Invoice $invoice
     * @return \PostFinanceCheckout\Sdk\Model\TaxCreate
     */
    protected function getShippingTax($invoice)
    {
        return parent::getShippingTax($invoice->getOrder());
    }

    /**
     *
     * @param Invoice\Item $entityItem
     * @return string
     */
    protected function getUniqueId($entityItem)
    {
        return $entityItem->getOrderItem()->getQuoteItemId();
    }

    /**
     * Gets the currency code of the given order.
     *
     * @param Invoice $invoice
     * @return string
     */
    protected function getCurrencyCode($invoice)
    {
        return $invoice->getOrderCurrencyCode();
    }
}