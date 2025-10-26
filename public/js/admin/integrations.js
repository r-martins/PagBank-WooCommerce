/**
 * PagBank Connect - Integrations Admin JavaScript
 */

jQuery(document).ready(function($) {
    'use strict';

    // Dokan Split Integration
    function initDokanSplitIntegration() {
        const $dokanEnabled = $('#woocommerce_rm-pagbank-integrations_dokan_split_enabled');
        const $marketplaceAccount = $('#woocommerce_rm-pagbank-integrations_marketplace_account_id');
        const $marketplaceReason = $('#woocommerce_rm-pagbank-integrations_split_marketplace_reason');
        const $custodyDays = $('#woocommerce_rm-pagbank-integrations_split_custody_days');
        const $chargebackLiability = $('#woocommerce_rm-pagbank-integrations_split_chargeback_liability');
        const $notifications = $('#woocommerce_rm-pagbank-integrations_split_notifications');
        
        // Toggle dependent fields
        function toggleDokanFields() {
            const isEnabled = $dokanEnabled.is(':checked');
            $marketplaceAccount.closest('tr').toggle(isEnabled);
            $marketplaceReason.closest('tr').toggle(isEnabled);
            $custodyDays.closest('tr').toggle(isEnabled);
            $chargebackLiability.closest('tr').toggle(isEnabled);
            $notifications.closest('tr').toggle(isEnabled);
        }
        
        $dokanEnabled.on('change', toggleDokanFields);
        toggleDokanFields(); // Initial state
        
        // Validate Account ID format
        $('#woocommerce_rm-pagbank-integrations_marketplace_account_id').on('blur', function() {
            const accountId = $(this).val();
            const pattern = /^ACCO_[A-Fa-f0-9]{8}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{12}$/;
            
            if (accountId && !pattern.test(accountId)) {
                $(this).addClass('error');
                if (!$(this).next('.error-message').length) {
                    $(this).after('<span class="error-message" style="color: red; font-size: 12px;">Formato inválido. Use: ACCO_xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx</span>');
                }
            } else {
                $(this).removeClass('error');
                $(this).next('.error-message').remove();
            }
        });
        
        // Validate custody days
        $('#woocommerce_rm-pagbank-integrations_split_custody_days').on('change', function() {
            const days = parseInt($(this).val());
            if (days < 1 || days > 30) {
                $(this).addClass('error');
                if (!$(this).next('.error-message').length) {
                    $(this).after('<span class="error-message" style="color: red; font-size: 12px;">Prazo deve ser entre 1 e 30 dias</span>');
                }
            } else {
                $(this).removeClass('error');
                $(this).next('.error-message').remove();
            }
        });
    }
    
    // Status refresh functionality
    function initStatusRefresh() {
        const $refreshBtn = $('.pagbank-refresh-status');
        if ($refreshBtn.length) {
            $refreshBtn.on('click', function(e) {
                e.preventDefault();
                const $btn = $(this);
                const originalText = $btn.text();
                
                $btn.text('Atualizando...').prop('disabled', true);
                
                // Simulate refresh (in real implementation, this would be an AJAX call)
                setTimeout(function() {
                    $btn.text(originalText).prop('disabled', false);
                    location.reload();
                }, 1000);
            });
        }
    }
    
    // Initialize all functionality
    initDokanSplitIntegration();
    initStatusRefresh();
    
    // Add refresh button to status section if not exists
    if ($('.pagbank-integration-status').length && !$('.pagbank-refresh-status').length) {
        $('.pagbank-integration-status').after(
            '<p><button type="button" class="button pagbank-refresh-status">Atualizar Status</button></p>'
        );
        initStatusRefresh();
    }
    
    // Integration status indicators
    $('.status-item').each(function() {
        const $item = $(this);
        const text = $item.text();
        
        if (text.includes('✓') || text.includes('Ativo') || text.includes('Configurado') || text.includes('Habilitado')) {
            $item.addClass('status-success');
        } else if (text.includes('⚠') || text.includes('Não configurado')) {
            $item.addClass('status-warning');
        } else if (text.includes('✗') || text.includes('Não instalado')) {
            $item.addClass('status-error');
        }
    });
});
