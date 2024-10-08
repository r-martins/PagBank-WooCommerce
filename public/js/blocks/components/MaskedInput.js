import React from 'react';
import InputMask from 'react-input-mask';

const MaskedInput = ({ name, label, mask, rowClass = "form-row-wide", ...props }) => {
    return (
        <div>
            <p className={`form-row ${rowClass}`}>
                <label htmlFor={name}>{label}</label>
                <InputMask
                    id={name}
                    name={name}
                    mask={mask}
                    {...props} />
            </p>
        </div>
    );
};

export default MaskedInput;