// Refresh installment table when a variable product variation is selected
jQuery(document).ready(function ($) {
    jQuery('form.variations_form').on('found_variation', function (event, variation) {
        const productId = jQuery(this).find('input[name="product_id"]').val() // Product ID
        const variationId = variation.variation_id // Variation ID
        const price = variation.display_price
        $('.pagbank-connect-installments').hide();
        $.get(ajax_object.rest_installments, {
            _product_id: productId,
            _variation_id: variationId,
            _price: price
        }, function (response) {
            if (typeof response.html !== 'undefined' && response.html !== '') {
                $('.pagbank-connect-installments').replaceWith(response.html);
                $('.pagbank-connect-installments').show();
            }
        });
    })
})