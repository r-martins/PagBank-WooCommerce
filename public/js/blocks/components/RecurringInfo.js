import React, { useEffect, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { getSetting } from '@woocommerce/settings';

const { useSelect } = window.wp.data;
const { checkoutStore } = window.wc?.wcBlocksData || {};

/**
 * Recurring terms must refresh when Blocks recalculates the cart (shipping, coupon, tax).
 * Server settings.recurringTerms is only the initial snapshot.
 */
const RecurringInfo = ({ billing }) => {
    const settings = getSetting('rm-pagbank-cc_data', {});
    const [termsHtml, setTermsHtml] = useState(settings.recurringTerms || '');

    const isCalculating = useSelect((select) => {
        if (!checkoutStore) {
            return false;
        }
        try {
            return select(checkoutStore)?.isCalculating() || false;
        } catch (e) {
            return false;
        }
    });

    useEffect(() => {
        if (!settings.isCartRecurring || isCalculating) {
            return;
        }
        if (!settings.ajax_url || !settings.rm_pagbank_nonce) {
            return;
        }

        const controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
        const body = new URLSearchParams({
            action: 'ps_get_recurring_terms',
            payment_method: 'creditcard',
            nonce: settings.rm_pagbank_nonce,
        });

        fetch(settings.ajax_url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body.toString(),
            signal: controller?.signal,
        })
            .then((response) => response.json())
            .then((payload) => {
                if (payload?.success && typeof payload?.data?.terms === 'string') {
                    setTermsHtml(payload.data.terms);
                }
            })
            .catch(() => {
                // Keep last known terms on network/abort errors.
            });

        return () => {
            controller?.abort();
        };
    }, [billing?.cartTotal?.value, isCalculating, settings.ajax_url, settings.isCartRecurring, settings.rm_pagbank_nonce]);

    if (!termsHtml) {
        return null;
    }

    return (
        <div>
            <p><strong>{__('Pagamento Recorrente', 'pagbank-connect')}</strong></p>
            <p dangerouslySetInnerHTML={{ __html: termsHtml }}></p>
        </div>
    );
};

export default RecurringInfo;
