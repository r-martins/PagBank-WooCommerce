jQuery(document).ready(function($) {
    
    //dismiss pix notice
    $(document).on('click', '.pagbank-pix-notice .notice-dismiss', function() {
        $.post(script_data.ajaxurl, { action: script_data.action });
    });

    // expiration field validation
    let hold_stock_pix_validation = script_data.woocommerce_hold_stock_pix_validation;
    let hold_stock_boleto_validation = script_data.woocommerce_hold_stock_boleto_validation;

    let message_hold_stock = document.createElement('p');
    message_hold_stock.innerHTML = '⚠️ A <a href="?page=wc-settings&tab=products&section=inventory">retenção de estoque</a> está configurada com um prazo inferior a este e poderá cancelar os pedidos antes deste prazo.';
    message_hold_stock.style.color = '#f30649';

    if (hold_stock_pix_validation) {
        // insert under pix expiration field
        jQuery(message_hold_stock).insertAfter('#woocommerce_rm-pagbank-pix_pix_expiry_minutes~.description');
    }
    if (hold_stock_boleto_validation) {
        // insert under boleto expiration field
        jQuery(message_hold_stock).insertAfter('#woocommerce_rm-pagbank-boleto_boleto_expiry_days~.description');
    }
    
    // region show or hide fields based on the success behavior
    function handleSuccessBehaviorChange(element) {
        jQuery('#woocommerce_rm-pagbank_success_behavior_url').closest('tr').hide();
        jQuery('#woocommerce_rm-pagbank_success_behavior_js').closest('tr').hide();
        if (element.target.value === 'redirect') {
            jQuery('#woocommerce_rm-pagbank_success_behavior_url').closest('tr').show();
            jQuery('#woocommerce_rm-pagbank_success_behavior_js').closest('tr').hide();
        }

        if (element.target.value === 'js') {
            jQuery('#woocommerce_rm-pagbank_success_behavior_js').closest('tr').show();
            jQuery('#woocommerce_rm-pagbank_success_behavior_url').closest('tr').hide();
        }
    }
    jQuery(document).on('change', '#woocommerce_rm-pagbank_success_behavior', handleSuccessBehaviorChange);
    handleSuccessBehaviorChange({ target: $('#woocommerce_rm-pagbank_success_behavior')[0] });
    // endregion
});