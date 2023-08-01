/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
define([
    'jquery',
    'pagBankCardJs',
    'Magento_Ui/js/modal/modal',
    'mage/translate'
], function ($, _pagBankCardJs, modalToggle) {
    'use strict';

    return function (config, deleteButton) {
        var form = $("#" + config.formId),
            cardPs,
            cardTokenized,
            cardHasError,
            cardError,
            cardId = config.cardId,
            cardData = {
                publicKey: $('input[name="pagbank_public_key"]').val(),
                holder: $('#' + cardId + '_cc_owner').val(),
                number:  $('#' + cardId + '_cc_number').val().replace(/\s/g,''),
                expMonth: $('#' + cardId + '_expiration').val(),
                expYear: $('#' + cardId + '_expiration_yr').val()
            };

        config.buttons = [
            {
                text: $.mage.__('Cancel'),
                class: 'action secondary cancel'
            },
            {
                text: $.mage.__('Submit'),
                class: 'action primary',

                /**
                 * Default action on button click
                 */
                click: function (event) { //eslint-disable-line no-unused-vars
                    console.log(cardData);

                    // eslint-disable-next-line no-undef
                    cardPs = PagSeguro.encryptCard(cardData);
                    cardTokenized = cardPs.encryptedCard;
                    cardHasError = cardPs.hasErrors;
                    if (cardHasError) {
                        cardError = cardPs.errors;
                        console.log(cardError);
                    }

                    if (cardTokenized) {
                        console.log(cardTokenized);
                        console.log(form.attr('action'));
                        console.log(form.serialize());

                        $.ajax({
                            url: form.attr('action'),
                            data: form.serialize(),
                            type: 'post',
                            dataType: 'json',

                            /** Show loader before send */
                            beforeSend: function () {
                                $('body').trigger('processStart');
                            }
                        }).always(function () {
                            $('body').trigger('processStop');
                        });
                    }
                }
            }
        ];

        modalToggle(config, deleteButton);
    };
});
