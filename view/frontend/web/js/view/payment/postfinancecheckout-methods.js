/**
 * PostFinance Checkout Magento 2
 *
 * This Magento 2 extension enables to process payments with PostFinance Checkout (https://www.postfinance.ch/checkout/).
 *
 * @package PostFinanceCheckout_Payment
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */
define([
	'jquery',
	'uiComponent',
	'Magento_Checkout/js/model/payment/renderer-list'
], function(
	$,
	Component,
	rendererList
) {
	'use strict';
	
	// Loads the PostFinance Checkout Javascript File
	if (window.checkoutConfig.postfinancecheckout.javascriptUrl) {
		$.getScript(window.checkoutConfig.postfinancecheckout.javascriptUrl);
	}
	
	// Registers the PostFinance Checkout payment methods
	$.each(window.checkoutConfig.payment, function(code){
		if (code.indexOf('postfinancecheckout_payment_') === 0) {
			rendererList.push({
			    type: code,
			    component: 'PostFinanceCheckout_Payment/js/view/payment/method-renderer/postfinancecheckout-method'
			});
		}
	});
	
	return Component.extend({});
});