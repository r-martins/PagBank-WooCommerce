/**
 * CPF (numeric) and CNPJ (numeric or alphanumeric) helpers for checkout UI and API payloads.
 */

export const CPF_LENGTH = 11;
export const CNPJ_LENGTH = 14;
export const CNPJ_PATTERN = /^[A-Z0-9]{12}[0-9]{2}$/;

/** Inputmask definition: alphanumeric body slot (A–Z, 0–9), uppercased. */
export const CNPJ_ALNUM_MASK_DEFINITIONS = {
    S: {
        validator: '[0-9A-Za-z]',
        casing: 'upper',
    },
};

export const CPF_MASK = '999.999.999-99';
export const CNPJ_ALNUM_MASK = 'SS.SSS.SSS/SSSS-99';
/** @deprecated Use formatTaxIdForDisplay(); Inputmask alternator blocks letters after leading digits. */
export const TAX_ID_MASKS = [CPF_MASK, CNPJ_ALNUM_MASK];

/** @deprecated */
export const TAX_ID_MASK_OPTIONS = {
    definitions: CNPJ_ALNUM_MASK_DEFINITIONS,
    showMaskOnHover: false,
};

export const TAX_ID_DISPLAY_MAX_LENGTH = 18;

/**
 * Strip mask chars; keep letters and digits (uppercase). For PagBank API tax_id.
 *
 * @param {string} value
 * @returns {string}
 */
export function sanitizeTaxIdForApi(value) {
    if (!value) {
        return '';
    }
    return String(value).toUpperCase().replace(/[^A-Z0-9]/g, '');
}

/**
 * @param {string} sanitized
 * @returns {'cpf'|'cnpj'|null}
 */
export function detectTaxIdType(sanitized) {
    const clean = sanitizeTaxIdForApi(sanitized);
    if (!clean) {
        return null;
    }
    if (/[A-Z]/.test(clean)) {
        return 'cnpj';
    }
    if (clean.length <= CPF_LENGTH && /^\d+$/.test(clean)) {
        return 'cpf';
    }
    if (clean.length >= CPF_LENGTH + 1) {
        return 'cnpj';
    }
    return null;
}

/**
 * @param {string} sanitized
 * @returns {boolean}
 */
export function isValidTaxIdFormat(sanitized) {
    const clean = sanitizeTaxIdForApi(sanitized);
    if (clean.length === CPF_LENGTH && /^\d{11}$/.test(clean)) {
        return true;
    }
    return clean.length === CNPJ_LENGTH && CNPJ_PATTERN.test(clean);
}

/**
 * Progressive CPF/CNPJ display formatting while typing.
 *
 * CPF: only while every typed character is a digit and length <= 11 (999.999.999-99).
 * CNPJ: as soon as a letter appears or length exceeds 11 (XX.XXX.XXX/XXXX-DV per RFB).
 *
 * @param {string} value Raw input
 * @returns {string}
 */
export function formatTaxIdForDisplay(value) {
    const clean = sanitizeTaxIdForApi(value);
    if (!clean) {
        return '';
    }

    const isCpfInProgress = clean.length <= CPF_LENGTH && /^\d+$/.test(clean);

    if (isCpfInProgress) {
        let out = clean;
        if (out.length > 3) {
            out = out.slice(0, 3) + '.' + out.slice(3);
        }
        if (out.length > 7) {
            out = out.slice(0, 7) + '.' + out.slice(7);
        }
        if (out.length > 11) {
            out = out.slice(0, 11) + '-' + out.slice(11);
        }
        return out.slice(0, 14);
    }

    const body = clean.slice(0, CNPJ_LENGTH);
    let out = body.slice(0, 2);
    if (body.length > 2) {
        out += '.' + body.slice(2, 5);
    }
    if (body.length > 5) {
        out += '.' + body.slice(5, 8);
    }
    if (body.length > 8) {
        out += '/' + body.slice(8, 12);
    }
    if (body.length > 12) {
        out += '-' + body.slice(12, 14);
    }
    return out.slice(0, 18);
}
