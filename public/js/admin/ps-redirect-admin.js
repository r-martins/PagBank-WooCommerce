jQuery(function($){
    // Field selectors
    var $enabled = $('#woocommerce_rm-pagbank-redirect_enabled');
    var $methods = $('#woocommerce_rm-pagbank-redirect_redirect_payment_methods');
    var $form = $enabled.closest('form');

    function validateMethodsRequired() {
        if ($enabled.is(':checked')) {
            var val = $methods.val();
            if (!val || val.length === 0) {
                $methods.closest('tr').addClass('woocommerce-invalid');
                $methods[0].setCustomValidity('Selecione pelo menos um método de pagamento.');
                return false;
            } else {
                $methods.closest('tr').removeClass('woocommerce-invalid');
                $methods[0].setCustomValidity('');
                return true;
            }
        } else {
            $methods.closest('tr').removeClass('woocommerce-invalid');
            $methods[0].setCustomValidity('');
            return true;
        }
    }

    $enabled.on('change', validateMethodsRequired);
    $methods.on('change', validateMethodsRequired);
    $form.on('submit', function(e){
        if (!validateMethodsRequired()) {
            $methods.focus();
            e.preventDefault();
            e.stopImmediatePropagation();
            alert('Selecione pelo menos um método de pagamento para habilitar o PagBank.');
            return false;
        }
    });
});
