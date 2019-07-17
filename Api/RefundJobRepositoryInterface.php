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
namespace PostFinanceCheckout\Payment\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use PostFinanceCheckout\Payment\Api\Data\RefundJobInterface;

/**
 * Refund job CRUD interface.
 *
 * @api
 */
interface RefundJobRepositoryInterface
{

    /**
     * Create refund job
     *
     * @param RefundJobInterface $object
     * @return RefundJobInterface
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function save(RefundJobInterface $object);

    /**
     * Get job about refund job by entity ID
     *
     * @param int $entityId
     * @return RefundJobInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function get($entityId);

    /**
     * Get job about refund job by order ID
     *
     * @param int $orderId
     * @return RefundJobInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getByOrderId($orderId);

    /**
     * Get job about refund job by external ID
     *
     * @param string $externalId
     * @return RefundJobInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getByExternalId($externalId);

    /**
     * Retrieve refund jobs matching the specified criteria.
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return \PostFinanceCheckout\Payment\Api\Data\RefundJobSearchResultsInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getList(SearchCriteriaInterface $searchCriteria);

    /**
     * Delete refund job
     *
     * @param RefundJobInterface $object
     * @return bool Will returned True if deleted
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\StateException
     */
    public function delete(RefundJobInterface $object);

    /**
     * Delete refund job by identifier
     *
     * @param string $entityId
     * @return bool Will returned True if deleted
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\StateException
     */
    public function deleteByIdentifier($entityId);
}