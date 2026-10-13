import React from 'react';
import { useForm } from 'react-hook-form';
import { useHookFormMask } from 'use-mask-input';

const MaskedInput = ({ name, label, mask, maskOptions = {}, rowClass = "form-row-wide", onChange, ...props }) => {
    const { register } = useForm();
    const registerWithMask = useHookFormMask(register);
    const field = registerWithMask(name, mask, {
        required: true,
        showMaskOnHover: false,
        ...maskOptions,
    });
    const { onChange: registerOnChange, ...fieldRest } = field;
    return (
        <div>
            <p className={`form-row ${rowClass}`}>
                <label htmlFor={name}>{label}</label>
                <input
                    {...fieldRest}
                    id={name}
                    name={name}
                    {...props}
                    onChange={(e) => {
                        if (typeof registerOnChange === 'function') {
                            registerOnChange(e);
                        }
                        if (typeof onChange === 'function') {
                            onChange(e);
                        }
                    }}
                />
            </p>
        </div>
    );
};

export default MaskedInput;
