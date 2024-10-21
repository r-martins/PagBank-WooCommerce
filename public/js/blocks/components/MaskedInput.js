import React from 'react';
import { useForm } from 'react-hook-form';
import { useHookFormMask } from 'use-mask-input';

const MaskedInput = ({ name, label, mask, rowClass = "form-row-wide", ...props }) => {
    const { register } = useForm();
    const registerWithMask = useHookFormMask(register);
    return (
        <div>
            <p className={`form-row ${rowClass}`}>
                <label htmlFor={name}>{label}</label>
                <input
                    {...registerWithMask(name, mask, {
                        required: true,
                        showMaskOnHover: false
                    })}
                    id={name}
                    name={name}
                    {...props} />
            </p>
        </div>
    );
};

export default MaskedInput;