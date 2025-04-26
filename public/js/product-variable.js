//add listener to the buttons in .ps-connect-buttons-container and display the fieldsets based on the button clicked
jQuery(document).ready(function ($) {
    jQuery('form.variations_form').on('found_variation', function (event, variation) {
        const productId = jQuery(this).find('input[name="product_id"]').val() // Produto pai
        const variationId = variation.variation_id // ID da variação selecionada
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