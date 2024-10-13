import React from 'react';
import { __, _n } from '@wordpress/i18n';
import MaskedInput from './MaskedInput';
const CustomerDocumentField = () => {
    return (
        <MaskedInput
            name="rm-pagbank-customer-document"
            type="text"
            className="input-text"
            label={__('CPF/CNPJ', 'rm-pagbank')}
            placeholder="documento do pagador"
            mask={["999.999.999-99", "99.999.999/9999-99"]}
            required
        />
    );
};

export default CustomerDocumentField;