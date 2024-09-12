const pixSettings = window.wc.wcSettings.getSetting( 'rm-pagbank-pix_data', {} );
const pixLabel = window.wp.htmlEntities.decodeEntities( pixSettings.title ) || window.wp.i18n.__( 'Pix Gateway', 'rm-pagbank-pix' );
const PixContent = () => {
    return window.wp.htmlEntities.decodeEntities( pixSettings.description || '' );
};
const Rm_Pagbank_Pix_Block_Gateway = {
    name: 'rm-pagbank-pix',
    label: pixLabel,
    content: Object( window.wp.element.createElement )( PixContent, null ),
    edit: Object( window.wp.element.createElement )( PixContent, null ),
    canMakePayment: () => true,
    ariaLabel: pixLabel,
    supports: {
        features: pixSettings.supports,
    },
};
window.wc.wcBlocksRegistry.registerPaymentMethod( Rm_Pagbank_Pix_Block_Gateway );