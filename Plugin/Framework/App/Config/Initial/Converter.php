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
namespace PostFinanceCheckout\Payment\Plugin\Framework\App\Config\Initial;

use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Initial\SchemaLocator;
use Magento\Framework\Filesystem\DriverPool;
use Magento\Framework\Module\Dir\Reader as ModuleDirReader;
use Magento\Store\Model\StoreManagerInterface;
use PostFinanceCheckout\Payment\Api\PaymentMethodConfigurationRepositoryInterface;
use PostFinanceCheckout\Payment\Api\Data\PaymentMethodConfigurationInterface;
use PostFinanceCheckout\Payment\Helper\Locale as LocaleHelper;
use PostFinanceCheckout\Payment\Model\PaymentMethodConfiguration;
use PostFinanceCheckout\Payment\Model\Config\Dom;
use PostFinanceCheckout\Payment\Model\Config\DomFactory;

/**
 * Interceptor to dynamically extend the initial configuration with the PostFinance Checkout payment method data.
 */
class Converter
{

    /**
     *
     * @var PaymentMethodConfigurationRepositoryInterface
     */
    private $paymentMethodConfigurationRepository;

    /**
     *
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     *
     * @var FilterBuilder
     */
    private $filterBuilder;

    /**
     *
     * @var FilterGroupBuilder
     */
    private $filterGroupBuilder;

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
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     *
     * @var DomFactory
     */
    private $domFactory;

    /**
     *
     * @var SchemaLocator
     */
    private $schemaFile;

    /**
     *
     * @var ModuleDirReader
     */
    private $moduleReader;

    /**
     *
     * @var DriverPool
     */
    private $driverPool;

    /**
     *
     * @var string
     */
    private $template;

    /**
     *
     * @param PaymentMethodConfigurationRepositoryInterface $paymentMethodConfigurationRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param FilterBuilder $filterBuilder
     * @param FilterGroupBuilder $filterGroupBuilder
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param ResourceConnection $resourceConnection
     * @param DomFactory $domFactory
     * @param SchemaLocator $schemaLocator
     * @param ModuleDirReader $moduleReader
     * @param DriverPool $driverPool
     */
    public function __construct(PaymentMethodConfigurationRepositoryInterface $paymentMethodConfigurationRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder, FilterBuilder $filterBuilder,
        FilterGroupBuilder $filterGroupBuilder, StoreManagerInterface $storeManager, ScopeConfigInterface $scopeConfig,
        ResourceConnection $resourceConnection, DomFactory $domFactory, SchemaLocator $schemaLocator,
        ModuleDirReader $moduleReader, DriverPool $driverPool)
    {
        $this->paymentMethodConfigurationRepository = $paymentMethodConfigurationRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->filterBuilder = $filterBuilder;
        $this->filterGroupBuilder = $filterGroupBuilder;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->resourceConnection = $resourceConnection;
        $this->domFactory = $domFactory;
        $this->schemaFile = $schemaLocator->getSchema();
        $this->moduleReader = $moduleReader;
        $this->driverPool = $driverPool;
    }

    public function beforeConvert(\Magento\Framework\App\Config\Initial\Converter $subject, $source)
    {
        if (! $this->isTableExists()) {
            return [
                $source
            ];
        }

        $configMerger = $this->domFactory->createDom(
            [
                'xml' => Dom::CONFIG_INITIAL_CONTENT,
                'schemaFile' => $this->schemaFile
            ]);
        $configMerger->setDom($source);

        $stateFilter = $this->filterBuilder->setConditionType('in')
            ->setField(PaymentMethodConfigurationInterface::STATE)
            ->setValue([
            PaymentMethodConfiguration::STATE_ACTIVE,
            PaymentMethodConfiguration::STATE_INACTIVE
        ])
            ->create();
        $filterGroup = $this->filterGroupBuilder->setFilters([
            $stateFilter
        ])->create();
        $searchCriteria = $this->searchCriteriaBuilder->setFilterGroups([
            $filterGroup
        ])->create();

        $configurations = $this->paymentMethodConfigurationRepository->getList($searchCriteria)->getItems();
        foreach ($configurations as $configuration) {
            $configMerger->merge($this->generateXml($configuration));
        }

        return [
            $configMerger->getDom()
        ];
    }

    private function generateXml(PaymentMethodConfigurationInterface $configuration)
    {
        return \str_replace([
            '{id}',
            '{active}',
            '{title}',
            '{description}',
            '{sortOrder}',
            '{spaceId}'
        ],
            [
                $configuration->getEntityId(),
                $configuration->getState() == PaymentMethodConfiguration::STATE_ACTIVE ? 1 : 0,
                $this->getTranslatedTitle($configuration, LocaleHelper::DEFAULT_LANGUAGE),
                $this->translate($configuration->getDescription(), LocaleHelper::DEFAULT_LANGUAGE),
                $configuration->getSortOrder(),
                $configuration->getSpaceId()
            ], $this->getTemplate());
    }

    /**
     * Gets whether the payment method configuration database table exists.
     *
     * @return boolean
     */
    private function isTableExists()
    {
        try {
            $this->resourceConnection->getConnection();
        } catch (\Exception $e) {
            return false;
        }

        return $this->resourceConnection->getConnection()->isTableExists(
            $this->resourceConnection->getTableName('postfinancecheckout_payment_method_configuration'));
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
        $translatedTitle = $this->translate($configuration->getTitle(), $language);
        if (! empty($translatedTitle)) {
            return $translatedTitle;
        } else {
            return $configuration->getConfigurationName();
        }
    }

    private function translate($translatedString, $language)
    {
        $language = \str_replace('_', '-', $language);
        if (isset($translatedString[$language])) {
            return $translatedString[$language];
        }

        if (isset($translatedString[LocaleHelper::DEFAULT_LANGUAGE])) {
            return $translatedString[LocaleHelper::DEFAULT_LANGUAGE];
        }

        return null;
    }

    private function getTemplate()
    {
        if ($this->template == null) {
            $templatePath = $this->moduleReader->getModuleDir(\Magento\Framework\Module\Dir::MODULE_ETC_DIR,
                'PostFinanceCheckout_Payment') . '/config-method-template.xml';
            $this->template = $this->driverPool->getDriver(DriverPool::FILE)->fileGetContents($templatePath);
        }
        return $this->template;
    }
}