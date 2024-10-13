import React from 'react';
import { __, _n } from '@wordpress/i18n';

const InstallmentsOptions = ({ installments, ...props }) => {
    return (
        <div>
            <p className="form-row form-row-full">
                <label htmlFor="rm-pagbank-card-installments">
                    {__('Parcelas', 'rm-pagbank')}
                </label>
                <select
                    id="rm-pagbank-card-installments"
                    name="rm-pagbank-card-installments"
                    className="input-text wc-credit-card-form-card-installments"
                    {...props}
                >
                    {installments === undefined || installments === null ? (
                        <option value="">{__('Informe um número de cartão', 'pagbank-connect')}</option>
                    ) : (
                        Object.keys(installments).map((key, index) => {
                            const installment = installments[key];
                            let infoText = installment.interest_free ? 'sem acréscimo' : `Total: R$ ${installment.total_amount}`;
                            return (
                                <option
                                    key={index}
                                    value={installment.installments}
                                >
                                    {installment.installments}x de R$ {installment.installment_amount} ({infoText})
                                </option>
                            );
                        })
                    )}
                </select>
            </p>
        </div>
    );
};

export default InstallmentsOptions;