import React, { useState } from 'react';
import { __ } from '@wordpress/i18n';
const RetryInput = () => {
    const [isChecked, setIsChecked] = useState(true);

    const handleCheckboxChange = () => {
        setIsChecked(!isChecked);
    };

    return (
        <div className="rm-pagbank-retry-select">
            <input
                type="checkbox"
                id="rm-pagbank-card-retry-with-3ds"
                name="rm-pagbank-card-retry-with-3ds"
                className="rm-pagbank-checkbox"
                value="1"
                checked={isChecked}
                onChange={handleCheckboxChange}
            />
            <label htmlFor="rm-pagbank-card-retry-with-3ds">
                {__('Tentar novamente com Validação 3DS', 'pagbank-connect')}
            </label>
        </div>
    );
};

export default RetryInput;