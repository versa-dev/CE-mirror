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
namespace PostFinanceCheckout\Payment\Setup;

use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;

/**
 * Installs the data in the database.
 */
class InstallData implements InstallDataInterface
{

    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $data = [
            [
                'status' => 'processing_postfinancecheckout',
                'label' => \__('Hold Delivery')
            ]
        ];
        $setup->getConnection()->insertArray($setup->getTable('sales_order_status'),
            [
                'status',
                'label'
            ], $data);

        $data = [
            [
                'status' => 'processing_postfinancecheckout',
                'state' => 'processing',
                'is_default' => 0
            ]
        ];
        $setup->getConnection()->insertArray($setup->getTable('sales_order_status_state'),
            [
                'status',
                'state',
                'is_default'
            ], $data);
    }
}