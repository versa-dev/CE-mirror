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

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use PostFinanceCheckout\Payment\Api\TokenInfoRepositoryInterface;
use PostFinanceCheckout\Payment\Api\Data\TokenInfoInterface;
use PostFinanceCheckout\Payment\Api\Data\TokenInfoSearchResultsInterfaceFactory;
use PostFinanceCheckout\Payment\Model\ResourceModel\TokenInfo as TokenInfoResource;
use PostFinanceCheckout\Payment\Model\ResourceModel\TokenInfo\CollectionFactory as TokenInfoCollectionFactory;

/**
 * Token info CRUD service.
 */
class TokenInfoRepository implements TokenInfoRepositoryInterface
{

    /**
     *
     * @var TokenInfoFactory
     */
    protected $_tokenInfoFactory;

    /**
     *
     * @var TokenInfoCollectionFactory
     */
    protected $_tokenInfoCollectionFactory;

    /**
     *
     * @var TokenInfoSearchResultsInterfaceFactory
     */
    protected $_searchResultsFactory;

    /**
     *
     * @var TokenInfoResource
     */
    protected $_resource;

    /**
     *
     * @var CollectionProcessorInterface
     */
    protected $_collectionProcessor;

    /**
     *
     * @param TokenInfoFactory $tokenInfoFactory
     * @param TokenInfoCollectionFactory $tokenInfoCollectionFactory
     * @param TokenInfoSearchResultsInterfaceFactory $searchResultsFactory
     * @param TokenInfoResource $resource
     * @param CollectionProcessorInterface $collectionProcessor
     */
    public function __construct(TokenInfoFactory $tokenInfoFactory,
        TokenInfoCollectionFactory $tokenInfoCollectionFactory,
        TokenInfoSearchResultsInterfaceFactory $searchResultsFactory, TokenInfoResource $resource,
        CollectionProcessorInterface $collectionProcessor)
    {
        $this->_tokenInfoFactory = $tokenInfoFactory;
        $this->_tokenInfoCollectionFactory = $tokenInfoCollectionFactory;
        $this->_searchResultsFactory = $searchResultsFactory;
        $this->_resource = $resource;
        $this->_collectionProcessor = $collectionProcessor;
    }

    /**
     * Create token info
     *
     * @param TokenInfoInterface $object
     * @return TokenInfo
     * @throws CouldNotSaveException
     */
    public function save(TokenInfoInterface $object)
    {
        try {
            $this->_resource->save($object);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(\__('Could not save the token info: %1', $exception->getMessage()),
                $exception);
        }
        return $object;
    }

    /**
     * Get info about token info by entity ID
     *
     * @param int $entityId
     * @return TokenInfo
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function get($entityId)
    {
        if (! $entityId) {
            throw new InputException(\__('ID required'));
        }

        /** @var TokenInfo $object */
        $object = $this->_tokenInfoFactory->create();
        $this->_resource->load($object, $entityId);
        if (! $object->getEntityId()) {
            throw new NoSuchEntityException(\__('Requested entity does not exist'));
        }
        return $object;
    }

    /**
     * Get info about token info by token ID
     *
     * @param int $spaceId
     * @param int $tokenId
     * @return TokenInfo
     * @throws NoSuchEntityException
     */
    public function getByTokenId($spaceId, $tokenId)
    {
        if (! $spaceId) {
            throw new InputException(\__('Space ID required'));
        }
        if (! $tokenId) {
            throw new InputException(\__('Token ID required'));
        }

        /** @var TokenInfo $object */
        $object = $this->_tokenInfoFactory->create();
        $this->_resource->loadByToken($object, $spaceId, $tokenId);
        if (! $object->getEntityId()) {
            throw new NoSuchEntityException(\__('Requested entity does not exist'));
        }
        return $object;
    }

    public function getList(SearchCriteriaInterface $searchCriteria)
    {
        /** @var \PostFinanceCheckout\Payment\Model\ResourceModel\TokenInfo\Collection $collection */
        $collection = $this->_tokenInfoCollectionFactory->create();

        $this->_collectionProcessor->process($searchCriteria, $collection);

        /** @var \PostFinanceCheckout\Payment\Api\Data\TokenInfoSearchResultsInterface $searchResults */
        $searchResults = $this->_searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());
        return $searchResults;
    }

    public function delete(TokenInfoInterface $object)
    {
        try {
            $this->_resource->delete($object);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(\__('Could not delete the token info: %1', $exception->getMessage()));
        }
        return true;
    }

    public function deleteByIdentifier($entityId)
    {
        $this->delete($this->get($entityId));
    }
}