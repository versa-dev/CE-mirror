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
namespace PostFinanceCheckout\Payment\Model;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order;
use PostFinanceCheckout\Payment\Api\TransactionInfoManagementInterface;
use PostFinanceCheckout\Payment\Api\TransactionInfoRepositoryInterface;
use PostFinanceCheckout\Payment\Api\Data\TransactionInfoInterface;
use PostFinanceCheckout\Payment\Helper\Data as Helper;
use PostFinanceCheckout\Sdk\Model\ChargeAttemptState;
use PostFinanceCheckout\Sdk\Model\EntityQuery;
use PostFinanceCheckout\Sdk\Model\EntityQueryFilter;
use PostFinanceCheckout\Sdk\Model\EntityQueryFilterType;
use PostFinanceCheckout\Sdk\Model\FailureReason;
use PostFinanceCheckout\Sdk\Model\Transaction;
use PostFinanceCheckout\Sdk\Model\TransactionState;
use PostFinanceCheckout\Sdk\Service\ChargeAttemptService;

/**
 * Transaction info management service.
 */
class TransactionInfoManagement implements TransactionInfoManagementInterface
{

    /**
     *
     * @var Helper
     */
    protected $_helper;

    /**
     *
     * @var TransactionInfoRepositoryInterface
     */
    protected $_transactionInfoRepository;

    /**
     *
     * @var TransactionInfoFactory
     */
    protected $_transactionInfoFactory;

    /**
     *
     * @var ApiClient
     */
    protected $_apiClient;

    /**
     *
     * @param Helper $helper
     * @param TransactionInfoRepositoryInterface $transactionInfoRepository
     * @param TransactionInfoFactory $transactionInfoFactory
     * @param ApiClient $apiClient
     */
    public function __construct(Helper $helper, TransactionInfoRepositoryInterface $transactionInfoRepository,
        TransactionInfoFactory $transactionInfoFactory, ApiClient $apiClient)
    {
        $this->_helper = $helper;
        $this->_transactionInfoRepository = $transactionInfoRepository;
        $this->_transactionInfoFactory = $transactionInfoFactory;
        $this->_apiClient = $apiClient;
    }

    public function update(Transaction $transaction, Order $order)
    {
        try {
            $info = $this->_transactionInfoRepository->getByTransactionId($transaction->getLinkedSpaceId(),
                $transaction->getId());
        } catch (NoSuchEntityException $e) {
            $info = $this->_transactionInfoFactory->create();
        }
        $info->setData(TransactionInfoInterface::TRANSACTION_ID, $transaction->getId());
        $info->setData(TransactionInfoInterface::AUTHORIZATION_AMOUNT, $transaction->getAuthorizationAmount());
        $info->setData(TransactionInfoInterface::ORDER_ID, $order->getId());
        $info->setData(TransactionInfoInterface::STATE, $transaction->getState());
        $info->setData(TransactionInfoInterface::SPACE_ID, $transaction->getLinkedSpaceId());
        $info->setData(TransactionInfoInterface::SPACE_VIEW_ID, $transaction->getSpaceViewId());
        $info->setData(TransactionInfoInterface::LANGUAGE, $transaction->getLanguage());
        $info->setData(TransactionInfoInterface::CURRENCY, $transaction->getCurrency());
        $info->setData(TransactionInfoInterface::CONNECTOR_ID,
            $transaction->getPaymentConnectorConfiguration() != null ? $transaction->getPaymentConnectorConfiguration()
                ->getConnector() : null);
        $info->setData(TransactionInfoInterface::PAYMENT_METHOD_ID,
            $transaction->getPaymentConnectorConfiguration() != null && $transaction->getPaymentConnectorConfiguration()
                ->getPaymentMethodConfiguration() != null ? $transaction->getPaymentConnectorConfiguration()
                ->getPaymentMethodConfiguration()
                ->getPaymentMethod() : null);
        $info->setData(TransactionInfoInterface::IMAGE, $this->getPaymentMethodImage($transaction, $order));
        $info->setData(TransactionInfoInterface::LABELS, $this->getTransactionLabels($transaction));
        if ($transaction->getState() == TransactionState::FAILED || $transaction->getState() == TransactionState::DECLINE) {
            $info->setData(TransactionInfoInterface::FAILURE_REASON,
                $transaction->getFailureReason() instanceof FailureReason ? $transaction->getFailureReason()
                    ->getDescription() : null);
        }
        $this->_transactionInfoRepository->save($info);
        return $info;
    }

    /**
     * Gets an array of the transaction's labels.
     *
     * @param Transaction $transaction
     * @return string[]
     */
    protected function getTransactionLabels(Transaction $transaction)
    {
        $chargeAttempt = $this->getChargeAttempt($transaction);
        if ($chargeAttempt != null) {
            $labels = array();
            foreach ($chargeAttempt->getLabels() as $label) {
                $labels[$label->getDescriptor()->getId()] = $label->getContentAsString();
            }

            return $labels;
        } else {
            return array();
        }
    }

    /**
     * Gets the successful charge attempt of the transaction.
     *
     * @param Transaction $transaction
     * @return \PostFinanceCheckout\Sdk\Model\ChargeAttempt
     */
    protected function getChargeAttempt(Transaction $transaction)
    {
        $query = new EntityQuery();
        $filter = new EntityQueryFilter();
        $filter->setType(EntityQueryFilterType::_AND);
        $filter->setChildren(
            array(
                $this->_helper->createEntityFilter('charge.transaction.id', $transaction->getId()),
                $this->_helper->createEntityFilter('state', ChargeAttemptState::SUCCESSFUL)
            ));
        $query->setFilter($filter);
        $query->setNumberOfEntities(1);
        $result = $this->_apiClient->getService(ChargeAttemptService::class)->search($transaction->getLinkedSpaceId(),
            $query);
        if ($result != null && ! empty($result)) {
            return \current($result);
        } else {
            return null;
        }
    }

    /**
     * Gets the payment method's image.
     *
     * @param Transaction $transaction
     * @param Order $order
     * @return string
     */
    protected function getPaymentMethodImage(Transaction $transaction, Order $order)
    {
        if ($transaction->getPaymentConnectorConfiguration() != null &&
            $transaction->getPaymentConnectorConfiguration()->getPaymentMethodConfiguration() != null) {
            return $this->extractImagePath(
                $transaction->getPaymentConnectorConfiguration()
                    ->getPaymentMethodConfiguration()
                    ->getResolvedImageUrl());
        } else {
            return $order->getPayment()
                ->getMethodInstance()
                ->getPaymentMethodConfiguration()
                ->getImage();
        }
    }

    /**
     * Extracts the image path from the URL.
     *
     * @param string $resolvedImageUrl
     * @return string
     */
    protected function extractImagePath($resolvedImageUrl)
    {
        $index = \strpos($resolvedImageUrl, 'resource/');
        return \substr($resolvedImageUrl, $index + \strlen('resource/'));
    }
}