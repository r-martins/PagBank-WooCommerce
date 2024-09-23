import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { getSetting } from '@woocommerce/settings';
import { useEffect } from '@wordpress/element';
import { decodeEntities } from '@wordpress/html-entities';
import { sprintf, __ } from '@wordpress/i18n';

import PaymentInstructions from './components/PaymentInstructions';
import PaymentUnavailable from './components/PaymentUnavailable';

const settings = getSetting('rm-pagbank-pix_data', {});
const label = decodeEntities( settings.title ) || window.wp.i18n.__( 'Pix Gateway', 'rm-pagbank' );

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
    let instructions = settings.instructions;
    let expiry = settings.expirationTime;
    let expiryText = sprintf( __( 'Você terá %s para pagar com seu código PIX.', 'rm-pagbank' ), expiry );
    let discountText = settings.hasDiscount ? settings.discountText : '';

    instructions = `${instructions} <br> ${expiryText} <br> ${discountText}`;

    if (settings.paymentUnavailable) {
        return (
            <div className="rm-pagbank-pix">
                <PaymentUnavailable />
            </div>
        );
    }

    return (
        <div className="rm-pagbank-pix">
            <PaymentInstructions
                checkoutClass={'pix'}
                instructions={instructions}
            />
            <input type="hidden" name="ps_connect_method" value="pix"/>
        </div>
    );
};

const Rm_Pagbank_Pix_Block_Gateway = {
    name: 'rm-pagbank-pix',
    label: <Label />,
    content: <Content />,
    edit: <Content />,
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
        features: settings?.supports ?? [],
    },
};

registerPaymentMethod( Rm_Pagbank_Pix_Block_Gateway );