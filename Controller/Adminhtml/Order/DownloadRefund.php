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

use Magento\Backend\App\Action\Context;
use Magento\Backend\App\Response\Http\FileFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\CreditmemoRepository;
use PostFinanceCheckout\Payment\Api\TransactionInfoRepositoryInterface;
use PostFinanceCheckout\Payment\Helper\Data as Helper;
use PostFinanceCheckout\Payment\Model\ApiClient;
use PostFinanceCheckout\Sdk\Model\EntityQuery;
use PostFinanceCheckout\Sdk\Service\RefundService;

/**
 * Backend controller action to download a refund document.
 */
class DownloadRefund extends \PostFinanceCheckout\Payment\Controller\Adminhtml\Order
{

    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'Magento_Sales::sales_creditmemo';

    /**
     *
     * @var CreditmemoRepository
     */
    protected $_creditmemoRepository;

    /**
     *
     * @var ApiClient
     */
    protected $_apiClient;

    /**
     *
     * @param Context $context
     * @param ForwardFactory $resultForwardFactory
     * @param FileFactory $fileFactory
     * @param Helper $helper
     * @param OrderRepositoryInterface $orderRepository
     * @param TransactionInfoRepositoryInterface $transactionInfoRepository
     * @param ApiClient $apiClient
     * @param CreditmemoRepository $creditmemoRepository
     */
    public function __construct(Context $context, ForwardFactory $resultForwardFactory, FileFactory $fileFactory,
        Helper $helper, OrderRepositoryInterface $orderRepository,
        TransactionInfoRepositoryInterface $transactionInfoRepository, ApiClient $apiClient,
        CreditmemoRepository $creditmemoRepository)
    {
        parent::__construct($context, $resultForwardFactory, $fileFactory, $helper, $orderRepository,
            $transactionInfoRepository, $apiClient);
        $this->_creditmemoRepository = $creditmemoRepository;
        $this->_apiClient = $apiClient;
    }

    public function execute()
    {
        $creditmemoId = $this->getRequest()->getParam('creditmemo_id');
        if ($creditmemoId) {
            $creditmemo = $this->_creditmemoRepository->get($creditmemoId);
            if ($creditmemo->getPostfinancecheckoutExternalId() == null) {
                return $this->_resultForwardFactory->create()->forward('noroute');
            }

            $transaction = $this->_transactionInfoRepository->getByOrderId($creditmemo->getOrderId());
            $refund = $this->getRefundByExternalId($transaction->getSpaceId(),
                $creditmemo->getPostfinancecheckoutExternalId());
            $document = $this->_apiClient->getService(RefundService::class)->getRefundDocument(
                $transaction->getSpaceId(), $refund->getId());
            return $this->_fileFactory->create($document->getTitle() . '.pdf', \base64_decode($document->getData()),
                DirectoryList::VAR_DIR, 'application/pdf');
        } else {
            return $this->_resultForwardFactory->create()->forward('noroute');
        }
    }

    /**
     * Fetches the refund's latest state from PostFinance Checkout by its external ID.
     *
     * @param int $spaceId
     * @param string $externalId
     * @throws \Exception
     * @return \PostFinanceCheckout\Sdk\Model\Refund
     */
    protected function getRefundByExternalId($spaceId, $externalId)
    {
        $query = new EntityQuery();
        $query->setFilter($this->_helper->createEntityFilter('externalId', $externalId));
        $query->setNumberOfEntities(1);
        $result = $this->_apiClient->getService(RefundService::class)->search($spaceId, $query);
        if (! empty($result)) {
            return \current($result);
        } else {
            throw new \Exception('The refund could not be found.');
        }
    }
}