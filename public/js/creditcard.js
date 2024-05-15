jQuery(document).ready(function ($) {
    window.ps_cc_bin = '';

    //region Encrypt card method
    /**
     * Encrypts the card and sets the encrypted card in the hidden input and window.ps_cc_* variables for number and cvv
     * @returns {boolean}
     */
    let encryptCard = function () {
        let cardHasChanged = (window.ps_cc_has_changed === true);
        let card, cc_number, cc_cvv;
        //replace trim and remove duplicated spaces from holder name
        let holder_name = jQuery('#rm-pagbank-card-holder-name').val().trim().replace(/\s+/g, ' ');
        try {
            cc_number = cardHasChanged ? jQuery('#rm-pagbank-card-number').val().replace(/\s/g, '') : window.ps_cc_number;
            cc_cvv = cardHasChanged ? jQuery('#rm-pagbank-card-cvc').val().replace(/\s/g, '') : window.ps_cc_cvv;
            card = PagSeguro.encryptCard({
                publicKey: pagseguro_connect_public_key,
                holder: holder_name,
                number: cc_number,
                expMonth: jQuery('#rm-pagbank-card-expiry').val().split('/')[0].replace(/\s/g, ''),
                expYear: '20' + jQuery('#rm-pagbank-card-expiry').val().split('/')[1].slice(-2).replace(/\s/g, ''),
                securityCode: cc_cvv,
            });
        } catch (e) {
            alert("Erro ao criptografar o cartão.\nVerifique se os dados digitados estão corretos.");
            return false;
        }
        if (card.hasErrors) {
            let error_codes = [
            {code: 'INVALID_NUMBER', message: 'Número do cartão inválido'},
            {
                code: 'INVALID_SECURITY_CODE',
                message: 'CVV Inválido. Você deve passar um valor com 3, 4 ou mais dígitos.'
            },
            {
                code: 'INVALID_EXPIRATION_MONTH',
                message: 'Mês de expiração incorreto. Passe um valor entre 1 e 12.'
            },
            {code: 'INVALID_EXPIRATION_YEAR', message: 'Ano de expiração inválido.'},
            {code: 'INVALID_PUBLIC_KEY', message: 'Chave Pública inválida.'},
            {code: 'INVALID_HOLDER', message: 'Nome do titular do cartão inválido.'},
            ]
            //extract error message
            let error = '';
            for (let i = 0; i < card.errors.length; i++) {
                //loop through error codes to find the message
                for (let j = 0; j < error_codes.length; j++) {
                    if (error_codes[j].code === card.errors[i].code) {
                        error += error_codes[j].message + '\n';
                        break;
                    }
                }
            }
            alert('Erro ao criptografar cartão.\n' + error);
            throw new Error('Erro ao criptografar cartão');
            // return false;
        }
        
        jQuery('#rm-pagbank-card-encrypted').val(card.encryptedCard);
        // window.ps_cc_has_changed = false;
        // window.ps_cc_number = cc_number;
        // window.ps_cc_cvv = cc_cvv;
        return true;
    }
    //endregion
    
    /*region extending cards and card types from jqueryPayment to support new types*/
    const typesPagBank = [
    {
        title: 'MasterCard',
        type: 'mastercard',
        pattern: '^(?:5[1-5][0-9]{2}|222[1-9]|22[3-9][0-9]|2[3-6][0-9]{2}|27[01][0-9]|2720)[0-9]{12}$',
        gaps: [4, 8, 12],
        lengths: [16],
        code: {
            name: 'CVC',
            size: 3
        }
    },
    {
        title: 'American Express',
        type: 'amex',
        pattern: '^3([47]\\d*)?$',
        isAmex: true,
        gaps: [4, 10],
        lengths: [15],
        code: {
            name: 'CID',
            size: 4
        }
    },
    {
        title: 'Diners',
        type: 'dinnersclub',
        pattern: '^(3(0[0-5]|095|6|[8-9]))\\d*$',
        gaps: [4, 10],
        lengths: [14, 16, 17, 18, 19],
        code: {
            name: 'CVV',
            size: 3
        }
    },
    {
        title: 'Elo',
        type: 'elo',
        pattern: '^((451416)|(509091)|(636368)|(636297)|(504175)|(438935)|(40117[8-9])|(45763[1-2])|' +
            '(457393)|(431274)|(50990[0-2])|(5099[7-9][0-9])|(50996[4-9])|(509[1-8][0-9][0-9])|' +
            '(5090(0[0-2]|0[4-9]|1[2-9]|[24589][0-9]|3[1-9]|6[0-46-9]|7[0-24-9]))|' +
            '(5067(0[0-24-8]|1[0-24-9]|2[014-9]|3[0-379]|4[0-9]|5[0-3]|6[0-5]|7[0-8]))|' +
            '(6504(0[5-9]|1[0-9]|2[0-9]|3[0-9]))|' +
            '(6504(8[5-9]|9[0-9])|6505(0[0-9]|1[0-9]|2[0-9]|3[0-8]))|' +
            '(6505(4[1-9]|5[0-9]|6[0-9]|7[0-9]|8[0-9]|9[0-8]))|' +
            '(6507(0[0-9]|1[0-8]))|(65072[0-7])|(6509(0[1-9]|1[0-9]|20))|' +
            '(6516(5[2-9]|6[0-9]|7[0-9]))|(6550(0[0-9]|1[0-9]))|' +
            '(6550(2[1-9]|3[0-9]|4[0-9]|5[0-8])))\\d*$',
        gaps: [4, 8, 12],
        lengths: [16],
        code: {
            name: 'CVC',
            size: 3
        }
    },
    {
        title: 'Hipercard',
        type: 'hipercard',
        pattern: '^((606282)|(637095)|(637568)|(637599)|(637609)|(637612))\\d*$',
        gaps: [4, 8, 12],
        lengths: [13, 16],
        code: {
            name: 'CVC',
            size: 3
        }
    },
    {
        title: 'Aura',
        type: 'aura',
        pattern: '^5078\\d*$',
        gaps: [4, 8, 12],
        lengths: [19],
        code: {
            name: 'CVC',
            size: 3
        }
    }];

    //cardsFriendly is used only to allow setCardType to remove incorrect cards, but not to check the card
    const cardsFriendly = [
        {type: 'elo', patterns: [], length: [], cssLength: [], format: '', luhn: false},
        {type: 'aura', patterns: [], length: [], cssLength: [], format: '', luhn: false},
        {type: 'hipercard', patterns: [], length: [], cssLength: [], format: '', luhn: false},
    ]
    // jQuery.extend(jQuery.payment.cardsPagBank, typesPagBank);
    jQuery.extend(jQuery.payment.cards, cardsFriendly);

    // original method
    const originalCardType = jQuery.payment.cardType;

    // Extending the cardType method from jqueryPayment
    jQuery.extend(jQuery.payment, {
        cardType: function (num) {
            // Try to find in our card array
            let cardTypes = getCardTypes(num);
            if (cardTypes.length > 0) {
                return cardTypes[0].type;
            }

            // if we don't find, we return the original result
            return originalCardType.call(this, num);
        }
    });

    /**
     * Gets the credit card types from the card number
     * @param cardNumber
     * @returns {*|*[]}
     */
    let getCardTypes = function (cardNumber) {
        //remove spaces
        cardNumber = cardNumber.replace(/\s/g, '');
        let result = [];

        if (jQuery.isEmptyObject(cardNumber)) {
            return result;
        }

        // if (cardNumber === '') {
        //     return jQuery.extend(true, [], jQuery.payment.cardsPagBank);
        // }

        for (let i = 0; i < typesPagBank.length; i++) {
            let value = typesPagBank[i];
            if (new RegExp(value.pattern).test(cardNumber)) {
                result.push(jQuery.extend(true, {}, value));
            }
        }

        return result.slice(-1);
    }

    /*endregion*/


    jQuery(document.body).on('updated_checkout', function (e) {
        jQuery(document.body).trigger('update_installments');
    });

    //region 3ds authentication
    let isSubmitting = false;
    let checkoutFormIdentifiers = 'form.woocommerce-checkout, form#order_review';
    if (!jQuery(checkoutFormIdentifiers).length) {
        console.debug('PagBank: checkout form not found');
        return true;
    }
    let originalSubmitHandler = () => {};
    // get the original submit handler for checkout or order-pay page
    if (jQuery._data(jQuery(checkoutFormIdentifiers)[0], "events") !== undefined) {
        let formCheckout = jQuery('form.woocommerce-checkout, form#order_review')[0];
        let formEvents = jQuery._data(formCheckout, "events");
        
        if (formEvents && formEvents.submit) {
            originalSubmitHandler = formEvents.submit[0].handler;
        }
    }

    let pagBankSubmitHandler = async function (e) {
        console.debug('PagBank: submit');

        if (isSubmitting) {
            return true;
        }
        e.preventDefault();
        e.stopImmediatePropagation();

        if ((jQuery('#ps-connect-payment-cc').attr('disabled') !== undefined ||
            jQuery('#payment_method_rm-pagbank').is(':checked') === false) &&
            jQuery('input[name="payment_method"]:checked').val() !== 'rm-pagbank-cc') //when using standalone methods 
        {
            isSubmitting = true;
            jQuery(checkoutFormIdentifiers).on('submit', originalSubmitHandler);
            jQuery(checkoutFormIdentifiers).trigger('submit');
            return true;
        }

        if (encryptCard() === false) {
            return false;
        }

        //if 3ds is not enabled, continue
        if ('undefined' === typeof pagseguro_connect_3d_session || !pagseguro_connect_3d_session) {
            isSubmitting = true;
            jQuery(checkoutFormIdentifiers).on('submit', originalSubmitHandler);
            jQuery(checkoutFormIdentifiers).trigger('submit');
            return true;
        }

        //if 3ds authorization is successful, continue
        if ('undefined' !== typeof pagbank3dAuthorized && pagbank3dAuthorized === true) {
            isSubmitting = true;
            jQuery(checkoutFormIdentifiers).on('submit', originalSubmitHandler);
            jQuery(checkoutFormIdentifiers).trigger('submit');
            return true;
        }

        //region 3ds authentication method
        PagSeguro.setUp({
            session: pagseguro_connect_3d_session,
            env: pagseguro_connect_environment,
        });

        var checkoutFormData = jQuery(this).serializeArray();
        // Convert the form data to an object
        var checkoutFormDataObj = {};
        jQuery.each(checkoutFormData, function (i, field) {
            checkoutFormDataObj[field.name] = field.value;
        });
        let cartTotal;
        let selectedInstallments = jQuery('#rm-pagbank-card-installments').val();
        cartTotal = window.ps_cc_installments.find((installment, idx, installments)=> installments[idx].installments == selectedInstallments).total_amount
        cartTotal = parseInt(parseFloat(cartTotal.toString()).toFixed(2) * 100);

        let expiryVal = jQuery('#rm-pagbank-card-expiry').val();

        let request = {
            data: {
                paymentMethod: {
                    type: 'CREDIT_CARD',
                    installments: jQuery('#rm-pagbank-card-installments').val()*1,
                    card: {
                        number: window.ps_cc_number || jQuery('#rm-pagbank-card-number').val().replace(/\s/g, ''),
                        expMonth: jQuery('#rm-pagbank-card-expiry').val().split('/')[0].replace(/\s/g, ''),
                        expYear: expiryVal.includes('/') ? '20' + expiryVal.split('/')[1].slice(-2).replace(/\s/g, '') : '',
                        holder: {
                            name: jQuery('#rm-pagbank-card-holder-name').val().trim().replace(/\s+/g, ' '),
                        }
                    }
                },
                dataOnly: false
            }
        }
        
        let orderData = typeof pagBankOrderDetails !== 'undefined'
            ? pagBankOrderDetails.data //if order-pay page
            : { //if checkout page get from form fields
                customer: {
                    name: checkoutFormDataObj['billing_first_name'] + ' ' + checkoutFormDataObj['billing_last_name'],
                    email: checkoutFormDataObj['billing_email'],
                    phones: [
                        {
                            country: '55',
                            area: checkoutFormDataObj['billing_phone'].replace(/\D/g, '').substring(0, 2),
                            number: checkoutFormDataObj['billing_phone'].replace(/\D/g, '').substring(2),
                            type: 'MOBILE'
                    }]
                },
                amount: {
                    value: cartTotal,
                    currency: 'BRL'
                },
                billingAddress: {
                    street: checkoutFormDataObj['billing_address_1'].replace(/\s+/g, ' '),
                    number: checkoutFormDataObj['billing_number'].replace(/\s+/g, ' '),
                    complement: checkoutFormDataObj['billing_neighborhood'].replace(/\s+/g, ' '),
                    regionCode: checkoutFormDataObj['billing_state'].replace(/\s+/g, ' '),
                    country: 'BRA',
                    city: checkoutFormDataObj['billing_city'].replace(/\s+/g, ' '),
                    postalCode: checkoutFormDataObj['billing_postcode'].replace(/\D+/g, '')
                }
        };

        request.data = {
            ...request.data,
            ...orderData
        };
        
        console.debug('PagBank 3DS Request Amount: ' + request.data.amount.value);
        //disable place order button
        jQuery('.woocommerce-checkout-payment, .woocommerce-checkout-review-order-table, form#order_review').block({
            message: 'Autenticação 3D em andamento',
            overlayCSS: {
                background: '#fff',
                opacity: 0.6
            },
            css: {border: 0}
        });

        PagSeguro.authenticate3DS(request).then(result => {
            switch (result.status) {
                case 'CHANGE_PAYMENT_METHOD':
                    // The user must change the payment method used
                    alert('Pagamento negado pelo PagBank. Escolha outro método de pagamento ou cartão.');
                    jQuery('.woocommerce-checkout-payment, .woocommerce-checkout-review-order-table').unblock();
                    return false;
                case 'AUTH_FLOW_COMPLETED':
                    //O processo de autenticação foi realizado com sucesso, dessa forma foi gerado um id do 3DS que poderá ter o resultado igual a Autenticado ou Não Autenticado.
                    if (result.authenticationStatus === 'AUTHENTICATED') {
                        //O cliente foi autenticado com sucesso, dessa forma o pagamento foi autorizado.
                        jQuery('#rm-pagbank-card-3d').val(result.id);
                        console.debug('PagBank: 3DS Autenticado ou Sem desafio');
                        pagbank3dAuthorized = true;
                        jQuery('.woocommerce-checkout-payment, .woocommerce-checkout-review-order-table').unblock();
                        isSubmitting = true;
                        jQuery('form.woocommerce-checkout, form#order_review').on('submit', originalSubmitHandler);
                        jQuery('form.woocommerce-checkout, form#order_review').trigger('submit');
                        return true;
                    }
                    alert('Autenticação 3D falhou. Tente novamente.');
                    pagbank3dAuthorized = false;
                    jQuery('.woocommerce-checkout-payment, .woocommerce-checkout-review-order-table').unblock();
                    return false;
                case 'AUTH_NOT_SUPPORTED':
                    //A autenticação 3DS não ocorreu, isso pode ter ocorrido por falhas na comunicação com emissor ou bandeira, ou algum controle que não possibilitou a geração do 3DS id, essa transação não terá um retorno de status de autenticação e seguirá como uma transação sem 3DS.
                    //O cliente pode seguir adiante sem 3Ds (exceto débito)
                    if (pagseguro_connect_cc_3ds_allow_continue === 'yes') {
                        console.debug('PagBank: 3DS não suportado pelo cartão. Continuando sem 3DS.');
                        pagbank3dAuthorized = true;
                        jQuery('.woocommerce-checkout-payment, .woocommerce-checkout-review-order-table').unblock();

                        isSubmitting = true;
                        jQuery('form.woocommerce-checkout, form#order_review').on('submit', originalSubmitHandler);
                        jQuery('form.woocommerce-checkout, form#order_review').trigger('submit');
                        return true;
                    }
                    alert('Seu cartão não suporta autenticação 3D. Escolha outro método de pagamento ou cartão.');
                    jQuery('.woocommerce-checkout-payment, .woocommerce-checkout-review-order-table').unblock();
                    return false;
                case 'REQUIRE_CHALLENGE':
                    //É um status intermediário que é retornando em casos que o banco emissor solicita desafios, é importante para identificar que o desafio deve ser exibido.
                    console.debug('PagBank: REQUIRE_CHALLENGE - O desafio está sendo exibido pelo banco.');
                    break;
            }
        }).catch((err) => {
            if (err instanceof PagSeguro.PagSeguroError ) {
                console.error(err);
                console.debug('PagBank: ' + err.detail);
                let errMsgs = err.detail.errorMessages.map(error => pagBankParseErrorMessage(error)).join('\n');
                alert('Falha na requisição de autenticação 3D.\n' + errMsgs);
                jQuery('.woocommerce-checkout-payment, .woocommerce-checkout-review-order-table').unblock();
                return false;
            }
        })
        //endregion

        return false;
    }
    
    jQuery(checkoutFormIdentifiers).off('submit');
    jQuery(checkoutFormIdentifiers).on('submit', pagBankSubmitHandler);
    
    
    //endregion

    jQuery(checkoutFormIdentifiers).off('checkout_place_order').on('checkout_place_order', async function (e) {
        console.debug('PagBank: checkout_place_order');
        
        //if not pagseguro connect or not credit card, return
        if ((jQuery('#ps-connect-payment-cc').attr('disabled') !== undefined ||
            jQuery('#payment_method_rm-pagbank').is(':checked') === false) &&
            jQuery('input[name="payment_method"]:checked').val() !== 'rm-pagbank-cc') //when using standalone methods 
        {
            return true;
        }
        

         /*region Encrypt card and obfuscates before submit*/
        if (encryptCard() === false) {
            return;
        }

        //obfuscates cvv
        // saves in window the card number and cvv, so we can reuse it if the first attempt fails for some reason
        // pagbank requires a new encryption for each attempt, and we don't want to ask the customer to type again
        if (window.ps_cc_has_changed !== false) {
            let card_number = jQuery('#rm-pagbank-card-number').val();
            window.ps_cc_number = card_number.replace(/\s/g, '');
            window.ps_cc_cvv = jQuery('#rm-pagbank-card-cvc').val().replace(/\s/g, '');
    
            jQuery('#rm-pagbank-card-cvc').val('***');
            //obfuscates card number between 8th and last 4 digits
            let obfuscated_card_number = '';
            for (let i = 0; i < card_number.length; i++) {
                if (i > 6 && i < card_number.length - 4)
                    obfuscated_card_number += '*';
                else
                    obfuscated_card_number += card_number[i];
            }
            jQuery('#rm-pagbank-card-number').val(obfuscated_card_number);
            window.ps_cc_has_changed = false;
        }
        /*endregion*/
        
        isSubmitting = false;
    });

    
});

// jQuery(document.body).on('init_checkout', ()=>{
    jQuery(document).on('keyup change paste', '#rm-pagbank-card-number', (e)=>{
        window.ps_cc_has_changed = true;
        if ('undefined' !== typeof pagseguro_connect_3d_session) {
            pagbank3dAuthorized = false;
        }
        let cardNumber = jQuery(e.target).val();
        let ccBin = cardNumber.replace(/\s/g, '').substring(0, 6);
        if (ccBin !== window.ps_cc_bin && ccBin.length === 6) {
            window.ps_cc_bin = ccBin;
            jQuery(document.body).trigger('update_installments');
        }
    });
    jQuery(document).on('keyup change paste', '#rm-pagbank-card-cvc', (e)=>{
        window.ps_cc_has_changed = true;
    });
    jQuery(document).on('input change paste', '#rm-pagbank-card-holder-name', (e)=>{
        jQuery(e.target).val(jQuery(e.target).val().toUpperCase());
    });
// });

    jQuery(document.body).on('update_installments', ()=> {
        //if success, update the installments select with the response
        //if error, show error message
        let ccBin = typeof window.ps_cc_bin === 'undefined' || window.ps_cc_bin.replace(/[^0-9]/g, '').length < 6 ? '555566' : window.ps_cc_bin;
        let total = jQuery('.order-total bdi, .product-total bdi').html();
        //extract amount from total, removing html elements
        total = total.replace(/<[^>]*>?/gm, '');
        //remove ,
        total = total.replace(/,/g, '');
        //replace , with .
        total = total.replace(/\./g, ',');
        //remove non numbers and . ,
        total = total.replace(/[^0-9,]/g, '');
    
    
        //convert to cents
        let orderTotal = parseFloat(total).toFixed(2) * 100;
        if (orderTotal < 100) {
            return;
        }
        // let maxInstallments = jQuery('#rm-pagbank-card-installments').attr('max_installments');
        let url = ajax_object.ajax_url;
        let encryptedOrderId = typeof pagBankOrderDetails !== 'undefined' ?
            pagBankOrderDetails?.encryptedOrderId : null;
        jQuery.ajax({
            url: url,
            method: 'POST',
            data: {
                cc_bin: ccBin,
                nonce: rm_pagbank_nonce,
                action: 'ps_get_installments',
                order_id: encryptedOrderId,
            },
            success: (response)=>{
                let select = jQuery('#rm-pagbank-card-installments');
                select.empty();
                for (let i = 0; i < response.length; i++) {
                    let option = jQuery('<option></option>');
                    option.attr('value', response[i].installments);
                    let text = response[i].installments + 'x de R$ ' + response[i].installment_amount;
                    let additional_text = ' (sem juros)';
                    if (response[i].interest_free === false)
                        additional_text = ' (Total R$ ' + response[i].total_amount + ')';
    
                    option.text(text + additional_text);
                    select.append(option);
                }
                window.ps_cc_installments = response;
            },
            error: (response)=>{
                alert('Erro ao calcular parcelas. Verifique os dados do cartão e tente novamente.');
                console.info('Lojista: Verifique os logs em WooCommerce > Status > Logs ' +
                    'para ver os possíveis problemas na obtenção das parcelas. Note que cartões de teste falharão ' +
                    'na maioria dos casos.');
            }
        });
    });
