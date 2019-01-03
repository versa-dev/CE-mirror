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

use PostFinanceCheckout\Payment\Model\Service\ManualTaskService;
use PostFinanceCheckout\Payment\Model\Webhook\ListenerInterface;
use PostFinanceCheckout\Payment\Model\Webhook\Request;

/**
 * Webhook listener to handle manual tasks.
 */
class ManualTaskListener implements ListenerInterface
{

    /**
     *
     * @var ManualTaskService
     */
    private $manualTaskService;

    /**
     *
     * @param ManualTaskService $manualTaskService
     */
    public function __construct(ManualTaskService $manualTaskService)
    {
        $this->manualTaskService = $manualTaskService;
    }

    public function execute(Request $request)
    {
        $this->manualTaskService->update();
    }
}