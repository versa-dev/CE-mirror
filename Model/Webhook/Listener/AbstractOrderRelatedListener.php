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
use Magento\Framework\Exception\NotFoundException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;
use PostFinanceCheckout\Payment\Api\TransactionInfoManagementInterface;
use PostFinanceCheckout\Payment\Api\TransactionInfoRepositoryInterface;
use PostFinanceCheckout\Payment\Model\Webhook\ListenerInterface;
use PostFinanceCheckout\Payment\Model\Webhook\Request;

/**
 * Abstract webhook listener for order related entities.
 */
abstract class AbstractOrderRelatedListener implements ListenerInterface
{

    /**
     *
     * @var ResourceConnection
     */
    private $resource;

    /**
     *
     * @var LoggerInterface
     */
    private $logger;

    /**
     *
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     *
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     *
     * @var CommandPoolInterface
     */
    private $commandPool;

    /**
     *
     * @var TransactionInfoRepositoryInterface
     */
    private $transactionInfoRepository;

    /**
     *
     * @var TransactionInfoManagementInterface
     */
    private $transactionInfoManagement;

    /**
     *
     * @param ResourceConnection $resource
     * @param LoggerInterface $logger
     * @param OrderRepositoryInterface $orderRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param CommandPoolInterface $commandPool
     * @param TransactionInfoRepositoryInterface $transactionInfoRepository
     * @param TransactionInfoManagementInterface $transactionInfoManagement
     */
    public function __construct(ResourceConnection $resource, LoggerInterface $logger,
        OrderRepositoryInterface $orderRepository, SearchCriteriaBuilder $searchCriteriaBuilder,
        CommandPoolInterface $commandPool, TransactionInfoRepositoryInterface $transactionInfoRepository,
        TransactionInfoManagementInterface $transactionInfoManagement)
    {
        $this->resource = $resource;
        $this->logger = $logger;
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->commandPool = $commandPool;
        $this->transactionInfoRepository = $transactionInfoRepository;
        $this->transactionInfoManagement = $transactionInfoManagement;
    }

    public function execute(Request $request)
    {
        $entity = $this->loadEntity($request);

        $connection = $this->beginTransaction();
        try {
            $order = $this->getOrderByTransaction($request->getSpaceId(), $this->getTransactionId($entity));
            if ($order instanceof Order) {
                if ($order->getIncrementId() == $this->getOrderIncrementId($entity)) {
                    $this->lock($order);
                    $this->process($entity, $order->load($order->getId()));
                }
            }
            $connection->commit();
        } catch (\Exception $e) {
            $this->logger->critical($e);
            $connection->rollBack();
            throw $e;
        }
    }

    /**
     * Starts a database transaction with isolation level 'read uncommitted'.
     *
     * In case of two parallel requests linked to the same order, data written to the database by the first will
     * not be up-to-date in the second. This can lead to processing the same data multiple times. By setting the
     * isolation level to 'read uncommitted' this issue can be avoided.
     *
     * An alternative solution to this problem would be to use optimistic locking. However, this could lead to database
     * rollbacks and as for example updating the order status could lead to triggering further processes which may not
     * propertly handle rollbacks, this could result in inconsistencies.
     *
     * @return \Magento\Framework\DB\Adapter\AdapterInterface
     */
    private function beginTransaction()
    {
        $connection = $this->resource->getConnection('sales');
        $connection->rawQuery("SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;");
        $connection->beginTransaction();
        return $connection;
    }

    /**
     * Gets the order linked to the given transaction.
     *
     * @param int $spaceId
     * @param int $transactionId
     * @return Order|NULL
     */
    private function getOrderByTransaction($spaceId, $transactionId)
    {
        $searchCriteria = $this->searchCriteriaBuilder->addFilter('postfinancecheckout_space_id', $spaceId)
            ->addFilter('postfinancecheckout_transaction_id', $transactionId)
            ->setPageSize(1)
            ->create();
        $orders = $this->orderRepository->getList($searchCriteria)->getItems();
        if (! empty($orders)) {
            return \current($orders);
        } else {
            return null;
        }
    }

    /**
     * Creates a lock to prevent concurrency.
     *
     * @param Order $order
     */
    private function lock(Order $order)
    {
        $this->resource->getConnection()->update($this->resource->getTableName('sales_order'),
            [
                'postfinancecheckout_lock' => \date('Y-m-d H:i:s')
            ], [
                'entity_id = ?' => $order->getId()
            ]);
    }

    /**
     * Actually processes the order related webhook request.
     *
     * @param mixed $entity
     * @param Order $order
     */
    protected function process($entity, Order $order)
    {
        try {
            $this->commandPool->get(\strtolower($entity->getState()))
                ->execute($entity, $order);
        } catch (NotFoundException $e) {}
    }

    /**
     * Loads the entity for the webhook request.
     *
     * @param Request $request
     * @return mixed
     */
    abstract protected function loadEntity(Request $request);

    /**
     * Gets the order's increment id linked to the entity.
     *
     * @param mixed $entity
     * @return string
     */
    abstract protected function getOrderIncrementId($entity);

    /**
     * Gets the transaction's id linked to the entity.
     *
     * @param mixed $entity
     * @return int
     */
    abstract protected function getTransactionId($entity);
}