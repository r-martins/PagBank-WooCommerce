import React, { useState } from 'react';
import { __, _n } from '@wordpress/i18n';
import MaskedInput from './MaskedInput';
const CustomerDocumentField = () => {
    const [mask, setMask] = useState('999.999.999-999');

    const handleMask = (e) => {
        const value = e.target.value.replace(/\D/g, '');
        if (value.length < 11) {
            return setMask('999.999.999-99');
        }

        setMask('99.999.999/9999-99');
    };

    return (
        <MaskedInput
            name="rm-pagbank-customer-document"
            type="text"
            className="input-text"
            label={__('CPF/CNPJ', 'rm-pagbank')}
            placeholder="documento do pagador"
            mask={mask}
            maskChar={null}
            onKeyDown={handleMask}
            required
        />
    );
};

export default CustomerDocumentField;