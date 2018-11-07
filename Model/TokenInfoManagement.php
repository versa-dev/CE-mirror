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
use PostFinanceCheckout\Payment\Api\PaymentMethodConfigurationRepositoryInterface;
use PostFinanceCheckout\Payment\Api\TokenInfoManagementInterface;
use PostFinanceCheckout\Payment\Api\TokenInfoRepositoryInterface;
use PostFinanceCheckout\Payment\Api\Data\TokenInfoInterface;
use PostFinanceCheckout\Payment\Helper\Data as Helper;
use PostFinanceCheckout\Sdk\Model\CreationEntityState;
use PostFinanceCheckout\Sdk\Model\EntityQuery;
use PostFinanceCheckout\Sdk\Model\EntityQueryFilter;
use PostFinanceCheckout\Sdk\Model\EntityQueryFilterType;
use PostFinanceCheckout\Sdk\Model\TokenVersion;
use PostFinanceCheckout\Sdk\Model\TokenVersionState;
use PostFinanceCheckout\Sdk\Service\TokenService;
use PostFinanceCheckout\Sdk\Service\TokenVersionService;

/**
 * Token info management service.
 */
class TokenInfoManagement implements TokenInfoManagementInterface
{

    /**
     *
     * @var Helper
     */
    protected $_helper;

    /**
     *
     * @var TokenInfoRepositoryInterface
     */
    protected $_tokenInfoRepository;

    /**
     *
     * @var TokenInfoFactory
     */
    protected $_tokenInfoFactory;

    /**
     *
     * @var PaymentMethodConfigurationRepositoryInterface
     */
    protected $_paymentMethodConfigurationRepository;

    /**
     *
     * @var ApiClient
     */
    protected $_apiClient;

    /**
     *
     * @param Helper $helper
     * @param TokenInfoRepositoryInterface $tokenInfoRepository
     * @param TokenInfoFactory $tokenInfoFactory
     * @param PaymentMethodConfigurationRepositoryInterface $paymentMethodConfigurationRepository
     * @param ApiClient $apiClient
     */
    public function __construct(Helper $helper, TokenInfoRepositoryInterface $tokenInfoRepository,
        TokenInfoFactory $tokenInfoFactory,
        PaymentMethodConfigurationRepositoryInterface $paymentMethodConfigurationRepository, ApiClient $apiClient)
    {
        $this->_helper = $helper;
        $this->_tokenInfoRepository = $tokenInfoRepository;
        $this->_tokenInfoFactory = $tokenInfoFactory;
        $this->_paymentMethodConfigurationRepository = $paymentMethodConfigurationRepository;
        $this->_apiClient = $apiClient;
    }

    public function updateTokenVersion($spaceId, $tokenVersionId)
    {
        $tokenVersion = $this->_apiClient->getService(TokenVersionService::class)->read($spaceId, $tokenVersionId);
        $this->updateTokenVersionInfo($tokenVersion);
    }

    public function updateToken($spaceId, $tokenId)
    {
        $query = new EntityQuery();
        $filter = new EntityQueryFilter();
        $filter->setType(EntityQueryFilterType::_AND);
        $filter->setChildren(
            [
                $this->_helper->createEntityFilter('token.id', $tokenId),
                $this->_helper->createEntityFilter('state', TokenVersionState::ACTIVE)
            ]);
        $query->setFilter($filter);
        $query->setNumberOfEntities(1);
        $tokenVersions = $this->_apiClient->getService(TokenVersionService::class)->search($spaceId, $query);
        if (! empty($tokenVersions)) {
            $this->updateTokenVersionInfo($tokenVersions[0]);
        } else {
            try {
                $tokenInfo = $this->_tokenInfoRepository->getByTokenId($spaceId, $tokenId);
                $this->_tokenInfoRepository->delete($tokenInfo);
            } catch (NoSuchEntityException $e) {}
        }
    }

    protected function updateTokenVersionInfo(TokenVersion $tokenVersion)
    {
        try {
            $tokenInfo = $this->_tokenInfoRepository->getByTokenId($tokenVersion->getLinkedSpaceId(),
                $tokenVersion->getToken()
                    ->getId());
        } catch (NoSuchEntityException $e) {
            $tokenInfo = $this->_tokenInfoFactory->create();
        }

        if (! \in_array($tokenVersion->getToken()->getState(),
            [
                CreationEntityState::ACTIVE,
                CreationEntityState::INACTIVE
            ])) {
            if ($tokenInfo->getId()) {
                $this->_tokenInfoRepository->delete($tokenInfo);
            }
        } else {
            $tokenInfo->setData(TokenInfoInterface::CUSTOMER_ID,
                $tokenVersion->getToken()
                    ->getCustomerId());
            $tokenInfo->setData(TokenInfoInterface::NAME, $tokenVersion->getName());
            $tokenInfo->setData(TokenInfoInterface::PAYMENT_METHOD_ID,
                $this->_paymentMethodConfigurationRepository->getByConfigurationId($tokenVersion->getLinkedSpaceId(),
                    $tokenVersion->getPaymentConnectorConfiguration()
                        ->getPaymentMethodConfiguration()
                        ->getId())
                    ->getId());
            $tokenInfo->setData(TokenInfoInterface::CONNECTOR_ID,
                $tokenVersion->getPaymentConnectorConfiguration()
                    ->getId());
            $tokenInfo->setData(TokenInfoInterface::SPACE_ID, $tokenVersion->getLinkedSpaceId());
            $tokenInfo->setData(TokenInfoInterface::STATE, $tokenVersion->getToken()
                ->getState());
            $tokenInfo->setData(TokenInfoInterface::TOKEN_ID, $tokenVersion->getToken()
                ->getId());
            $this->_tokenInfoRepository->save($tokenInfo);
        }
    }

    public function deleteToken(TokenInfo $token)
    {
        $this->_apiClient->getService(TokenService::class)->delete($token->getSpaceId(), $token->getTokenId());
        $this->_tokenInfoRepository->delete($token);
    }
}