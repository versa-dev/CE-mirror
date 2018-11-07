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
namespace PostFinanceCheckout\Payment\Model\Webhook\Listener;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use PostFinanceCheckout\Payment\Api\TransactionInfoManagementInterface;
use PostFinanceCheckout\Payment\Api\TransactionInfoRepositoryInterface;
use PostFinanceCheckout\Payment\Model\ApiClient;
use PostFinanceCheckout\Payment\Model\Webhook\Request;
use PostFinanceCheckout\Sdk\Service\RefundService;

/**
 * Webhook listener to handle refunds.
 */
class RefundListener extends AbstractOrderRelatedListener
{

    /**
     *
     * @var ApiClient
     */
    protected $_apiClient;

    /**
     *
     * @param ResourceConnection $resource
     * @param LoggerInterface $logger
     * @param OrderRepositoryInterface $orderRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param CommandPoolInterface $commandPool
     * @param TransactionInfoRepositoryInterface $transactionInfoRepository
     * @param TransactionInfoManagementInterface $transactionInfoManagement
     * @param ApiClient $apiClient
     */
    public function __construct(ResourceConnection $resource, LoggerInterface $logger,
        OrderRepositoryInterface $orderRepository, SearchCriteriaBuilder $searchCriteriaBuilder,
        CommandPoolInterface $commandPool, TransactionInfoRepositoryInterface $transactionInfoRepository,
        TransactionInfoManagementInterface $transactionInfoManagement, ApiClient $apiClient)
    {
        parent::__construct($resource, $logger, $orderRepository, $searchCriteriaBuilder, $commandPool,
            $transactionInfoRepository, $transactionInfoManagement);
        $this->_apiClient = $apiClient;
    }

    /**
     * Loads the refund for the webhook request.
     *
     * @param Request $request
     * @return \PostFinanceCheckout\Sdk\Model\Refund
     */
    protected function loadEntity(Request $request)
    {
        return $this->_apiClient->getService(RefundService::class)->read($request->getSpaceId(), $request->getEntityId());
    }

    /**
     * Gets the order's increment id linked to the refund.
     *
     * @param \PostFinanceCheckout\Sdk\Model\Refund $entity
     * @return string
     */
    protected function getOrderIncrementId($entity)
    {
        return $entity->getTransaction()->getMerchantReference();
    }

    /**
     * Gets the transaction's ID.
     *
     * @param \PostFinanceCheckout\Sdk\Model\Refund $entity
     * @return int
     */
    protected function getTransactionId($entity)
    {
        return $entity->getTransaction()->getId();
    }
}