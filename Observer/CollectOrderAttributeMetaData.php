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
namespace PostFinanceCheckout\Payment\Observer;

use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Sales\Model\Order;

/**
 * Observer to collect the order attribute meta data for the transaction.
 */
class CollectOrderAttributeMetaData implements ObserverInterface
{

    /**
     *
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     *
     * @var ModuleManager
     */
    private $moduleManager;

    /**
     *
     * @param ObjectManagerInterface $objectManager
     * @param ModuleManager $moduleManager
     */
    public function __construct(ObjectManagerInterface $objectManager, ModuleManager $moduleManager)
    {
        $this->objectManager = $objectManager;
        $this->moduleManager = $moduleManager;
    }

    public function execute(Observer $observer)
    {
        /* @var \Magento\Sales\Model\Order $order */
        $order = $observer->getOrder();
        $transport = $observer->getTransport();

        if ($this->moduleManager->isEnabled('Amasty_Orderattr')) {
            $transport->setData('metaData',
                \array_merge($transport->getData('metaData'), $this->collectOrderAttributeMetaData($order)));
        }
    }

    /**
     * Collects the data that is to be transmitted to the gateway as transaction meta data.
     *
     * @param Order $order
     * @return array
     */
    protected function collectOrderAttributeMetaData(Order $order)
    {
        $metaData = [];
        /* @var \Amasty\Orderattr\Model\ResourceModel\Attribute\Collection $attributeCollection */
        $attributeCollection = $this->objectManager->get(
            'Amasty\Orderattr\Model\ResourceModel\Attribute\CollectionFactory')->create();
        $attributeCollection->addFieldToSelect('attribute_code');
        $attributeCollection->addFieldToSelect('frontend_label');
        foreach ($attributeCollection->getData() as $attribute) {
            $metaData['order_' . $attribute['attribute_code']] = $order->getData($attribute['attribute_code']);
        }
        return $metaData;
    }
}