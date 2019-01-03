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
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use PostFinanceCheckout\Payment\Api\RefundJobRepositoryInterface;
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
     * @var ForwardFactory
     */
    private $resultForwardFactory;

    /**
     *
     * @var LocaleHelper
     */
    private $localeHelper;

    /**
     *
     * @var RefundJobRepositoryInterface
     */
    private $refundJobRepository;

    /**
     *
     * @var ApiClient
     */
    private $apiClient;

    /**
     *
     * @param Context $context
     * @param ForwardFactory $resultForwardFactory
     * @param LocaleHelper $localeHelper
     * @param RefundJobRepositoryInterface $refundJobRepository
     * @param ApiClient $apiClient
     */
    public function __construct(Context $context, ForwardFactory $resultForwardFactory, LocaleHelper $localeHelper,
        RefundJobRepositoryInterface $refundJobRepository, ApiClient $apiClient)
    {
        parent::__construct($context);
        $this->resultForwardFactory = $resultForwardFactory;
        $this->localeHelper = $localeHelper;
        $this->refundJobRepository = $refundJobRepository;
        $this->apiClient = $apiClient;
    }

    public function execute()
    {
        $orderId = $this->getRequest()->getParam('order_id');
        if ($orderId) {
            try {
                $refundJob = $this->refundJobRepository->getByOrderId($orderId);

                try {
                    $refund = $this->apiClient->getService(RefundService::class)->refund($refundJob->getSpaceId(),
                        $refundJob->getRefund());

                    if ($refund->getState() == RefundState::FAILED) {
                        $this->messageManager->addErrorMessage(
                            $this->localeHelper->translate($refund->getFailureReason()
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
                        $this->messageManager->addErrorMessage($e->getResponseObject()
                            ->getMessage());
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
            return $this->resultRedirectFactory->create()->setPath('sales/order/view', [
                'order_id' => $orderId
            ]);
        } else {
            return $this->resultForwardFactory->create()->forward('noroute');
        }
    }
}