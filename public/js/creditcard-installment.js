jQuery(document).ready(function ($) {
    jQuery('form.variations_form').on('found_variation', function (event, variation) {
        const productId = jQuery( this ).find('input[name="product_id"]').val() // Produto pai
        const variationId = variation.variation_id // ID da variação selecionada
        const preco = variation.display_price
        fetch( ajax_obj.rest_installments, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ _price: preco, _product_id: productId, _variation_id: variationId, _type: ajax_obj.type })
        })
        .then( res => res.json())
        .then( data => jQuery("#pagbank_load_installment").html(data.data.html))
    })
})