<?php

namespace RM_PagBank\Partner;

use RM_PagBank\Helpers\OrderMeta;
use WC_Order;

/**
 * Display names / icons by partner (settings key vs order meta).
 */
class Branding
{
    /** @var PartnerDetector */
    private $detector;

    public function __construct(?PartnerDetector $detector = null)
    {
        $this->detector = $detector ?: PartnerDetector::fromSettings();
    }

    public static function forOrderPartner(string $partner): self
    {
        // Force partner via fake key prefix for display helpers
        $fake = $partner === Partner::VINDI ? 'CONVD' : 'CON';
        return new self(new PartnerDetector($fake));
    }

    /**
     * Order-note / status prefix: "Vindi" or "PagBank" (from order meta, else settings).
     *
     * @param WC_Order|null $order
     */
    public static function notePrefix($order = null): string
    {
        if ($order instanceof WC_Order) {
            $partner = OrderMeta::readPartner($order);
            if ($partner !== '') {
                return self::forOrderPartner($partner)->productName();
            }
        }

        return (new self())->productName();
    }

    public function productName(): string
    {
        return $this->detector->isVindi() ? 'Vindi' : 'PagBank';
    }

    /** Checkout notice when cart total is below R$1,00 (non-recurring). */
    public function paymentUnavailableBelowMinimumMessage(): string
    {
        return __('Não disponível para pedidos inferiores a R$1,00', 'pagbank-connect');
    }

    /** Label for connectInfo tooltip (generic — works for any partner). */
    public function accountLabel(): string
    {
        return __('Conta Gateway', 'pagbank-connect');
    }

    /** Noun used in admin settings (“Fornecer ao Gateway…”). */
    public function gatewayNoun(): string
    {
        return __('Gateway', 'pagbank-connect');
    }

    public const VINDI_SANDBOX_WEBHOOK_DOC =
        'https://ajuda.pbintegracoes.com/hc/pt-br/articles/49433839195533-Como-configurar-o-Webhook-de-Sandbox';

    /**
     * Sandbox admin notice + optional doc links (HTML, already escaped where needed).
     * Warning style (orange) — not an error.
     */
    public function sandboxAdminNoticeHtml(): string
    {
        $name = $this->productName();
        $html = '<div class="rm-pagbank-connect-key-info is-warning">';
        $html .= '<span class="rm-pagbank-notice-icon" aria-hidden="true">⚠️</span>';
        $html .= '<strong>' . esc_html__('Sandbox ativo', 'pagbank-connect') . ': </strong> ';

        if ($this->detector->isVindi()) {
            $html .= esc_html(sprintf(
                /* translators: %s = partner name (Vindi) */
                __('você está testando a %s. Pedidos feitos neste ambiente não aparecerão na conta de produção.', 'pagbank-connect'),
                $name
            ));
            $html .= '<br>';
            $html .= '<a href="' . esc_url('https://app.vindi.com.br/admin/sandbox') . '" target="_blank" rel="noopener noreferrer" class="rm-pagbank-doc-link">';
            $html .= esc_html__('Acessar Sandbox Vindi', 'pagbank-connect');
            $html .= '</a><br>';
            $html .= '<a href="' . esc_url('https://developers.vindi.com.br/docs/modo-trial-e-sandbox-quais-n%C3%BAmeros-de-cart%C3%B5es-posso-usar-para-teste') . '" target="_blank" rel="noopener noreferrer" class="rm-pagbank-doc-link">';
            $html .= esc_html__('Cartões de teste (sandbox)', 'pagbank-connect');
            $html .= '</a>';
        } else {
            $html .= esc_html__(
                'você está testando o PagBank. Pedidos feitos neste ambiente não aparecerão no PagBank.',
                'pagbank-connect'
            );
            $html .= '<br>';
            $html .= '<a href="' . esc_url('https://developer.pagbank.com.br/docs/simulador') . '" target="_blank" rel="noopener noreferrer" class="rm-pagbank-doc-link">';
            $html .= esc_html__('Documentação do Simulador', 'pagbank-connect');
            $html .= '</a><br>';
            $html .= '<a href="' . esc_url('https://ajuda.pbintegracoes.com/hc/pt-br/articles/22375426666253-Cart%C3%B5es-de-Cr%C3%A9dito-para-Testes-PagBank') . '" target="_blank" rel="noopener noreferrer" class="rm-pagbank-doc-link">';
            $html .= esc_html__('Cartões de Teste', 'pagbank-connect');
            $html .= '</a>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Vindi sandbox: partner webhooks API unavailable — merchant must register URL manually.
     *
     * @param string $webhookUrl Full notification URL from connectInfo
     */
    public function sandboxManualWebhookNoticeHtml(string $webhookUrl): string
    {
        $html = '<div class="rm-pagbank-connect-key-info is-warning">';
        $html .= '<span class="rm-pagbank-notice-icon" aria-hidden="true">ℹ️️ </span>';
        $html .= '<strong>' . esc_html__('Webhook de sandbox', 'pagbank-connect') . ': </strong> ';
        $html .= esc_html__(
            'No sandbox Vindi o webhook não é criado automaticamente. Cadastre a URL abaixo no painel Sandbox para receber notificações.',
            'pagbank-connect'
        );
        if ($webhookUrl !== '') {
            $html .= '<br><code>' . esc_html($webhookUrl) . '</code>';
        }
        $html .= '<br>';
        $html .= '<a href="' . esc_url(self::VINDI_SANDBOX_WEBHOOK_DOC) . '" target="_blank" rel="noopener noreferrer" class="rm-pagbank-doc-link">';
        $html .= esc_html__('Como configurar o Webhook de Sandbox', 'pagbank-connect');
        $html .= '</a>';
        $html .= '</div>';

        return $html;
    }

    public function companyName(): string
    {
        return 'PB Integrações';
    }

    public function methodTitlePrefix(): string
    {
        return $this->detector->isVindi() ? 'via Vindi' : 'via PagBank';
    }

    public function softDescriptorDefault(): string
    {
        return $this->detector->isVindi() ? 'CompraViaVindi' : 'CompraViaPagBank';
    }

    public function privacyEmailDomain(): string
    {
        return 'pbintegracoes.cc';
    }

    /** Relative path under public/images/ */
    public function menuIcon(): string
    {
        if ($this->detector->isVindi() && file_exists(WC_PAGSEGURO_CONNECT_BASE_DIR . '/public/images/vindi/icon.svg')) {
            return 'vindi/icon.svg';
        }
        return 'pagbank.svg';
    }

    /** Logo for Woo admin order details (wider wordmark). */
    public function orderAdminLogo(): string
    {
        if ($this->detector->isVindi()
            && file_exists(WC_PAGSEGURO_CONNECT_BASE_DIR . '/public/images/vindi/logo-vindi.svg')
        ) {
            return 'vindi/logo-vindi.svg';
        }
        return 'pagbank.svg';
    }

    public function orderAdminLogoAlt(): string
    {
        return $this->productName() . ' Logo';
    }

    /**
     * Vindi admin bill URL (fatura). Prefer this over charges for unpaid PIX/boleto.
     */
    public function adminBillUrl(string $billId, bool $isSandbox): ?string
    {
        $billId = trim($billId);
        if ($billId === '' || !$this->detector->isVindi()) {
            return null;
        }

        $host = $isSandbox ? 'sandbox-app.vindi.com.br' : 'app.vindi.com.br';
        return 'https://' . $host . '/admin/bills/' . rawurlencode($billId);
    }

    /**
     * Admin portal URL for a charge, or null when not linkable (PagBank sandbox).
     */
    public function adminChargeUrl(string $chargeId, bool $isSandbox): ?string
    {
        $chargeId = trim($chargeId);
        if ($chargeId === '') {
            return null;
        }

        if ($this->detector->isVindi()) {
            $host = $isSandbox ? 'sandbox-app.vindi.com.br' : 'app.vindi.com.br';
            return 'https://' . $host . '/admin/charges/' . rawurlencode($chargeId);
        }

        if ($isSandbox) {
            return null;
        }

        $transaction = str_replace('CHAR_', '', $chargeId);
        return 'https://minhaconta.pagseguro.uol.com.br/transacao/detalhes/' . rawurlencode($transaction);
    }

    /**
     * Prefer Vindi bill URL (works for unpaid PIX/boleto); charge as fallback / PagBank.
     */
    public function adminPortalUrl(?string $billId, ?string $chargeId, bool $isSandbox): ?string
    {
        $billUrl = $this->adminBillUrl((string) $billId, $isSandbox);
        if ($billUrl) {
            return $billUrl;
        }

        return $this->adminChargeUrl((string) $chargeId, $isSandbox);
    }

    /**
     * Yapay intermediador sale page for a Vindi charge, or null when there is no sale id.
     */
    public function yapaySaleUrl(string $transactionId, bool $isSandbox): ?string
    {
        if (!$this->detector->isVindi()) {
            return null;
        }
        $transactionId = trim($transactionId);
        if ($transactionId === '') {
            return null;
        }
        $host = $isSandbox ? 'intermediador-sandbox.yapay.com.br' : 'intermediador.yapay.com.br';
        return 'https://' . $host . '/orders/' . rawurlencode($transactionId) . '/sale';
    }

    public function checkoutIconCc(): string
    {
        return 'cc.svg';
    }
}
