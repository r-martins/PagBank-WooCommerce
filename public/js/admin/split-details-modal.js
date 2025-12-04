/**
 * Split Details Modal
 * Handles the modal display for PagBank split payment details
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Handle click on "View Split Details" button
        $(document).on('click', '.pagbank-view-split-details', function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const orderId = $button.data('order-id');
            const splitId = $button.data('split-id') || '';
            const hasSplitData = $button.data('has-split-data') == 1;
            
            if (!orderId) {
                alert('Erro: ID do pedido não encontrado.');
                return;
            }
            
            if (!splitId && !hasSplitData) {
                alert('Erro: Dados do split não encontrados.');
                return;
            }
            
            // Disable button and show loading
            $button.prop('disabled', true);
            const originalText = $button.html();
            $button.html('<span class="spinner is-active" style="float: none; margin: 0 5px 0 0;"></span> Carregando...');
            
            // Make AJAX request
            $.ajax({
                url: pagbankSplitDetails.ajaxurl,
                type: 'POST',
                data: {
                    action: 'pagbank_get_split_details',
                    nonce: pagbankSplitDetails.nonce,
                    order_id: orderId,
                    split_id: splitId,
                    has_split_data: hasSplitData ? 1 : 0
                },
                success: function(response) {
                    $button.prop('disabled', false);
                    $button.html(originalText);
                    
                    if (response.success && response.data) {
                        // Create modal content HTML
                        const modalContentHtml = formatJsonDisplay(response.data.data);
                        
                        // Create or update inline content div
                        let $modalDiv = $('#pagbank-split-details-modal');
                        if ($modalDiv.length === 0) {
                            $modalDiv = $('<div id="pagbank-split-details-modal" style="display: none;"></div>');
                            $('body').append($modalDiv);
                        }
                        
                        // Update content
                        $modalDiv.html(modalContentHtml);
                        
                        // Open Thickbox modal
                        tb_show('Detalhes Completos do Split - PagBank', '#TB_inline?width=900&height=600&inlineId=pagbank-split-details-modal');
                        
                        // Adjust modal size after opening
                        setTimeout(function() {
                            $('#TB_window').css({
                                'max-width': '90%',
                                'margin-left': '-45%'
                            });
                            $('#TB_ajaxContent').css({
                                'max-height': '80vh',
                                'overflow-y': 'auto',
                                'padding': '20px'
                            });
                        }, 100);
                    } else {
                        alert('Erro ao carregar detalhes do split: ' + (response.data?.message || 'Erro desconhecido'));
                    }
                },
                error: function(xhr, status, error) {
                    $button.prop('disabled', false);
                    $button.html(originalText);
                    alert('Erro ao carregar detalhes do split: ' + error);
                }
            });
        });
    });
    
    /**
     * Format JSON data for display
     */
    function formatJsonDisplay(data) {
        if (!data) {
            return '<p>Nenhum dado disponível.</p>';
        }
        
        let html = '<div class="pagbank-split-details-container">';
        
        // Summary section
        html += '<div class="pagbank-split-summary" style="margin-bottom: 20px; padding: 15px; background: #f5f5f5; border-radius: 4px;">';
        html += '<h3 style="margin-top: 0;">Resumo</h3>';
        html += '<table class="widefat" style="margin-top: 10px;">';
        html += '<tr><td><strong>ID do Split:</strong></td><td><code>' + escapeHtml(data.id || 'N/A') + '</code></td></tr>';
        html += '<tr><td><strong>Status:</strong></td><td><span class="status-' + (data.status || '').toLowerCase() + '">' + escapeHtml(data.status || 'N/A') + '</span></td></tr>';
        html += '<tr><td><strong>Método:</strong></td><td>' + escapeHtml(data.method || 'N/A') + '</td></tr>';
        if (data.created_at) {
            html += '<tr><td><strong>Criado em:</strong></td><td>' + formatDate(data.created_at) + '</td></tr>';
        }
        if (data.confirmed_at) {
            html += '<tr><td><strong>Confirmado em:</strong></td><td>' + formatDate(data.confirmed_at) + '</td></tr>';
        }
        html += '</table>';
        html += '</div>';
        
        // Payment section
        if (data.payment) {
            html += '<div class="pagbank-split-payment" style="margin-bottom: 20px; padding: 15px; background: #f5f5f5; border-radius: 4px;">';
            html += '<h3 style="margin-top: 0;">Informações do Pagamento</h3>';
            html += '<table class="widefat" style="margin-top: 10px;">';
            if (data.payment.id) {
                html += '<tr><td><strong>ID do Pagamento:</strong></td><td><code>' + escapeHtml(data.payment.id) + '</code></td></tr>';
            }
            if (data.payment.amount) {
                const amount = data.payment.amount.value ? (data.payment.amount.value / 100).toFixed(2) : '0.00';
                html += '<tr><td><strong>Valor:</strong></td><td>R$ ' + amount + '</td></tr>';
            }
            if (data.payment.method) {
                html += '<tr><td><strong>Método:</strong></td><td>' + escapeHtml(data.payment.method) + '</td></tr>';
            }
            if (data.payment.paid !== undefined) {
                html += '<tr><td><strong>Pago:</strong></td><td>' + (data.payment.paid ? 'Sim' : 'Não') + '</td></tr>';
            }
            html += '</table>';
            html += '</div>';
        }
        
        // Receivers section
        if (data.receivers && Array.isArray(data.receivers)) {
            html += '<div class="pagbank-split-receivers" style="margin-bottom: 20px;">';
            html += '<h3>Recebedores (' + data.receivers.length + ')</h3>';
            
            data.receivers.forEach(function(receiver, index) {
                const isPrimary = receiver.type === 'PRIMARY';
                const borderColor = isPrimary ? '#0073aa' : '#46b450';
                
                html += '<div class="receiver-item" style="margin-bottom: 15px; padding: 15px; background: #fff; border-left: 4px solid ' + borderColor + '; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">';
                html += '<h4 style="margin-top: 0; color: ' + borderColor + ';">' + escapeHtml(receiver.type || 'UNKNOWN') + '</h4>';
                
                html += '<table class="widefat" style="margin-top: 10px;">';
                if (receiver.account && receiver.account.id) {
                    html += '<tr><td style="width: 150px;"><strong>Account ID:</strong></td><td><code>' + escapeHtml(receiver.account.id) + '</code></td></tr>';
                }
                if (receiver.account && receiver.account.email) {
                    html += '<tr><td><strong>Email:</strong></td><td>' + escapeHtml(receiver.account.email) + '</td></tr>';
                }
                if (receiver.amount && receiver.amount.value !== undefined) {
                    if (data.method === 'PERCENTAGE') {
                        html += '<tr><td><strong>Percentual:</strong></td><td>' + receiver.amount.value + '%</td></tr>';
                        if (data.payment && data.payment.amount && data.payment.amount.value) {
                            const totalValue = data.payment.amount.value / 100;
                            const receiverValue = (totalValue * receiver.amount.value) / 100;
                            html += '<tr><td><strong>Valor Pago:</strong></td><td>R$ ' + receiverValue.toFixed(2) + '</td></tr>';
                        }
                    } else {
                        const value = receiver.amount.value / 100;
                        html += '<tr><td><strong>Valor:</strong></td><td>R$ ' + value.toFixed(2) + '</td></tr>';
                    }
                }
                if (receiver.reason) {
                    html += '<tr><td><strong>Motivo:</strong></td><td>' + escapeHtml(receiver.reason) + '</td></tr>';
                }
                if (receiver.configurations) {
                    if (receiver.configurations.custody) {
                        const custodyActive = receiver.configurations.custody.apply || false;
                        html += '<tr><td><strong>Custódia:</strong></td><td>' + (custodyActive ? 'Ativa' : 'Inativa') + '</td></tr>';
                    }
                    if (receiver.configurations.liable !== undefined) {
                        html += '<tr><td><strong>Liable:</strong></td><td>' + (receiver.configurations.liable ? 'Sim' : 'Não') + '</td></tr>';
                    }
                }
                html += '</table>';
                html += '</div>';
            });
            
            html += '</div>';
        }
        
        // Full JSON section
        html += '<div class="pagbank-split-json" style="margin-top: 20px;">';
        html += '<h3>JSON Completo</h3>';
        html += '<div style="background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 4px; overflow-x: auto; max-height: 400px; overflow-y: auto;">';
        html += '<pre style="margin: 0; font-family: Consolas, Monaco, monospace; font-size: 12px; line-height: 1.5;">' + escapeHtml(JSON.stringify(data, null, 2)) + '</pre>';
        html += '</div>';
        html += '</div>';
        
        html += '</div>';
        
        return html;
    }
    
    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        if (text === null || text === undefined) {
            return '';
        }
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    /**
     * Format date string
     */
    function formatDate(dateString) {
        if (!dateString) return 'N/A';
        try {
            const date = new Date(dateString);
            return date.toLocaleString('pt-BR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        } catch (e) {
            return dateString;
        }
    }
    
})(jQuery);

