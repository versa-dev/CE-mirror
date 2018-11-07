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
namespace PostFinanceCheckout\Payment\Helper;

use Magento\Framework\App\Helper\AbstractHelper;

/**
 * Helper to provide line item reduction related functionality.
 */
class LineItemReduction extends AbstractHelper
{

    /**
     * Gets the amount of the line item's reductions.
     *
     * @param \PostFinanceCheckout\Sdk\Model\LineItem[] $lineItems
     * @param \PostFinanceCheckout\Sdk\Model\LineItemReduction[] $reductions
     */
    public function getReducedAmount(array $lineItems, array $reductions)
    {
        $lineItemMap = array();
        foreach ($lineItems as $lineItem) {
            $lineItemMap[$lineItem->getUniqueId()] = $lineItem;
        }

        $amount = 0;
        foreach ($reductions as $reduction) {
            $lineItem = $lineItemMap[$reduction->getLineItemUniqueId()];
            $amount += $lineItem->getUnitPriceIncludingTax() * $reduction->getQuantityReduction();
            $amount += $reduction->getUnitPriceReduction() *
                ($lineItem->getQuantity() - $reduction->getQuantityReduction());
        }

        return $amount;
    }
}