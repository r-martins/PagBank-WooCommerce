import React, { useState } from 'react';
import InputMask from 'react-input-mask';

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
        <div>
            <label htmlFor="document">Documento:</label>
            <InputMask
                id="rm-pagbank-customer-document"
                name="rm-pagbank-customer-document"
                mask={mask}
                maskChar={null}
                onKeyDown={handleMask} >
                {
                    (inputProps) => <input {...inputProps} type="text" />
                }
            </InputMask>
        </div>
    );
};

export default CustomerDocumentField;