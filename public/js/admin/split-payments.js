/**
 * PagBank Connect - Split Payments Admin JavaScript
 */
jQuery(document).ready(function($) {
    'use strict';

    const $container = $('.pagbank-split-payments-repeater');
    if (!$container.length) {
        return;
    }

    // Get the enabled checkbox
    const $enabledCheckbox = $('#woocommerce_rm-pagbank_split_payments_enabled');
    const $dokanEnabled = $('#woocommerce_rm-pagbank-integrations_dokan_split_enabled');
    const $repeaterRow = $container.closest('tr');
    const $primaryAccountRow = $('#woocommerce_rm-pagbank_split_payments_primary_account_id').closest('tr');

    // Check for mutual exclusivity with Dokan split
    function checkMutualExclusivity() {
        const splitPaymentsChecked = $enabledCheckbox.is(':checked');
        const dokanChecked = $dokanEnabled.length > 0 ? $dokanEnabled.is(':checked') : false;
        
        // Remove previous warnings
        $enabledCheckbox.closest('tr').find('.mutual-exclusivity-warning').remove();
        
        if (splitPaymentsChecked && dokanChecked) {
            const warningMsg = '<span class="mutual-exclusivity-warning" style="color: #d63638; font-weight: bold; display: block; margin-top: 5px;">⚠ ' +
                'Não é possível ativar Divisão de Pagamentos e Split Dokan simultaneamente. Desative o Split Dokan primeiro.</span>';
            
            if (!$enabledCheckbox.closest('tr').find('.mutual-exclusivity-warning').length) {
                $enabledCheckbox.closest('tr').find('td').append(warningMsg);
            }
        }
    }

    // Toggle visibility based on checkbox state
    function toggleRepeaterVisibility() {
        const isEnabled = $enabledCheckbox.is(':checked');
        const dokanChecked = $dokanEnabled.length > 0 ? $dokanEnabled.is(':checked') : false;
        
        // Disable if Dokan is enabled
        if (dokanChecked && isEnabled) {
            $enabledCheckbox.prop('checked', false);
            checkMutualExclusivity();
            return;
        }
        
        $repeaterRow.toggle(isEnabled);
        $primaryAccountRow.toggle(isEnabled);
    }

    // Initial state
    toggleRepeaterVisibility();
    checkMutualExclusivity();

    // Toggle on checkbox change
    $enabledCheckbox.on('change', function() {
        const dokanChecked = $dokanEnabled.length > 0 ? $dokanEnabled.is(':checked') : false;
        
        if ($(this).is(':checked') && dokanChecked) {
            $(this).prop('checked', false);
            alert('Não é possível ativar Divisão de Pagamentos enquanto o Split Dokan estiver ativo. Desative o Split Dokan primeiro.');
            return;
        }
        
        toggleRepeaterVisibility();
        checkMutualExclusivity();
    });
    
    // Also check when Dokan checkbox changes (if it exists on the same page)
    if ($dokanEnabled.length > 0) {
        $dokanEnabled.on('change', function() {
            if ($(this).is(':checked') && $enabledCheckbox.is(':checked')) {
                $enabledCheckbox.prop('checked', false);
                toggleRepeaterVisibility();
            }
            checkMutualExclusivity();
        });
    }

    const $tbody = $container.find('.pagbank-split-payments-tbody');
    const $addButton = $container.find('.pagbank-add-row');
    const fieldKey = $container.closest('tr').find('input[type="text"]').first().attr('name');
    const baseFieldKey = fieldKey ? fieldKey.match(/^(.+?)\[\d+\]\[account_id\]$/)?.[1] || 'woocommerce_rm-pagbank_split_payments_receivers' : 'woocommerce_rm-pagbank_split_payments_receivers';

    // Template for new row
    function getRowTemplate(index) {
        return `
            <tr class="pagbank-split-payment-row">
                <td class="account-id-column">
                    <input 
                        type="text" 
                        name="${baseFieldKey}[${index}][account_id]" 
                        value="" 
                        placeholder="ACCO_xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                        pattern="ACCO_[A-Fa-f0-9]{8}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{12}"
                        class="regular-text pagbank-account-id"
                        maxlength="41"
                    />
                    <br>
                    <small>
                        <a href="https://ws.pbintegracoes.com/pspro/v7/connect/account-id/authorize" target="_blank" rel="noopener noreferrer" style="text-decoration: none;">
                            Qual é meu Account Id?
                        </a>
                    </small>
                </td>
                <td class="percentage-column">
                    <input 
                        type="number" 
                        name="${baseFieldKey}[${index}][percentage]" 
                        value="" 
                        placeholder="0.00"
                        min="0"
                        max="100"
                        step="0.01"
                        class="small-text pagbank-percentage"
                    />
                </td>
                <td class="actions-column">
                    <button type="button" class="button pagbank-remove-row">Remover</button>
                </td>
            </tr>
        `;
    }

    // Calculate total percentage and validate
    function calculateTotal() {
        let total = 0;
        $tbody.find('.pagbank-percentage').each(function() {
            const value = parseFloat($(this).val()) || 0;
            total += value;
        });
        $container.find('.total-value').text(total.toFixed(2));
        
        // Show warning if total is >= 100% (must be less than 100% to leave room for primary account)
        const $warning = $container.find('.percentage-warning');
        if (total >= 100) {
            if (!$warning.length) {
                $container.find('.pagbank-total-percentage').after(
                    '<span class="percentage-warning" style="color: #d63638; margin-left: 10px;">⚠ ' + 
                    'A soma dos percentuais deve ser menor que 100%, pois a conta principal também receberá uma parte</span>'
                );
            }
        } else {
            $warning.remove();
        }
    }

    // Add new row
    $addButton.on('click', function() {
        const index = $tbody.find('tr').length;
        const $newRow = $(getRowTemplate(index));
        $tbody.append($newRow);
        
        // Attach event listeners to new row
        $newRow.find('.pagbank-percentage').on('input', calculateTotal);
        $newRow.find('.pagbank-remove-row').on('click', function() {
            $(this).closest('tr').remove();
            reindexRows();
            calculateTotal();
        });
    });

    // Remove row
    $tbody.on('click', '.pagbank-remove-row', function() {
        $(this).closest('tr').remove();
        reindexRows();
        calculateTotal();
    });

    // Reindex rows to maintain sequential array indices
    function reindexRows() {
        $tbody.find('tr').each(function(index) {
            $(this).find('input').each(function() {
                const $input = $(this);
                const name = $input.attr('name');
                if (name) {
                    const newName = name.replace(/\[\d+\]/, `[${index}]`);
                    $input.attr('name', newName);
                }
            });
        });
    }

    // Calculate total on percentage input
    $tbody.on('input', '.pagbank-percentage', calculateTotal);

    // Initial calculation
    calculateTotal();
    
    // Ensure form submission includes the field even if table is empty
    $container.closest('form').on('submit', function() {
        // If table is empty but split is enabled, ensure empty array is sent
        if ($enabledCheckbox.is(':checked') && $tbody.find('tr').length === 0) {
            // Add a hidden input to ensure field is sent as empty array
            if (!$container.find('input[type="hidden"][name="' + baseFieldKey + '"]').length) {
                $container.append('<input type="hidden" name="' + baseFieldKey + '" value="" />');
            }
        }
    });
});

