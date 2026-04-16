import {registerPaymentMethod} from '@woocommerce/blocks-registry';
import {getSetting} from '@woocommerce/settings';
import {useEffect, useRef} from '@wordpress/element';
import {decodeEntities} from '@wordpress/html-entities';
import {__} from '@wordpress/i18n';

import PaymentUnavailable from './components/PaymentUnavailable';
import CreditCardForm from "./components/CreditCardForm";
import CustomerDocumentField from './components/CustomerDocumentField';
import RecurringInfo from './components/RecurringInfo';
import RetryInput from './components/RetryInput';
import SavedCardInstallments from './components/SavedCardInstallments';
import SavedCreditCardToken from './components/SavedCreditCardToken';
const { useSelect } = window.wp.data;
const { checkoutStore } = window.wc?.wcBlocksData || {};
const settings = getSetting('rm-pagbank-cc_data', {});
const label = decodeEntities( settings.title ) || window.wp.i18n.__( 'PagBank Connect Cartão de Crédito', 'rm-pagbank-pix' );
let showRetryInput = false;

/**
 * Icon component
 * @returns {JSX.Element|string}
 * @constructor
 */
const Icon = () => {
    return (
        <div dangerouslySetInnerHTML={{ __html: settings.icon }}  style={{ marginLeft: '12px', lineHeight: '0.5rem' }} />
    )
}

/**
 * Label component
 *
 * @param {*} props Props from payment API.
 */
const Label = ( props ) => {
    const { PaymentMethodLabel } = props.components;
    return (
        <>
            <PaymentMethodLabel text={ label } />
            <Icon />
        </>
    );
};

/**
 * Content component
 */
const Content = ( props ) => {
    const { eventRegistration, emitResponse, billing } = props;
    
    // Safety check: ensure required props exist
    if (!eventRegistration || !emitResponse || !billing) {
        return null;
    }
    
    const { onPaymentSetup, onCheckoutValidation: onCheckoutValidation, onCheckoutSuccess, onCheckoutFail } = eventRegistration;
    const isCalculating = useSelect((select) => {
        if (!checkoutStore) {
            return false;
        }
        try {
            return select(checkoutStore)?.isCalculating() || false;
        } catch (e) {
            return false;
        }
    });
    const cartTotalRef = useRef(billing?.cartTotal?.value ?? 0);
    const latestBillingRef = useRef(billing);
    if (settings.paymentUnavailable) {
        useEffect( () => {
            const unsubscribe = onPaymentSetup(() => {
                console.error('PagBank indisponível para pedidos inferiores a R$1,00.');
                return {
                    type: emitResponse.responseTypes.ERROR,
                    messageContext: emitResponse.noticeContexts.PAYMENTS,
                    message: __('PagBank indisponível para pedidos inferiores a R$1,00.', 'rm-pagbank'),
                };
            });

            return () => {
                unsubscribe();
            };
        }, [onPaymentSetup] );

        return (
            <div className="rm-pagbank-cc">
                <PaymentUnavailable />
            </div>
        );
    }

    let canContinue = false;
    let encryptedCard = null;
    let card3d = '';

    useEffect( () => {
        return onCheckoutFail((response) => {
            console.error('PagBank: checkout fail', response);
            showRetryInput = settings.ccThreeDCanRetry
                && response.processingResponse.paymentDetails.message.includes('Vamos tentar com validação 3DS');
            return {
                type: emitResponse.responseTypes.ERROR,
                messageContext: emitResponse.noticeContexts.PAYMENTS,
                message: response.processingResponse.paymentDetails.message,
            };
        });
    }, [onCheckoutFail]);

    useEffect( () => {
        const pagBankParseErrorMessage = function(errorMessage) {
            const codes = {
                '40001': 'Parâmetro obrigatório',
                '40002': 'Parâmetro inválido',
                '40003': 'Parâmetro desconhecido ou não esperado',
                '40004': 'Limite de uso da API excedido',
                '40005': 'Método não permitido',
            };

            const descriptions = {
                "must match the regex: ^\\p{L}+['.-]?(?:\\s+\\p{L}+['.-]?)+$": 'parece inválido ou fora do padrão permitido',
                'cannot be blank': 'não pode estar em branco',
                'size must be between 8 and 9': 'deve ter entre 8 e 9 caracteres',
                'must be numeric': 'deve ser numérico',
                'must be greater than or equal to 100': 'deve ser maior ou igual a 100',
                'must be between 1 and 24': 'deve ser entre 1 e 24',
                'only ISO 3166-1 alpha-3 values are accepted': 'deve ser um código ISO 3166-1 alpha-3',
                'either paymentMethod.card.id or paymentMethod.card.encrypted should be informed': 'deve ser informado o cartão de crédito criptografado ou o id do cartão',
                'must be an integer number': 'deve ser um número inteiro',
                'card holder name must contain a first and last name': 'o nome do titular do cartão deve conter um primeiro e último nome',
                'must be a well-formed email address': 'deve ser um endereço de e-mail válido',
                'size must be between 6 and 9': 'deve ter entre 6 e 9 caracteres',
            };

            const parameters = {
                'amount.value': 'valor do pedido',
                'customer.name': 'nome do cliente',
                'customer.phones[0].number': 'número de telefone do cliente',
                'customer.phones[0].area': 'DDD do telefone do cliente',
                'billingAddress.complement': 'complemento/bairro do endereço de cobrança',
                'paymentMethod.installments': 'parcelas',
                'billingAddress.country': 'país de cobrança',
                'paymentMethod.card': 'cartão de crédito',
                'paymentMethod.card.encrypted': 'cartão de crédito criptografado',
                'customer.email': 'e-mail',
            };

            // Get the code, description, and parameterName from the errorMessage object
            const { code, description, parameterName } = errorMessage;

            // Look up the translations
            const codeTranslation = codes[code] || code;
            const descriptionTranslation = descriptions[description] || description;
            const parameterTranslation = parameters[parameterName] || parameterName;

            // Concatenate the translations into a single string
            return `${codeTranslation}: ${parameterTranslation} - ${descriptionTranslation}`;
        }

        const encryptCard = function () {
            let card, holder_name, cc_number, cc_cvv, expMonth, expYear;

            holder_name = document.getElementById('rm-pagbank-card-holder-name').value
                .trim().replace(/\s+/g, ' ').trim().replace(/\s+/g, ' ');
            cc_number = document.getElementById('rm-pagbank-card-number').value.replace(/\s/g, '');
            cc_cvv = document.getElementById('rm-pagbank-card-cvc').value.replace(/\s/g, '');
            expMonth = document.getElementById('rm-pagbank-card-expiry').value.split('/')[0].replace(/\s/g, '');
            expYear = document.getElementById('rm-pagbank-card-expiry').value.split('/')[1].replace(/\s/g, '');

            try {
                card = PagSeguro.encryptCard({
                    publicKey: settings.publicKey,
                    holder: holder_name,
                    number: cc_number,
                    expMonth: expMonth,
                    expYear: '20' + expYear,
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
            }

            return card.encryptedCard;
        }

        const authenticate3DS = async function (request) {

            //region 3ds authentication method
            PagSeguro.setUp({
                session: settings.ccThreeDSession,
                env: settings.pagbankConnectEnvironment,
            });

            await PagSeguro.authenticate3DS(request).then(result => {
                switch (result.status) {
                    case 'CHANGE_PAYMENT_METHOD':
                        // The user must change the payment method used
                        alert('Pagamento negado pelo PagBank. Escolha outro método de pagamento ou cartão.');
                        canContinue = false;
                        return Promise.resolve(false);
                    case 'AUTH_FLOW_COMPLETED':
                        //O processo de autenticação foi realizado com sucesso, dessa forma foi gerado um id do 3DS que poderá ter o resultado igual a Autenticado ou Não Autenticado.
                        if (result.authenticationStatus === 'AUTHENTICATED') {
                            //O cliente foi autenticado com sucesso, dessa forma o pagamento foi autorizado.
                            card3d = result.id;
                            console.debug('PagBank: 3DS Autenticado ou Sem desafio');
                            canContinue = true;
                            return Promise.resolve(true);
                        }

                        alert('Autenticação 3D falhou. Tente novamente.');
                        canContinue = false;
                        return Promise.resolve(false);
                    case 'AUTH_NOT_SUPPORTED':
                        //A autenticação 3DS não ocorreu, isso pode ter ocorrido por falhas na comunicação com emissor ou bandeira, ou algum controle que não possibilitou a geração do 3DS id, essa transação não terá um retorno de status de autenticação e seguirá como uma transação sem 3DS.
                        //O cliente pode seguir adiante sem 3Ds (exceto débito)
                        if (settings.ccThreeDAllowContinue === 'yes') {
                            console.debug('PagBank: 3DS não suportado pelo cartão. Continuando sem 3DS.');
                            canContinue = true;
                            card3d = false;
                            return Promise.resolve(true);
                        }

                        alert('Seu cartão não suporta autenticação 3D. Escolha outro método de pagamento ou cartão.');
                        return Promise.resolve(false);
                    case 'REQUIRE_CHALLENGE':
                        //É um status intermediário que é retornando em casos que o banco emissor solicita desafios, é importante para identificar que o desafio deve ser exibido.
                        console.debug('PagBank: REQUIRE_CHALLENGE - O desafio está sendo exibido pelo banco.');
                        canContinue = false;
                        break;
                }
            }).catch((err) => {
                if (err instanceof PagSeguro.PagSeguroError ) {
                    console.error(err);
                    console.debug('PagBank: ' + err.detail);
                    let errMsgs = err.detail.errorMessages.map(error => pagBankParseErrorMessage(error)).join('\n');
                    alert('Falha na requisição de autenticação 3D.\n' + errMsgs);
                }
                canContinue = false;
                return false;
            })
        }

        const unsubscribe = onCheckoutValidation(async () => {
            console.debug('PagBank: submit');
            console.debug('PagBank: encrypting card');
            encryptedCard = encryptCard();
            if (encryptedCard === false) {
                console.error('PagBank: error on encrypting card');
                return;
            }

            //if 3ds is not enabled, continue
            let treeDEnabled = settings.ccThreeDEnabled;
            let ccThreeDCanRetry = document.getElementById('rm-pagbank-card-retry-with-3ds')?.checked;
            if (!treeDEnabled && !ccThreeDCanRetry) {
                canContinue = true;
                card3d = false;
                return
            }

            let treeDSession = settings.ccThreeDSession;
            if ('undefined' === typeof treeDSession || !treeDSession) {
                canContinue = true;
                card3d = false;
                return;
            }

            //if 3ds is enabled, start 3ds verification
            console.debug('PagBank: initing 3ds verification');
            async function start3dsVerification() {
                let selectedInstallments = document.getElementById('rm-pagbank-card-installments')?.value;
                if (selectedInstallments === "" || selectedInstallments === null || selectedInstallments === undefined) {
                    selectedInstallments = 1;
                }

                // Always refresh with latest checkout total before 3DS request.
                const latestBillingData = latestBillingRef.current;
                const latestCartTotal = Number(latestBillingData?.cartTotal?.value ?? cartTotalRef.current ?? 0);
                cartTotalRef.current = Number.isFinite(latestCartTotal) ? latestCartTotal : 0;

                //if cart total is less than 100, don't continue with 3ds
                if (cartTotalRef.current < 100) {
                    canContinue = true;
                    card3d = false;
                    return true;
                }

                if (selectedInstallments > 1) {
                    const selectedInstallmentData = window.ps_cc_installments?.find(
                        (installment, idx, installments) => installments[idx].installments == selectedInstallments
                    );
                    if (selectedInstallmentData?.total_amount_raw) {
                        cartTotalRef.current = selectedInstallmentData.total_amount_raw;
                    }
                }

                let request = {
                    data: {
                        paymentMethod: {
                            type: 'CREDIT_CARD',
                            installments: selectedInstallments,
                            card: {
                                number: document.getElementById('rm-pagbank-card-number').value.replace(/\s/g, ''),
                                expMonth: document.getElementById('rm-pagbank-card-expiry').value.split('/')[0].replace(/\s/g, ''),
                                expYear: '20' + document.getElementById('rm-pagbank-card-expiry').value.split('/')[1].replace(/\s/g, ''),
                                holder: {
                                    name: document.getElementById('rm-pagbank-card-holder-name').value
                                        .trim().replace(/\s+/g, ' ').trim().replace(/\s+/g, ' ')
                                }
                            }
                        },
                        dataOnly: false
                    }
                }

                let customerName = (billing?.billingData?.first_name || '') + ' ' + (billing?.billingData?.last_name || '');
                if(customerName.trim() === '') {
                    const firstNameEl = document.getElementById('billing-first_name');
                    const lastNameEl = document.getElementById('billing-last_name');
                    customerName = (firstNameEl?.value || '').replace(/\s/g, '')
                        + ' ' + (lastNameEl?.value || '').replace(/\s/g, '');
                }
                customerName = customerName.trim().replace(/\s+/g, ' '); //removing duplicated spaces in the middle
                customerName = customerName.replace(/[^A-Za-zÀ-ÖØ-öø-ÿ\s]/g, '').replace(/\s+/g, ' '); //removing specials
                

                const getLiveCheckoutFieldValue = (preferredSelectors, billingValue = '') => {
                    for (const selector of preferredSelectors) {
                        const field = document.querySelector(selector);
                        const rawValue = (field?.value || '').toString();
                        if (rawValue.trim() !== '') {
                            return rawValue;
                        }
                    }

                    return (billingValue || '').toString();
                };

                const customerEmail = getLiveCheckoutFieldValue(
                    ['#email', 'input[name="email"]', 'input[name="billing_email"]'],
                    latestBillingData?.billingData?.email
                );

                const customerPhone = getLiveCheckoutFieldValue(
                    ['#billing-phone', '#billing-phone input', 'input[name="billing_phone"]', '#shipping-phone', '#shipping-phone input', 'input[name="shipping_phone"]'],
                    latestBillingData?.billingData?.phone
                );

                const billingAddress1 = getLiveCheckoutFieldValue(
                    ['#billing-address_1', '#billing-address_1 input', 'input[name="billing_address_1"]', '#shipping-address_1', '#shipping-address_1 input', 'input[name="shipping_address_1"]'],
                    latestBillingData?.billingData?.address_1
                );

                const billingAddress2 = getLiveCheckoutFieldValue(
                    ['#billing-address_2', '#billing-address_2 input', 'input[name="billing_address_2"]', '#shipping-address_2', '#shipping-address_2 input', 'input[name="shipping_address_2"]'],
                    latestBillingData?.billingData?.address_2
                );

                const regionCode = getLiveCheckoutFieldValue(
                    ['#billing-state', '#billing-state input', '#billing-state select', 'input[name="billing_state"]', 'select[name="billing_state"]', '#shipping-state', '#shipping-state input', '#shipping-state select', 'input[name="shipping_state"]', 'select[name="shipping_state"]'],
                    latestBillingData?.billingData?.state
                );

                const billingAddressCity = getLiveCheckoutFieldValue(
                    ['#billing-city', '#billing-city input', 'input[name="billing_city"]', '#shipping-city', '#shipping-city input', 'input[name="shipping_city"]'],
                    latestBillingData?.billingData?.city
                );

                const billingAddressPostcode = getLiveCheckoutFieldValue(
                    ['#billing-postcode', '#billing-postcode input', 'input[name="billing_postcode"]', '#shipping-postcode', '#shipping-postcode input', 'input[name="shipping_postcode"]'],
                    latestBillingData?.billingData?.postcode
                );

                let orderData = {
                    customer: {
                        name: customerName,
                        email: customerEmail,
                        phones: [
                            {
                                country: '55',
                                area: customerPhone.replace(/\D/g, '').substring(0, 2),
                                number: customerPhone.replace(/\D/g, '').substring(2),
                                type: 'MOBILE'
                            }]
                    },
                    amount: {
                        value: cartTotalRef.current,
                        currency: 'BRL'
                    },
                    billingAddress: {
                        street: billingAddress1.replace(/\s+/g, ' '),
                        number: billingAddress1.replace(/\s+/g, ' '),
                        complement: billingAddress2.replace(/\s+/g, ' '),
                        regionCode: regionCode.toUpperCase(),
                        country: 'BRA',
                        city: billingAddressCity.replace(/\s+/g, ' '),
                        postalCode: billingAddressPostcode.replace(/\D+/g, '')
                    }
                };

                request.data = {
                    ...request.data,
                    ...orderData
                };

                console.debug('PagBank 3DS Request Amount: ' + request.data.amount.value);

                let result;
                try {
                    result = await authenticate3DS(request);
                } catch (error) {
                    console.error('Erro ao verificar 3DS:', error);
                    result = false;
                }

                return result;
            }

            await start3dsVerification();
        } );

        return () => {
            unsubscribe();
        };
    }, [onCheckoutValidation] );

    useEffect( () => {
        const unsubscribe = onPaymentSetup(() => {
            if (encryptedCard === false) {
                console.error('PagBank: error on encrypting card');
                return {
                    type: emitResponse.responseTypes.ERROR,
                    messageContext: emitResponse.noticeContexts.PAYMENTS,
                    message: __('Erro ao criptografar o cartão. Verifique se os dados digitados estão corretos.', 'rm-pagbank'),
                };
            }

            if (canContinue === false) {
                console.error('PagBank: error during 3ds verification');
                return {
                    type: emitResponse.responseTypes.ERROR,
                    messageContext: emitResponse.noticeContexts.PAYMENTS,
                    message: __('Erro ao verificar 3DS. Tente novamente.', 'rm-pagbank'),
                };
            }

            const customerDocumentValue = document.getElementById('rm-pagbank-customer-document').value;
            const installments = document.getElementById('rm-pagbank-card-installments')?.value || 1;
            const ccNumber = document.getElementById('rm-pagbank-card-number').value;
            const ccHolderName = document.getElementById('rm-pagbank-card-holder-name').value;
            const card3dRetry = document.getElementById('rm-pagbank-card-retry-with-3ds');
            const newPaymentMethod = document.getElementById('rm-pagbank-cc-new-payment-method-in-block');

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
                        'rm-pagbank-card-3d': card3d,
                        'rm-pagbank-card-retry-with-3ds': card3dRetry?.checked,
                        'rm-pagbank-cc-new-payment-method-in-block': newPaymentMethod?.checked,
                    },
                },
            };
        } );

        return () => {
            unsubscribe();
        };
    }, [onPaymentSetup] );

    useEffect(() => {
        latestBillingRef.current = billing;
    }, [billing]);

    // Keep the 3DS amount in sync with the latest checkout total.
    useEffect(() => {
        const latestCartTotal = Number(billing?.cartTotal?.value ?? 0);
        if (Number.isFinite(latestCartTotal) && latestCartTotal > 0) {
            cartTotalRef.current = latestCartTotal;
        }
    }, [billing?.cartTotal?.value, isCalculating]);

    return (
        <div className="rm-pagbank-cc">
            {showRetryInput ? <RetryInput /> : null}
            <CreditCardForm billing={billing} />
            <SavedCreditCardToken />
            <CustomerDocumentField />
            {settings.isCartRecurring ? <RecurringInfo /> : null}
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
    savedTokenComponent: <SavedCardInstallments />,
};

registerPaymentMethod( Rm_Pagbank_Cc_Block_Gateway );