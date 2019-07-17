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
namespace PostFinanceCheckout\Payment\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use PostFinanceCheckout\Payment\Model\Service\WebhookService;

/**
 * Observer to install webhooks.
 */
class InstallWebhook implements ObserverInterface
{

    /**
     *
     * @var WebhookService
     */
    private $webhookService;

    /**
     *
     * @param WebhookService $webhookService
     */
    public function __construct(WebhookService $webhookService)
    {
        $this->webhookService = $webhookService;
    }

    public function execute(Observer $observer)
    {
        $this->webhookService->install();
    }
}