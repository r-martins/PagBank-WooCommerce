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
            var $row = jQuery('#woocommerce_rm-pagbank-cc_cc_3ds_allow_continue').closest('tr');
            if (!$row.length) {
                return;
            }
            if (jQuery(this).is(':checked')) {
                $row.show();
            } else {
                $row.hide();
            }
        };
    }

    jQuery(document).on('change', '#woocommerce_rm-pagbank-cc_cc_3ds', hideOrShow3dsAllowContinueOption());
    if (jQuery('#woocommerce_rm-pagbank-cc_cc_3ds').length) {
        hideOrShow3dsAllowContinueOption().call(jQuery('#woocommerce_rm-pagbank-cc_cc_3ds'));
    }

    // display #woocommerce_rm-pagbank-cc_cc_3ds_retry based on #woocommerce_rm-pagbank-cc_cc_3ds == no
    function hideOrShowRetryWith3dsOption() {
        return function () {
            var $row = jQuery('#woocommerce_rm-pagbank-cc_cc_3ds_retry').closest('tr');
            if (!$row.length) {
                return;
            }
            if (jQuery(this).is(':checked')) {
                $row.hide();
            } else {
                $row.show();
            }
        };
    }

    jQuery(document).on('change', '#woocommerce_rm-pagbank-cc_cc_3ds', hideOrShowRetryWith3dsOption());
    if (jQuery('#woocommerce_rm-pagbank-cc_cc_3ds').length) {
        hideOrShowRetryWith3dsOption().call(jQuery('#woocommerce_rm-pagbank-cc_cc_3ds'));
    }

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
    if (value && typeof connect_key_status !== 'underfine'){
		//create p element
		var e_div = document.createElement('div');
		e_div.innerHTML = connect_key_status;
        e_div.setAttribute('id', 'pagbank-connect-key-info')
		jQuery(e_div).insertAfter('#woocommerce_rm-pagbank_connect_key');

        const infoIcon = document.querySelector('#pagbank-connect-key-info .dashicons-info');
        if (infoIcon) {
            // Get tooltip content from data-tip attribute
            const tooltipContent = infoIcon.getAttribute("data-tip")

            // Create tooltip element
            const tooltip = document.createElement("div")
            tooltip.className = "pagbank-tooltip"
            tooltip.innerHTML = tooltipContent
            document.body.appendChild(tooltip)

            // Function to update tooltip position
            function updateTooltipPosition() {
                if (!tooltip.classList.contains("visible")) return

                const rect = infoIcon.getBoundingClientRect()
                const scrollTop = window.pageYOffset || document.documentElement.scrollTop
                const scrollLeft = window.pageXOffset || document.documentElement.scrollLeft

                tooltip.style.left = rect.left + scrollLeft + rect.width / 2 - tooltip.offsetWidth / 2 + "px"
                tooltip.style.top = rect.top + scrollTop - tooltip.offsetHeight - 10 + "px"
            }

            // Show tooltip on hover
            infoIcon.addEventListener("mouseenter", (e) => {
                tooltip.classList.add("visible")
                updateTooltipPosition()
            })

            // Hide tooltip when mouse leaves
            infoIcon.addEventListener("mouseleave", () => {
                tooltip.classList.remove("visible")
            })

            // Update tooltip position on scroll
            window.addEventListener("scroll", updateTooltipPosition, { passive: true })

            // Update tooltip position on resize
            window.addEventListener("resize", updateTooltipPosition, { passive: true })

            // Add subtle animation to refresh button
            const refreshButton = document.querySelector(".rm-pagbank-refresh-button")
            if (refreshButton) {
                refreshButton.addEventListener("click", function (e) {
                const icon = this.querySelector(".dashicons")
                icon.style.transition = "transform 0.5s ease"
                icon.style.transform = "rotate(360deg)"

                // Reset rotation after animation completes
                setTimeout(() => {
                    icon.style.transition = "none"
                    icon.style.transform = "rotate(0deg)"

                    // Re-enable transition after reset
                    setTimeout(() => {
                    icon.style.transition = "transform 0.5s ease"
                    }, 50)
                }, 500)
                })
            }
        }
	}
	//endregion

    jQuery(".icon-color-picker").wpColorPicker({defaultColor: 'gray'});
    //region show or hide the pix discount options based on the selected option
    function handleShowPixDiscount() {
        jQuery('#woocommerce_rm-pagbank-pix_pix_show_price_locations').closest('tr').hide();
        if(jQuery('#woocommerce_rm-pagbank-pix_pix_show_price_discount').is(':checked')){
            jQuery('#woocommerce_rm-pagbank-pix_pix_show_price_locations').closest('tr').show();
        }
    }
    jQuery(document).on('change', handleShowPixDiscount);
    handleShowPixDiscount();
    //endregion

    //region Checkout PB mode + shipping fields
    function checkoutPbFieldRow(fieldKey) {
        return jQuery('#woocommerce_rm-pagbank-checkout-pb_' + fieldKey).closest('tr');
    }

    function handleCheckoutPbModeShippingFields() {
        var $mode = jQuery('#woocommerce_rm-pagbank-checkout-pb_checkout_pb_mode');
        if (!$mode.length) {
            return;
        }
        var mode = $mode.val();
        // Frete só no checkout padrão: o Woo redireciona sem frete calculado.
        // Em "meio de pagamento" o frete já vem do checkout Woo.
        var isDefaultCheckout = mode === 'default';
        var shippingMode = jQuery('#woocommerce_rm-pagbank-checkout-pb_checkout_pb_shipping_mode').val();

        checkoutPbFieldRow('checkout_pb_shipping_mode').toggle(isDefaultCheckout);
        checkoutPbFieldRow('checkout_pb_shipping_fixed_amount').toggle(
            isDefaultCheckout && shippingMode === 'fixed'
        );

        // Title fields render as h3 + p (outside <tr>).
        var $hintTitle = jQuery(
            '#woocommerce_rm-pagbank-checkout-pb_checkout_pb_shipping_calculate_hint, h3.rm-checkout-pb-shipping-calculate-hint'
        );
        var $hintDesc = $hintTitle.next('p');
        $hintTitle.add($hintDesc).toggle(isDefaultCheckout && shippingMode === 'calculate');
    }

    jQuery(document).on(
        'change',
        '#woocommerce_rm-pagbank-checkout-pb_checkout_pb_mode, #woocommerce_rm-pagbank-checkout-pb_checkout_pb_shipping_mode',
        handleCheckoutPbModeShippingFields
    );
    handleCheckoutPbModeShippingFields();
    //endregion

    //region Checkout PB one-click fields
    function handleCheckoutPbOneClickFields() {
        var $enabled = jQuery('#woocommerce_rm-pagbank-checkout-pb_checkout_pb_one_click_enabled');
        if (!$enabled.length) {
            return;
        }
        checkoutPbFieldRow('checkout_pb_one_click_label').toggle($enabled.is(':checked'));
    }

    jQuery(document).on(
        'change',
        '#woocommerce_rm-pagbank-checkout-pb_checkout_pb_one_click_enabled',
        handleCheckoutPbOneClickFields
    );
    handleCheckoutPbOneClickFields();
    //endregion

    //region Checkout PB installment fields
    function hideOrShowCheckoutPbFixedOptions() {
        return function () {
            var $fixed = jQuery('#woocommerce_rm-pagbank-checkout-pb_checkout_pb_installment_options_fixed');
            if (!$fixed.length) {
                return;
            }
            if (jQuery(this).val() === 'fixed') {
                $fixed.closest('tr').show();
            } else {
                $fixed.closest('tr').hide();
            }
        };
    }

    function hideOrShowCheckoutPbMinTotalOptions() {
        return function () {
            var $min = jQuery('#woocommerce_rm-pagbank-checkout-pb_checkout_pb_installments_options_min_total');
            if (!$min.length) {
                return;
            }
            if (jQuery(this).val() === 'min_total') {
                $min.closest('tr').show();
            } else {
                $min.closest('tr').hide();
            }
        };
    }

    function hideOrShowCheckoutPbMaxInstallmentsOptions() {
        return function () {
            var $max = jQuery('#woocommerce_rm-pagbank-checkout-pb_checkout_pb_installments_options_max_installments');
            if (!$max.length) {
                return;
            }
            if (jQuery(this).val() === 'yes') {
                $max.closest('tr').show();
            } else {
                $max.closest('tr').hide();
            }
        };
    }

    var $checkoutPbInstallmentOptions = jQuery('#woocommerce_rm-pagbank-checkout-pb_checkout_pb_installment_options');
    if ($checkoutPbInstallmentOptions.length) {
        jQuery(document).on(
            'change',
            '#woocommerce_rm-pagbank-checkout-pb_checkout_pb_installment_options',
            hideOrShowCheckoutPbFixedOptions()
        );
        hideOrShowCheckoutPbFixedOptions().call($checkoutPbInstallmentOptions);

        jQuery(document).on(
            'change',
            '#woocommerce_rm-pagbank-checkout-pb_checkout_pb_installment_options',
            hideOrShowCheckoutPbMinTotalOptions()
        );
        hideOrShowCheckoutPbMinTotalOptions().call($checkoutPbInstallmentOptions);
    }

    var $checkoutPbLimitInstallments = jQuery(
        '#woocommerce_rm-pagbank-checkout-pb_checkout_pb_installments_options_limit_installments'
    );
    if ($checkoutPbLimitInstallments.length) {
        jQuery(document).on(
            'change',
            '#woocommerce_rm-pagbank-checkout-pb_checkout_pb_installments_options_limit_installments',
            hideOrShowCheckoutPbMaxInstallmentsOptions()
        );
        hideOrShowCheckoutPbMaxInstallmentsOptions().call($checkoutPbLimitInstallments);
    }
    //endregion

    //region Checkout PB preview
    (function initCheckoutPbPreview() {
        if (typeof rmPagBankCheckoutPbPreview === 'undefined') {
            return;
        }
        var cfg = rmPagBankCheckoutPbPreview;
        var prefix = 'woocommerce_rm-pagbank-checkout-pb_';

        function buildPreviewButton(id) {
            return jQuery(
                '<button type="button" class="button rm-pagbank-checkout-pb-preview" id="' + id + '">' +
                '<span class="dashicons dashicons-visibility" style="vertical-align:middle;margin-top:3px;" aria-hidden="true"></span> ' +
                jQuery('<span/>').text(cfg.label || 'Pré-visualizar').html() +
                '</button>'
            );
        }

        // Só ao lado de Salvar: a section Pré-visualização fica no rodapé e
        // um segundo botão ali parece duplicata.
        var $save = jQuery('.woocommerce-save-button');
        if ($save.length && !jQuery('#rm-pagbank-checkout-pb-preview-save').length) {
            $save.before(buildPreviewButton('rm-pagbank-checkout-pb-preview-save').css('margin-right', '8px'));
        }

        function collectSettings() {
            var settings = {};
            jQuery('#mainform').find('[name^="' + prefix + '"]').each(function () {
                var $el = jQuery(this);
                var name = $el.attr('name') || '';
                if (name.indexOf(prefix) !== 0) {
                    return;
                }
                var key = name.slice(prefix.length).replace(/\[\]$/, '');
                if (!key || key.indexOf('checkout_pb_preview') === 0) {
                    return;
                }
                if ($el.is(':checkbox')) {
                    if ($el.is(':checked')) {
                        settings[key] = $el.val() || 'yes';
                    }
                    return;
                }
                if ($el.is('select[multiple]')) {
                    settings[key] = $el.val() || [];
                    return;
                }
                if (name.slice(-2) === '[]') {
                    if (!Array.isArray(settings[key])) {
                        settings[key] = [];
                    }
                    settings[key].push($el.val());
                    return;
                }
                settings[key] = $el.val();
            });
            return settings;
        }

        jQuery(document).on('click', '.rm-pagbank-checkout-pb-preview', function (e) {
            e.preventDefault();
            var $btn = jQuery(this);
            if ($btn.prop('disabled') || $btn.attr('aria-busy') === 'true') {
                return;
            }
            $btn.prop('disabled', true).attr('aria-busy', 'true');
            jQuery.post(cfg.ajaxUrl, {
                action: cfg.action,
                nonce: cfg.nonce,
                settings: collectSettings()
            })
                .done(function (resp) {
                    var url = resp && resp.data && resp.data.url;
                    if (resp && resp.success && url) {
                        window.open(url, '_blank', 'noopener,noreferrer');
                        return;
                    }
                    var msg = (resp && resp.data && resp.data.message) || cfg.error;
                    window.alert(msg);
                })
                .fail(function (xhr) {
                    var msg = cfg.error;
                    try {
                        var json = xhr.responseJSON;
                        if (json && json.data && json.data.message) {
                            msg = json.data.message;
                        }
                    } catch (err) {
                        // keep default
                    }
                    window.alert(msg);
                })
                .always(function () {
                    $btn.prop('disabled', false).attr('aria-busy', 'false');
                });
        });
    })();
    //endregion
});
