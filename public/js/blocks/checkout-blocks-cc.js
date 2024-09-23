import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { getSetting } from '@woocommerce/settings';
import { useEffect } from '@wordpress/element';
import { decodeEntities } from '@wordpress/html-entities';

import PaymentUnavailable from './components/PaymentUnavailable';
import PaymentInstructions from "./components/PaymentInstructions";

const settings = getSetting('rm-pagbank-cc_data', {});
const label = decodeEntities( settings.title ) || window.wp.i18n.__( 'Cartão de Crédito Gateway', 'rm-pagbank-pix' );

/**
 * Label component
 *
 * @param {*} props Props from payment API.
 */
const Label = ( props ) => {
    const { PaymentMethodLabel } = props.components;
    return <PaymentMethodLabel text={ label } />;
};

/**
 * Content component
 */
const Content = () => {
    if (settings.paymentUnavailable) {
        return (
            <div className="rm-pagbank-cc">
                <PaymentUnavailable />
            </div>
        );
    }

    return (
        <div className="rm-pagbank-cc">
            <p>TESTE</p>
            <input type="hidden" name="ps_connect_method" value="cc"/>
        </div>
    );
};

const Rm_Pagbank_Cc_Block_Gateway = {
    name: 'rm-pagbank-cc',
    label: <Label />,
    content: <Content />,
    edit: <Content />,
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
        features: settings?.supports ?? [],
    },
};

registerPaymentMethod( Rm_Pagbank_Cc_Block_Gateway );