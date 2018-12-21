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
namespace PostFinanceCheckout\Payment\Model\Service\Order;

use Magento\Customer\Model\CustomerRegistry;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use Magento\Sales\Model\Order\Invoice;
use Magento\Store\Model\ScopeInterface;
use PostFinanceCheckout\Payment\Api\PaymentMethodConfigurationManagementInterface;
use PostFinanceCheckout\Payment\Api\TransactionInfoRepositoryInterface;
use PostFinanceCheckout\Payment\Helper\Data as Helper;
use PostFinanceCheckout\Payment\Model\ApiClient;
use PostFinanceCheckout\Payment\Model\Service\AbstractTransactionService;
use PostFinanceCheckout\Sdk\VersioningException;
use PostFinanceCheckout\Sdk\Model\AbstractTransactionPending;
use PostFinanceCheckout\Sdk\Model\AddressCreate;
use PostFinanceCheckout\Sdk\Model\CustomersPresence;
use PostFinanceCheckout\Sdk\Model\EntityQuery;
use PostFinanceCheckout\Sdk\Model\Token;
use PostFinanceCheckout\Sdk\Model\Transaction;
use PostFinanceCheckout\Sdk\Model\TransactionCreate;
use PostFinanceCheckout\Sdk\Model\TransactionPending;
use PostFinanceCheckout\Sdk\Model\TransactionState;
use PostFinanceCheckout\Sdk\Service\DeliveryIndicationService;
use PostFinanceCheckout\Sdk\Service\TransactionCompletionService;
use PostFinanceCheckout\Sdk\Service\TransactionInvoiceService;
use PostFinanceCheckout\Sdk\Service\TransactionService as TransactionApiService;
use PostFinanceCheckout\Sdk\Service\TransactionVoidService;

/**
 * Service to handle transactions in order context.
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
     * @var TransactionInfoRepositoryInterface
     */
    protected $_transactionInfoRepository;

    /**
     *
     * @var ApiClient
     */
    protected $_apiClient;

    /**
     *
     * @param ResourceConnection $resource
     * @param Helper $helper
     * @param ScopeConfigInterface $scopeConfig
     * @param CustomerRegistry $customerRegistry
     * @param CartRepositoryInterface $quoteRepository
     * @param PaymentMethodConfigurationManagementInterface $paymentMethodConfigurationManagement
     * @param ApiClient $apiClient
     * @param LineItemService $lineItemService
     * @param TransactionInfoRepositoryInterface $transactionInfoRepository
     */
    public function __construct(ResourceConnection $resource, Helper $helper, ScopeConfigInterface $scopeConfig,
        CustomerRegistry $customerRegistry, CartRepositoryInterface $quoteRepository,
        PaymentMethodConfigurationManagementInterface $paymentMethodConfigurationManagement, ApiClient $apiClient,
        LineItemService $lineItemService, TransactionInfoRepositoryInterface $transactionInfoRepository)
    {
        parent::__construct($resource, $helper, $scopeConfig, $customerRegistry, $quoteRepository,
            $paymentMethodConfigurationManagement, $apiClient);
        $this->_lineItemService = $lineItemService;
        $this->_transactionInfoRepository = $transactionInfoRepository;
        $this->_apiClient = $apiClient;
    }

    /**
     * Updates the transaction with the given order's data and confirms it.
     *
     * @param Order $order
     * @param Invoice $invoice
     * @param boolean $chargeFlow
     * @param Token $token
     * @throws VersioningException
     * @return Transaction
     */
    public function confirmTransaction(Order $order, Invoice $invoice, $chargeFlow = false, Token $token = null)
    {
        $spaceId = $order->getPostfinancecheckoutSpaceId();
        $transactionId = $order->getPostfinancecheckoutTransactionId();
        for ($i = 0; $i < 5; $i ++) {
            try {
                $transaction = $this->getTransaction($spaceId, $transactionId);
                if (! ($transaction instanceof Transaction) || $transaction->getState() != TransactionState::PENDING) {
                    return $this->createTransactionByOrder($order, $invoice, $chargeFlow, $token);
                }

                $pendingTransaction = new TransactionPending();
                $pendingTransaction->setId($transaction->getId());
                $pendingTransaction->setVersion($transaction->getVersion());
                $this->assembleTransactionDataFromOrder($pendingTransaction, $order, $invoice, $chargeFlow, $token);
                return $this->_apiClient->getService(TransactionApiService::class)->confirm($spaceId,
                    $pendingTransaction);
            } catch (VersioningException $e) {
                // Try to update the transaction again, if a versioning exception occurred.
            }
        }
        throw new VersioningException();
    }

    /**
     * Creates a transaction for the given order.
     *
     * @param Order $order
     * @param Invoice $invoice
     * @param boolean $chargeFlow
     * @param Token $token
     * @return Transaction
     */
    protected function createTransactionByOrder(Order $order, Invoice $invoice, $chargeFlow = false, Token $token = null)
    {
        $createTransaction = new TransactionCreate();
        $createTransaction->setCustomersPresence(CustomersPresence::VIRTUAL_PRESENT);
        $createTransaction->setAutoConfirmationEnabled(false);
        $this->assembleTransactionDataFromOrder($createTransaction, $order, $invoice, $chargeFlow, $token);
        $transaction = $this->_apiClient->getService(TransactionApiService::class)->create(
            $order->getPostfinancecheckoutSpaceId(), $createTransaction);
        $this->updateQuote($this->_quoteRepository->get($order->getQuoteId()), $transaction);
        return $transaction;
    }

    /**
     * Assembles the transaction data from the given order and invoice.
     *
     * @param AbstractTransactionPending $transaction
     * @param Order $order
     * @param Invoice $invoice
     * @param boolean $chargeFlow
     * @param Token $token
     */
    protected function assembleTransactionDataFromOrder(AbstractTransactionPending $transaction, Order $order,
        Invoice $invoice, $chargeFlow = false, Token $token = null)
    {
        $transaction->setCurrency($order->getOrderCurrencyCode());
        $transaction->setBillingAddress($this->convertOrderBillingAddress($order));
        $transaction->setShippingAddress($this->convertOrderShippingAddress($order));
        $transaction->setCustomerEmailAddress(
            $this->getCustomerEmailAddress($order->getCustomerEmail(), $order->getCustomerId()));
        $transaction->setLanguage(
            $this->_scopeConfig->getValue('general/locale/code', ScopeInterface::SCOPE_STORE, $order->getStoreId()));
        $transaction->setLineItems($this->_lineItemService->convertOrderLineItems($order));
        $transaction->setMerchantReference($order->getIncrementId());
        $transaction->setInvoiceMerchantReference($invoice->getIncrementId());
        if (! empty($order->getCustomerId())) {
            $transaction->setCustomerId($order->getCustomerId());
            $transaction->setMetaData(
                $this->collectCustomerMetaData($this->_customerRegistry->retrieve($order->getCustomerId())));
        }
        if ($order->getShippingAddress()) {
            $transaction->setShippingMethod(
                $this->_helper->fixLength(
                    $this->_helper->getFirstLine($order->getShippingAddress()
                        ->getShippingDescription()), 200));
        }
        if ($transaction instanceof TransactionCreate) {
            $transaction->setSpaceViewId(
                $this->_scopeConfig->getValue('postfinancecheckout_payment/general/store_view_id',
                    ScopeInterface::SCOPE_STORE, $order->getStoreId()));
        }
        if ($chargeFlow) {
            $transaction->setAllowedPaymentMethodConfigurations(
                [
                    $order->getPayment()
                        ->getMethodInstance()
                        ->getPaymentMethodConfiguration()
                        ->getConfigurationId()
                ]);
        } else {
            $transaction->setSuccessUrl($this->buildUrl('postfinancecheckout_payment/transaction/success', $order));
            $transaction->setFailedUrl($this->buildUrl('postfinancecheckout_payment/transaction/failure', $order));
        }
        if ($token != null) {
            $transaction->setToken($token->getId());
        }
    }

    /**
     * Builds the URL to an endpoint that is aware of the given order.
     *
     * @param string $route
     * @param Order $order
     * @throws \Exception
     * @return string
     */
    protected function buildUrl($route, Order $order)
    {
        $token = $order->getPostfinancecheckoutSecurityToken();
        if (empty($token)) {
            throw new \Exception('The PostFinance Checkout security token needs to be set on the order to build the URL.');
        }

        return $order->getStore()->getUrl($route,
            [
                '_secure' => true,
                'order_id' => $order->getId(),
                'token' => $token
            ]);
    }

    /**
     * Converts the billing address of the given order.
     *
     * @param Order $order
     * @return \PostFinanceCheckout\Sdk\Model\AddressCreate
     */
    protected function convertOrderBillingAddress(Order $order)
    {
        if (! $order->getBillingAddress()) {
            return null;
        }

        $address = $this->convertAddress($order->getBillingAddress());
        $address->setDateOfBirth($this->getDateOfBirth($order->getCustomerDob(), $order->getCustomerId()));
        $address->setEmailAddress($this->getCustomerEmailAddress($order->getCustomerEmail(), $order->getCustomerId()));
        $address->setGender($this->getGender($order->getCustomerGender(), $order->getCustomerId()));
        return $address;
    }

    /**
     * Converts the shipping address of the given order.
     *
     * @param Order $order
     * @return \PostFinanceCheckout\Sdk\Model\AddressCreate
     */
    protected function convertOrderShippingAddress(Order $order)
    {
        if (! $order->getShippingAddress()) {
            return null;
        }

        $address = $this->convertAddress($order->getShippingAddress());
        $address->setEmailAddress($this->getCustomerEmailAddress($order->getCustomerEmail(), $order->getCustomerId()));
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
        $street = $customerAddress->getStreet();
        $address->setStreet($this->_helper->fixLength(\is_array($street) ? \implode("\n", $street) : $street, 300));
        return $address;
    }

    /**
     * Completes the transaction linked to the given order.
     *
     * @param Order $order
     * @return \PostFinanceCheckout\Sdk\Model\TransactionCompletion
     */
    public function complete(Order $order)
    {
        return $this->_apiClient->getService(TransactionCompletionService::class)->completeOnline(
            $order->getPostfinancecheckoutSpaceId(), $order->getPostfinancecheckoutTransactionId());
    }

    /**
     * Voids the transaction linked to the given order.
     *
     * @param Order $order
     * @return \PostFinanceCheckout\Sdk\Model\TransactionVoid
     */
    public function void(Order $order)
    {
        return $this->_apiClient->getService(TransactionVoidService::class)->voidOnline(
            $order->getPostfinancecheckoutSpaceId(), $order->getPostfinancecheckoutTransactionId());
    }

    /**
     * Marks the delivery indication belonging to the given payment as suitable.
     *
     * @param Order $order
     * @return \PostFinanceCheckout\Sdk\Model\DeliveryIndication
     */
    public function accept(Order $order)
    {
        return $this->_apiClient->getService(DeliveryIndicationService::class)->markAsSuitable(
            $order->getPostfinancecheckoutSpaceId(), $this->getDeliveryIndication($order)
                ->getId());
    }

    /**
     * Marks the delivery indication belonging to the given payment as not suitable.
     *
     * @param Order $order
     * @return \PostFinanceCheckout\Sdk\Model\DeliveryIndication
     */
    public function deny(Order $order)
    {
        return $this->_apiClient->getService(DeliveryIndicationService::class)->markAsNotSuitable(
            $order->getPostfinancecheckoutSpaceId(), $this->getDeliveryIndication($order)
                ->getId());
    }

    /**
     *
     * @param Order $order
     * @return \PostFinanceCheckout\Sdk\Model\DeliveryIndication
     */
    protected function getDeliveryIndication(Order $order)
    {
        $query = new EntityQuery();
        $query->setFilter(
            $this->_helper->createEntityFilter('transaction.id', $order->getPostfinancecheckoutTransactionId()));
        $query->setNumberOfEntities(1);
        return \current(
            $this->_apiClient->getService(DeliveryIndicationService::class)->search(
                $order->getPostfinancecheckoutSpaceId(), $query));
    }

    /**
     * Gets the transaction invoice linked to the given order.
     *
     * @param Order $order
     * @throws \Exception
     * @return \PostFinanceCheckout\Sdk\Model\TransactionInvoice
     */
    public function getTransactionInvoice(Order $order)
    {
        $query = new EntityQuery();
        $query->setNumberOfEntities(1);
        $query->setFilter(
            $this->_helper->createEntityFilter('completion.lineItemVersion.transaction.id',
                $order->getPostfinancecheckoutTransactionId()));
        $result = $this->_apiClient->getService(TransactionInvoiceService::class)->search(
            $order->getPostfinancecheckoutSpaceId(), $query);
        if (! empty($result)) {
            return \current($result);
        } else {
            throw new NoSuchEntityException();
        }
    }

    /**
     * Waits for the transaction to be in one of the given states.
     *
     * @param Order $order
     * @param array $states
     * @param int $maxWaitTime
     * @return boolean
     */
    public function waitForTransactionState(Order $order, array $states, $maxWaitTime = 10)
    {
        $startTime = microtime(true);
        while (true) {
            if (microtime(true) - $startTime >= $maxWaitTime) {
                return false;
            }

            $transactionInfo = $this->_transactionInfoRepository->getByOrderId($order->getId());
            if (in_array($transactionInfo->getState(), $states)) {
                return true;
            }

            sleep(2);
        }
    }
}