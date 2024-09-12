const boletoSettings = window.wc.wcSettings.getSetting( 'rm-pagbank-boleto_data', {} );
const boletoLabel = window.wp.htmlEntities.decodeEntities( boletoSettings.title ) || window.wp.i18n.__( 'Boleto Gateway', 'rm-pagbank' );
const BoletoContent = () => {
    return window.wp.htmlEntities.decodeEntities( boletoSettings.description || '' );
};
const Rm_Pagbank_Boleto_Block_Gateway = {
    name: 'rm-pagbank-boleto',
    label: boletoLabel,
    content: Object( window.wp.element.createElement )( BoletoContent, null ),
    edit: Object( window.wp.element.createElement )( BoletoContent, null ),
    canMakePayment: () => true,
    ariaLabel: boletoLabel,
    supports: {
        features: boletoSettings.supports,
    },
};
window.wc.wcBlocksRegistry.registerPaymentMethod( Rm_Pagbank_Boleto_Block_Gateway );