jQuery(document).ready(function($) {
    //region Displaying and hiding credit card options
    //display #woocommerce_rm-pagbank-cc_cc_installment_options_fixed based on #woocommerce_rm-pagbank-cc_cc_installment_options == fixed
    function hideOrShowFixedOptions() {
        return function () {
            if (jQuery(this).val() === 'fixed') {
                jQuery('#woocommerce_rm-pagbank-cc_cc_installment_options_fixed').closest('tr').show();
            } else {
                jQuery('#woocommerce_rm-pagbank-cc_cc_installment_options_fixed').closest('tr').hide();
            }
        };
    }

    jQuery(document).on('change', '#woocommerce_rm-pagbank-cc_cc_installment_options', hideOrShowFixedOptions());
    hideOrShowFixedOptions().call(jQuery('#woocommerce_rm-pagbank-cc_cc_installment_options'));

    // display #woocommerce_rm-pagbank-cc_cc_installment_product_page_type based on #woocommerce_rm-pagbank-cc_cc_installment_product_page == yes or #woocommerce_rm-pagbank-cc_cc_installment_shortcode_enabled == yes
    function hideOrShowInstallmentTypeOption() {
        return function () {
            let installmentProductPage = jQuery('#woocommerce_rm-pagbank-cc_cc_installment_product_page');
            let installmentShotcode = jQuery('#woocommerce_rm-pagbank-cc_cc_installment_shortcode_enabled');
            if (installmentProductPage.is(':checked') || installmentShotcode.is(':checked')) {
                jQuery('#woocommerce_rm-pagbank-cc_cc_installment_product_page_type').closest('tr').show();
            } else {
                jQuery('#woocommerce_rm-pagbank-cc_cc_installment_product_page_type').closest('tr').hide();
            }
        };
    }

    jQuery(document).on('change', '#woocommerce_rm-pagbank-cc_cc_installment_product_page', hideOrShowInstallmentTypeOption());
    hideOrShowInstallmentTypeOption().call(jQuery('#woocommerce_rm-pagbank-cc_cc_installment_product_page'));

    jQuery(document).on('change', '#woocommerce_rm-pagbank-cc_cc_installment_shortcode_enabled', hideOrShowInstallmentTypeOption());
    hideOrShowInstallmentTypeOption().call(jQuery('#woocommerce_rm-pagbank-cc_cc_installment_shortcode_enabled'));


    //display woocommerce_rm-pagbank-cc_cc_installment_options_min_total based on #woocommerce_rm-pagbank-cc_cc_installment_options == min_total
    function hideOrShowMinTotalOptions() {
        return function () {
            if (jQuery(this).val() === 'min_total') {
                jQuery('#woocommerce_rm-pagbank-cc_cc_installments_options_min_total').closest('tr').show();
            } else {
                jQuery('#woocommerce_rm-pagbank-cc_cc_installments_options_min_total').closest('tr').hide();
            }
        };
    }

    jQuery(document).on('change', '#woocommerce_rm-pagbank-cc_cc_installment_options', hideOrShowMinTotalOptions());
    hideOrShowMinTotalOptions().call(jQuery('#woocommerce_rm-pagbank-cc_cc_installment_options'));


    //display #woocommerce_rm-pagbank-cc_cc_installments_options_max_installments based on #woocommerce_rm-pagbank-cc_cc_installments_options_limit_installments == yes
    function hideOrShowMaxInstallmentsOptions() {
        return function () {
            if (jQuery(this).val() === 'yes') {
                jQuery('#woocommerce_rm-pagbank-cc_cc_installments_options_max_installments').closest('tr').show();
            } else {
                jQuery('#woocommerce_rm-pagbank-cc_cc_installments_options_max_installments').closest('tr').hide();
            }
        };
    }

    jQuery(document).on('change', '#woocommerce_rm-pagbank-cc_cc_installments_options_limit_installments', hideOrShowMaxInstallmentsOptions());
    hideOrShowMaxInstallmentsOptions().call(jQuery('#woocommerce_rm-pagbank-cc_cc_installments_options_limit_installments'));

    // display #woocommerce_rm-pagbank-cc_cc_3ds_allow_continue based on #woocommerce_rm-pagbank-cc_cc_3ds == yes
    function hideOrShow3dsAllowContinueOption() {
        return function () {
            if (jQuery(this).is(':checked')) {
                jQuery('#woocommerce_rm-pagbank-cc_cc_3ds_allow_continue').closest('tr').show();
            } else {
                jQuery('#woocommerce_rm-pagbank-cc_cc_3ds_allow_continue').closest('tr').hide();
            }
        };
    }

    jQuery(document).on('change', '#woocommerce_rm-pagbank-cc_cc_3ds', hideOrShow3dsAllowContinueOption());
    hideOrShow3dsAllowContinueOption().call(jQuery('#woocommerce_rm-pagbank-cc_cc_3ds'));

    // display #woocommerce_rm-pagbank-cc_cc_3ds_retry based on #woocommerce_rm-pagbank-cc_cc_3ds == no
    function hideOrShowRetryWith3dsOption() {
        return function () {
            if (jQuery(this).is(':checked')) {
                jQuery('#woocommerce_rm-pagbank-cc_cc_3ds_retry').closest('tr').hide();
            } else {
                jQuery('#woocommerce_rm-pagbank-cc_cc_3ds_retry').closest('tr').show();
            }
        };
    }

    jQuery(document).on('change', '#woocommerce_rm-pagbank-cc_cc_3ds', hideOrShowRetryWith3dsOption());
    hideOrShowRetryWith3dsOption().call(jQuery('#woocommerce_rm-pagbank-cc_cc_3ds'));

    // display #woocommerce_rm-pagbank-cc_cc_3ds_retry based on #woocommerce_rm-pagbank-cc_cc_3ds == no
    function hideOrShowRetryAttempts() {
        return function () {
            if (jQuery(this).is(':checked')) {
                jQuery('#woocommerce_rm-pagbank-recurring_retry_attempts').closest('tr').show();
            } else {
                jQuery('#woocommerce_rm-pagbank-recurring_retry_attempts').closest('tr').hide();
            }
        };
    }

    jQuery(document).on('change', '#woocommerce_rm-pagbank-recurring_retry_charge', hideOrShowRetryAttempts());
    hideOrShowRetryAttempts().call(jQuery('#woocommerce_rm-pagbank-recurring_retry_charge'));

    //endregion

    var value = jQuery('#woocommerce_rm-pagbank_connect_key').val();
    //region Showing that you are using test mode (when using a CONSANDBOX key)
    if (value && value.indexOf('CONSANDBOX') === 0){
		//create p element
		var p = document.createElement('p');
		p.innerHTML = '⚠️ Você está usando o <strong>modo de testes</strong>. Veja <a href="https://dev.pagbank.uol.com.br/reference/simulador" target="_blank">documentação</a>.<br/>Para usar o modo de produção, altere suas credenciais.<br/>Lembre-se: pagamentos em Sandbox não aparecerão no PagBank, mesmo no ambiente Sandbox.';
		p.style.color = '#f30649';
		//insert under connect_key
		jQuery(p).insertAfter('#woocommerce_rm-pagbank_connect_key');
	}
	//endregion

    jQuery(".icon-color-picker").wpColorPicker({defaultColor: 'gray'});

    // region show or hide fields based on the success behavior
    function handleShowPixDiscount() {
        jQuery('#woocommerce_rm-pagbank-pix_pix_show_price_locations').closest('tr').hide();
        if(jQuery('#woocommerce_rm-pagbank-pix_pix_show_price_discount').is(':checked')){
            jQuery('#woocommerce_rm-pagbank-pix_pix_show_price_locations').closest('tr').show();
        }
    }
    jQuery(document).on('change', handleShowPixDiscount);
    handleShowPixDiscount();
});
