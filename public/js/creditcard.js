/**
 * Parse BR money text (e.g. "R$ 1.234,56") to integer cents.
 * parseFloat("60,67") === 60 — never use parseFloat on BR decimals directly.
 * Defined on window: used inside document.ready and by handlers outside it.
 */
window.rmPagbankParseBrlToCents = function (raw) {
    if (raw === null || raw === undefined) {
        return 0;
    }
    if (typeof raw === 'number' && Number.isFinite(raw)) {
        return Math.round(raw);
    }
    let s = String(raw).replace(/<[^>]*>/g, '').trim();
    s = s.replace(/[^\d.,]/g, '');
    if (!s) {
        return 0;
    }
    if (s.indexOf(',') >= 0 && s.indexOf('.') >= 0) {
        // 1.234,56 → 1234.56
        s = s.replace(/\./g, '').replace(',', '.');
    } else if (s.indexOf(',') >= 0) {
        // 60,67 → 60.67
        s = s.replace(',', '.');
    }
    const n = parseFloat(s);
    if (!Number.isFinite(n)) {
        return 0;
    }
    return Math.round(n * 100);
};

/**
 * Map PAN → Vindi payment_company_code.
 * Elo/Hipercard BINs overlap Visa/MC ranges — check BR brands before Visa.
 * Codes: mastercard, visa, american_express, diners_club, elo, hipercard, jcb.
 */
window.rmPagbankDetectVindiPaymentCompanyCode = function (cardNumber) {
    const number = String(cardNumber || '').replace(/\D/g, '');
    if (!number) {
        return 'mastercard';
    }
    const elo = new RegExp(
        '^((451416)|(509091)|(636368)|(636297)|(504175)|(438935)|(40117[8-9])|(45763[1-2])|' +
        '(457393)|(431274)|(50990[0-2])|(5099[7-9][0-9])|(50996[4-9])|(509[1-8][0-9][0-9])|' +
        '(5090(0[0-2]|0[4-9]|1[2-9]|[24589][0-9]|3[1-9]|6[0-46-9]|7[0-24-9]))|' +
        '(5067(0[0-24-8]|1[0-24-9]|2[014-9]|3[0-379]|4[0-9]|5[0-3]|6[0-5]|7[0-8]))|' +
        '(6504(0[5-9]|1[0-9]|2[0-9]|3[0-9]))|' +
        '(6504(8[5-9]|9[0-9])|6505(0[0-9]|1[0-9]|2[0-9]|3[0-8]))|' +
        '(6505(4[1-9]|5[0-9]|6[0-9]|7[0-9]|8[0-9]|9[0-8]))|' +
        '(6507(0[0-9]|1[0-8]))|(65072[0-7])|(6509(0[1-9]|1[0-9]|20))|' +
        '(6516(5[2-9]|6[0-9]|7[0-9]))|(6550(0[0-9]|1[0-9]))|' +
        '(6550(2[1-9]|3[0-9]|4[0-9]|5[0-8])))\\d*$'
    );
    const hipercard = /^((606282)|(637095)|(637568)|(637599)|(637609)|(637612))\d*$/;
    const amex = /^3[47]\d*$/;
    const diners = /^(3(0[0-5]|095|6|[8-9]))\d*$/;
    const jcb = /^(?:2131|1800|35\d{3})\d*$/;
    const mastercard = /^(?:5[1-5][0-9]{2}|222[1-9]|22[3-9][0-9]|2[3-6][0-9]{2}|27[01][0-9]|2720)\d*$/;
    const visa = /^4\d*$/;

    // Order matters: Elo/Hipercard before Visa/Mastercard.
    if (elo.test(number)) {
        return 'elo';
    }
    if (hipercard.test(number)) {
        return 'hipercard';
    }
    if (amex.test(number)) {
        return 'american_express';
    }
    if (diners.test(number)) {
        return 'diners_club';
    }
    if (jcb.test(number)) {
        return 'jcb';
    }
    if (mastercard.test(number)) {
        return 'mastercard';
    }
    if (visa.test(number)) {
        return 'visa';
    }
    return 'mastercard';
};

window.rmPagbankResolveCheckoutAmountCents = function (selectedInstallments) {
    if (typeof window.ps_cc_installments !== 'undefined' && Array.isArray(window.ps_cc_installments)) {
        const row = window.ps_cc_installments.find(function (installment) {
            return String(installment.installments) === String(selectedInstallments);
        });
        if (row && row.total_amount_raw !== undefined && row.total_amount_raw !== null) {
            const raw = Number(row.total_amount_raw);
            if (Number.isFinite(raw) && raw >= 100) {
                return Math.round(raw);
            }
        }
    }
    // Subscription change-card page: no cart — use recurring amount from the form.
    const changeCardAmount = Number(jQuery('#rm-pagbank-change-card-amount-cents').val() || 0);
    if (Number.isFinite(changeCardAmount) && changeCardAmount >= 100) {
        return Math.round(changeCardAmount);
    }
    if (typeof pagseguro_connect_cart_total_cents !== 'undefined') {
        const injected = Number(pagseguro_connect_cart_total_cents);
        if (Number.isFinite(injected) && injected >= 100) {
            return Math.round(injected);
        }
        if (Number.isFinite(injected) && injected > 0) {
            return Math.round(injected);
        }
    }
    if (Number.isFinite(changeCardAmount) && changeCardAmount > 0) {
        return Math.round(changeCardAmount);
    }
    const html = jQuery('.order-total bdi, .product-total bdi, .wc-block-components-totals-item__value').last().html();
    return window.rmPagbankParseBrlToCents(html);
};

jQuery(document).ready(function ($) {
    window.ps_cc_bin = '';

    //region Encrypt card method
    /**
     * Encrypts the card and sets the encrypted card in the hidden input and window.ps_cc_* variables for number and cvv
     * @returns {boolean}
     */
    let encryptCard = function () {
        let cardHasChanged = (window.ps_cc_has_changed === true);
        console.debug('PB Integrações: encryptCard', cardHasChanged);
        let card, cc_number, cc_cvv;
        //replace trim and remove duplicated spaces from holder name
        let holder_name = jQuery('#rm-pagbank-card-holder-name').val().trim().replace(/\s+/g, ' '); //dupl spaces
        holder_name = holder_name.trim().replace(/[^A-Za-zÀ-ÖØ-öø-ÿ\s]/g, ''); //specials
        cc_number = cardHasChanged ? jQuery('#rm-pagbank-card-number').val().replace(/\s/g, '') : window.ps_cc_number;
        cc_cvv = cardHasChanged ? jQuery('#rm-pagbank-card-cvc').val().replace(/\s/g, '') : window.ps_cc_cvv;

        // Vindi: tokenize via public payment_profiles (never send PAN to our WS)
        if (typeof pagseguro_connect_partner !== 'undefined' && pagseguro_connect_partner === 'vindi') {
            try {
                const expiryRaw = (jQuery('#rm-pagbank-card-expiry').val() || '').toString();
                const expiryParts = expiryRaw.split('/');
                // Strip spaces BEFORE slice — " 44 " → slice(-2) would become "4 " → "4" (year 2004).
                let expMonth = (expiryParts[0] || '').replace(/\D/g, '');
                let expYear = (expiryParts[1] || '').replace(/\D/g, '').slice(-2);
                expMonth = ('0' + expMonth).slice(-2);
                if (!/^(0[1-9]|1[0-2])$/.test(expMonth) || !/^\d{2}$/.test(expYear)) {
                    alert('Validade do cartão inválida. Use o formato MM/AA.');
                    return false;
                }
                // Prefer MM/YYYY so sandbox does not pivot YY (e.g. 44 → 1944 → "expirado").
                const cardExpiration = expMonth + '/20' + expYear;
                const company = (typeof window.rmPagbankDetectVindiPaymentCompanyCode === 'function')
                    ? window.rmPagbankDetectVindiPaymentCompanyCode(cc_number)
                    : 'mastercard';
                let auth = btoa(String(pagseguro_vindi_public_key) + ':');
                let xhr = new XMLHttpRequest();
                xhr.open('POST', pagseguro_vindi_payment_profiles_url, false);
                xhr.setRequestHeader('Content-Type', 'application/json');
                xhr.setRequestHeader('Authorization', 'Basic ' + auth);
                xhr.send(JSON.stringify({
                    holder_name: holder_name,
                    card_expiration: cardExpiration,
                    card_number: cc_number,
                    card_cvv: cc_cvv,
                    payment_method_code: 'credit_card',
                    payment_company_code: company
                }));
                if (xhr.status < 200 || xhr.status >= 300) {
                    let detail = '';
                    try {
                        const errBody = JSON.parse(xhr.responseText);
                        if (errBody && Array.isArray(errBody.errors)) {
                            detail = errBody.errors.map(function (e) {
                                return (e.parameter ? e.parameter + ': ' : '') + (e.message || '');
                            }).filter(Boolean).join('\n');
                        }
                    } catch (parseErr) { /* ignore */ }
                    alert('Erro ao tokenizar o cartão.' + (detail ? '\n' + detail : '\nVerifique os dados e tente novamente.'));
                    return false;
                }
                let body = JSON.parse(xhr.responseText);
                let token = body.payment_profile && (body.payment_profile.gateway_token || body.gateway_token);
                if (!token) {
                    alert('Erro ao tokenizar o cartão (token ausente).');
                    return false;
                }
                jQuery('#rm-pagbank-card-encrypted').val(token);
                jQuery('#rm-pagbank-payment-company-code').val(company);
                window.ps_vindi_payment_company = company;
                return true;
            } catch (e) {
                console.error(e);
                alert('Erro ao tokenizar o cartão.\nVerifique se os dados digitados estão corretos.');
                return false;
            }
        }

        try {
            const pbExpiry = (jQuery('#rm-pagbank-card-expiry').val() || '').toString().split('/');
            const pbMonth = (pbExpiry[0] || '').replace(/\D/g, '');
            const pbYear2 = (pbExpiry[1] || '').replace(/\D/g, '').slice(-2);
            card = PagSeguro.encryptCard({
                publicKey: pagseguro_connect_public_key,
                holder: holder_name,
                number: cc_number,
                expMonth: pbMonth,
                expYear: '20' + pbYear2,
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
        // Keep server cart total in sync for Vindi 3DS amount (centavos).
        if (typeof ajax_object === 'undefined' || typeof rm_pagbank_nonce === 'undefined') {
            return;
        }
        jQuery.ajax({
            url: ajax_object.ajax_url,
            method: 'POST',
            data: {
                action: 'get_cart_total',
                nonce: rm_pagbank_nonce,
                ccBin: (window.ps_cc_bin || '555566').toString().replace(/\D/g, '').slice(0, 6) || '555566',
            },
            success: function (total) {
                const reais = parseFloat(String(total).replace(',', '.'));
                if (Number.isFinite(reais) && reais > 0) {
                    window.pagseguro_connect_cart_total_cents = Math.round(reais * 100);
                    console.debug('PB Integrações: cart total cents updated', window.pagseguro_connect_cart_total_cents);
                }
            }
        });
    });

    //region 3ds authentication
    let isSubmitting = false;

    /**
     * Fetches a fresh 3D Secure session from the server so PagSeguro.setUp does not use an expired one.
     * @returns {Promise<string>}
     */
    const refresh3dSession = async function () {
        const ajaxUrl = (typeof ajax_object !== 'undefined' && ajax_object.ajax_url)
            ? ajax_object.ajax_url
            : '';
        if (!ajaxUrl || typeof rm_pagbank_nonce === 'undefined') {
            return typeof pagseguro_connect_3d_session !== 'undefined' ? pagseguro_connect_3d_session : '';
        }

        try {
            const response = await jQuery.ajax({
                url: ajaxUrl,
                method: 'POST',
                data: {
                    action: 'ps_get_3d_session',
                    nonce: rm_pagbank_nonce,
                }
            });
            if (response && response.session) {
                pagseguro_connect_3d_session = response.session;
                console.debug('Sessão 3D atualizada', pagseguro_connect_3d_session);
                return pagseguro_connect_3d_session;
            }
        } catch (e) {
            console.debug('PB Integrações: falha ao atualizar sessão 3D', e);
        }

        return typeof pagseguro_connect_3d_session !== 'undefined' ? pagseguro_connect_3d_session : '';
    };

    const parseBrlToCents = window.rmPagbankParseBrlToCents;
    const resolveCheckoutAmountCents = window.rmPagbankResolveCheckoutAmountCents;

    /** Fields needed by Vindi 3DS AJAX — never send PAN/CVV/expiry to WordPress. */
    const VINDI_CHECKOUT_SAFE_KEYS = [
        'billing_first_name', 'billing_last_name', 'billing_email', 'billing_phone', 'billing_cellphone',
        'billing_address_1', 'billing_address_2', 'billing_number', 'billing_neighborhood',
        'billing_city', 'billing_state', 'billing_postcode', 'billing_country',
        'billing_cpf', 'billing_cnpj', 'rm-pagbank-customer-document', 'rm-pagbank-card-holder-name',
    ];

    const pickBillingCheckoutFields = function (formDataObj) {
        const out = {};
        if (!formDataObj || typeof formDataObj !== 'object') {
            return out;
        }
        VINDI_CHECKOUT_SAFE_KEYS.forEach(function (key) {
            if (formDataObj[key] !== undefined && formDataObj[key] !== null && formDataObj[key] !== '') {
                out[key] = formDataObj[key];
            }
        });
        return out;
    };

    /** After tokenize: keep only masked PAN/CVV in the DOM so checkout POST never carries full card data. */
    const obfuscateSensitiveCardFields = function () {
        if (window.ps_cc_has_changed === false) {
            return;
        }
        let card_number = jQuery('#rm-pagbank-card-number').val() || '';
        if (card_number && card_number.indexOf('*') === -1) {
            window.ps_cc_number = card_number.replace(/\s/g, '');
            window.ps_cc_cvv = (jQuery('#rm-pagbank-card-cvc').val() || '').replace(/\s/g, '');
            jQuery('#rm-pagbank-card-cvc').val('***');
            let obfuscated = '';
            for (let i = 0; i < card_number.length; i++) {
                obfuscated += (i > 6 && i < card_number.length - 4) ? '*' : card_number[i];
            }
            jQuery('#rm-pagbank-card-number').val(obfuscated);
        }
        window.ps_cc_has_changed = false;
    };

    const vindiAjax = function (action, body) {
        const ajaxUrl = (typeof ajax_object !== 'undefined' && ajax_object.ajax_url)
            ? ajax_object.ajax_url
            : '';
        return jQuery.ajax({
            url: ajaxUrl + (ajaxUrl.indexOf('?') >= 0 ? '&' : '?') + 'action=' + encodeURIComponent(action)
                + '&nonce=' + encodeURIComponent(rm_pagbank_nonce),
            method: 'POST',
            contentType: 'application/json; charset=utf-8',
            dataType: 'json',
            data: JSON.stringify(body || {}),
        }).then(function (resp) {
            if (resp && resp.success) {
                return resp.data;
            }
            const msg = (resp && resp.data && resp.data.message) ? resp.data.message : 'Falha na autenticação 3D Secure.';
            return jQuery.Deferred().reject(new Error(msg)).promise();
        }, function (xhr) {
            let msg = 'Falha na autenticação 3D Secure.';
            try {
                const resp = xhr.responseJSON;
                if (resp && resp.data && resp.data.message) {
                    msg = resp.data.message;
                } else if (xhr.responseText) {
                    const parsed = JSON.parse(xhr.responseText);
                    if (parsed && parsed.data && parsed.data.message) {
                        msg = parsed.data.message;
                    }
                }
            } catch (e) { /* ignore */ }
            return jQuery.Deferred().reject(new Error(msg)).promise();
        });
    };

    const runVindiDdc = function (setup) {
        return new Promise(function (resolve, reject) {
            const vindiOrigin = (typeof pagseguro_vindi_mpi_origin !== 'undefined' && pagseguro_vindi_mpi_origin)
                ? pagseguro_vindi_mpi_origin
                : 'https://sandbox-mpi.vindi.com.br';
            // PDF: listen on MPI Vindi origin. Cardinal may also postMessage during Cruise Collect.
            const allowedOrigins = [
                vindiOrigin,
                'https://sandbox-mpi.vindi.com.br',
                'https://mpi.vindi.com.br',
                'https://centinelapistag.cardinalcommerce.com',
                'https://centinelapi.cardinalcommerce.com',
            ];
            let settled = false;

            const finish = function (ok, err) {
                if (settled) {
                    return;
                }
                settled = true;
                clearTimeout(timeout);
                window.removeEventListener('message', onMessage);
                if (ok) {
                    resolve(true);
                } else {
                    reject(err || new Error('Falha na coleta de dados do dispositivo (3DS).'));
                }
            };

            // PDF: only call enroll after Status===true. Do not soft-continue (causes AUTHENTICATION_SKIPPED).
            const timeout = setTimeout(function () {
                console.warn('PagBank Vindi 3DS: DDC timeout sem Status=true', { vindiOrigin });
                finish(false, new Error(
                    'Não foi possível coletar os dados do dispositivo (3DS). '
                    + 'Desative bloqueadores de anúncio/privacidade nesta página e tente novamente.'
                ));
            }, 15000);

            function originAllowed(origin) {
                if (allowedOrigins.indexOf(origin) !== -1) {
                    return true;
                }
                try {
                    const host = new URL(origin).hostname;
                    return /(^|\.)vindi\.com\.br$|(^|\.)cardinalcommerce\.com$/i.test(host);
                } catch (e) {
                    return false;
                }
            }

            function onMessage(event) {
                if (!originAllowed(event.origin)) {
                    return;
                }
                let data = event.data;
                try {
                    if (typeof data === 'string') {
                        data = JSON.parse(data);
                    }
                } catch (e) {
                    return;
                }
                if (!data || typeof data !== 'object') {
                    return;
                }
                console.debug('PagBank Vindi 3DS: DDC message', event.origin, data);
                // Official Vindi PDF: data.Status === true
                const statusOk = data.Status === true || data.Status === 'true' || data.Status === 'True';
                if (statusOk) {
                    finish(true);
                    return;
                }
                // Cardinal profile.completed with Status true already covered; Status false = fail DDC.
                if (data.MessageType === 'profile.completed' && data.Status === false) {
                    finish(false, new Error(
                        'Coleta de dados do dispositivo (3DS) incompleta. '
                        + 'Desative bloqueadores e tente novamente.'
                    ));
                }
            }

            window.addEventListener('message', onMessage);

            // Fresh iframe each attempt (reuse can keep aborted Cardinal session).
            let oldIframe = document.getElementById('rm-pagbank-vindi-ddc-iframe');
            if (oldIframe) {
                oldIframe.remove();
            }
            const iframe = document.createElement('iframe');
            iframe.id = 'rm-pagbank-vindi-ddc-iframe';
            iframe.name = 'rm-pagbank-vindi-ddc-iframe';
            iframe.setAttribute('aria-hidden', 'true');
            // 1x1 visible to layout (display:none can block some Collect scripts / Notification).
            iframe.style.cssText = 'width:1px;height:1px;border:0;position:absolute;left:0;top:0;opacity:0;pointer-events:none;';
            document.body.appendChild(iframe);

            let form = document.getElementById('rm-pagbank-vindi-ddc-form');
            if (form) {
                form.remove();
            }
            form = document.createElement('form');
            form.id = 'rm-pagbank-vindi-ddc-form';
            form.method = 'POST';
            form.target = 'rm-pagbank-vindi-ddc-iframe';
            form.action = setup.device_data_collection_url;
            form.style.display = 'none';
            // PDF sample: JWT only (no Bin).
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'JWT';
            input.value = setup.access_token;
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        });
    };

    const runVindiChallenge = function (enroll) {
        return new Promise(function (resolve, reject) {
            const timeout = setTimeout(function () {
                window.removeEventListener('message', onMessage);
                cleanup();
                reject(new Error('Timeout no desafio 3D Secure.'));
            }, 120000);

            function cleanup() {
                const wrap = document.getElementById('rm-pagbank-vindi-challenge-wrap');
                if (wrap) {
                    wrap.remove();
                }
                const form = document.getElementById('rm-pagbank-vindi-stepup-form');
                if (form) {
                    form.remove();
                }
            }

            function onMessage(event) {
                const data = event.data || {};
                if (!data || data.source !== 'rm-pagbank-vindi-3ds') {
                    return;
                }
                clearTimeout(timeout);
                window.removeEventListener('message', onMessage);
                cleanup();
                if (!data.TransactionId) {
                    reject(new Error('Challenge 3DS sem TransactionId.'));
                    return;
                }
                resolve(String(data.TransactionId));
            }

            window.addEventListener('message', onMessage);
            cleanup();
            const wrap = document.createElement('div');
            wrap.id = 'rm-pagbank-vindi-challenge-wrap';
            wrap.style.cssText = 'position:fixed;z-index:99999;inset:0;background:rgba(0,0,0,.45);display:flex;align-items:center;justify-content:center;';
            const iframe = document.createElement('iframe');
            iframe.id = 'rm-pagbank-vindi-stepup-iframe';
            iframe.name = 'rm-pagbank-vindi-stepup-iframe';
            iframe.width = '400';
            iframe.height = '400';
            iframe.style.cssText = 'background:#fff;border:0;border-radius:4px;max-width:95vw;max-height:90vh;';
            wrap.appendChild(iframe);
            document.body.appendChild(wrap);

            const form = document.createElement('form');
            form.id = 'rm-pagbank-vindi-stepup-form';
            form.method = 'POST';
            form.target = 'rm-pagbank-vindi-stepup-iframe';
            form.action = enroll.step_up_url;
            form.style.display = 'none';
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'JWT';
            input.value = enroll.access_token;
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        });
    };

    const buildThreeDsPayload = function (authResult) {
        const info = (authResult && authResult.consumer_authentication_information)
            ? authResult.consumer_authentication_information
            : {};
        const out = {
            status: authResult.status || 'AUTHENTICATION_SUCCESSFUL',
            liability_shift: !!authResult.liability_shift,
        };
        if (info.eci || info.cavv || info.xid) {
            out.consumer_authentication_information = {
                eci: info.eci || '',
                cavv: info.cavv || '',
                xid: info.xid || '',
            };
            if (info.directory_server_transaction_id) {
                out.consumer_authentication_information.directory_server_transaction_id = info.directory_server_transaction_id;
            }
        }
        if (authResult.authentication_transaction_id) {
            out.authentication_transaction_id = authResult.authentication_transaction_id;
        }
        return out;
    };

    /**
     * Vindi 3DS: persist profile → setup → DDC → enroll → (challenge → validate) → place order.
     */
    const runVindi3dsPipeline = async function (cartTotal, selectedInstallments, checkoutFormDataObj) {
        const token = jQuery('#rm-pagbank-card-encrypted').val();
        const company = jQuery('#rm-pagbank-payment-company-code').val()
            || window.ps_vindi_payment_company
            || 'mastercard';
        if (!token) {
            throw new Error('Token do cartão ausente.');
        }

        const billingOnly = pickBillingCheckoutFields(checkoutFormDataObj);
        const profileData = await vindiAjax('rm_pagbank_vindi_payment_profile', {
            gateway_token: token,
            payment_company_code: company,
            checkout: billingOnly,
        });
        const profileId = profileData.payment_profile_id;
        jQuery('#rm-pagbank-payment-profile-id').val(String(profileId));
        if (profileData.customer_id) {
            jQuery('#rm-pagbank-vindi-customer-id').val(String(profileData.customer_id));
        }

        const doSetupDdcEnroll = async function () {
            const setupData = await vindiAjax('rm_pagbank_vindi_3ds_setup', {
                payment_profile_id: profileId,
            });
            const setup = setupData.setup;
            if (!setup || !setup.session_id || !setup.access_token || !setup.device_data_collection_url) {
                throw new Error('Resposta de setup 3DS inválida.');
            }
            await runVindiDdc(setup);
            console.debug('PagBank Vindi 3DS enroll amount (cents)', cartTotal);
            const enrollData = await vindiAjax('rm_pagbank_vindi_3ds_enroll', {
                session_id: setup.session_id,
                amount: cartTotal,
                installments: selectedInstallments * 1,
                device_info: {
                    user_agent: navigator.userAgent,
                    screen_height: window.screen.height,
                    screen_width: window.screen.width,
                    color_depth: window.screen.colorDepth || 24,
                    timezone_offset: new Date().getTimezoneOffset() * -1,
                    language: navigator.language || 'pt-BR',
                    java_enabled: false,
                },
                checkout: billingOnly,
            });
            let enroll = (enrollData && (enrollData.enroll || enrollData)) || {};
            if (enroll.enroll && typeof enroll.enroll === 'object') {
                enroll = enroll.enroll;
            }
            console.debug('PagBank Vindi 3DS enroll result', enroll);
            return enroll;
        };

        let enroll = await doSetupDdcEnroll();
        // AUTHENTICATION_SKIPPED = DDC incomplete OR amount below MPI minimum (see enroll.reason).
        if (String(enroll.status || '').toUpperCase() === 'AUTHENTICATION_SKIPPED'
            && String(enroll.reason || '').toUpperCase() !== 'BELOW_MINIMUM_AMOUNT') {
            console.warn('PagBank Vindi 3DS: AUTHENTICATION_SKIPPED — retry setup/DDC/enroll once', enroll);
            enroll = await doSetupDdcEnroll();
        }

        const enrollStatus = String(enroll.status || '').toUpperCase();
        const enrollReason = String(
            enroll.reason
            || enroll.skip_reason
            || enroll.authentication_reason
            || ''
        ).toUpperCase();
        const isBelowMinimum = enrollReason === 'BELOW_MINIMUM_AMOUNT'
            || enrollReason.indexOf('BELOW_MINIMUM') !== -1;
        const challengeRequired = !isBelowMinimum && (
            enroll.challenge_required === true
            || enroll.challenge_required === 'true'
            || enrollStatus === 'PENDING_AUTHENTICATION'
        );
        // Vindi docs: veres N/U or card_enrolled false → accept without 3DS or ask another card.
        // AUTHENTICATION_FAILED with enrolled card (veres Y) must not use allow-continue.
        // Never treat BELOW_MINIMUM as “card not enrolled”.
        const notEnrolledIn3ds = !isBelowMinimum && (
            enroll.card_enrolled === false
            || enroll.veres_enrolled === 'N'
            || enroll.veres_enrolled === 'U'
            || enroll.veres_enrolled === 'B' // merchant / issuer bypass — not a real liability shift
            || (enrollStatus === 'AUTHENTICATION_FAILED'
                && enroll.veres_enrolled !== 'Y'
                && enroll.card_enrolled !== true)
        );
        const authFailedWhileEnrolled = !isBelowMinimum
            && enrollStatus === 'AUTHENTICATION_FAILED'
            && !notEnrolledIn3ds;
        const authOk = enroll.liability_shift === true
            || enrollStatus === 'AUTHENTICATION_SUCCESSFUL'
            || enrollStatus === 'SUCCESSFUL'
            || enrollStatus === 'Y'
            || enrollStatus === 'A';
        const allowContinueWithout3ds = (typeof pagseguro_connect_cc_3ds_allow_continue !== 'undefined'
            && pagseguro_connect_cc_3ds_allow_continue === 'yes');
        const isChangeCardPage = (typeof pagseguro_connect_change_card_page !== 'undefined'
            && (pagseguro_connect_change_card_page === true || pagseguro_connect_change_card_page === 'true'));

        const continueWithout3ds = function (reason) {
            console.warn('PagBank Vindi 3DS: ' + reason + ' — concluindo sem 3DS');
            jQuery('#rm-pagbank-payment-profile-id').val('');
            jQuery('#rm-pagbank-vindi-customer-id').val('');
            jQuery('#rm-pagbank-card-3ds-payload').val('');
            jQuery('#rm-pagbank-card-3d').val('');
            // Re-tokenize from in-memory PAN (DOM may already be masked).
            window.ps_cc_has_changed = false;
            if (encryptCard() === false) {
                throw new Error('Não foi possível tokenizar o cartão para concluir sem 3DS.');
            }
            return true;
        };

        // Reason wins over status — MPI may return BELOW_MINIMUM with FAILED/SKIPPED + card_enrolled false.
        if (isBelowMinimum) {
            // Change-card is only saving a profile (no charge now) — always allow without 3DS.
            if (!allowContinueWithout3ds && !isChangeCardPage) {
                throw new Error(
                    'Valor abaixo do mínimo para 3D Secure. A loja não permite concluir sem autenticação. '
                    + 'Use outro método de pagamento ou aumente o valor do pedido.'
                );
            }
            // MPI will not run 3DS — charge with a fresh gateway_token (private profile consumed the first one).
            return continueWithout3ds('BELOW_MINIMUM_AMOUNT (amount cents=' + cartTotal + ')');
        }

        if (enrollStatus === 'AUTHENTICATION_SKIPPED') {
            throw new Error(
                '3D Secure pulado (AUTHENTICATION_SKIPPED'
                + (enroll.reason ? ': ' + enroll.reason : '')
                + '). Valor enviado: '
                + cartTotal
                + ' centavos. Desative bloqueadores e tente novamente.'
            );
        }

        if (authFailedWhileEnrolled && !challengeRequired) {
            throw new Error('Autenticação 3D Secure falhou. Use outro cartão ou método de pagamento.');
        }

        if (notEnrolledIn3ds && !challengeRequired) {
            if (allowContinueWithout3ds) {
                return continueWithout3ds('cartão fora do 3DS (veres=' + (enroll.veres_enrolled || '?') + ')');
            }
            throw new Error('Este cartão não tem suporte a 3D Secure. Use outro cartão ou método de pagamento.');
        }

        let authResult = enroll;
        if (challengeRequired) {
            if (!enroll.step_up_url || !enroll.access_token) {
                throw new Error('Desafio 3DS solicitado sem URL do emissor. Status: ' + (enroll.status || 'desconhecido'));
            }
            const txId = await runVindiChallenge(enroll);
            const cardType = (company === 'american_express') ? 'american_express'
                : (company === 'mastercard') ? 'mastercard'
                : (company === 'elo') ? 'elo'
                : (company === 'hipercard') ? 'hipercard'
                : (company === 'diners_club') ? 'diners_club'
                : (company === 'jcb') ? 'jcb'
                : 'visa';
            const validateData = await vindiAjax('rm_pagbank_vindi_3ds_validate', {
                authentication_transaction_id: txId,
                card_type: cardType,
            });
            authResult = (validateData && (validateData.validate || validateData)) || {};
            if (authResult.validate && typeof authResult.validate === 'object') {
                authResult = authResult.validate;
            }
            const validateStatus = String(authResult.status || '').toUpperCase();
            const validateOk = authResult.liability_shift === true
                || validateStatus === 'AUTHENTICATION_SUCCESSFUL'
                || validateStatus === 'SUCCESSFUL';
            if (!validateOk) {
                throw new Error('Autenticação 3D Secure falhou. Status: ' + (authResult.status || 'desconhecido'));
            }
        } else if (!authOk) {
            throw new Error(
                'Autenticação 3D Secure não concluída. Status: '
                + (enroll.status || 'ausente')
                + '. Tente novamente.'
            );
        }

        const threeDs = buildThreeDsPayload(authResult);
        jQuery('#rm-pagbank-card-3ds-payload').val(JSON.stringify(threeDs));
        jQuery('#rm-pagbank-card-3d').val(authResult.authentication_transaction_id || 'vindi_3ds');
        return true;
    };

    /**
     * PagBank SDK errors may include errorMessages[] or only a message (e.g. expired session 403).
     * @param {*} err
     * @returns {string}
     */
    const getPagSeguro3dsErrorMessage = function (err) {
        const detail = err && err.detail ? err.detail : null;
        if (detail && Array.isArray(detail.errorMessages) && detail.errorMessages.length) {
            return detail.errorMessages.map(error => pagBankParseErrorMessage(error)).join('\n');
        }
        if (detail && detail.message) {
            if (/expired session/i.test(detail.message)) {
                return 'A sessão 3D Secure expirou. Recarregue a página e tente novamente.';
            }
            return detail.message;
        }
        if (err && err.message) {
            return err.message;
        }
        return 'Tente novamente.';
    };

    let checkoutFormIdentifiers = 'form.woocommerce-checkout, form#order_review, .wc-block-components-form, .wc-block-checkout__form, form#order_update, form#add_payment_method';
    if (!jQuery(checkoutFormIdentifiers).length) {
        console.debug('PB Integrações: checkout form not found');
        return true;
    }
    let originalSubmitHandler = () => {};
    // get the original submit handler for checkout or order-pay page
    if (jQuery._data(jQuery(checkoutFormIdentifiers)[0], "events") !== undefined) {
        let formCheckout = jQuery('form.woocommerce-checkout, form#order_review, form#order_update, form#add_payment_method')[0];
        let formEvents = jQuery._data(formCheckout, "events");
        
        if (formEvents && formEvents.submit) {
            originalSubmitHandler = formEvents.submit[0].handler;
        }
    }

    let pagBankSubmitHandler = async function (e) {
        console.debug('PB Integrações: submit');

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

        const cc_payment_token = jQuery('input[name="wc-rm-pagbank-cc-payment-token"]:checked').val();
        if (cc_payment_token && cc_payment_token !== 'new') {
            console.debug('PB Integrações: cartão salvo selecionado, não criptografar.');
            isSubmitting = true;
            jQuery(checkoutFormIdentifiers).on('submit', originalSubmitHandler);
            jQuery(checkoutFormIdentifiers).trigger('submit');
            return true;
        }

        let has3dSession = typeof pagseguro_connect_3d_session !== 'undefined' && pagseguro_connect_3d_session;
        let is3dsEnabled = pagseguro_connect_3ds_enabled;
        let isRetryEnabled = pagseguro_connect_3ds_retry_enabled;
        let canRetry = jQuery('#rm-pagbank-card-retry-with-3ds')?.is(':checked');
        const isVindi = typeof pagseguro_connect_partner !== 'undefined' && pagseguro_connect_partner === 'vindi';

        // 2nd submit after Vindi 3DS (same card): keep profile/token — do not re-tokenize.
        // If the shopper changed PAN/CVV (or checkout just failed), force a fresh pipeline.
        const vindi3dsAlreadyDone = isVindi && is3dsEnabled
            && typeof pagbank3dAuthorized !== 'undefined' && pagbank3dAuthorized === true
            && !!jQuery('#rm-pagbank-payment-profile-id').val()
            && window.ps_cc_has_changed === false;
        if (vindi3dsAlreadyDone) {
            obfuscateSensitiveCardFields();
            isSubmitting = true;
            jQuery(checkoutFormIdentifiers).on('submit', originalSubmitHandler);
            jQuery(checkoutFormIdentifiers).trigger('submit');
            return true;
        }
        if (isVindi && window.ps_cc_has_changed === true
            && typeof window.clearVindiCardAuthState === 'function') {
            window.clearVindiCardAuthState();
        }

        if (window.ps_cc_has_changed !== false) {
            console.debug('PB Integrações: cartao mudou NOVO');
            let card_number = jQuery('#rm-pagbank-card-number').val();
            window.ps_cc_number = card_number.replace(/\s/g, '');
            window.ps_cc_cvv = jQuery('#rm-pagbank-card-cvc').val().replace(/\s/g, '');
        }

        if (encryptCard() === false) {
            return false;
        }
        // Mask PAN/CVV in the DOM before any WordPress AJAX or checkout POST.
        obfuscateSensitiveCardFields();

        let selectedInstallments = jQuery('#rm-pagbank-card-installments').val();
        if (selectedInstallments === "" || selectedInstallments === undefined) {
            selectedInstallments = 1;
        }

        let cartTotal = resolveCheckoutAmountCents(selectedInstallments);
        console.debug('PB Integrações: checkout amount cents', cartTotal);

        // Vindi 3DS pipeline (classic checkout)
        if (isVindi && is3dsEnabled) {
            const allowContinueWithout3ds = (typeof pagseguro_connect_cc_3ds_allow_continue !== 'undefined'
                && pagseguro_connect_cc_3ds_allow_continue === 'yes');
            // Change-card / add-payment-method: not a charge — never block on MPI minimum.
            const isChangeCardPage = (typeof pagseguro_connect_change_card_page !== 'undefined'
                && (pagseguro_connect_change_card_page === true || pagseguro_connect_change_card_page === 'true'));

            // MPI does not authenticate below R$ 1,00 — same policy as “cartão fora do 3DS”.
            if (cartTotal < 100) {
                if (!allowContinueWithout3ds && !isChangeCardPage) {
                    alert('Este pedido está abaixo do valor mínimo para 3D Secure. Use outro método de pagamento ou aumente o valor do carrinho.');
                    return false;
                }
                isSubmitting = true;
                jQuery(checkoutFormIdentifiers).on('submit', originalSubmitHandler);
                jQuery(checkoutFormIdentifiers).trigger('submit');
                return true;
            }

            var checkoutFormDataVindi = jQuery(this).serializeArray();
            var checkoutFormDataObjVindi = {};
            jQuery.each(checkoutFormDataVindi, function (i, field) {
                checkoutFormDataObjVindi[field.name] = field.value;
            });
            checkoutFormDataObjVindi = pickBillingCheckoutFields(checkoutFormDataObjVindi);

            jQuery('.woocommerce-checkout-payment, .woocommerce-checkout-review-order-table, form#order_review').block({
                message: 'Autenticação 3D em andamento',
                overlayCSS: {
                    background: '#fff',
                    opacity: 0.6
                },
                css: {border: 0}
            });

            try {
                await runVindi3dsPipeline(cartTotal, selectedInstallments, checkoutFormDataObjVindi);
                pagbank3dAuthorized = true;
                jQuery('.woocommerce-checkout-payment, .woocommerce-checkout-review-order-table, form#order_review').unblock();
                isSubmitting = true;
                jQuery('form.woocommerce-checkout, form#order_review').on('submit', originalSubmitHandler);
                jQuery('form.woocommerce-checkout, form#order_review').trigger('submit');
                return true;
            } catch (err) {
                console.error(err);
                alert((err && err.message) ? err.message : 'Falha na autenticação 3D Secure.');
                pagbank3dAuthorized = false;
                jQuery('.woocommerce-checkout-payment, .woocommerce-checkout-review-order-table, form#order_review').unblock();
                return false;
            }
        }

        // If 3ds and 3ds_retry is not enabled, continue
        if (!is3dsEnabled && !isRetryEnabled || !has3dSession) {
            isSubmitting = true;
            jQuery(checkoutFormIdentifiers).on('submit', originalSubmitHandler);
            jQuery(checkoutFormIdentifiers).trigger('submit');
            return true;
        }

        // If 3DS authorization has already occurred, proceed normally
        if (typeof pagbank3dAuthorized !== 'undefined' && pagbank3dAuthorized === true) {
            isSubmitting = true;
            jQuery(checkoutFormIdentifiers).on('submit', originalSubmitHandler);
            jQuery(checkoutFormIdentifiers).trigger('submit');
            return true;
        }

        if (isRetryEnabled && !canRetry && !is3dsEnabled) {
            isSubmitting = true;
            jQuery(checkoutFormIdentifiers).on('submit', originalSubmitHandler);
            jQuery(checkoutFormIdentifiers).trigger('submit');
            return true;
        }

        //if cart total is less than 100, don't continue with 3ds
        if (cartTotal < 100) {
            isSubmitting = true;
            jQuery(checkoutFormIdentifiers).on('submit', originalSubmitHandler);
            jQuery(checkoutFormIdentifiers).trigger('submit');
            return true;
        }

        //region 3ds authentication method
        var checkoutFormData = jQuery(this).serializeArray();
        // Convert the form data to an object
        var checkoutFormDataObj = {};
        jQuery.each(checkoutFormData, function (i, field) {
            checkoutFormDataObj[field.name] = field.value;
        });

        let expiryVal = jQuery('#rm-pagbank-card-expiry').val() || '';
        const expiryPartsPb = expiryVal.split('/');
        const expMonthPb = (expiryPartsPb[0] || '').replace(/\D/g, '');
        const expYearPb = (expiryPartsPb[1] || '').replace(/\D/g, '').slice(-2);

        let request = {
            data: {
                paymentMethod: {
                    type: 'CREDIT_CARD',
                    installments: selectedInstallments*1,
                    card: {
                        number: window.ps_cc_number || jQuery('#rm-pagbank-card-number').val().replace(/\s/g, ''),
                        expMonth: expMonthPb,
                        expYear: expYearPb ? '20' + expYearPb : '',
                        holder: {
                            name: jQuery('#rm-pagbank-card-holder-name').val().trim().replace(/\s+/g, ' ').replace(/[^A-Za-zÀ-ÖØ-öø-ÿ\s]/g, '')
                        }
                    }
                },
                dataOnly: false
            }
        }
        
        let customerName = checkoutFormDataObj['billing_first_name'] + ' ' + checkoutFormDataObj['billing_last_name'];
        customerName = customerName.trim().replace(/\s+/g, ' '); //removing duplicated spaces in the middle
        customerName = customerName.replace(/[^A-Za-zÀ-ÖØ-öø-ÿ\s]/g, '').replace(/\s+/g, ' '); //removing specials
        var billing_phone = checkoutFormDataObj['billing_cellphone']?.length ? checkoutFormDataObj['billing_cellphone'] : checkoutFormDataObj['billing_phone'] ?? null;

        if (!billing_phone) {
          const phones = typeof pagBankOrderDetails !== 'undefined' ? pagBankOrderDetails?.data?.customer?.phones : [];
          // If no phone is provided in the form, try to get it from the order details
          if (Array.isArray(phones) && phones.length > 0) {
            // ordem manual (pay_for_order)
            const { area, number } = phones[0];
            billing_phone = area + number;
          }

          if (!billing_phone) {
            alert(
              "Por favor, preencha o campo Telefone ou Celular para continuar com o pagamento."
            );
            return false;
          }
        }
        
        let orderData = typeof pagBankOrderDetails !== 'undefined'
            ? pagBankOrderDetails.data //if order-pay page
            : { //if checkout page get from form fields
                customer: {
                    name: customerName,
                    email: checkoutFormDataObj['billing_email'],
                    phones: [
                        {
                            country: '55',
                            area: billing_phone.replace(/\D/g, '').substring(0, 2),
                            number: billing_phone.replace(/\D/g, '').substring(2),
                            type: 'MOBILE'
                    }]
                },
                amount: {
                    value: cartTotal,
                    currency: 'BRL'
                },
                billingAddress: {
                    street: checkoutFormDataObj['billing_address_1'] ? checkoutFormDataObj['billing_address_1'].replace(/\s+/g, ' ') : 'Rua não informada',
                    number: checkoutFormDataObj['billing_number'] ? checkoutFormDataObj['billing_number'].replace(/\s+/g, ' ') : 'n/d',
                    complement: checkoutFormDataObj['billing_neighborhood'] ? checkoutFormDataObj['billing_neighborhood'].replace(/\s+/g, ' ') : 'n/d',
                    regionCode: checkoutFormDataObj['billing_state'] ? checkoutFormDataObj['billing_state'].replace(/\s+/g, ' ').toUpperCase() : 'SP',
                    country: 'BRA',
                    city: checkoutFormDataObj['billing_city'] ? checkoutFormDataObj['billing_city'].replace(/\s+/g, ' ') : 'n/d',
                    postalCode: checkoutFormDataObj['billing_postcode'] ? checkoutFormDataObj['billing_postcode'].replace(/\D+/g, '') : '00000000'
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

        await refresh3dSession();

        PagSeguro.setUp({
            session: pagseguro_connect_3d_session,
            env: pagseguro_connect_environment,
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
                        console.debug('PB Integrações: 3DS Autenticado ou Sem desafio');
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
                        console.debug('PB Integrações: 3DS não suportado pelo cartão. Continuando sem 3DS.');
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
                    console.debug('PB Integrações: REQUIRE_CHALLENGE - O desafio está sendo exibido pelo banco.');
                    break;
            }
        }).catch((err) => {
            console.error(err);
            console.debug('PB Integrações:', err && err.detail ? err.detail : err);
            const errMsgs = getPagSeguro3dsErrorMessage(err);
            alert('Falha na requisição de autenticação 3D.\n' + errMsgs);
            jQuery('.woocommerce-checkout-payment, .woocommerce-checkout-review-order-table, form#order_review').unblock();
            return false;
        })
        //endregion

        return false;
    }
    
    jQuery(checkoutFormIdentifiers).off('submit');
    jQuery(checkoutFormIdentifiers).on('submit', pagBankSubmitHandler);
    
    
    //endregion

    jQuery(checkoutFormIdentifiers).off('checkout_place_order').on('checkout_place_order', async function (e) {
        console.debug('PB Integrações: checkout_place_order');
        
        const cc_payment_token = jQuery('input[name="wc-rm-pagbank-cc-payment-token"]:checked').val();
        //if not pagseguro connect or not credit card, return
        if ((jQuery('#ps-connect-payment-cc').attr('disabled') !== undefined ||
            jQuery('#payment_method_rm-pagbank').is(':checked') === false) &&
            jQuery('input[name="payment_method"]:checked').val() !== 'rm-pagbank-cc' ||
            (cc_payment_token && cc_payment_token !== 'new')) //when using standalone methods
        {
            return true;
        }

        const isVindi = typeof pagseguro_connect_partner !== 'undefined' && pagseguro_connect_partner === 'vindi';
        const vindiReady = isVindi && window.ps_cc_has_changed === false && (
            (typeof pagbank3dAuthorized !== 'undefined' && pagbank3dAuthorized === true)
            || !!jQuery('#rm-pagbank-payment-profile-id').val()
            || !!jQuery('#rm-pagbank-card-encrypted').val()
        );
        // Vindi: token/profile already set by submit handler — do not create a second public payment_profile.
        if (vindiReady) {
            obfuscateSensitiveCardFields();
            isSubmitting = false;
            return true;
        }

         /*region Encrypt card and obfuscates before submit*/
        if (encryptCard() === false) {
            return;
        }

        //obfuscates cvv
        // saves in window the card number and cvv, so we can reuse it if the first attempt fails for some reason
        // pagbank requires a new encryption for each attempt, and we don't want to ask the customer to type again
        obfuscateSensitiveCardFields();
        /*endregion*/
        
        isSubmitting = false;
    });

    
});

    /** Drop stale Vindi profile / 3DS payload so a new PAN re-runs enroll. */
    window.clearVindiCardAuthState = function () {
        if (typeof pagbank3dAuthorized !== 'undefined') {
            pagbank3dAuthorized = false;
        }
        jQuery('#rm-pagbank-payment-profile-id').val('');
        jQuery('#rm-pagbank-vindi-customer-id').val('');
        jQuery('#rm-pagbank-card-3ds-payload').val('');
        jQuery('#rm-pagbank-card-3d').val('');
        jQuery('#rm-pagbank-card-encrypted').val('');
    };

// jQuery(document.body).on('init_checkout', ()=>{
    jQuery(document).on('keyup change paste', '#rm-pagbank-card-number', (e)=>{
        window.ps_cc_has_changed = true;
        // PagBank session OR Vindi profile from a prior attempt — never reuse after PAN edit.
        if ('undefined' !== typeof pagseguro_connect_3d_session
            || (typeof pagseguro_connect_partner !== 'undefined' && pagseguro_connect_partner === 'vindi')) {
            window.clearVindiCardAuthState();
        } else if (typeof pagbank3dAuthorized !== 'undefined') {
            pagbank3dAuthorized = false;
        }
        let cardNumber = jQuery(e.target).val();
        let digits = cardNumber.replace(/\D/g, '');
        let ccBin = digits.substring(0, 6);
        if (digits.length >= 6 && typeof window.rmPagbankDetectVindiPaymentCompanyCode === 'function') {
            const company = window.rmPagbankDetectVindiPaymentCompanyCode(digits);
            jQuery('#rm-pagbank-payment-company-code').val(company);
            window.ps_vindi_payment_company = company;
        }
        if (ccBin !== window.ps_cc_bin && ccBin.length === 6 && !pagseguro_connect_change_card_page) {
            window.ps_cc_bin = ccBin;
            jQuery(document.body).trigger('update_installments');
        }
    });
    jQuery(document).on('keyup change paste', '#rm-pagbank-card-cvc', (e)=>{
        window.ps_cc_has_changed = true;
        if (typeof pagseguro_connect_partner !== 'undefined' && pagseguro_connect_partner === 'vindi') {
            window.clearVindiCardAuthState();
        }
    });
    jQuery(document).on('input change paste', '#rm-pagbank-card-holder-name', (e)=>{
        jQuery(e.target).val(jQuery(e.target).val().toUpperCase());
    });
    
    // CPF/CNPJ formatting (CPF numeric; CNPJ alphanumeric)
    jQuery(document).on('input change paste', '#rm-pagbank-card-cpf-cnpj', (e)=>{
        const formattedValue = window.rmPagbankTaxId.formatTaxIdForDisplay(jQuery(e.target).val());
        jQuery(e.target).val(formattedValue);
    });
// });

    jQuery(document.body).on('update_installments', (event, selector_installments = '#rm-pagbank-card-installments')=> {
        //if success, update the installments select with the response
        //if error, show error message
        let ccBin = typeof window.ps_cc_bin === 'undefined' || window.ps_cc_bin.replace(/[^0-9]/g, '').length < 6 ? '555566' : window.ps_cc_bin;
        let total = jQuery('.order-total bdi, .product-total bdi, .wc-block-components-totals-item__value').last().html();
        let orderTotal = (typeof window.rmPagbankParseBrlToCents === 'function')
            ? window.rmPagbankParseBrlToCents(total)
            : 0;
        if (typeof pagseguro_connect_cart_total_cents !== 'undefined') {
            const injected = Number(pagseguro_connect_cart_total_cents);
            if (Number.isFinite(injected) && injected >= 100) {
                orderTotal = Math.round(injected);
            }
        }
        if (orderTotal < 100) {
            let select = jQuery(selector_installments);
            select.parent().hide();
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
                let select = jQuery(selector_installments);
                select.empty();
                let found = false;
                let previouslySelected = window.ps_cc_selected_installment || jQuery(selector_installments).val();
                for (let i = 0; i < response.length; i++) {
                    let option = jQuery('<option></option>');
                    option.attr('value', response[i].installments);
                    let text = response[i].installments + 'x de R$ ' + response[i].installment_amount;
                    let additional_text = ' (sem juros)';
                    if (response[i].interest_free === false)
                        additional_text = ' (Total R$ ' + response[i].total_amount + ')';
    
                    option.text(text + additional_text);
                    select.append(option);
                    if (previouslySelected == response[i].installments) {
                        found = true;
                    }
                }
                window.ps_cc_installments = response;

                // if previously selected installment is found, select it
                if (found) {
                    select.val(previouslySelected);
                } else if (response?.length > 0 && typeof window.ps_cc_selected_installment !== 'undefined') {
                    // If the previously selected installment is not found, select the last one
                    select.val(response[response.length-1].installments);
                }
            },
            error: (response)=>{
                alert('Erro ao calcular parcelas. Verifique os dados do cartão e tente novamente.');
                console.info('Lojista: Verifique os logs em WooCommerce > Status > Logs ' +
                    'para ver os possíveis problemas na obtenção das parcelas. Note que cartões de teste falharão ' +
                    'na maioria dos casos.');
            }
        });
    });

jQuery(document.body).on('checkout_error', function(event, error_data) {
    // After a declined charge, drop profile/3DS so the next attempt re-tokenizes + re-enrolls.
    // Keep ps_cc_has_changed=false when the PAN is still masked in the DOM — encryptCard
    // will reuse window.ps_cc_number from the previous successful tokenize.
    if (typeof pagseguro_connect_partner !== 'undefined' && pagseguro_connect_partner === 'vindi') {
        if (typeof window.clearVindiCardAuthState === 'function') {
            window.clearVindiCardAuthState();
        } else if (typeof pagbank3dAuthorized !== 'undefined') {
            pagbank3dAuthorized = false;
        }
    }

    let method = jQuery('input[name="payment_method"]:checked').val();
    const errText = (typeof error_data === 'string') ? error_data : String(error_data || '');
    if (errText.includes('Vamos tentar com validação 3DS') && method === 'rm-pagbank-cc') {
        const retry3dsInput = '<div class="rm-pagbank-retry-select">' +
            '<input type="checkbox" id="rm-pagbank-card-retry-with-3ds" class="rm-pagbank-checkbox" name="rm-pagbank-card-retry-with-3ds" value="1" checked>' +
            '<label for="rm-pagbank-card-retry-with-3ds">Tentar novamente com Validação 3DS</label>' +
            '</div>';

        const rmPagbankCcForm = jQuery('#wc-rm-pagbank-cc-form');
        if (rmPagbankCcForm.find('.rm-pagbank-retry-select').length > 0) {
            jQuery('#rm-pagbank-card-retry-with-3ds').prop('checked', true);
            return;
        }

        rmPagbankCcForm.prepend(retry3dsInput);
    }
});

jQuery(document).on("change", "#rm-pagbank-card-installments, #rm-pagbank-card-installments-token", function () {
  window.ps_cc_selected_installment = jQuery(this).val();
});

jQuery(function($){
    function togglePagBankFields() {
        var selected = $('input[name="wc-rm-pagbank-cc-payment-token"]:checked').val();
        if (selected !== 'new') {
            var bin = jQuery(this).data('cc-bin');
            if (bin) {
                window.ps_cc_bin = bin.toString();
                jQuery(document.body).trigger('update_installments', '#rm-pagbank-card-installments-token');
            }
        }
        return selected && selected !== 'new' ?  $('#rm-pagbank-installments-token').show() : $('#rm-pagbank-installments-token').hide();
    }
    $(document).on('change', 'input[name="wc-rm-pagbank-cc-payment-token"]', togglePagBankFields);
    togglePagBankFields();
});