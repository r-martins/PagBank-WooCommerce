import React, { useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import InstallmentsOptions from './InstallmentsOptions';

const SavedCardInstallments = (props) => {
    const { token, emitResponse, eventRegistration, billing } = props;
     const { onPaymentSetup, onCheckoutValidation: onCheckoutValidation, onCheckoutSuccess, onCheckoutFail } = eventRegistration;
    // Example: fetch installments by saved card BIN
    const [installments, setInstallments] = useState([]);
    const [selectedInstallment, setSelectedInstallment] = useState('1'); // default to 1 installment

    // Function to fetch installments based on BIN and total amount
    const fetchInstallments = (bin, total) => {
        if (!bin || bin.length < 6) return;
        jQuery.ajax({
            url: window.wc.wcSettings.getSetting('rm-pagbank-cc_data', {}).ajax_url,
            method: 'POST',
            data: {
                cc_bin: bin,
                amount: total,
                nonce: window.wc.wcSettings.getSetting('rm-pagbank-cc_data', {}).rm_pagbank_nonce,
                action: 'ps_get_installments',
            },
            success: (response) => {
                setInstallments(response);
            },
            error: () => {
                alert('Erro ao calcular parcelas. Verifique os dados do cartão e tente novamente.');
            }
        });
    };

    useEffect(() => {
        // When selecting the token, fetch installments using the default BIN and total checkout amount
        const total = billing?.cartTotal?.value || 0;
        fetchInstallments('555566', total);
    }, [token, billing?.cartTotal?.value]);

    // Integrate with checkout blocks to send the selected installment
    useEffect(() => {
        if (!eventRegistration || !emitResponse) return;

            const unsubscribe = onPaymentSetup(() => { 
            return {
                type: emitResponse.responseTypes.SUCCESS,
                meta: {
                    paymentMethodData: {
                        'rm-pagbank-card-installments-token': selectedInstallment,
                        'token': token,
                        'payment_method': token,
                        'payment_method': 'rm-pagbank-cc',
                        'wc-rm-pagbank-cc-payment-token': token,
                        'isSavedToken': true,
                    },
                },
            };
        } );

        return () => {
            unsubscribe();
        }
        
    }, [selectedInstallment, token, onPaymentSetup]);

    return (
        <div style={{ marginTop: '1em' }}>
            <InstallmentsOptions
                installments={installments}
                onChange={e => setSelectedInstallment(e.target.value)}
            />
        </div>
    );
};

export default SavedCardInstallments;
