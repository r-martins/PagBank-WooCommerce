jQuery(document).ready(function($) {

    //region Navigation Tabs
    jQuery('#ps-nav a').click(function(e) {
        e.preventDefault();
        jQuery('#ps-nav a').removeClass('nav-tab-active');
        jQuery(this).addClass('nav-tab-active');
        jQuery('.tab-content').hide();
        var selected_tab = jQuery(this).attr('href');
        jQuery(selected_tab).fadeIn();
        //update hash
        window.location.hash = selected_tab;
    });

    //load current tab from #
    var hash = window.location.hash;
    if (hash) {
        jQuery('.nav-tab-wrapper a[href="' + hash + '"]').click();
    }
    //endregion
    
    
    //region Displaying and hiding credit card options
    //display #woocommerce_rm-pagbank_cc_installment_options_fixed based on #woocommerce_rm-pagbank_cc_installment_options == fixed
    function hideOrShowFixedOptions() {
        return function () {
            if (jQuery(this).val() === 'fixed') {
                jQuery('#woocommerce_rm-pagbank_cc_installment_options_fixed').closest('tr').show();
            } else {
                jQuery('#woocommerce_rm-pagbank_cc_installment_options_fixed').closest('tr').hide();
            }
        };
    }

    jQuery(document).on('change', '#woocommerce_rm-pagbank_cc_installment_options', hideOrShowFixedOptions());
    hideOrShowFixedOptions().call(jQuery('#woocommerce_rm-pagbank_cc_installment_options'));

    //display woocommerce_rm-pagbank_cc_installment_options_min_total based on #woocommerce_rm-pagbank_cc_installment_options == min_total
    function hideOrShowMinTotalOptions() {
        return function () {
            if (jQuery(this).val() === 'min_total') {
                jQuery('#woocommerce_rm-pagbank_cc_installments_options_min_total').closest('tr').show();
            } else {
                jQuery('#woocommerce_rm-pagbank_cc_installments_options_min_total').closest('tr').hide();
            }
        };
    }

    jQuery(document).on('change', '#woocommerce_rm-pagbank_cc_installment_options', hideOrShowMinTotalOptions());
    hideOrShowMinTotalOptions().call(jQuery('#woocommerce_rm-pagbank_cc_installment_options'));


    //display #woocommerce_rm-pagbank_cc_installments_options_max_installments based on #woocommerce_rm-pagbank_cc_installments_options_limit_installments == yes
    function hideOrShowMasInstallmentsOptions() {
        return function () {
            if (jQuery(this).val() === 'yes') {
                jQuery('#woocommerce_rm-pagbank_cc_installments_options_max_installments').closest('tr').show();
            } else {
                jQuery('#woocommerce_rm-pagbank_cc_installments_options_max_installments').closest('tr').hide();
            }
        };
    }

    jQuery(document).on('change', '#woocommerce_rm-pagbank_cc_installments_options_limit_installments', hideOrShowMasInstallmentsOptions());
    hideOrShowMasInstallmentsOptions().call(jQuery('#woocommerce_rm-pagbank_cc_installments_options_limit_installments'));

    //endregion

	//region Showing that you are using test mode (when using a CONSANDBOX key)
	if (jQuery('#woocommerce_rm-pagbank_connect_key').val().indexOf('CONSANDBOX') === 0){
		//create p element
		var p = document.createElement('p');
		p.innerHTML = '⚠️ Você está usando o <strong>modo de testes</strong>. Veja <a href="https://dev.pagbank.uol.com.br/reference/simulador" target="_blank">documentação</a>.<br/>Para usar o modo de produção, altere suas credenciais.<br/>Lembre-se: pagamentos em Sandbox não aparecerão no PagBank, mesmo no ambiente Sandbox.';
		p.style.color = '#f30649';
		//insert under connect_key
		jQuery(p).insertAfter('#woocommerce_rm-pagbank_connect_key');
	}
	//endregion

    jQuery(".icon-color-picker").wpColorPicker({defaultColor: 'gray'});
});
