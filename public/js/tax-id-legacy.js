(function (window) {
    'use strict';

    var CPF_LENGTH = 11;
    var CNPJ_LENGTH = 14;

    function sanitizeTaxIdForApi(value) {
        if (!value) {
            return '';
        }
        return String(value).toUpperCase().replace(/[^A-Z0-9]/g, '');
    }

    function formatTaxIdForDisplay(value) {
        var clean = sanitizeTaxIdForApi(value);
        if (!clean) {
            return '';
        }

        if (clean.length <= CPF_LENGTH && /^\d+$/.test(clean)) {
            var out = clean;
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

        var body = clean.slice(0, CNPJ_LENGTH);
        var out = body.slice(0, 2);
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

    window.rmPagbankTaxId = {
        sanitizeTaxIdForApi: sanitizeTaxIdForApi,
        formatTaxIdForDisplay: formatTaxIdForDisplay,
    };
}(window));
