import React, { useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import InstallmentsOptions from './InstallmentsOptions';
import RecurringInfo from './RecurringInfo';

const SavedCardInstallments = (props) => {
    const { token, emitResponse, eventRegistration, billing } = props;
    const total = billing?.cartTotal?.value || 0;
    const showInstallments = Number(total) > 0;
    
    // Safety check: ensure eventRegistration exists
    if (!eventRegistration) {
        return null;
    }
    
    const { onPaymentSetup, onCheckoutValidation: onCheckoutValidation, onCheckoutSuccess, onCheckoutFail } = eventRegistration;
    // Example: fetch installments by saved card BIN
    const [installments, setInstallments] = useState([]);
    const [selectedInstallment, setSelectedInstallment] = useState('1'); // default to 1 installment

    // Function to fetch installments based on BIN and total amount
    const fetchInstallments = (bin, total) => {
        if (!bin || bin.length < 6 || !total || Number(total) <= 0) return;
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

    // Function to get the CC BIN for a specific token
    const getTokenBin = (tokenId) => {
        const savedTokens = window.wc.wcSettings.getSetting('rm-pagbank-cc_data', {}).savedTokens || [];
        const tokenData = savedTokens.find(t => t.id == tokenId);
        return tokenData ? tokenData.cc_bin : '555566'; // fallback to default BIN
    };

    // Function to get the customer document for a specific token
    const getTokenCustomerDocument = (tokenId) => {
        const savedTokens = window.wc.wcSettings.getSetting('rm-pagbank-cc_data', {}).savedTokens || [];
        const tokenData = savedTokens.find(t => t.id == tokenId);
        return tokenData ? tokenData.customer_document : '';
    };

    useEffect(() => {
        // When selecting the token, fetch installments using the token's BIN and total checkout amount
        const total = billing?.cartTotal?.value || 0;
        const tokenBin = getTokenBin(token);
        fetchInstallments(tokenBin, total);
    }, [token, billing?.cartTotal?.value]);

    // Integrate with checkout blocks to send the selected installment
    useEffect(() => {
        if (!eventRegistration || !emitResponse) return;

            const unsubscribe = onPaymentSetup(() => { 
            const customerDocument = getTokenCustomerDocument(token);
            
            const paymentMethodData = {
                'rm-pagbank-card-installments-token': selectedInstallment,
                'token': token,
                'payment_method': token,
                'payment_method': 'rm-pagbank-cc',
                'wc-rm-pagbank-cc-payment-token': token,
                'isSavedToken': true,
            };

            // Add customer document if available
            if (customerDocument) {
                paymentMethodData['rm-pagbank-customer-document'] = customerDocument;
            }

            return {
                type: emitResponse.responseTypes.SUCCESS,
                meta: {
                    paymentMethodData: paymentMethodData,
                },
            };
        } );

        return () => {
            unsubscribe();
        }
        
    }, [selectedInstallment, token, onPaymentSetup]);

    const settings = window.wc.wcSettings.getSetting('rm-pagbank-cc_data', {});
    if (!showInstallments) {
        return settings.isCartRecurring ? <RecurringInfo /> : null;
    }
    return (
        <div style={{ marginTop: '1em' }}>
            <InstallmentsOptions
                installments={installments}
                onChange={e => setSelectedInstallment(e.target.value)}
            />
            {settings.isCartRecurring ? <RecurringInfo /> : null}
        </div>
    );
};

export default SavedCardInstallments;
