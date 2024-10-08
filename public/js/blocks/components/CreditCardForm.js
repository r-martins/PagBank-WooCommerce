import React from 'react';
import { __, _n } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { getSetting } from '@woocommerce/settings';
import MaskedInput from './MaskedInput';
import InstallmentsOptions from './InstallmentsOptions';
const PaymentInstructions = () => {
    const settings = getSetting('rm-pagbank-cc_data', {});
    const defaultInstallments = settings.installments || [];
    const [creditCardNumber, setCreditCardNumber] = useState('');
    const [installments, setInstallments] = useState(defaultInstallments);

    let ccBin = '';
    useEffect( () => {
        console.debug('creditCardNumber', creditCardNumber);
        let ccBinNew = creditCardNumber.replace(/\D/g, '').substring(0, 6);
        //get only digits from credit card number
        console.debug('ccBin', ccBin);
        console.debug('ccBinNew', ccBinNew);

        if (ccBinNew === ccBin || ccBinNew.length < 6) {
            return;
        }
        console.debug('ccBinNew === ccBin');
        ccBin = ccBinNew;
        let url = settings.ajax_url;

        jQuery.ajax({
            url: url,
            method: 'POST',
            data: {
                cc_bin: ccBin,
                nonce: settings.rm_pagbank_nonce,
                action: 'ps_get_installments',
            },
            success: (response)=>{
                console.debug('response ajax', response);
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
            />

            <MaskedInput
                name="rm-pagbank-card-number"
                type="tel"
                className="input-text"
                label={__('Número do cartão', 'rm-pagbank')}
                mask="9999 9999 9999 9999"
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
            />

            <MaskedInput
                name="rm-pagbank-card-cvc"
                type="tel"
                className="input-text"
                rowClass="form-row-last"
                label={__('Código do cartão', 'rm-pagbank')}
                mask="9999"
                placeholder="CVC"
            />

            <InstallmentsOptions
                installments={installments}
            />

        </div>
    )
};

export default PaymentInstructions;
