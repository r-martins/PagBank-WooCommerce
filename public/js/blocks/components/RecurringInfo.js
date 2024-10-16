import React from 'react';
import { __, _n } from '@wordpress/i18n';
import { getSetting } from '@woocommerce/settings';
const RecurringInfo = () => {
    const settings = getSetting('rm-pagbank-cc_data', {});

    return (
        <div>
            <p><strong>{__('Pagamento Recorrente', 'pagbank-connect')}</strong></p>
            <p dangerouslySetInnerHTML={{ __html: settings.recurringTerms }}></p>
        </div>
    );
};

export default RecurringInfo;