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

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Cache\TypeListInterface as CacheTypeList;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface as StorageWriter;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use PostFinanceCheckout\Payment\Api\PaymentMethodConfigurationManagementInterface;
use PostFinanceCheckout\Payment\Api\PaymentMethodConfigurationRepositoryInterface;
use PostFinanceCheckout\Payment\Api\Data\PaymentMethodConfigurationInterface;
use PostFinanceCheckout\Payment\Helper\Locale as LocaleHelper;
use PostFinanceCheckout\Sdk\Model\CreationEntityState;
use PostFinanceCheckout\Sdk\Model\EntityQuery;
use PostFinanceCheckout\Sdk\Service\PaymentMethodConfigurationService;

/**
 * Payment method configuration management service.
 */
class PaymentMethodConfigurationManagement implements PaymentMethodConfigurationManagementInterface
{

    /**
     *
     * @var PaymentMethodConfigurationFactory
     */
    private $paymentMethodConfigurationFactory;

    /**
     *
     * @var PaymentMethodConfigurationRepositoryInterface
     */
    private $paymentMethodConfigurationRepository;

    /**
     *
     * @var LocaleHelper
     */
    private $localeHelper;

    /**
     *
     * @var ApiClient
     */
    private $apiClient;

    /**
     *
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     *
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     *
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     *
     * @var StorageWriter
     */
    private $configWriter;

    /**
     *
     * @var CacheTypeList
     */
    private $cacheTypeList;

    /**
     *
     * @param PaymentMethodConfigurationFactory $paymentMethodConfigurationFactory
     * @param PaymentMethodConfigurationRepositoryInterface $paymentMethodConfigurationRepository
     * @param LocaleHelper $localeHelper
     * @param ApiClient $apiClient
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param StorageWriter $configWriter
     * @param CacheTypeList $cacheTypeList
     */
    public function __construct(PaymentMethodConfigurationFactory $paymentMethodConfigurationFactory,
        PaymentMethodConfigurationRepositoryInterface $paymentMethodConfigurationRepository, LocaleHelper $localeHelper,
        ApiClient $apiClient, SearchCriteriaBuilder $searchCriteriaBuilder, StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig, StorageWriter $configWriter, CacheTypeList $cacheTypeList)
    {
        $this->paymentMethodConfigurationFactory = $paymentMethodConfigurationFactory;
        $this->paymentMethodConfigurationRepository = $paymentMethodConfigurationRepository;
        $this->localeHelper = $localeHelper;
        $this->apiClient = $apiClient;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->configWriter = $configWriter;
        $this->cacheTypeList = $cacheTypeList;
    }

    public function synchronize()
    {
        $existingConfigurations = $this->paymentMethodConfigurationRepository->getList(
            $this->searchCriteriaBuilder->create())
            ->getItems();
        foreach ($existingConfigurations as $existingConfiguration) {
            /** @var PaymentMethodConfiguration $existingConfiguration */
            $existingConfiguration->setData(PaymentMethodConfigurationInterface::STATE,
                PaymentMethodConfiguration::STATE_HIDDEN);
        }

        $spaceIds = [];
        $existingFound = [];
        $createdEntities = [];
        foreach ($this->storeManager->getWebsites() as $website) {
            $spaceId = $this->scopeConfig->getValue('postfinancecheckout_payment/general/space_id',
                ScopeInterface::SCOPE_WEBSITE, $website->getId());
            if ($spaceId && ! in_array($spaceId, $spaceIds)) {
                $configurations = $this->apiClient->getService(PaymentMethodConfigurationService::class)->search(
                    $spaceId, new EntityQuery());
                foreach ($configurations as $configuration) {
                    /** @var PaymentMethodConfiguration $entity */
                    $entity = null;
                    foreach ($existingConfigurations as $existingConfiguration) {
                        /** @var PaymentMethodConfiguration $existingConfiguration */
                        if ($existingConfiguration->getSpaceId() == $spaceId &&
                            $existingConfiguration->getConfigurationId() == $configuration->getId()) {
                            $entity = $existingConfiguration;
                            $existingFound[] = $entity->getId();
                            break;
                        }
                    }

                    if ($entity == null) {
                        $entity = $this->paymentMethodConfigurationFactory->create();
                        $createdEntities[] = $entity;
                    }

                    $entity->setData(PaymentMethodConfigurationInterface::SPACE_ID, $spaceId);
                    $entity->setData(PaymentMethodConfigurationInterface::STATE,
                        $this->toConfigurationState($configuration->getState()));
                    $entity->setData(PaymentMethodConfigurationInterface::CONFIGURATION_ID, $configuration->getId());
                    $entity->setData(PaymentMethodConfigurationInterface::CONFIGURATION_NAME, $configuration->getName());
                    $entity->setData(PaymentMethodConfigurationInterface::TITLE, $configuration->getResolvedTitle());
                    $entity->setData(PaymentMethodConfigurationInterface::DESCRIPTION,
                        $configuration->getResolvedDescription());
                    $entity->setData(PaymentMethodConfigurationInterface::IMAGE,
                        $this->extractImagePath($configuration->getResolvedImageUrl()));
                    $entity->setData(PaymentMethodConfigurationInterface::SORT_ORDER, $configuration->getSortOrder());
                    $this->paymentMethodConfigurationRepository->save($entity);
                }
            }
        }

        foreach ($createdEntities as $entity) {
            $this->storeConfigValues($entity);
        }

        foreach ($existingConfigurations as $existingConfiguration) {
            if (! in_array($existingConfiguration->getId(), $existingFound)) {
                $existingConfiguration->setData(PaymentMethodConfigurationInterface::STATE,
                    PaymentMethodConfiguration::STATE_HIDDEN);
                $this->paymentMethodConfigurationRepository->save($existingConfiguration);
            }
        }

        $this->clearCache();
    }

    private function clearCache()
    {
        $this->cacheTypeList->cleanType(\Magento\Framework\App\Cache\Type\Config::TYPE_IDENTIFIER);
    }

    private function storeConfigValues(PaymentMethodConfigurationInterface $configuration)
    {
        $defaultLocale = $this->scopeConfig->getValue('general/locale/code');

        $this->storeConfigValue($configuration, 'title', $this->getTranslatedTitle($configuration, $defaultLocale));
        $this->storeConfigValue($configuration, 'description',
            $this->localeHelper->translate($configuration->getDescription(), $defaultLocale));

        $stores = $this->storeManager->getStores();
        foreach ($this->storeManager->getWebsites() as $website) {
            $websiteLocale = $this->scopeConfig->getValue('general/locale/code', ScopeInterface::SCOPE_WEBSITES,
                $website->getId());
            if ($websiteLocale != $defaultLocale) {
                $this->storeConfigValue($configuration, 'title',
                    $this->getTranslatedTitle($configuration, $websiteLocale), ScopeInterface::SCOPE_WEBSITES,
                    $website->getId());
                $this->storeConfigValue($configuration, 'description',
                    $this->localeHelper->translate($configuration->getDescription(), $websiteLocale),
                    ScopeInterface::SCOPE_WEBSITES, $website->getId());
            }

            foreach ($stores as $store) {
                if ($store->getWebsiteId() == $website->getId()) {
                    $storeLocale = $this->scopeConfig->getValue('general/locale/code', ScopeInterface::SCOPE_STORES,
                        $store->getId());
                    if ($storeLocale != $websiteLocale) {
                        $this->storeConfigValue($configuration, 'title',
                            $this->getTranslatedTitle($configuration, $storeLocale), ScopeInterface::SCOPE_STORES,
                            $store->getId());
                        $this->storeConfigValue($configuration, 'description',
                            $this->localeHelper->translate($configuration->getDescription(), $storeLocale),
                            ScopeInterface::SCOPE_STORES, $store->getId());
                    }
                }
            }
        }
    }

    private function storeConfigValue(PaymentMethodConfigurationInterface $configuration, $key, $value,
        $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, $scopeId = 0)
    {
        $this->configWriter->save('payment/postfinancecheckout_payment_' . $configuration->getEntityId() . '/' . $key,
            $value, $scope, $scopeId);
    }

    /**
     * Gets the translated title of the payment method configuration.
     *
     * If the title is not set, the configuration's name will be returned instead.
     *
     * @param PaymentMethodConfiguration $configuration
     * @param string $language
     * @return string
     */
    private function getTranslatedTitle(PaymentMethodConfiguration $configuration, $language)
    {
        $translatedTitle = $this->localeHelper->translate($configuration->getTitle(), $language);
        if (! empty($translatedTitle)) {
            return $translatedTitle;
        } else {
            return $configuration->getConfigurationName();
        }
    }

    public function update(\PostFinanceCheckout\Sdk\Model\PaymentMethodConfiguration $configuration)
    {
        try {
            $entity = $this->paymentMethodConfigurationRepository->getByConfigurationId($configuration->getSpaceId(),
                $configuration->getId());
            if ($this->hasConfigurationChanged($configuration, $entity)) {
                $entity->setData(PaymentMethodConfigurationInterface::CONFIGURATION_NAME, $configuration->getName());
                $entity->setData(PaymentMethodConfigurationInterface::TITLE, $configuration->getResolvedTitle());
                $entity->setData(PaymentMethodConfigurationInterface::DESCRIPTION,
                    $configuration->getResolvedDescription());
                $entity->setData(PaymentMethodConfigurationInterface::IMAGE,
                    $this->extractImagePath($configuration->getResolvedImageUrl()));
                $entity->setData(PaymentMethodConfigurationInterface::SORT_ORDER, $configuration->getSortOrder());
                $this->paymentMethodConfigurationRepository->save($entity);
            }
        } catch (NoSuchEntityException $e) {}
    }

    private function hasConfigurationChanged(\PostFinanceCheckout\Sdk\Model\PaymentMethodConfiguration $configuration,
        PaymentMethodConfigurationInterface $entity)
    {
        if ($configuration->getName() != $entity->getConfigurationName()) {
            return true;
        }

        if ($configuration->getResolvedTitle() != $entity->getTitle()) {
            return true;
        }

        if ($configuration->getResolvedDescription() != $entity->getDescription()) {
            return true;
        }

        if ($this->extractImagePath($configuration->getResolvedImageUrl()) != $entity->getImage()) {
            return true;
        }

        if ($configuration->getSortOrder() != $entity->getSortOrder()) {
            return true;
        }

        return false;
    }

    /**
     * Extracts the image path from the URL.
     *
     * @param string $resolvedImageUrl
     * @return string
     */
    private function extractImagePath($resolvedImageUrl)
    {
        $index = \strpos($resolvedImageUrl, 'resource/');
        return \substr($resolvedImageUrl, $index + \strlen('resource/'));
    }

    /**
     * Gets the state for the payment method configuration.
     *
     * @param string $state
     * @return number
     */
    private function toConfigurationState($state)
    {
        switch ($state) {
            case CreationEntityState::ACTIVE:
                return PaymentMethodConfiguration::STATE_ACTIVE;
            case CreationEntityState::INACTIVE:
                return PaymentMethodConfiguration::STATE_INACTIVE;
            default:
                return PaymentMethodConfiguration::STATE_HIDDEN;
        }
    }
}