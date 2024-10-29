import React from 'react';
import { useEffect, useState, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { getSetting } from '@woocommerce/settings';
import MaskedInput from './MaskedInput';
import InstallmentsOptions from './InstallmentsOptions';
const PaymentInstructions = () => {
    const settings = getSetting('rm-pagbank-cc_data', {});
    const defaultInstallments = settings.defaultInstallments || [];
    const [creditCardNumber, setCreditCardNumber] = useState('');
    const [ccBin, setCcBin] = useState('');
    const [cardBrand, setCardBrand] = useState('');
    const prevCcBinRef = useRef();
    const [installments, setInstallments] = useState(defaultInstallments);
    window.ps_cc_installments = installments;

    useEffect(() => {
        prevCcBinRef.current = ccBin;
    }, [ccBin]);

    const prevCcBin = prevCcBinRef.current;

    useEffect( () => {
        // Detect card brand
        const detectCardBrand = (number) => {
            const visaRegex = /^4[0-9]{0,}$/;
            const mastercardRegex = /^(?:5[1-5][0-9]{2}|222[1-9]|22[3-9][0-9]|2[3-6][0-9]{2}|27[01][0-9]|2720)[0-9]{12}$/;
            const amexRegex = /^3([47]\d*)?$/;
            const dinersRegex = /^(3(0[0-5]|095|6|[8-9]))\d*$/;
            const jcbRegex = /^(?:2131|1800|35\d{3})\d{0,}$/;
            const auraRegex = /^5078\d*$/;
            const hiperRegex = /^((606282)|(637095)|(637568)|(637599)|(637609)|(637612))\d*$/;
            const eloRegex = new RegExp(
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

            if (mastercardRegex.test(number)) return 'mastercard';
            if (amexRegex.test(number)) return 'amex';
            if (dinersRegex.test(number)) return 'diners';
            if (jcbRegex.test(number)) return 'jcb';
            if (auraRegex.test(number)) return 'aura';
            if (hiperRegex.test(number)) return 'hipercard';
            if (eloRegex.test(number)) return 'elo';
            if (visaRegex.test(number)) return 'visa';
            
            return '';
        };

        setCardBrand(detectCardBrand(creditCardNumber.replace(/\D/g, '')));

        if (settings.isCartRecurring === true) {
            return;
        }

        if (creditCardNumber.replace(/\D/g, '').length < 6) {
            return;
        }

        let ccBinNew = creditCardNumber.replace(/\D/g, '').substring(0, 6);
        if (ccBinNew === prevCcBin) {
            return;
        }

        setCcBin(ccBinNew);

        let url = settings.ajax_url;

        jQuery.ajax({
            url: url,
            method: 'POST',
            data: {
                cc_bin: ccBinNew,
                nonce: settings.rm_pagbank_nonce,
                action: 'ps_get_installments',
            },
            success: (response)=>{
                window.ps_cc_installments = response;
                setInstallments(response);
            },
            error: (response)=>{
                alert('Erro ao calcular parcelas. Verifique os dados do cartão e tente novamente.');
                console.info('Lojista: Verifique os logs em WooCommerce > Status > Logs ' +
                    'para ver os possíveis problemas na obtenção das parcelas. Note que cartões de teste falharão ' +
                    'na maioria dos casos.');
            }
        });

    }, [creditCardNumber] );

    return (
        <div>

            <MaskedInput
                name="rm-pagbank-card-holder-name"
                type="text"
                className="input-text"
                label={__('Titular do Cartão', 'rm-pagbank')}
                mask=""
                placeholder="como gravado no cartão"
                onKeyDown={e => e.target.value = e.target.value.toUpperCase()}
            />

            <MaskedInput
                name="rm-pagbank-card-number"
                type="text"
                className={'input-text card-number-input ' + cardBrand}
                label={__('Número do cartão', 'rm-pagbank')}
                mask={["9999 999999 99999", "9999 9999 9999 9999"]}
                placeholder="•••• •••• •••• ••••"
                onChange={e => setCreditCardNumber(e.target.value)}
            />

            <MaskedInput
                name="rm-pagbank-card-expiry"
                type="tel"
                className="input-text"
                rowClass="form-row-first"
                label={__('Validade (MM/AA)', 'rm-pagbank')}
                mask="99/99"
                placeholder="MM / AA"
                /*if first char is > 1, add 0 + typed char */
                onKeyDown={e => {
                    if (e.target.value === e.key + '_/__') {
                        if (parseInt(e.key) > 1) {
                            e.target.value = '0' + e.key;
                            e.preventDefault();
                        }
                }}}
            />

            <MaskedInput
                name="rm-pagbank-card-cvc"
                type="tel"
                className="input-text"
                rowClass="form-row-last"
                label={__('Código do cartão', 'rm-pagbank')}
                mask="999[9]"
                placeholder="CVC"
            />

            {installments.hasOwnProperty('error') || installments == 0 ? null :
                <InstallmentsOptions
                    installments={installments}
                />
            }

        </div>
    )
};

export default PaymentInstructions;
