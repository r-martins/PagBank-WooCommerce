import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { getSetting } from '@woocommerce/settings';
import { useEffect } from '@wordpress/element';
import { decodeEntities } from '@wordpress/html-entities';
import { __, _n } from '@wordpress/i18n';

import PaymentInstructions from './components/PaymentInstructions';
import PaymentUnavailable from './components/PaymentUnavailable';

const settings = getSetting('rm-pagbank-boleto_data', {});
const label = decodeEntities( settings.title ) || window.wp.i18n.__( 'Boleto Gateway', 'rm-pagbank-pix' );

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
    let expiryText = expiry === 1 ? __('Seu boleto vencerá amanhã.', 'rm-pagbank') : sprintf( __( 'Seu boleto vence em %d dias.', 'rm-pagbank' ), expiry );
    let discountText = settings.hasDiscount ? settings.discountText : '';

    instructions = `${instructions} <br> ${expiryText} <br> ${discountText}`;

    if (settings.paymentUnavailable) {
        return (
            <div className="rm-pagbank-boleto">
                <PaymentUnavailable />
            </div>
        );
    }

    return (
        <div className="rm-pagbank-boleto">
            <PaymentInstructions
                checkoutClass={'boleto'}
                instructions={instructions}
            />
            <input type="hidden" name="ps_connect_method" value="boleto"/>
        </div>
    );
};

const Rm_Pagbank_Boleto_Block_Gateway = {
    name: 'rm-pagbank-boleto',
    label: <Label />,
    content: <Content />,
    edit: <Content />,
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
        features: settings?.supports ?? [],
    },
};

registerPaymentMethod( Rm_Pagbank_Boleto_Block_Gateway );