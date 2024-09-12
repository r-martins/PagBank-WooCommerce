const ccSettings = window.wc.wcSettings.getSetting( 'rm-pagbank-cc_data', {} );
const ccLabel = window.wp.htmlEntities.decodeEntities( ccSettings.title ) || window.wp.i18n.__( 'Cartão de Crédito Gateway', 'rm-pagbank' );
const CcContent = () => {
    return window.wp.htmlEntities.decodeEntities( ccSettings.description || '' );
};
const Rm_Pagbank_Cc_Block_Gateway = {
    name: 'rm-pagbank-cc',
    label: ccLabel,
    content: Object( window.wp.element.createElement )( CcContent, null ),
    edit: Object( window.wp.element.createElement )( CcContent, null ),
    canMakePayment: () => true,
    ariaLabel: ccLabel,
    supports: {
        features: ccSettings.supports,
    },
};
window.wc.wcBlocksRegistry.registerPaymentMethod( Rm_Pagbank_Cc_Block_Gateway );