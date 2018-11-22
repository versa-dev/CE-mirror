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
namespace PostFinanceCheckout\Payment\Model\Service\Quote;

use Magento\Customer\Model\CustomerRegistry;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Store\Model\ScopeInterface;
use PostFinanceCheckout\Payment\Api\PaymentMethodConfigurationManagementInterface;
use PostFinanceCheckout\Payment\Helper\Data as Helper;
use PostFinanceCheckout\Payment\Model\ApiClient;
use PostFinanceCheckout\Payment\Model\Service\AbstractTransactionService;
use PostFinanceCheckout\Sdk\ApiException;
use PostFinanceCheckout\Sdk\VersioningException;
use PostFinanceCheckout\Sdk\Model\AbstractTransactionPending;
use PostFinanceCheckout\Sdk\Model\AddressCreate;
use PostFinanceCheckout\Sdk\Model\CustomersPresence;
use PostFinanceCheckout\Sdk\Model\Transaction;
use PostFinanceCheckout\Sdk\Model\TransactionCreate;
use PostFinanceCheckout\Sdk\Model\TransactionPending;
use PostFinanceCheckout\Sdk\Model\TransactionState;
use PostFinanceCheckout\Sdk\Service\TransactionService as TransactionApiService;

/**
 * Service to handle transactions in quote context.
 */
class TransactionService extends AbstractTransactionService
{

    /**
     *
     * @var LineItemService
     */
    protected $_lineItemService;

    /**
     *
     * @var \PostFinanceCheckout\Sdk\Model\Transaction[]
     */
    private $transactionCache = array();

    /**
     *
     * @var \PostFinanceCheckout\Sdk\Model\PaymentMethodConfiguration[]
     */
    private $possiblePaymentMethodCache = array();

    /**
     *
     * @param Helper $helper
     * @param ScopeConfigInterface $scopeConfig
     * @param CustomerRegistry $customerRegistry
     * @param CartRepositoryInterface $quoteRepository
     * @param PaymentMethodConfigurationManagementInterface $paymentMethodConfigurationManagement
     * @param ApiClient $apiClient
     * @param LineItemService $lineItemService
     */
    public function __construct(Helper $helper, ScopeConfigInterface $scopeConfig, CustomerRegistry $customerRegistry,
        CartRepositoryInterface $quoteRepository,
        PaymentMethodConfigurationManagementInterface $paymentMethodConfigurationManagement, ApiClient $apiClient,
        LineItemService $lineItemService)
    {
        parent::__construct($helper, $scopeConfig, $customerRegistry, $quoteRepository,
            $paymentMethodConfigurationManagement, $apiClient);
        $this->_lineItemService = $lineItemService;
    }

    /**
     * Gets the URL to the JavaScript library that is required to display the payment form.
     *
     * @param Quote $quote
     * @return string
     */
    public function getJavaScriptUrl(Quote $quote)
    {
        $transaction = $this->getTransactionByQuote($quote);
        return $this->_apiClient->getService(TransactionApiService::class)->buildJavaScriptUrl(
            $transaction->getLinkedSpaceId(), $transaction->getId());
    }

    /**
     * Gets the URL to the payment page.
     *
     * @param Quote $quote
     * @return string
     */
    public function getPaymentPageUrl(Quote $quote)
    {
        $transaction = $this->getTransactionByQuote($quote);
        return $this->_apiClient->getService(TransactionApiService::class)->buildPaymentPageUrl(
            $transaction->getLinkedSpaceId(), $transaction->getId());
    }

    /**
     * Gets the payment methods that can be used with the given quote.
     *
     * @param Quote $quote
     * @return \PostFinanceCheckout\Sdk\Model\PaymentMethodConfiguration[]
     */
    public function getPossiblePaymentMethods(Quote $quote)
    {
        if (! array_key_exists($quote->getId(), $this->possiblePaymentMethodCache) ||
            $this->possiblePaymentMethodCache[$quote->getId()] == null) {
            $transaction = $this->getTransactionByQuote($quote);
            try {
                $paymentMethods = $this->_apiClient->getService(TransactionApiService::class)->fetchPossiblePaymentMethods(
                    $transaction->getLinkedSpaceId(), $transaction->getId());
            } catch (ApiException $e) {
                $this->possiblePaymentMethodCache[$quote->getId()] = [];
                throw $e;
            }
            $this->updatePaymentMethodConfigurations($paymentMethods);
            $this->possiblePaymentMethodCache[$quote->getId()] = $paymentMethods;
        }
        return $this->possiblePaymentMethodCache[$quote->getId()];
    }

    /**
     * Gets the transaction for the given quote.
     *
     * If there is not transaction for the quote, a new one is created.
     *
     * @param Quote $quote
     * @return Transaction
     */
    public function getTransactionByQuote(Quote $quote)
    {
        if (! array_key_exists($quote->getId(), $this->transactionCache) ||
            $this->transactionCache[$quote->getId()] == null) {
            $transactionId = $quote->getPostfinancecheckoutTransactionId();
            if (empty($transactionId)) {
                $this->transactionCache[$quote->getId()] = $this->createTransactionByQuote($quote);
            } else {
                $this->transactionCache[$quote->getId()] = $this->updateTransactionByQuote($quote);
            }
        }
        return $this->transactionCache[$quote->getId()];
    }

    /**
     * Creates a transaction for the given quote.
     *
     * @param Quote $quote
     * @return Transaction
     */
    protected function createTransactionByQuote(Quote $quote)
    {
        $spaceId = $this->_scopeConfig->getValue('postfinancecheckout_payment/general/space_id',
            ScopeInterface::SCOPE_STORE, $quote->getStoreId());

        $createTransaction = new TransactionCreate();
        $createTransaction->setCustomersPresence(CustomersPresence::VIRTUAL_PRESENT);
        $createTransaction->setAutoConfirmationEnabled(false);
        $this->assembleTransactionDataFromQuote($createTransaction, $quote);
        $transaction = $this->_apiClient->getService(TransactionApiService::class)->create($spaceId, $createTransaction);
        $this->updateQuote($quote, $transaction);
        return $transaction;
    }

    /**
     * Updates the transaction with the given quote's data.
     *
     * @param Quote $quote
     * @throws VersioningException
     * @return Transaction
     */
    protected function updateTransactionByQuote(Quote $quote)
    {
        for ($i = 0; $i < 5; $i ++) {
            try {
                $transaction = $this->_apiClient->getService(TransactionApiService::class)->read(
                    $quote->getPostfinancecheckoutSpaceId(), $quote->getPostfinancecheckoutTransactionId());
                if (! ($transaction instanceof Transaction) || $transaction->getState() != TransactionState::PENDING) {
                    return $this->createTransactionByQuote($quote);
                }

                $pendingTransaction = new TransactionPending();
                $pendingTransaction->setId($transaction->getId());
                $pendingTransaction->setVersion($transaction->getVersion());
                $this->assembleTransactionDataFromQuote($pendingTransaction, $quote);
                return $this->_apiClient->getService(TransactionApiService::class)->update(
                    $quote->getPostfinancecheckoutSpaceId(), $pendingTransaction);
            } catch (VersioningException $e) {
                // Try to update the transaction again, if a versioning exception occurred.
            }
        }
        throw new VersioningException();
    }

    /**
     * Assembles the transaction data from the given quote.
     *
     * @param AbstractTransactionPending $transaction
     * @param Quote $quote
     */
    protected function assembleTransactionDataFromQuote(AbstractTransactionPending $transaction, Quote $quote)
    {
        $transaction->setAllowedPaymentMethodConfigurations([]);
        $transaction->setCurrency($quote->getQuoteCurrencyCode());
        $transaction->setBillingAddress($this->convertQuoteBillingAddress($quote));
        $transaction->setShippingAddress($this->convertQuoteShippingAddress($quote));
        $transaction->setCustomerEmailAddress(
            $this->getCustomerEmailAddress($quote->getCustomerEmail(), $quote->getCustomerId()));
        $transaction->setLanguage(
            $this->_scopeConfig->getValue('general/locale/code', ScopeInterface::SCOPE_STORE, $quote->getStoreId()));
        $transaction->setLineItems($this->_lineItemService->convertQuoteLineItems($quote));
        if (! empty($quote->getCustomerId())) {
            $transaction->setCustomerId($quote->getCustomerId());
        }
        if ($quote->getShippingAddress()) {
            $transaction->setShippingMethod(
                $this->_helper->fixLength(
                    $this->_helper->getFirstLine(
                        $quote->getShippingAddress()
                            ->getShippingDescription()), 200));
        }

        if ($transaction instanceof TransactionCreate) {
            $transaction->setSpaceViewId(
                $this->_scopeConfig->getValue('postfinancecheckout_payment/general/store_view_id',
                    ScopeInterface::SCOPE_STORE, $quote->getStoreId()));
        }
    }

    /**
     * Converts the billing address of the given quote.
     *
     * @param Quote $quote
     * @return \PostFinanceCheckout\Sdk\Model\AddressCreate
     */
    protected function convertQuoteBillingAddress(Quote $quote)
    {
        if (! $quote->getBillingAddress()) {
            return null;
        }

        $address = $this->convertAddress($quote->getBillingAddress());
        $address->setDateOfBirth($this->getDateOfBirth($quote->getCustomerDob(), $quote->getCustomerId()));
        $address->setEmailAddress($this->getCustomerEmailAddress($quote->getCustomerEmail(), $quote->getCustomerId()));
        $address->setGender($this->getGender($quote->getCustomerGender(), $quote->getCustomerId()));
        $address->setSalesTaxNumber($this->getTaxNumber($quote->getCustomerTaxvat(), $quote->getCustomerId()));
        return $address;
    }

    /**
     * Converts the shipping address of the given quote.
     *
     * @param Quote $quote
     * @return \PostFinanceCheckout\Sdk\Model\AddressCreate
     */
    protected function convertQuoteShippingAddress(Quote $quote)
    {
        if (! $quote->getShippingAddress()) {
            return null;
        }

        $address = $this->convertAddress($quote->getShippingAddress());
        $address->setEmailAddress($this->getCustomerEmailAddress($quote->getCustomerEmail(), $quote->getCustomerId()));
        return $address;
    }

    /**
     * Converts the given address.
     *
     * @param Address $customerAddress
     * @return AddressCreate
     */
    protected function convertAddress(Address $customerAddress)
    {
        $address = new AddressCreate();
        $address->setSalutation(
            $this->_helper->fixLength($this->_helper->removeLinebreaks($customerAddress->getPrefix()), 20));
        $address->setCity($this->_helper->fixLength($this->_helper->removeLinebreaks($customerAddress->getCity()), 100));
        $address->setCountry($customerAddress->getCountryId());
        $address->setFamilyName(
            $this->_helper->fixLength($this->_helper->removeLinebreaks($customerAddress->getLastname()), 100));
        $address->setGivenName(
            $this->_helper->fixLength($this->_helper->removeLinebreaks($customerAddress->getFirstname()), 100));
        $address->setOrganizationName(
            $this->_helper->fixLength($this->_helper->removeLinebreaks($customerAddress->getCompany()), 100));
        $address->setPhoneNumber($customerAddress->getTelephone());
        $address->setPostalState($customerAddress->getRegionCode());
        $address->setPostCode(
            $this->_helper->fixLength($this->_helper->removeLinebreaks($customerAddress->getPostcode()), 40));
        $address->setStreet($this->_helper->fixLength($customerAddress->getStreetFull(), 300));
        return $address;
    }
}