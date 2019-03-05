/**
 * PostFinance Checkout Magento 2
 *
 * This Magento 2 extension enables to process payments with PostFinance Checkout (https://www.postfinance.ch/).
 *
 * @package PostFinanceCheckout_Payment
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */
define([
	'underscore',
	'Magento_Checkout/js/model/quote',
	'Magento_Checkout/js/action/set-shipping-information',
	'PostFinanceCheckout_Payment/js/model/default-checkout'
], function(
	_,
	quote,
	setShippingInformationAction,
	defaultCheckoutAdapter,
	pluginCheckoutAdapter
){
	'use strict';
	return function(isActive, loadPaymentForm){
		var billingAddressCache = {},
			shippingAddressCache = {},
			hasAddressChanged = false,
			addressTimeout,
			pluginCheckoutAdapter;
		
		function getCheckoutAdapter(){
			if (pluginCheckoutAdapter) {
				return pluginCheckoutAdapter;
			} else {
				return defaultCheckoutAdapter;
			}
		}
		
		function covertToCacheableAddress(address){
			var cacheableAddress = {};
			_.each(address, function(value, key){
				if (!_.isFunction(value)) {
					cacheableAddress[key] = value;
				}
			});
			return cacheableAddress;
		}
		
		function hasAddressesChanged(){
			return !_.isEqual(shippingAddressCache, covertToCacheableAddress(getCheckoutAdapter().getShippingAddress()))
				|| !_.isEqual(billingAddressCache, covertToCacheableAddress(quote.billingAddress()));
		}
		
		function storeShippingAddress(){
			return getCheckoutAdapter().storeShippingAddress();
		}
		
		function validateAddresses(){
			return getCheckoutAdapter().validateAddresses();
		}
		
		function updateAddresses() {
			storeShippingAddress();
			setShippingInformationAction().done(function(){
				loadPaymentForm();
			});
		}
		
		function checkAddresses(){
			if (isActive() && validateAddresses()) {
				if (hasAddressesChanged()) {
					hasAddressChanged = true;
					clearTimeout(addressTimeout);
					billingAddressCache = covertToCacheableAddress(quote.billingAddress());
					shippingAddressCache = covertToCacheableAddress(getCheckoutAdapter().getShippingAddress());
				} else if (hasAddressChanged) {
					hasAddressChanged = false;
					clearTimeout(addressTimeout);
					addressTimeout = setTimeout(function(){
						updateAddresses();
					}, 500);
				}
			}
			setTimeout(checkAddresses, 100);
		}
		
		if (require.specified('postfinancecheckout_checkout_adapter')) {
			require(['postfinancecheckout_checkout_adapter'], function(adapter){
				pluginCheckoutAdapter = adapter;
				checkAddresses();
			});
		} else {
			checkAddresses();
		}
		
		return {
			hasAddressesChanged: hasAddressesChanged,
			validateAddresses: validateAddresses,
			updateAddresses: updateAddresses
		};
	};
});