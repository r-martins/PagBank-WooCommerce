import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { getSetting } from '@woocommerce/settings';
import { useEffect, useState } from '@wordpress/element';
import { decodeEntities } from '@wordpress/html-entities';
import { __, _n } from '@wordpress/i18n';

import PaymentUnavailable from './components/PaymentUnavailable';
import CreditCardForm from "./components/CreditCardForm";
import CustomerDocumentField from './components/CustomerDocumentField';

const settings = getSetting('rm-pagbank-cc_data', {});
const label = decodeEntities( settings.title ) || window.wp.i18n.__( 'PagBank Connect Cartão de Crédito', 'rm-pagbank-pix' );

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
const Content = ( props ) => {
    if (settings.paymentUnavailable) {
        return (
            <div className="rm-pagbank-cc">
                <PaymentUnavailable />
            </div>
        );
    }

    const { eventRegistration, emitResponse } = props;
    const { onPaymentSetup, onCheckoutBeforeProcessing, onCheckoutSuccess, onCheckoutFail } = eventRegistration;

    let encryptedCard, card3d = null;

    // console.debug('props', props)
    // console.debug('eventRegistration', eventRegistration)

    // 4000000000002701
    useEffect( () => {
        const unsubscribe = onPaymentSetup(() => {
            const customerDocumentValue = document.getElementById('rm-pagbank-customer-document').value;
            const installments = document.getElementById('rm-pagbank-card-installments').value || 1;
            const ccNumber = document.getElementById('rm-pagbank-card-number').value;
            const ccHolderName = document.getElementById('rm-pagbank-card-holder-name').value;

            return {
                type: emitResponse.responseTypes.SUCCESS,
                meta: {
                    paymentMethodData: {
                        'payment_method': 'cc',
                        'rm-pagbank-customer-document': customerDocumentValue.replace(/\D/g, ''),
                        'rm-pagbank-card-encrypted': encryptedCard,
                        'rm-pagbank-card-installments': installments,
                        'rm-pagbank-card-number': ccNumber.replace(/\D/g, ''),
                        'rm-pagbank-card-holder-name': ccHolderName,
                        // 'rm-pagbank-card-3d': card3d
                    },
                },
            };
        } );

        return () => {
            unsubscribe();
        };
    }, [onPaymentSetup] );

    useEffect( () => {
        const encryptCard = function () {
            let card, cc_number, cc_cvv;
            //replace trim and remove duplicated spaces from holder name
            let holder_name = jQuery('#rm-pagbank-card-holder-name').val().trim().replace(/\s+/g, ' ');
            try {
                cc_number = jQuery('#rm-pagbank-card-number').val().replace(/\s/g, '');
                cc_cvv = jQuery('#rm-pagbank-card-cvc').val().replace(/\s/g, '');
                card = PagSeguro.encryptCard({
                    publicKey: settings.publicKey,
                    holder: holder_name,
                    number: cc_number,
                    expMonth: jQuery('#rm-pagbank-card-expiry').val().split('/')[0].replace(/\s/g, ''),
                    expYear: '20' + jQuery('#rm-pagbank-card-expiry').val().split('/')[1].slice(-2).replace(/\s/g, ''),
                    securityCode: cc_cvv,
                });
            } catch (e) {
                alert("Erro ao criptografar o cartão.\nVerifique se os dados digitados estão corretos.");
                return false;
            }
            if (card.hasErrors) {
                let error_codes = [
                    {code: 'INVALID_NUMBER', message: 'Número do cartão inválido'},
                    {
                        code: 'INVALID_SECURITY_CODE',
                        message: 'CVV Inválido. Você deve passar um valor com 3, 4 ou mais dígitos.'
                    },
                    {
                        code: 'INVALID_EXPIRATION_MONTH',
                        message: 'Mês de expiração incorreto. Passe um valor entre 1 e 12.'
                    },
                    {code: 'INVALID_EXPIRATION_YEAR', message: 'Ano de expiração inválido.'},
                    {code: 'INVALID_PUBLIC_KEY', message: 'Chave Pública inválida.'},
                    {code: 'INVALID_HOLDER', message: 'Nome do titular do cartão inválido.'},
                ]
                //extract error message
                let error = '';
                for (let i = 0; i < card.errors.length; i++) {
                    //loop through error codes to find the message
                    for (let j = 0; j < error_codes.length; j++) {
                        if (error_codes[j].code === card.errors[i].code) {
                            error += error_codes[j].message + '\n';
                            break;
                        }
                    }
                }
                alert('Erro ao criptografar cartão.\n' + error);
                throw new Error('Erro ao criptografar cartão');
                // return false;
            }

            // jQuery('#rm-pagbank-card-encrypted').val(card.encryptedCard);
            // window.ps_cc_has_changed = false;
            // window.ps_cc_number = cc_number;
            // window.ps_cc_cvv = cc_cvv;
            return card.encryptedCard;
        }

        const unsubscribe = onCheckoutBeforeProcessing(() => {
            console.debug('PagBank: submit');
            encryptedCard = encryptCard();
            if (encryptedCard === false) {
                console.debug('PagBank: error on encryptCard');
                return false;
            }
        } );

        return () => {
            unsubscribe();
        };
    }, [onCheckoutBeforeProcessing] );

    return (
        <div className="rm-pagbank-cc">
            <CreditCardForm />
            <CustomerDocumentField />
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