/**
 * CNPJ details modal (ReceitaWS) — Thickbox, same pattern as split details.
 */
(function ($) {
    'use strict';

    function escapeHtml(text) {
        if (text === null || text === undefined) {
            return '';
        }
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function row(label, value) {
        if (value === null || value === undefined || String(value).trim() === '') {
            return '';
        }
        return (
            '<tr><th scope="row" style="width:32%;text-align:left;padding:8px 10px;border-bottom:1px solid #eee;">' +
            escapeHtml(label) +
            '</th><td style="padding:8px 10px;border-bottom:1px solid #eee;">' +
            escapeHtml(value) +
            '</td></tr>'
        );
    }

    function formatAddress(data) {
        const parts = [];
        const line1 = [data.logradouro, data.numero].filter(Boolean).join(', ');
        if (line1) {
            parts.push(line1);
        }
        if (data.complemento) {
            parts.push(data.complemento);
        }
        if (data.bairro) {
            parts.push(data.bairro);
        }
        const cityUf = [data.municipio, data.uf].filter(Boolean).join(' / ');
        if (cityUf) {
            parts.push(cityUf);
        }
        if (data.cep) {
            parts.push('CEP ' + data.cep);
        }
        return parts.join(' — ');
    }

    function formatCnpjHtml(data) {
        let html = '<div class="pagbank-cnpj-details-container">';
        html += '<table class="widefat striped" style="border:1px solid #e2e4e7;margin:0;">';
        html += '<tbody>';
        html += row('CNPJ', data.cnpj);
        html += row('Razão social', data.nome);
        html += row('Nome fantasia', data.fantasia);
        html += row('Situação', data.situacao);
        html += row('Abertura', data.abertura);
        html += row('Tipo', data.tipo);
        html += row('Porte', data.porte);
        html += row('Natureza jurídica', data.natureza_juridica);
        html += row('Capital social', data.capital_social);
        html += row('Telefone', data.telefone);
        html += row('E-mail', data.email);
        html += row('Endereço', formatAddress(data));

        const ap = data.atividade_principal || {};
        if (ap.code || ap.text) {
            html += row(
                'Atividade principal',
                (ap.code ? ap.code + ' — ' : '') + (ap.text || '')
            );
        }

        if (Array.isArray(data.atividades_secundarias) && data.atividades_secundarias.length) {
            const list = data.atividades_secundarias
                .map(function (a) {
                    return (a.code ? a.code + ' — ' : '') + (a.text || '');
                })
                .filter(Boolean)
                .join('\n');
            html +=
                '<tr><th scope="row" style="width:32%;text-align:left;padding:8px 10px;border-bottom:1px solid #eee;vertical-align:top;">Atividades secundárias</th>' +
                '<td style="padding:8px 10px;border-bottom:1px solid #eee;white-space:pre-line;">' +
                escapeHtml(list) +
                '</td></tr>';
        }

        if (Array.isArray(data.qsa) && data.qsa.length) {
            const socios = data.qsa
                .map(function (s) {
                    return s.nome + (s.qual ? ' (' + s.qual + ')' : '');
                })
                .join('\n');
            html +=
                '<tr><th scope="row" style="width:32%;text-align:left;padding:8px 10px;border-bottom:1px solid #eee;vertical-align:top;">Quadro societário</th>' +
                '<td style="padding:8px 10px;border-bottom:1px solid #eee;white-space:pre-line;">' +
                escapeHtml(socios) +
                '</td></tr>';
        }

        html += '</tbody></table>';
        html +=
            '<p style="margin:16px 0 0;color:#646970;font-size:12px;text-align:right;">Fonte: Receitaws</p>';
        html += '</div>';
        return html;
    }

    $(document).ready(function () {
        $(document).on('click', '.pagbank-view-cnpj-details', function (e) {
            e.preventDefault();

            const $btn = $(this);
            const orderId = $btn.data('order-id');
            const cnpj = String($btn.data('cnpj') || '');

            if (!orderId || !cnpj) {
                window.alert('CNPJ ou pedido não encontrado.');
                return;
            }

            if (typeof pagbankCnpjDetails === 'undefined') {
                window.alert('Script de consulta CNPJ não carregado.');
                return;
            }

            $btn.addClass('is-busy').attr('aria-busy', 'true');

            $.ajax({
                url: pagbankCnpjDetails.ajaxurl,
                type: 'POST',
                data: {
                    action: 'pagbank_get_cnpj_details',
                    nonce: pagbankCnpjDetails.nonce,
                    order_id: orderId,
                    cnpj: cnpj
                },
                success: function (response) {
                    $btn.removeClass('is-busy').attr('aria-busy', 'false');

                    if (!response.success || !response.data) {
                        window.alert(
                            (response.data && response.data.message) ||
                                'Erro ao consultar CNPJ.'
                        );
                        return;
                    }

                    const data = response.data;
                    const title =
                        'Informações do CNPJ ' + (data.cnpj || cnpj);

                    let $modalDiv = $('#pagbank-cnpj-details-modal');
                    if ($modalDiv.length === 0) {
                        $modalDiv = $(
                            '<div id="pagbank-cnpj-details-modal" style="display:none;"></div>'
                        );
                        $('body').append($modalDiv);
                    }
                    $modalDiv.html(formatCnpjHtml(data));

                    tb_show(
                        title,
                        '#TB_inline?width=720&height=620&inlineId=pagbank-cnpj-details-modal'
                    );

                    setTimeout(function () {
                        $('#TB_window').css({
                            'max-width': '90%',
                            'margin-left': '-36%'
                        });
                        $('#TB_ajaxContent').css({
                            'max-height': '75vh',
                            'overflow-y': 'auto',
                            padding: '20px'
                        });
                    }, 100);
                },
                error: function (_xhr, _status, error) {
                    $btn.removeClass('is-busy').attr('aria-busy', 'false');
                    window.alert('Erro ao consultar CNPJ: ' + error);
                }
            });
        });
    });
})(jQuery);
