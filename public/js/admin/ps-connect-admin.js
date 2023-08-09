jQuery(document).ready(function($) {

    //region Navigation Tabs
    $('#ps-nav a').click(function(e) {
        e.preventDefault();
        $('#ps-nav a').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        $('.tab-content').hide();
        var selected_tab = $(this).attr('href');
        $(selected_tab).fadeIn();
        //update hash
        window.location.hash = selected_tab;
    });

    //load current tab from #
    var hash = window.location.hash;
    if (hash) {
        $('.nav-tab-wrapper a[href="' + hash + '"]').click();
    }
    //endregion

    
    //region Displaying and hiding credit card options
    //display #woocommerce_rm-pagbank_cc_installment_options_fixed based on #woocommerce_rm-pagbank_cc_installment_options == fixed
    function hideOrShowFixedOptions() {
        return function () {
            if ($(this).val() === 'fixed') {
                $('#woocommerce_rm-pagbank_cc_installment_options_fixed').closest('tr').show();
            } else {
                $('#woocommerce_rm-pagbank_cc_installment_options_fixed').closest('tr').hide();
            }
        };
    }

    $('#woocommerce_rm-pagbank_cc_installment_options').change(hideOrShowFixedOptions());
    hideOrShowFixedOptions().call($('#woocommerce_rm-pagbank_cc_installment_options'));

    //display woocommerce_rm-pagbank_cc_installment_options_min_total based on #woocommerce_rm-pagbank_cc_installment_options == min_total
    function hideOrShowMinTotalOptions() {
        return function () {
            if ($(this).val() === 'min_total') {
                $('#woocommerce_rm-pagbank_cc_installments_options_min_total').closest('tr').show();
            } else {
                $('#woocommerce_rm-pagbank_cc_installments_options_min_total').closest('tr').hide();
            }
        };
    }
    
    $('#woocommerce_rm-pagbank_cc_installment_options').change(hideOrShowMinTotalOptions());
    hideOrShowMinTotalOptions().call($('#woocommerce_rm-pagbank_cc_installment_options'));


    //display #woocommerce_rm-pagbank_cc_installments_options_max_installments based on #woocommerce_rm-pagbank_cc_installments_options_limit_installments == yes
    function hideOrShowMasInstallmentsOptions() {
        return function () {
            if ($(this).val() === 'yes') {
                $('#woocommerce_rm-pagbank_cc_installments_options_max_installments').closest('tr').show();
            } else {
                $('#woocommerce_rm-pagbank_cc_installments_options_max_installments').closest('tr').hide();
            }
        };
    }
    
    $('#woocommerce_rm-pagbank_cc_installments_options_limit_installments').change(hideOrShowMasInstallmentsOptions());
    hideOrShowMasInstallmentsOptions().call($('#woocommerce_rm-pagbank_cc_installments_options_limit_installments'));
    
    //endregion
    
});