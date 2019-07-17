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
namespace PostFinanceCheckout\Payment\Controller\Order;

use Magento\Framework\Registry;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Sales\Controller\AbstractController\OrderLoaderInterface;
use PostFinanceCheckout\Payment\Api\TransactionInfoRepositoryInterface;
use PostFinanceCheckout\Payment\Helper\Document as DocumentHelper;
use PostFinanceCheckout\Payment\Model\ApiClient;
use PostFinanceCheckout\Sdk\Service\TransactionService;

/**
 * Frontend controller action to download a packing slip.
 */
class DownloadPackingSlip extends \PostFinanceCheckout\Payment\Controller\Order
{

    /**
     *
     * @var ForwardFactory
     */
    private $resultForwardFactory;

    /**
     *
     * @var FileFactory
     */
    private $fileFactory;

    /**
     *
     * @var Registry
     */
    private $registry;

    /**
     *
     * @var DocumentHelper
     */
    private $documentHelper;

    /**
     *
     * @var OrderLoaderInterface
     */
    private $orderLoader;

    /**
     *
     * @var TransactionInfoRepositoryInterface
     */
    private $transactionInfoRepository;

    /**
     *
     * @var ApiClient
     */
    private $apiClient;

    /**
     *
     * @param Context $context
     * @param ForwardFactory $resultForwardFactory
     * @param FileFactory $fileFactory
     * @param Registry $registry
     * @param DocumentHelper $documentHelper
     * @param OrderLoaderInterface $orderLoader
     * @param TransactionInfoRepositoryInterface $transactionInfoRepository
     * @param ApiClient $apiClient
     */
    public function __construct(Context $context, ForwardFactory $resultForwardFactory, FileFactory $fileFactory,
        Registry $registry, DocumentHelper $documentHelper, OrderLoaderInterface $orderLoader,
        TransactionInfoRepositoryInterface $transactionInfoRepository, ApiClient $apiClient)
    {
        parent::__construct($context);
        $this->resultForwardFactory = $resultForwardFactory;
        $this->fileFactory = $fileFactory;
        $this->registry = $registry;
        $this->documentHelper = $documentHelper;
        $this->orderLoader = $orderLoader;
        $this->transactionInfoRepository = $transactionInfoRepository;
        $this->apiClient = $apiClient;
    }

    public function execute()
    {
        $result = $this->orderLoader->load($this->_request);
        if ($result instanceof ResultInterface) {
            return $result;
        }

        /** @var \Magento\Sales\Model\Order $order */
        $order = $this->registry->registry('current_order');
        $transaction = $this->transactionInfoRepository->getByOrderId($order->getId());
        if ($this->documentHelper->isInvoiceDownloadAllowed($transaction, $order->getStoreId())) {
            $document = $this->apiClient->getService(TransactionService::class)->getPackingSlip(
                $transaction->getSpaceId(), $transaction->getTransactionId());
            return $this->fileFactory->create($document->getTitle() . '.pdf', \base64_decode($document->getData()),
                DirectoryList::VAR_DIR, 'application/pdf');
        } else {
            return $this->resultForwardFactory->create()->forward('noroute');
        }
    }
}