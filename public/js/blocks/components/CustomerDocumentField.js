import React, { useCallback } from 'react';
import { __ } from '@wordpress/i18n';
import { formatTaxIdForDisplay } from '../../shared/tax-id';

/** Masked display length: XX.XXX.XXX/XXXX-DV */
const TAX_ID_DISPLAY_MAX_LENGTH = 18;

const CustomerDocumentField = () => {
    const handleInput = useCallback((event) => {
        const input = event.target;
        const formatted = formatTaxIdForDisplay(input.value);
        if (input.value !== formatted) {
            input.value = formatted;
        }
    }, []);

    return (
        <div>
            <p className="form-row form-row-wide">
                <label htmlFor="rm-pagbank-customer-document">{__('CPF/CNPJ', 'rm-pagbank')}</label>
                <input
                    id="rm-pagbank-customer-document"
                    name="rm-pagbank-customer-document"
                    type="text"
                    inputMode="text"
                    autoComplete="off"
                    autoCapitalize="characters"
                    className="input-text"
                    placeholder="documento do pagador"
                    maxLength={TAX_ID_DISPLAY_MAX_LENGTH}
                    required
                    onInput={handleInput}
                    onChange={handleInput}
                />
            </p>
        </div>
    );
};

export default CustomerDocumentField;
