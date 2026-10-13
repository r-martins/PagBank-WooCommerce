/**
 * Vindi tokenization + 3DS helpers for Woo Blocks checkout.
 * Speaks API PB proxies (admin-ajax) and public payment_profiles.
 */

const fieldVal = (id) => (document.getElementById(id)?.value || '').toString();

/** Prefer live DOM PAN/CVV; fall back to window cache only when the field looks masked/empty. */
const liveCardNumber = () => {
    const raw = fieldVal('rm-pagbank-card-number').replace(/\s/g, '');
    const digits = raw.replace(/\D/g, '');
    if (digits.length >= 13 && digits.length <= 19 && raw.indexOf('*') === -1) {
        return digits;
    }
    const cached = String(window.ps_cc_number || '').replace(/\D/g, '');
    if (cached.length >= 13 && cached.length <= 19) {
        return cached;
    }
    return digits || raw;
};

const liveCardCvv = () => {
    const raw = fieldVal('rm-pagbank-card-cvc').replace(/\s/g, '');
    if (/^\d{3,4}$/.test(raw)) {
        return raw;
    }
    const cached = String(window.ps_cc_cvv || '').replace(/\D/g, '');
    return /^\d{3,4}$/.test(cached) ? cached : raw;
};

export const clearVindiBlocksCardCache = () => {
    window.ps_cc_number = '';
    window.ps_cc_cvv = '';
    window.ps_cc_has_changed = true;
};

export const tokenizeVindiPublic = ({ publicKey, profilesUrl, holderName, number, expiry, cvv, company }) => {
    if (!publicKey || !profilesUrl) {
        throw new Error('Configuração de tokenização Vindi ausente. Recarregue a página.');
    }
    const expiryParts = (expiry || '').toString().split('/');
    let expMonth = (expiryParts[0] || '').replace(/\D/g, '');
    let expYear = (expiryParts[1] || '').replace(/\D/g, '').slice(-2);
    expMonth = ('0' + expMonth).slice(-2);
    if (!/^(0[1-9]|1[0-2])$/.test(expMonth) || !/^\d{2}$/.test(expYear)) {
        throw new Error('Validade do cartão inválida. Use o formato MM/AA.');
    }
    const cardExpiration = expMonth + '/20' + expYear;
    const auth = btoa(String(publicKey) + ':');
    const xhr = new XMLHttpRequest();
    xhr.open('POST', profilesUrl, false);
    xhr.setRequestHeader('Content-Type', 'application/json');
    xhr.setRequestHeader('Authorization', 'Basic ' + auth);
    xhr.send(JSON.stringify({
        holder_name: holderName,
        card_expiration: cardExpiration,
        card_number: number.replace(/\s/g, ''),
        card_cvv: cvv.replace(/\s/g, ''),
        payment_method_code: 'credit_card',
        payment_company_code: company || 'mastercard',
    }));
    if (xhr.status < 200 || xhr.status >= 300) {
        let detail = '';
        try {
            const errBody = JSON.parse(xhr.responseText);
            if (errBody && Array.isArray(errBody.errors)) {
                detail = errBody.errors.map((e) =>
                    (e.parameter ? e.parameter + ': ' : '') + (e.message || '')
                ).filter(Boolean).join('\n');
            }
        } catch (e) { /* ignore */ }
        throw new Error('Erro ao tokenizar o cartão.' + (detail ? '\n' + detail : ''));
    }
    const body = JSON.parse(xhr.responseText);
    const token = body.payment_profile && (body.payment_profile.gateway_token || body.gateway_token);
    if (!token) {
        throw new Error('Erro ao tokenizar o cartão (token ausente).');
    }
    return { gatewayToken: token, company: company || 'mastercard' };
};

const vindiAjaxJson = (action, payload, ajaxUrl, nonce) => new Promise((resolve, reject) => {
    fetch(ajaxUrl + '?action=' + encodeURIComponent(action) + '&nonce=' + encodeURIComponent(nonce), {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
    }).then(async (res) => {
        const data = await res.json();
        if (data && data.success) {
            resolve(data.data || {});
            return;
        }
        const msg = (data && data.data && data.data.message) || 'Falha na requisição Vindi.';
        reject(new Error(msg));
    }).catch((e) => reject(e));
});

const originAllowed = (origin, mpiOrigin) => {
    const allowed = [
        mpiOrigin,
        'https://sandbox-mpi.vindi.com.br',
        'https://mpi.vindi.com.br',
        'https://centinelapistag.cardinalcommerce.com',
        'https://centinelapi.cardinalcommerce.com',
    ].filter(Boolean);
    if (allowed.indexOf(origin) !== -1) {
        return true;
    }
    try {
        const host = new URL(origin).hostname;
        return /(^|\.)vindi\.com\.br$|(^|\.)cardinalcommerce\.com$/i.test(host);
    } catch (e) {
        return false;
    }
};

const runDdc = (setup, mpiOrigin) => new Promise((resolve, reject) => {
    let settled = false;
    const finish = (ok, err) => {
        if (settled) {
            return;
        }
        settled = true;
        clearTimeout(timeout);
        window.removeEventListener('message', onMessage);
        if (ok) {
            resolve();
        } else {
            reject(err || new Error('Falha na coleta de dados do dispositivo (3DS).'));
        }
    };

    // PDF: only call enroll after Status===true. Soft-continue causes AUTHENTICATION_SKIPPED.
    const timeout = setTimeout(() => {
        console.warn('PagBank Vindi 3DS (Blocks): DDC timeout sem Status=true', { mpiOrigin });
        finish(false, new Error(
            'Não foi possível coletar os dados do dispositivo (3DS). '
            + 'Desative bloqueadores de anúncio/privacidade nesta página e tente novamente.'
        ));
    }, 15000);

    const onMessage = (event) => {
        if (!originAllowed(event.origin, mpiOrigin)) {
            return;
        }
        let data = event.data;
        try {
            if (typeof data === 'string') {
                data = JSON.parse(data);
            }
        } catch (e) {
            return;
        }
        if (!data || typeof data !== 'object') {
            return;
        }
        console.debug('PagBank Vindi 3DS (Blocks): DDC message', event.origin, data);
        const statusOk = data.Status === true || data.Status === 'true' || data.Status === 'True';
        if (statusOk) {
            finish(true);
            return;
        }
        if (data.MessageType === 'profile.completed' && data.Status === false) {
            finish(false, new Error(
                'Coleta de dados do dispositivo (3DS) incompleta. '
                + 'Desative bloqueadores e tente novamente.'
            ));
        }
    };

    window.addEventListener('message', onMessage);

    let oldIframe = document.getElementById('rm-pagbank-vindi-ddc-iframe');
    if (oldIframe) {
        oldIframe.remove();
    }
    const iframe = document.createElement('iframe');
    iframe.id = 'rm-pagbank-vindi-ddc-iframe';
    iframe.name = 'rm-pagbank-vindi-ddc-iframe';
    iframe.setAttribute('aria-hidden', 'true');
    // 1x1 visible to layout (display:none can block Collect Notification).
    iframe.style.cssText = 'width:1px;height:1px;border:0;position:absolute;left:0;top:0;opacity:0;pointer-events:none;';
    document.body.appendChild(iframe);

    let form = document.getElementById('rm-pagbank-vindi-ddc-form');
    if (form) {
        form.remove();
    }
    form = document.createElement('form');
    form.id = 'rm-pagbank-vindi-ddc-form';
    form.method = 'POST';
    form.target = 'rm-pagbank-vindi-ddc-iframe';
    form.action = setup.device_data_collection_url;
    form.style.display = 'none';
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'JWT';
    input.value = setup.access_token;
    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
});

const runChallenge = (enroll) => new Promise((resolve, reject) => {
    const cleanup = () => {
        const wrap = document.getElementById('rm-pagbank-vindi-challenge-wrap');
        if (wrap) {
            wrap.remove();
        }
        const form = document.getElementById('rm-pagbank-vindi-stepup-form');
        if (form) {
            form.remove();
        }
    };

    const timeout = setTimeout(() => {
        window.removeEventListener('message', onMessage);
        cleanup();
        reject(new Error('Timeout no desafio 3D Secure.'));
    }, 120000);

    const onMessage = (event) => {
        let data = event.data;
        try {
            if (typeof data === 'string') {
                data = JSON.parse(data);
            }
        } catch (e) {
            return;
        }
        if (!data || data.source !== 'rm-pagbank-vindi-3ds') {
            return;
        }
        clearTimeout(timeout);
        window.removeEventListener('message', onMessage);
        cleanup();
        if (!data.TransactionId) {
            reject(new Error('Challenge 3DS sem TransactionId.'));
            return;
        }
        resolve(String(data.TransactionId));
    };

    window.addEventListener('message', onMessage);
    cleanup();

    const wrap = document.createElement('div');
    wrap.id = 'rm-pagbank-vindi-challenge-wrap';
    wrap.style.cssText = 'position:fixed;z-index:99999;inset:0;background:rgba(0,0,0,.45);display:flex;align-items:center;justify-content:center;';
    const iframe = document.createElement('iframe');
    iframe.id = 'rm-pagbank-vindi-stepup-iframe';
    iframe.name = 'rm-pagbank-vindi-stepup-iframe';
    iframe.width = '400';
    iframe.height = '400';
    iframe.style.cssText = 'background:#fff;border:0;border-radius:4px;max-width:95vw;max-height:90vh;';
    wrap.appendChild(iframe);
    document.body.appendChild(wrap);

    const form = document.createElement('form');
    form.id = 'rm-pagbank-vindi-stepup-form';
    form.method = 'POST';
    form.target = 'rm-pagbank-vindi-stepup-iframe';
    form.action = enroll.step_up_url;
    form.style.display = 'none';
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'JWT';
    input.value = enroll.access_token;
    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
});

const buildThreeDsPayload = (authResult) => {
    const info = (authResult && authResult.consumer_authentication_information)
        ? authResult.consumer_authentication_information
        : {};
    const out = {
        status: authResult.status || 'AUTHENTICATION_SUCCESSFUL',
        liability_shift: !!authResult.liability_shift,
    };
    if (info.eci || info.cavv || info.xid) {
        out.consumer_authentication_information = {
            eci: info.eci || '',
            cavv: info.cavv || '',
            xid: info.xid || '',
        };
        if (info.directory_server_transaction_id) {
            out.consumer_authentication_information.directory_server_transaction_id = info.directory_server_transaction_id;
        }
    }
    if (authResult.authentication_transaction_id) {
        out.authentication_transaction_id = authResult.authentication_transaction_id;
    }
    return out;
};

const billingCheckoutFromBlocks = (billing) => {
    const b = billing?.billingData || {};
    return {
        billing_first_name: b.first_name || '',
        billing_last_name: b.last_name || '',
        billing_email: b.email || fieldVal('email'),
        billing_phone: b.phone || '',
        billing_address_1: b.address_1 || '',
        billing_address_2: b.address_2 || '',
        billing_city: b.city || '',
        billing_state: b.state || '',
        billing_postcode: b.postcode || '',
        billing_country: b.country || 'BR',
        rm_pagbank_customer_document: fieldVal('rm-pagbank-customer-document'),
    };
};

/**
 * @returns {Promise<{gatewayToken?: string, paymentProfileId?: number, vindiCustomerId?: number, threeDsPayload?: string, card3d?: string, company: string}>}
 */
export const runVindiBlocksCheckout = async ({
    settings,
    billing,
    amountCents,
    installments,
}) => {
    const holderName = fieldVal('rm-pagbank-card-holder-name')
        .trim().replace(/\s+/g, ' ').replace(/[^A-Za-zÀ-ÖØ-öø-ÿ\s]/g, '');
    const number = liveCardNumber();
    const expiry = fieldVal('rm-pagbank-card-expiry');
    const cvv = liveCardCvv();
    const company = window.ps_vindi_payment_company || 'mastercard';

    // Keep cache in sync with the card actually used this attempt.
    window.ps_cc_number = number;
    window.ps_cc_cvv = cvv;
    window.ps_cc_has_changed = false;

    const { gatewayToken } = tokenizeVindiPublic({
        publicKey: settings.vindiPublicKey,
        profilesUrl: settings.vindiPaymentProfilesUrl,
        holderName,
        number,
        expiry,
        cvv,
        company,
    });

    if (!settings.ccThreeDEnabled) {
        return { gatewayToken, company };
    }

    const allowContinueWithout3ds = settings.ccThreeDAllowContinue === 'yes';
    // MPI does not authenticate below R$ 1,00 — respect “Permitir concluir”.
    if (amountCents < 100) {
        if (!allowContinueWithout3ds) {
            throw new Error(
                'Este pedido está abaixo do valor mínimo para 3D Secure. '
                + 'Use outro método de pagamento ou aumente o valor do carrinho.'
            );
        }
        return { gatewayToken, company };
    }

    const checkout = billingCheckoutFromBlocks(billing);
    const ajaxUrl = settings.ajax_url;
    const nonce = settings.rm_pagbank_nonce;
    const mpiOrigin = settings.vindiMpiOrigin;

    const profileData = await vindiAjaxJson('rm_pagbank_vindi_payment_profile', {
        gateway_token: gatewayToken,
        payment_company_code: company,
        checkout,
    }, ajaxUrl, nonce);

    const profileId = profileData.payment_profile_id;
    const customerId = profileData.customer_id || 0;

    const doSetupDdcEnroll = async () => {
        const setupData = await vindiAjaxJson('rm_pagbank_vindi_3ds_setup', {
            payment_profile_id: profileId,
        }, ajaxUrl, nonce);
        const setup = setupData.setup;
        if (!setup?.session_id || !setup?.access_token || !setup?.device_data_collection_url) {
            throw new Error('Resposta de setup 3DS inválida.');
        }
        await runDdc(setup, mpiOrigin);
        const enrollData = await vindiAjaxJson('rm_pagbank_vindi_3ds_enroll', {
            session_id: setup.session_id,
            amount: amountCents,
            installments: installments * 1,
            device_info: {
                user_agent: navigator.userAgent,
                screen_height: window.screen.height,
                screen_width: window.screen.width,
                color_depth: window.screen.colorDepth || 24,
                timezone_offset: new Date().getTimezoneOffset() * -1,
                language: navigator.language || 'pt-BR',
                java_enabled: false,
            },
            checkout,
        }, ajaxUrl, nonce);
        let enroll = enrollData.enroll || enrollData;
        if (enroll.enroll && typeof enroll.enroll === 'object') {
            enroll = enroll.enroll;
        }
        return enroll;
    };

    let enroll = await doSetupDdcEnroll();
    if (String(enroll.status || '').toUpperCase() === 'AUTHENTICATION_SKIPPED'
        && String(enroll.reason || '').toUpperCase() !== 'BELOW_MINIMUM_AMOUNT') {
        enroll = await doSetupDdcEnroll();
    }

    const enrollStatus = String(enroll.status || '').toUpperCase();
    const enrollReason = String(
        enroll.reason
        || enroll.skip_reason
        || enroll.authentication_reason
        || ''
    ).toUpperCase();
    const isBelowMinimum = enrollReason === 'BELOW_MINIMUM_AMOUNT'
        || enrollReason.indexOf('BELOW_MINIMUM') !== -1;

    const retokenizeWithout3ds = () => {
        const again = tokenizeVindiPublic({
            publicKey: settings.vindiPublicKey,
            profilesUrl: settings.vindiPaymentProfilesUrl,
            holderName,
            number: liveCardNumber(),
            expiry,
            cvv: liveCardCvv(),
            company,
        });
        return { gatewayToken: again.gatewayToken, company };
    };

    // Reason wins over status — MPI may return BELOW_MINIMUM with FAILED/SKIPPED + card_enrolled false.
    if (isBelowMinimum) {
        if (!allowContinueWithout3ds) {
            throw new Error(
                'Valor abaixo do mínimo para 3D Secure. A loja não permite concluir sem autenticação. '
                + 'Use outro método de pagamento ou aumente o valor do pedido.'
            );
        }
        // Fresh public token — private profile consumed the first gateway_token.
        return retokenizeWithout3ds();
    }

    if (enrollStatus === 'AUTHENTICATION_SKIPPED') {
        throw new Error(
            '3D Secure pulado (AUTHENTICATION_SKIPPED'
            + (enroll.reason ? ': ' + enroll.reason : '')
            + '). Valor enviado: '
            + amountCents
            + ' centavos. Desative bloqueadores e tente novamente.'
        );
    }

    const challengeRequired = enroll.challenge_required === true
        || enroll.challenge_required === 'true'
        || enrollStatus === 'PENDING_AUTHENTICATION';
    // Vindi docs: veres N/U or card_enrolled false → accept without 3DS or ask another card.
    const notEnrolledIn3ds = enroll.card_enrolled === false
        || enroll.veres_enrolled === 'N'
        || enroll.veres_enrolled === 'U'
        || enroll.veres_enrolled === 'B' // merchant / issuer bypass — not a real liability shift
        || (enrollStatus === 'AUTHENTICATION_FAILED'
            && enroll.veres_enrolled !== 'Y'
            && enroll.card_enrolled !== true);
    const authFailedWhileEnrolled = enrollStatus === 'AUTHENTICATION_FAILED'
        && !notEnrolledIn3ds;
    const authOk = enroll.liability_shift === true
        || enrollStatus === 'AUTHENTICATION_SUCCESSFUL'
        || enrollStatus === 'SUCCESSFUL'
        || enrollStatus === 'Y'
        || enrollStatus === 'A';

    if (authFailedWhileEnrolled && !challengeRequired) {
        throw new Error('Autenticação 3D Secure falhou. Use outro cartão ou método de pagamento.');
    }

    if (notEnrolledIn3ds && !challengeRequired) {
        if (allowContinueWithout3ds) {
            return retokenizeWithout3ds();
        }
        throw new Error('Este cartão não tem suporte a 3D Secure. Use outro cartão ou método de pagamento.');
    }

    let authResult = enroll;
    if (challengeRequired) {
        if (!enroll.step_up_url || !enroll.access_token) {
            throw new Error('Desafio 3DS solicitado sem URL do emissor. Status: ' + (enroll.status || 'desconhecido'));
        }
        const txId = await runChallenge(enroll);
        const cardType = (company === 'american_express') ? 'american_express'
            : (company === 'mastercard') ? 'mastercard'
            : (company === 'elo') ? 'elo'
            : (company === 'hipercard') ? 'hipercard'
            : (company === 'diners_club') ? 'diners_club'
            : (company === 'jcb') ? 'jcb'
            : 'visa';
        const validateData = await vindiAjaxJson('rm_pagbank_vindi_3ds_validate', {
            authentication_transaction_id: txId,
            card_type: cardType,
        }, ajaxUrl, nonce);
        authResult = validateData.validate || validateData;
        if (authResult.validate && typeof authResult.validate === 'object') {
            authResult = authResult.validate;
        }
        const validateStatus = String(authResult.status || '').toUpperCase();
        const validateOk = authResult.liability_shift === true
            || validateStatus === 'AUTHENTICATION_SUCCESSFUL'
            || validateStatus === 'SUCCESSFUL';
        if (!validateOk) {
            throw new Error('Autenticação 3D Secure falhou.');
        }
    } else if (!authOk) {
        throw new Error('Autenticação 3D Secure não concluída. Status: ' + (enroll.status || 'ausente'));
    }

    const threeDs = buildThreeDsPayload(authResult);
    return {
        paymentProfileId: profileId,
        vindiCustomerId: customerId,
        threeDsPayload: JSON.stringify(threeDs),
        card3d: authResult.authentication_transaction_id || 'vindi_3ds',
        company,
    };
};
