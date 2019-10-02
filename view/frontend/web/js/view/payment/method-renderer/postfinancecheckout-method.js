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
	'Magento_Checkout/js/view/payment/default',
	'Magento_Checkout/js/model/full-screen-loader',
	'Magento_Checkout/js/model/payment/method-list',
	'mage/url',
	'Magento_Checkout/js/model/quote',
	'Magento_Checkout/js/model/payment/additional-validators',
	'PostFinanceCheckout_Payment/js/model/checkout-handler'
], function(
	$,
	Component,
	fullScreenLoader,
	methodList,
	urlBuilder,
	quote,
	additionalValidators,
	checkoutHandler
){
	'use strict';
	return Component.extend({
		defaults: {
			template: 'PostFinanceCheckout_Payment/payment/form'
		},
		redirectAfterPlaceOrder: false,
		loadingIframe: false,
		checkoutHandler: null,
		
		/**
		 * @override
		 */
		initialize: function(){
			this._super();
			this.checkoutHandler = checkoutHandler(this.getFormId(), this.isActive.bind(this), this.createHandler.bind(this));
		},
		
		getFormId: function(){
			return this.getCode() + '-payment-form';
		},
		
		getConfigurationId: function(){
			return window.checkoutConfig.payment[this.getCode()].configurationId;
		},
		
		isActive: function(){
			return quote.paymentMethod() ? quote.paymentMethod().method == this.getCode() : null;
		},
		
		isShowDescription: function(){
			return window.checkoutConfig.payment[this.getCode()].showDescription;
		},
		
		getDescription: function(){
			return window.checkoutConfig.payment[this.getCode()].description;
		},
		
		isShowImage: function(){
			return window.checkoutConfig.payment[this.getCode()].showImage;
		},
		
		getImageUrl: function(){
			return window.checkoutConfig.payment[this.getCode()].imageUrl;
		},
		
		createHandler: function(){
			if (this.handler) {
				this.checkoutHandler.selectPaymentMethod();
			} else if (typeof window.IframeCheckoutHandler != 'undefined' && this.isActive() && this.checkoutHandler.validateAddresses()) {
				if (this.checkoutHandler.canReplacePrimaryAction()) {
					window.IframeCheckoutHandler.configure('replacePrimaryAction', true);
				}
				
				this.loadingIframe = true;
				fullScreenLoader.startLoader();
				this.handler = window.IframeCheckoutHandler(this.getConfigurationId());
				this.handler.setResetPrimaryActionCallback(function(){
					this.checkoutHandler.resetPrimaryAction();
				}.bind(this));
				this.handler.setReplacePrimaryActionCallback(function(label){
					this.checkoutHandler.replacePrimaryAction(label);
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
				}).bind(this), (function(){
					fullScreenLoader.stopLoader();
					this.loadingIframe = false;
				}).bind(this));
			}
		},
		
		getSubmitButton: function(){
			return $('#' + this.getFormId()).parents('.payment-method-content').find('button.checkout');
		},
		
		selectPaymentMethod: function(){
			this.checkoutHandler.updateAddresses(this._super.bind(this));
			return true;
		},
		
		validateIframe: function(){
			if (this.loadingIframe) {
				return;
			}
			if (this.handler) {
				if (this.checkoutHandler.isPrimaryActionReplaced()) {
					this.handler.trigger();
				} else {
					this.handler.validate();
				}
			} else {
				this.placeOrder();
			}
		},
		
        placeOrder: function (data, event) {
            var self = this;

            if (event) {
                event.preventDefault();
            }

            if (this.validate() && additionalValidators.validate()) {
                this.isPlaceOrderActionAllowed(false);

                this.getPlaceOrderDeferredObject()
                    .fail(
                        function (response) {
                        	var error = null;
                        	try {
                                error = JSON.parse(response.responseText);
                            } catch (exception) {
                            }
                        	if (typeof error == 'object' && error.message == 'postfinancecheckout_checkout_failure') {
                        		window.location.replace(urlBuilder.build("postfinancecheckout_payment/checkout/failure"));
                        	} else {
                        		self.isPlaceOrderActionAllowed(true);
                        	}
                        }
                    ).done(
                        function () {
                            self.afterPlaceOrder();
                        }
                    );

                return true;
            }

            return false;
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