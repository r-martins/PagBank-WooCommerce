(function ($) {
    'use strict';

    function collectVariation() {
        var variation = {};
        $('form.variations_form select[name^="attribute_"]').each(function () {
            var name = $(this).attr('name');
            if (name) {
                variation[name] = $(this).val() || '';
            }
        });
        return variation;
    }

    $(document).on('click', '.rm-pagbank-checkout-pb-one-click', function (e) {
        e.preventDefault();
        var $btn = $(this);
        if ($btn.prop('disabled')) {
            return;
        }
        var cfg = window.rmPagBankCheckoutPbOneClick || {};
        var productId = $btn.data('product-id');
        var variationId = $('input[name="variation_id"]').val() || 0;
        var quantity = $('form.cart input.qty').val() || 1;

        if ($('form.variations_form').length && (!variationId || variationId === '0')) {
            window.alert(cfg.errorGeneric || 'Selecione as opções do produto.');
            return;
        }

        $btn.prop('disabled', true).addClass('loading');

        $.ajax({
            url: cfg.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: cfg.action,
                nonce: cfg.nonce,
                product_id: productId,
                variation_id: variationId,
                quantity: quantity,
                variation: collectVariation()
            }
        })
            .done(function (resp) {
                if (resp && resp.success && resp.data && resp.data.redirect) {
                    window.location.href = resp.data.redirect;
                    return;
                }
                var msg = (resp && resp.data && resp.data.message) || cfg.errorGeneric;
                window.alert(msg);
            })
            .fail(function (xhr) {
                var msg = cfg.errorGeneric;
                try {
                    var json = xhr.responseJSON;
                    if (json && json.data && json.data.message) {
                        msg = json.data.message;
                    }
                } catch (err) { /* ignore */ }
                window.alert(msg);
            })
            .always(function () {
                $btn.prop('disabled', false).removeClass('loading');
            });
    });
})(jQuery);
