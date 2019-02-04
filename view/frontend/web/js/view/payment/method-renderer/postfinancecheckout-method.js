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
	'jquery',
	'Magento_Checkout/js/view/payment/default',
	'rjsResolver',
	'Magento_Checkout/js/model/full-screen-loader',
	'Magento_Checkout/js/model/payment/method-list',
	'mage/url'
], function(
	$,
	Component,
	resolver,
	fullScreenLoader,
	methodList,
	urlBuilder
){
	'use strict';
	return Component.extend({
		defaults: {
    		template: 'PostFinanceCheckout_Payment/payment/form'
		},
		redirectAfterPlaceOrder: false,
		submitDisabled: false,
		
		/**
		 * @override
		 */
		initialize: function(){
			this._super();
			
			resolver((function(){
				if (this.isChecked() == this.getCode()) {
					this.createHandler();
				}
			}).bind(this));
			
			/*methodList.subscribe($.proxy(function(methods){
				if (methods) {
					this.handler = null;
					$('#' + this.getFormId()).find('iframe').remove();
					this.createHandler();
				}
			}, this));*/
		},
        
		getFormId: function(){
			return this.getCode() + '-payment-form';
		},
		
		getConfigurationId: function(){
			return window.checkoutConfig.payment[this.getCode()].configurationId;
		},
		
		createHandler: function(){
			if (this.handler) {
				$('button.checkout').prop('disabled', this.submitDisabled);
			} else if (typeof window.IframeCheckoutHandler != 'undefined') {
				fullScreenLoader.startLoader();
				this.handler = window.IframeCheckoutHandler(this.getConfigurationId());
				this.handler.setEnableSubmitCallback(function(){
					$('button.checkout').prop('disabled', false);
					this.submitDisabled = false;
				}.bind(this));
				this.handler.setDisableSubmitCallback(function(){
					$('button.checkout').prop('disabled', true);
					this.submitDisabled = true;
				}.bind(this));
				this.handler.create(this.getFormId(), (function(validationResult){
					if (validationResult.success) {
						this.placeOrder();
					} else {
						$('html, body').animate({ scrollTop: $('#' + this.getCode()).offset().top - 20 });
						if (validationResult.errors) {
							for (var i = 0; i < validationResult.errors.length; i++) {
								this.messageContainer.addErrorMessage({
									message: this.stripHtml(validationResult.errors[i])
								});
							}
						}
					}
				}).bind(this), function(){
					fullScreenLoader.stopLoader();
				});
			}
		},
		
		selectPaymentMethod: function(){
			var result = this._super();
			this.createHandler();
			return result;
		},
		
        validateIframe: function(){
        	if (this.handler) {
        		this.handler.validate();
        	} else {
        		this.placeOrder();
        	}
        },
        
        afterPlaceOrder: function(){
        	if (this.handler) {
        		this.handler.submit();
        	} else {
        		fullScreenLoader.startLoader();
        		if (window.checkoutConfig.postfinancecheckout.paymentPageUrl) {
        			window.location.replace(window.checkoutConfig.postfinancecheckout.paymentPageUrl + "&paymentMethodConfigurationId=" + this.getConfigurationId());
        		} else {
        			window.location.replace(urlBuilder.build("postfinancecheckout_payment/checkout/failure"));
        		}
        	}
        },
        
        stripHtml: function(input){
        	return $('<div>' + input + '</div>').text();
        }
	});
});