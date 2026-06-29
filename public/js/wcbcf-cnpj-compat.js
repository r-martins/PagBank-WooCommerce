(function ($) {
    'use strict';

    function applyCnpjCompat($field) {
        if (!$field.length || !window.rmPagbankTaxId) {
            return;
        }

        try {
            $field.unmask();
        } catch (e) {
            // jquery.mask may not be initialized yet.
        }

        $field
            .attr('type', 'text')
            .attr('inputmode', 'text')
            .attr('autocapitalize', 'characters')
            .attr('maxlength', '18');

        $field.off('input.rmPagbankWcbcfCnpj change.rmPagbankWcbcfCnpj paste.rmPagbankWcbcfCnpj');

        $field.on('input.rmPagbankWcbcfCnpj change.rmPagbankWcbcfCnpj paste.rmPagbankWcbcfCnpj', function () {
            var formatted = window.rmPagbankTaxId.formatTaxIdForDisplay($(this).val());
            if ($(this).val() !== formatted) {
                $(this).val(formatted);
            }
        });
    }

    function init() {
        applyCnpjCompat($('#billing_cnpj'));
    }

    $(function () {
        init();
        $(document.body).on('updated_checkout', init);
    });
}(jQuery));
