/**
 * PagBank Payment Magento Module.
 *
 * Copyright © 2023 PagBank. All rights reserved.
 *
 * @author    Bruno Elisei <brunoelisei@o2ti.com>
 * @license   See LICENSE for license details.
 */

/* @api */
define([
    'uiElement',
    'pagBankCardJs',
    'jquery',
    'mage/translate',
    'Magento_Ui/js/modal/confirm',
    'Magento_Checkout/js/model/full-screen-loader',
    'PagBank_PaymentMagento/js/action/checkout/three-d-secure-session',
    'PagBank_PaymentMagento/js/model/pagbank-pre-order-data'
], function (
    Component,
    _pagBankCardJs,
    $,
    $t,
    confirmation,
    fullScreenLoader,
    ThreeDS,
    PagBankPreOrderData
) {
    'use strict';

    return Component.extend({

        /**
         * Init component
         * @returns {Void}
         */
        initialize() {
            var self = this;

            self._super();
        },

        /**
         * Get PagBank Place
         * @param {Object} context
         * @param {Function} callback
         * @param {Function} errorCallback
         *
         * @returns {Void}
         */
        getPagBankPlace(context, callback, errorCallback) {
            var self = this,
                encrypted;

            fullScreenLoader.startLoader();

            encrypted = self.getPagBankTokenize(context);

            if (!encrypted) {
                errorCallback();
            }

            if (encrypted) {
                if (context.baseDataForPaymentForm.hasThreeDs()) {
                    ThreeDS()
                        .then((session) => {
                            var sessionId = session.session_id,
                                cardPayData = {
                                    type: 'CREDIT_CARD',
                                    installments: context.creditCardInstallment(),
                                    card: {
                                        number: context.creditCardNumber().replace(/\s/g,''),
                                        expMonth: context.creditCardExpMonth(),
                                        expYear: context.creditCardExpYear(),
                                        holder: {
                                            name: context.creditCardHolderName()
                                        }
                                    }
                                },
                                data = PagBankPreOrderData.getPreOrderData(cardPayData);

                                return self.sendDataForThreeDS(sessionId, data, context);
                        }).then((result) => {
                            if (result) {
                                callback();
                            } else {
                                errorCallback();
                            }
                        }).catch(() => {
                            errorCallback();
                        });
                } else {
                    callback();
                }
            }
        },

        /**
         * Send Data for 3ds
         * @param {String} sessionId
         * @param {Object} data
         * @param {Object} context
         */
        sendDataForThreeDS(sessionId, data, context) {
            var self = this,
                deferred = $.Deferred();

            // eslint-disable-next-line no-undef
            PagSeguro.setUp({
                session: sessionId,
                env: context.baseDataForPaymentForm.getEnv()
            });

            // eslint-disable-next-line no-undef
            PagSeguro.authenticate3DS(data).then(result => {
                var authId = result.id,
                    authStatus = result.status,
                    authenticationStatus = result.authenticationStatus,
                    isComplet = self.completOrderAuth(context, authStatus, authenticationStatus);

                self.setThreeDsData(context, authId, authStatus, authenticationStatus);
                deferred.resolve(isComplet);

            }).catch((err) => {
                // eslint-disable-next-line no-undef
                if (err instanceof PagSeguro.PagSeguroError) {
                    console.log(err);
                    self.showError(err.detail.message);
                    deferred.resolve(false);
                } else {
                    deferred.reject(err);
                }
            });

            return deferred.promise();
        },

        /**
         * Set 3ds Data
         * @param {Object} context
         * @param {String} authId
         * @param {String} authStatus
         * @param {String} authenticationStatus
         */
        setThreeDsData(context, authId, authStatus, authenticationStatus) {
            context.threeDSecureSession(authId);
            context.threeDSecureAuth(authStatus);
            context.threeDSecureAuthStatus(authenticationStatus);
        },

        /**
         * Get PagBank Tokenize
         * @param {Object} context
         * @returns {Boolean}
         */
        getPagBankTokenize(context) {
            var cardPs,
                cardTokenized,
                cardHasError,
                cardData = {
                    publicKey: context.baseDataForPaymentForm.getPublicKey(),
                    holder: context.creditCardHolderName(),
                    number: context.creditCardNumber().replace(/\s/g,''),
                    expMonth: context.creditCardExpMonth(),
                    expYear: context.creditCardExpYear(),
                    securityCode: context.creditCardVerificationNumber()
                };

            // eslint-disable-next-line no-undef
            cardPs = PagSeguro.encryptCard(cardData);
            cardTokenized = cardPs.encryptedCard;
            cardHasError = cardPs.hasErrors;

            if (cardHasError) {
                this.showError(
                    $t('Unable to complete the payment with this card, please verify the information and try again.')
                );
                return false;
            }

            context.creditCardNumberToken(cardTokenized);

            return true;
        },

        /**
         * Show error message
         * @param {String} errorMessage
         */
        showError(errorMessage) {

            confirmation({
                title: $t('Error while processing Payment'),
                content: errorMessage,
                buttons: [{
                    text: $t('Ok'),
                    class: 'action-primary action-accept',
                    click: function (event) {
                        this.closeModal(event, true);
                    }
                }]
            });

            fullScreenLoader.stopLoader(true);
        },

        /**
         * Complet Order Auth
         * @param {String} Context
         * @param {String} authStatus
         * @param {String} authenticationStatus
         * @returns {Boolean}
         */
        completOrderAuth(context, authStatus, authenticationStatus) {
            var self = this,
                reject = context.baseDataForPaymentForm.hasThreeDsRejectNotAuth();

            if (authStatus === 'AUTH_NOT_SUPPORTED') {
                return true;
            }

            if (authStatus === 'CHANGE_PAYMENT_METHOD') {
                self.showError($t('Change Payment Method'));
                return false;
            }

            if (authStatus === 'AUTH_FLOW_COMPLETED' && reject) {
                if (authenticationStatus === 'NOT_AUTHENTICATED') {
                    self.showError($t('Change Payment Method'));
                    return false;
                }
                return authenticationStatus === 'AUTHENTICATED';
            }
            return true;
        }
    });
});
