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

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UpgradeSchemaInterface;

/**
 * Updates the database schema.
 */
class UpgradeSchema implements UpgradeSchemaInterface
{

    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();

        if (version_compare($context->getVersion(), '1.0.40') < 0) {
            $this->addDerecognizedStatusToInvoiceTable($installer);
        }

        $installer->endSetup();
    }

    private function addDerecognizedStatusToInvoiceTable(SchemaSetupInterface $installer)
    {
        $installer->getConnection()->addColumn($installer->getTable('sales_invoice'),
            'postfinancecheckout_derecognized',
            [
                'type' => Table::TYPE_BOOLEAN,
                'default' => false,
                'comment' => 'PostFinance Checkout Payment Derecognized'
            ]);
    }
}