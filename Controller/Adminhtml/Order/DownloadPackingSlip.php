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
namespace PostFinanceCheckout\Payment\Controller\Adminhtml\Order;

use Magento\Framework\App\Filesystem\DirectoryList;
use PostFinanceCheckout\Sdk\Service\TransactionService;

/**
 * Backend controller action to download a packing slip.
 */
class DownloadPackingSlip extends \PostFinanceCheckout\Payment\Controller\Adminhtml\Order
{

    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'Magento_Sales::shipment';

    public function execute()
    {
        $orderId = $this->getRequest()->getParam('order_id');
        if ($orderId) {
            $transaction = $this->_transactionInfoRepository->getByOrderId($orderId);
            $document = $this->_apiClient->getService(TransactionService::class)->getPackingSlip(
                $transaction->getSpaceId(), $transaction->getTransactionId());
            return $this->_fileFactory->create($document->getTitle() . '.pdf', \base64_decode($document->getData()),
                DirectoryList::VAR_DIR, 'application/pdf');
        } else {
            return $this->_resultForwardFactory->create()->forward('noroute');
        }
    }
}