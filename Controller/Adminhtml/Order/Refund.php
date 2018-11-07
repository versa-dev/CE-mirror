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
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\OrderRepositoryInterface;
use PostFinanceCheckout\Payment\Api\RefundJobRepositoryInterface;
use PostFinanceCheckout\Payment\Api\TransactionInfoRepositoryInterface;
use PostFinanceCheckout\Payment\Helper\Data as Helper;
use PostFinanceCheckout\Payment\Helper\Locale as LocaleHelper;
use PostFinanceCheckout\Payment\Model\ApiClient;
use PostFinanceCheckout\Sdk\Model\RefundState;
use PostFinanceCheckout\Sdk\Service\RefundService;

/**
 * Backend controller action to send a refund request to PostFinance Checkout.
 */
class Refund extends \PostFinanceCheckout\Payment\Controller\Adminhtml\Order
{

    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'Magento_Sales::sales_creditmemo';

    /**
     *
     * @var LocaleHelper
     */
    protected $_localeHelper;

    /**
     *
     * @var RefundJobRepositoryInterface
     */
    protected $_refundJobRepository;

    /**
     *
     * @param Context $context
     * @param ForwardFactory $resultForwardFactory
     * @param FileFactory $fileFactory
     * @param Helper $helper
     * @param OrderRepositoryInterface $orderRepository
     * @param TransactionInfoRepositoryInterface $transactionInfoRepository
     * @param ApiClient $apiClient
     * @param LocaleHelper $localeHelper
     * @param RefundJobRepositoryInterface $refundJobRepository
     */
    public function __construct(Context $context, ForwardFactory $resultForwardFactory, FileFactory $fileFactory,
        Helper $helper, OrderRepositoryInterface $orderRepository,
        TransactionInfoRepositoryInterface $transactionInfoRepository, ApiClient $apiClient, LocaleHelper $localeHelper,
        RefundJobRepositoryInterface $refundJobRepository)
    {
        parent::__construct($context, $resultForwardFactory, $fileFactory, $helper, $orderRepository,
            $transactionInfoRepository, $apiClient);
        $this->_localeHelper = $localeHelper;
        $this->_refundJobRepository = $refundJobRepository;
    }

    public function execute()
    {
        $orderId = $this->getRequest()->getParam('order_id');
        if ($orderId) {
            try {
                $refundJob = $this->_refundJobRepository->getByOrderId($orderId);

                try {
                    $refund = $this->_apiClient->getService(RefundService::class)->refund($refundJob->getSpaceId(),
                        $refundJob->getRefund());

                    if ($refund->getState() == RefundState::FAILED) {
                        $this->messageManager->addErrorMessage(
                            $this->_localeHelper->translate(
                                $refund->getFailureReason()
                                    ->getDescription()));
                    } elseif ($refund->getState() == RefundState::PENDING ||
                        $refund->getState() == RefundState::MANUAL_CHECK) {
                        $this->messageManager->addErrorMessage(
                            \__('The refund was requested successfully, but is still pending on the gateway.'));
                    } else {
                        $this->messageManager->addSuccessMessage(\__('Successfully refunded.'));
                    }
                } catch (\PostFinanceCheckout\Sdk\ApiException $e) {
                    if ($e->getResponseObject() instanceof \PostFinanceCheckout\Sdk\Model\ClientError) {
                        $this->messageManager->addErrorMessage($e->getResponseObject()->getMessage());
                    } else {
                        $this->messageManager->addErrorMessage(
                            \__('There has been an error while sending the refund to the gateway.'));
                    }
                } catch (\Exception $e) {
                    $this->messageManager->addErrorMessage(
                        \__('There has been an error while sending the refund to the gateway.'));
                }
            } catch (NoSuchEntityException $e) {
                $this->messageManager->addErrorMessage(\__('For this order no refund request exists.'));
            }
            return $this->resultRedirectFactory->create()->setPath('sales/order/view',
                [
                    'order_id' => $orderId
                ]);
        } else {
            return $this->_resultForwardFactory->create()->forward('noroute');
        }
    }
}