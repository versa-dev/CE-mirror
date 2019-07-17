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
namespace PostFinanceCheckout\Payment\Block\Adminhtml\Customer\Tab\Renderer;

use Magento\Backend\Block\Widget\Grid\Column\Renderer\AbstractRenderer;
use Magento\Framework\DataObject;
use PostFinanceCheckout\Sdk\Model\CreationEntityState;

/**
 * Block to render the state grid column of the token grid.
 */
class State extends AbstractRenderer
{

    public function render(DataObject $row)
    {
        switch ($row->getData($this->getColumn()
            ->getIndex())) {
            case CreationEntityState::ACTIVE:
                return \__('Active');
            case CreationEntityState::INACTIVE:
                return \__('Inactive');
            default:
                return \__('Unknown State');
        }
    }
}