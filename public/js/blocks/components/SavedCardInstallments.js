import React, { useState, useEffect, useRef } from 'react';
import { __ } from '@wordpress/i18n';
import InstallmentsOptions from './InstallmentsOptions';
import RecurringInfo from './RecurringInfo';

const SavedCardInstallments = (props) => {
    const { token, emitResponse, eventRegistration, billing } = props;
    const total = billing?.cartTotal?.value || 0;
    const showInstallments = Number(total) > 0;
    const [installments, setInstallments] = useState([]);
    const [selectedInstallment, setSelectedInstallment] = useState('1'); // default to 1 installment
    const lastFetchKeyRef = useRef('');
    const xhrRef = useRef(null);
    const debounceRef = useRef(null);
    const onPaymentSetup = eventRegistration?.onPaymentSetup;

    const fetchInstallments = (bin, amountCents) => {
        if (!bin || String(bin).length < 6) return;
        const totalNum = Number(amountCents) || 0;
        if (totalNum <= 0) return;

        const fetchKey = String(bin) + '|' + String(totalNum) + '|token:' + String(token);
        if (lastFetchKeyRef.current === fetchKey) {
            return;
        }
        lastFetchKeyRef.current = fetchKey;

        if (debounceRef.current) {
            clearTimeout(debounceRef.current);
        }
        debounceRef.current = setTimeout(() => {
            if (xhrRef.current && xhrRef.current.readyState !== 4) {
                xhrRef.current.abort();
            }
            xhrRef.current = jQuery.ajax({
                url: window.wc.wcSettings.getSetting('rm-pagbank-cc_data', {}).ajax_url,
                method: 'POST',
                data: {
                    cc_bin: bin,
                    nonce: window.wc.wcSettings.getSetting('rm-pagbank-cc_data', {}).rm_pagbank_nonce,
                    action: 'ps_get_installments',
                },
                success: (response) => {
                    setInstallments(response);
                },
                error: (xhr) => {
                    if (xhr && xhr.statusText === 'abort') {
                        return;
                    }
                    if (lastFetchKeyRef.current === fetchKey) {
                        lastFetchKeyRef.current = '';
                    }
                    alert('Erro ao calcular parcelas. Verifique os dados do cartão e tente novamente.');
                }
            });
        }, 250);
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
        const cartTotal = billing?.cartTotal?.value || 0;
        const tokenBin = getTokenBin(token);
        fetchInstallments(tokenBin, cartTotal);
    }, [token, billing?.cartTotal?.value]);

    useEffect(() => {
        return () => {
            if (debounceRef.current) {
                clearTimeout(debounceRef.current);
            }
            if (xhrRef.current && xhrRef.current.readyState !== 4) {
                xhrRef.current.abort();
            }
        };
    }, []);

    // Integrate with checkout blocks to send the selected installment
    useEffect(() => {
        if (!eventRegistration || !emitResponse || typeof onPaymentSetup !== 'function') return;

        const unsubscribe = onPaymentSetup(() => {
            const customerDocument = getTokenCustomerDocument(token);

            const paymentMethodData = {
                'rm-pagbank-card-installments-token': selectedInstallment,
                'token': token,
                'payment_method': 'rm-pagbank-cc',
                'wc-rm-pagbank-cc-payment-token': token,
                'isSavedToken': true,
            };

            if (customerDocument) {
                paymentMethodData['rm-pagbank-customer-document'] = customerDocument;
            }

            return {
                type: emitResponse.responseTypes.SUCCESS,
                meta: {
                    paymentMethodData: paymentMethodData,
                },
            };
        });

        return () => {
            unsubscribe();
        };
    }, [selectedInstallment, token, onPaymentSetup, eventRegistration, emitResponse]);

    const settings = window.wc.wcSettings.getSetting('rm-pagbank-cc_data', {});
    if (!eventRegistration) {
        return null;
    }
    if (!showInstallments) {
        return settings.isCartRecurring ? <RecurringInfo billing={billing} /> : null;
    }
    return (
        <div style={{ marginTop: '1em' }}>
            <InstallmentsOptions
                installments={installments}
                onChange={e => setSelectedInstallment(e.target.value)}
            />
            {settings.isCartRecurring ? <RecurringInfo billing={billing} /> : null}
        </div>
    );
};

export default SavedCardInstallments;
