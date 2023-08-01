jQuery(document).ready(function ($) {
    window.ps_cc_bin = '';
    
    /*region extending cards and card types from jqueryPayment to support new types*/
    const typesPagBank = [
        {
            title: 'Visa',
            type: 'visa',
            pattern: '^(?!4\\d*$)\\d*$',
            gaps: [4, 8, 12],
            lengths: [16, 18, 19],
            code: {
                name: 'CVV',
                size: 3
            }
        },
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
        }
    ];
    
    //cardsFriendly é usado apenas para permitir que o setCardType remova os cartões incorretos, mas não para verificar
    const cardsFriendly = [
        {type: 'elo', patterns: [], length: [], cssLength: [], format: '', luhn: false},
        {type: 'aura', patterns: [], length: [], cssLength: [], format: '', luhn: false},
        {type: 'hipercard', patterns: [], length: [], cssLength: [], format: '', luhn: false},
    ]
    $.extend($.payment.cardsPagBank, typesPagBank);
    $.extend($.payment.cards, cardsFriendly);
    
    // método original
    const originalCardType = $.payment.cardType;

    // Estendemos a cardType do jqueryPayment
    $.extend($.payment, {
        cardType: function(num) {
            // Tentamos buscar no nosso array de cartões
            let cardTypes = getCardTypes(num);
            if (cardTypes.length > 0) {
                return cardTypes[0].type;
            }
            
            // Se não encontrarmos, retornamos o resultado original
            return originalCardType.call(this, num);
        }
    });

    /**
     * Retorna o tipo de cartão
     * @param cardNumber
     * @returns {*|*[]}
     */
    let getCardTypes = function (cardNumber) {
        //remove spaces
        cardNumber = cardNumber.replace(/\s/g, '');
        let result = [];

        if ($.isEmptyObject(cardNumber)) {
            return result;
        }

        if (cardNumber === '') {
            return $.extend(true, [], $.payment.cardsPagBank);
        }

        for (let i = 0; i < typesPagBank.length; i++) {
            let value = typesPagBank[i];
            if (new RegExp(value.pattern).test(cardNumber)) {
                result.push($.extend(true, {}, value));
            }
        }

        return result.slice(-1);
    }
    
    /*endregion*/
    
    
    $(document.body).on('update_checkout', function(e){
        $(document.body).trigger('update_installments');
    });
    
    
    $('form.woocommerce-checkout').on('checkout_place_order', function (e) {
        
        //if not pagseguro connect or not credit card, return
        if ($('#ps-connect-payment-cc').attr('disabled') !== undefined || 
            $('#payment_method_rm_pagseguro_connect').is(':checked') === false)
            return true;
        
        
         let form = $(this);
         let card;
        //replace trim and remove duplicated spaces from holder name
        let holder_name = $('#rm_pagseguro_connect-card-holder-name').val().trim().replace(/\s+/g, ' ');
        
         /*region Encrypt card*/
        try {
            card = PagSeguro.encryptCard({
                publicKey: pagseguro_connect_public_key,
                holder: holder_name,
                number: $('#rm_pagseguro_connect-card-number').val().replace(/\s/g, ''),
                expMonth: $('#rm_pagseguro_connect-card-expiry').val().split('/')[0].replace(/\s/g, ''),
                expYear: '20' + $('#rm_pagseguro_connect-card-expiry').val().split('/')[1].replace(/\s/g, ''),
                securityCode: $('#rm_pagseguro_connect-card-cvc').val().replace(/\s/g, ''),
            });
        } catch (e) {
            alert("Erro ao criptografar o cartão.\nVerifique se os dados digitados estão corretos.");
            return false;
        } 
        if (card.hasErrors) {
            let error_codes = [
                {code: 'INVALID_NUMBER', message:'Número do cartão inválido'},
                {code: 'INVALID_SECURITY_CODE', message:'CVV Inválido. Você deve passar um valor com 3, 4 ou mais dígitos.'},
                {code: 'INVALID_EXPIRATION_MONTH', message:'Mês de expiração incorreto. Passe um valor entre 1 e 12.'},
                {code: 'INVALID_EXPIRATION_YEAR', message:'Ano de expiração inválido.'},
                {code: 'INVALID_PUBLIC_KEY', message:'Chave Pública inválida.'},
                {code: 'INVALID_HOLDER', message:'Nome do titular do cartão inválido.'},
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
            return false;
        }
        $('#rm_pagseguro_connect-card-encrypted').val(card.encryptedCard);
        
        //obfuscates cvv
        $('#rm_pagseguro_connect-card-cvc').val('***');
        //obfuscates card number between 7th and last 4 digits
        let card_number = $('#rm_pagseguro_connect-card-number').val();
        let obfuscated_card_number = '';
        for (let i = 0; i < card_number.length; i++) {
            if (i > 5 && i < card_number.length - 4)
                obfuscated_card_number += '*';
            else
                obfuscated_card_number += card_number[i];
        }
        $('#rm_pagseguro_connect-card-number').val(obfuscated_card_number);
        
        /*endregion*/
    });
    
});

jQuery(document.body).on('init_checkout', ()=>{
    jQuery(document).on('keyup change paste', '#rm_pagseguro_connect-card-number', (e)=>{
        let cardNumber = jQuery(e.target).val();
        let ccBin = cardNumber.replace(/\s/g, '').substring(0, 6);
        if (ccBin !== window.ps_cc_bin && ccBin.length === 6) {
            window.ps_cc_bin = ccBin;
            jQuery(document.body).trigger('update_installments');
        }
    });
});

jQuery(document.body).on('update_installments', ()=>{
    //if success, update the installments select with the response
    //if error, show error message
    let ccBin = window.ps_cc_bin ?? '411111';
    let total = jQuery('.order-total bdi').html();
    //extact amount from total, removing html elements
    total = total.replace(/<[^>]*>?/gm, '');
    //remove ,
    total = total.replace(/,/g, '');
    //replace , with .
    total = total.replace(/\./g, ',');
    //remove non numbers and . ,
    total = total.replace(/[^0-9,]/g, '');
    

    //convert to cents
    let orderTotal = parseFloat(total).toFixed(2) * 100;
    // let maxInstallments = jQuery('#rm_pagseguro_connect-card-installments').attr('max_installments');
    let url = ajax_object.ajax_url;
    jQuery.ajax({
        url: url,
        method: 'POST',
        data: {
            cc_bin: ccBin,
            action: 'ps_get_installments',
        },
        success: (response)=>{
            // debugger;
            let select = jQuery('#rm_pagseguro_connect-card-installments');
            select.empty();
            for (let i = 0; i < response.length; i++) {
                let option = jQuery('<option></option>');
                option.attr('value', response[i].quantity);
                let text = response[i].installments + 'x de R$ ' + response[i].installment_amount;
                let additional_text = ' (sem juros)';
                if (response[i].interest_free === false)
                    additional_text = ' (Total R$ ' + response[i].total_amount + ')';
                
                option.text(text + additional_text);
                select.append(option);
            }
        },
        error: (response)=>{
            alert('Erro ao calcular parcelas. Verifique os dados do cartão e tente novamente.');
            console.info('Lojista: Verifique os logs em WooCommerce > Status > Logs ' +
                'para ver os possíveis problemas na obtenção das parcelas.');
        }
    });
});