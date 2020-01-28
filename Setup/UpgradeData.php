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
namespace PostFinanceCheckout\Payment\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\UpgradeDataInterface;

/**
 * Upgrades the data in the database.
 */
class UpgradeData implements UpgradeDataInterface
{

    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();

        if (version_compare($context->getVersion(), '1.0.60') < 0) {
            $this->addOrderStatusShipped($installer);
        }

        $installer->endSetup();
    }

    private function addOrderStatusShipped(ModuleDataSetupInterface $setup)
    {
        $select = $setup->getConnection()
            ->select()
            ->from($setup->getTable('sales_order_status'), [
            'status'
        ])
            ->where('status = ?', 'shipped_postfinancecheckout');

        if (count($setup->getConnection()->fetchAll($select)) == 0) {
            $data = [
                [
                    'status' => 'shipped_postfinancecheckout',
                    'label' => \__('Shipped')
                ]
            ];
            $setup->getConnection()->insertArray($setup->getTable('sales_order_status'), [
                'status',
                'label'
            ], $data);

            $data = [
                [
                    'status' => 'shipped_postfinancecheckout',
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
}