console.debug('PagBank Connect BLOCK loaded');
// import { sprintf, __ } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';
// const { registerPaymentMethod } = window.wc.wcBlocksRegistry
// const { getSetting } = window.wc.wcSettings
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { getSetting } from '@woocommerce/settings';


const settings = getSetting( 'rm-pagbank-boleto_data', {} )

// const label = decodeEntities( settings.title )
const defaultLabel = __(
    'TESTE Payments',
    'woo-gutenberg-products-block'
);
const label = decodeEntities( settings.title ) || defaultLabel;

const Content = () => {
    return decodeEntities( settings.description || '' )
}

const Label = ( props ) => {
    const { PaymentMethodLabel } = props.components
    return <PaymentMethodLabel text={ label } />
}

const Connect = {
    name: "rm-pagbank-boleto",
    label: <Label />,
    content: <Content />,
    edit: <Content />,
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
        features: settings.supports,
    },
};

registerPaymentMethod( Connect );


// import { registerPaymentMethod } from '@woocommerce/blocks-registry';
//
// const config = {
//     name: 'rm-pagbank-boleto',
//     label: 'Boleto PagBank',
//     placeOrderButtonLabel: 'Pagar com Boleto PagBank',
//     content: <div>Conteúdo do método de pagamento Boleto PagBank</div>,
//     edit: <div>Conteúdo do método de pagamento Boleto PagBank</div>,
//     canMakePayment: () => true,
//     paymentMethodId: 'rm-pagbank-boleto',
// };
//
// registerPaymentMethod( config );