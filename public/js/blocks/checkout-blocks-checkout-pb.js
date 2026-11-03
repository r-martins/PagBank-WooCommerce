import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { getSetting } from '@woocommerce/settings';
import { useEffect } from '@wordpress/element';
import { decodeEntities } from '@wordpress/html-entities';
import { __ } from '@wordpress/i18n';

import PaymentInstructions from './components/PaymentInstructions';
import PaymentUnavailable from './components/PaymentUnavailable';

const settings = getSetting('rm-pagbank-checkout-pb_data', {});
const label = decodeEntities(settings.title) || window.wp.i18n.__('Checkout externo', 'pagbank-connect');

const Label = (props) => {
    const { PaymentMethodLabel } = props.components;
    return <PaymentMethodLabel text={label} />;
};

const Content = (props) => {
    const { eventRegistration, emitResponse } = props;
    const { onPaymentSetup } = eventRegistration;

    let instructions = __('Você será redirecionado ao Checkout PB para concluir o pagamento.', 'pagbank-connect');
    const expiry = settings.expirationTime;
    const expiryText = sprintf(__('Você terá %s para finalizar sua compra no checkout.', 'pagbank-connect'), expiry);
    const discountText = settings.hasDiscount ? settings.discountText : '';
    instructions = `${instructions} <br> ${expiryText} <br> ${discountText}`;

    if (settings.paymentUnavailable) {
        const unavailableMessage = settings.unavailableMessage
            || __('Não disponível para pedidos inferiores a R$1,00', 'pagbank-connect');
        useEffect(() => {
            const unsubscribe = onPaymentSetup(() => {
                return {
                    type: emitResponse.responseTypes.ERROR,
                    messageContext: emitResponse.noticeContexts.PAYMENTS,
                    message: unavailableMessage,
                };
            });
            return () => unsubscribe();
        }, [onPaymentSetup]);

        return (
            <div className="rm-pagbank-checkout-pb">
                <PaymentUnavailable message={unavailableMessage} />
            </div>
        );
    }

    return (
        <div className="rm-pagbank-checkout-pb">
            <PaymentInstructions
                checkoutClass={'checkout-pb'}
                instructions={instructions}
            />
            <input type="hidden" name="ps_connect_method" value="checkout_pb" />
        </div>
    );
};

registerPaymentMethod({
    name: 'rm-pagbank-checkout-pb',
    label: <Label />,
    content: <Content />,
    edit: <Content />,
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
        features: settings?.supports ?? [],
    },
});
