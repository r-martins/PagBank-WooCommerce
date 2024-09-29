import React from 'react';
import InputMask from 'react-input-mask';
const PaymentInstructions = ({fields}) => (
    <div>
        <InputMask mask="(99) 99999-9999" maskChar={null}>
            {(inputProps) => <input {...inputProps} type="text" />}
        </InputMask>
        {Object.keys(fields).map((key, index) => (
            <div key={index} dangerouslySetInnerHTML={{ __html: fields[key] }} />
        ))}
    </div>
);

export default PaymentInstructions;
