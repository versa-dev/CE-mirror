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
namespace PostFinanceCheckout\Payment\Model\Provider;

use Magento\Framework\Cache\FrontendInterface;
use PostFinanceCheckout\Payment\Model\ApiClient;
use PostFinanceCheckout\Sdk\Service\LanguageService;

/**
 * Provider of language information from the gateway.
 */
class LanguageProvider extends AbstractProvider
{

    /**
     *
     * @var ApiClient
     */
    private $apiClient;

    /**
     *
     * @param FrontendInterface $cache
     * @param ApiClient $apiClient
     */
    public function __construct(FrontendInterface $cache, ApiClient $apiClient)
    {
        parent::__construct($cache, 'postfinancecheckout_payment_languages');
        $this->apiClient = $apiClient;
    }

    /**
     * Gets the language by the given code.
     *
     * @param string $code
     * @return \PostFinanceCheckout\Sdk\Model\RestLanguage
     */
    public function find($code)
    {
        return parent::find($code);
    }

    /**
     * Gets the primary language in the given group.
     *
     * @param string $code
     * @return \PostFinanceCheckout\Sdk\Model\RestLanguage
     */
    public function findPrimary($code)
    {
        $code = \substr($code, 0, 2);
        foreach ($this->getAll() as $language) {
            if ($language->getIso2Code() == $code && $language->getPrimaryOfGroup()) {
                return $language;
            }
        }

        return false;
    }

    /**
     * Gets a list of languages.
     *
     * @return \PostFinanceCheckout\Sdk\Model\RestLanguage[]
     */
    public function getAll()
    {
        return parent::getAll();
    }

    protected function fetchData()
    {
        return $this->apiClient->getService(LanguageService::class)->all();
    }

    protected function getId($entry)
    {
        /** @var \PostFinanceCheckout\Sdk\Model\RestLanguage $entry */
        return $entry->getIetfCode();
    }
}