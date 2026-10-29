<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/** @var WC_ORDER $order */

use RM_PagBank\Connect;
use RM_PagBank\Helpers\Functions;
use RM_PagBank\Helpers\OrderMeta;
use RM_PagBank\Helpers\Recurring;
use RM_PagBank\Partner\Branding;
use RM_PagBank\Partner\Partner;

if (OrderMeta::get($order, 'payment_method') == ''){
	return;
}
$charge_id = OrderMeta::get($order, 'charge_id');
$bill_id = OrderMeta::get($order, 'partner_bill_id');
$isRecurringOrder = OrderMeta::get($order, '_is_recurring') > 0 || $order->get_meta('_recurring_cycle') > 0;
$pbOrderId = OrderMeta::get($order, 'order_id');
// Checkout PB / redirect pending: only checkout session id until payment creates an order.
if (!$pbOrderId) {
	$pbOrderId = OrderMeta::get($order, 'checkout_id');
}
$isSandbox = (string) OrderMeta::get($order, 'is_sandbox') === '1';
$partner = (string) OrderMeta::get($order, 'partner');
if ($partner === '') {
	// Trial / legacy tokens path may omit partner meta — infer Vindi from profile id.
	if ((int) OrderMeta::get($order, '_payment_profile_id') > 0) {
		$partner = Partner::VINDI;
	} else {
		$partner = Partner::PAGBANK;
	}
}
$branding = Branding::forOrderPartner($partner);
$partnerName = $branding->productName();
$logoRel = $branding->orderAdminLogo();
?>
<p class="form-field form-field-wide">
    <img src="<?php echo esc_url(plugins_url('public/images/' . $logoRel, WC_PAGSEGURO_CONNECT_PLUGIN_FILE));?>" style="width: 100px; height: auto; margin-right: 10px; float: left;" alt="<?php echo esc_attr($branding->orderAdminLogoAlt()); ?>"/>
	<?php if($isSandbox):?>
        <span class="sandbox-label">
        <span class="sandbox-icon"></span>
        <span class="sandbox" title="<?php esc_attr_e('Ambiente de Testes', 'pagbank-connect')?>"><?php esc_html_e('Sandbox', 'pagbank-connect')?></span>
    </span>
    <?php endif;?>
    
    <?php if(OrderMeta::get($order, '_split_applied')):?>
        <a href="#pagbank_split_details" class="split-label" title="<?php esc_attr_e('Pedido com Divisão de Pagamento', 'pagbank-connect')?>">
            <span class="dashicons dashicons-share split-icon"></span>
            <span class="split-text"><?php esc_html_e('Divisão de Pagamento', 'pagbank-connect')?></span>
        </a>
    <?php endif;?>
    
    <?php 
    if($isRecurringOrder):?>
    <a href="<?php echo Recurring::getAdminSubscriptionDetailsUrl($order)?>" class="recurring-label">
        <span class="recurring-icon"></span>
        <span class="recurring"><?php echo __('Pedido Recorrente', 'pagbank-connect')?></span>
    </a>
	<?php endif;?>

    <?php
	$paymentMethodMeta = (string) OrderMeta::get($order, 'payment_method');
	$hostedOrigin = (string) OrderMeta::get($order, '_hosted_checkout_origin');
	$checkoutSessionId = (string) OrderMeta::get($order, 'checkout_id');
	$isHostedCheckout = in_array($paymentMethodMeta, ['checkout_pb', 'redirect'], true)
		|| in_array($hostedOrigin, ['checkout_pb', 'redirect'], true)
		|| (strpos($checkoutSessionId, 'chk_') === 0)
		|| (strpos($checkoutSessionId, 'CHEC') === 0);
	$chargesMeta = OrderMeta::get($order, 'order_charges');
	$chargePm = is_array($chargesMeta) && isset($chargesMeta[0]['payment_method']) && is_array($chargesMeta[0]['payment_method'])
		? $chargesMeta[0]['payment_method']
		: [];
	$chargeType = (string) ($chargePm['type'] ?? '');

	$isBoletoMethod = $paymentMethodMeta === 'boleto'
		|| ($isHostedCheckout && ($chargeType === 'BOLETO' || OrderMeta::get($order, 'boleto_pdf')));
	if ($isBoletoMethod):?>
        <span class="form-field form-field-wide ps-pagbank-info">
			<?php if (OrderMeta::get($order, 'boleto_pdf')): ?>
                <span class="dashicons dashicons-download small-text"></span><a href="<?php echo esc_url(OrderMeta::get($order, 'boleto_pdf'))?>" title="Baixar Boleto em PDF">Baixar Boleto</a>
			<?php else: ?>
				<?php esc_html_e('Boleto', 'pagbank-connect'); ?>
			<?php endif; ?>
                <?php if (OrderMeta::get($order, 'partner') !== 'vindi' && OrderMeta::get($order, 'boleto_png')): ?>
                <span class="dashicons dashicons-format-image small-text"></span><a href="<?php echo esc_url(OrderMeta::get($order, 'boleto_png'))?>" title="Ver imagem do boleto">Ver Boleto</a>
                <?php endif; ?>
			<?php
			$boletoBarcode = (string) (
				OrderMeta::get($order, 'boleto_barcode_formatted')
				?: OrderMeta::get($order, 'boleto_barcode')
				?: ''
			);
			if ($boletoBarcode !== ''):
				?>
				<br/><small style="word-break:break-all;"><?php echo esc_html($boletoBarcode); ?></small>
			<?php endif; ?>
        </span>
    <?php endif;?>

    <?php
	if ($isHostedCheckout):
		$hostedLabel = ($hostedOrigin === 'redirect' || $paymentMethodMeta === 'redirect' || strpos($checkoutSessionId, 'CHEC') === 0)
			? __('Checkout PagBank', 'pagbank-connect')
			: __('Checkout PB', 'pagbank-connect');
		$hostedMethodHint = '';
		if (in_array($paymentMethodMeta, ['pix', 'boleto', 'credit_card', 'credit_card_token', 'cc'], true)) {
			$hostedMethodHint = [
				'pix' => __('PIX', 'pagbank-connect'),
				'boleto' => __('Boleto', 'pagbank-connect'),
				'credit_card' => __('Cartão de Crédito', 'pagbank-connect'),
				'credit_card_token' => __('Cartão de Crédito', 'pagbank-connect'),
				'cc' => __('Cartão de Crédito', 'pagbank-connect'),
			][ $paymentMethodMeta ] ?? '';
		} elseif (in_array($chargeType, ['PIX', 'BOLEPIX', 'BOLETO', 'CREDIT_CARD', 'CREDIT_CARD_3DS'], true)) {
			$hostedMethodHint = [
				'PIX' => __('PIX', 'pagbank-connect'),
				'BOLEPIX' => __('PIX', 'pagbank-connect'),
				'BOLETO' => __('Boleto', 'pagbank-connect'),
				'CREDIT_CARD' => __('Cartão de Crédito', 'pagbank-connect'),
				'CREDIT_CARD_3DS' => __('Cartão de Crédito', 'pagbank-connect'),
			][ $chargeType ] ?? '';
		}
		?>
		<span class="form-field form-field-wide ps-pagbank-info">
			<?php
			echo esc_html(
				$hostedMethodHint !== ''
					? sprintf('%s — %s', $hostedLabel, $hostedMethodHint)
					: $hostedLabel
			);
			?>
			<?php if ($checkoutSessionId !== ''): ?>
				<br/><small><?php echo esc_html(sprintf(
					/* translators: %s = checkout session id */
					__('Sessão: %s', 'pagbank-connect'),
					$checkoutSessionId
				)); ?></small>
			<?php endif; ?>
			<?php
			$payUrl = OrderMeta::get($order, 'redirect_url');
			if ($payUrl && !$order->is_paid() && in_array($order->get_status(), ['pending', 'on-hold'], true)):
				?>
				<br/><a href="<?php echo esc_url($payUrl); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Link de pagamento', 'pagbank-connect'); ?></a><span class="dashicons dashicons-external"></span>
			<?php endif; ?>
		</span>
	<?php endif; ?>

    <?php
	$isPixMethod = $paymentMethodMeta === 'pix'
		|| ($isHostedCheckout && (
			in_array($chargeType, ['PIX', 'BOLEPIX'], true)
			|| OrderMeta::get($order, 'pix_qrcode')
			|| OrderMeta::get($order, 'pix_qrcode_text')
		));
	if ($isPixMethod):?>
        <span class="form-field form-field-wide ps-pagbank-info">
			<?php esc_html_e('PIX', 'pagbank-connect'); ?>
			<?php if (OrderMeta::get($order, 'pix_qrcode') || OrderMeta::get($order, 'pix_qrcode_text')): ?>
            — <a href="<?php echo esc_url(\RM_PagBank\Helpers\PixQr::getDisplayUrl($order))?>" target="_blank" rel="noopener noreferrer" title="<?php echo esc_attr__('Abrir QrCode Pix em nova aba.', 'pagbank-connect'); ?>">Ver QrCode Pix</a><span class="dashicons dashicons-external"></span>
			<?php endif; ?>
			<?php
			$pixText = (string) OrderMeta::get($order, 'pix_qrcode_text');
			if ($pixText !== ''):
				?>
				<br/><small style="word-break:break-all;"><?php echo esc_html($pixText); ?></small>
			<?php endif; ?>
        </span>
    <?php endif;?>

	<?php
	$isCreditCardMethod = in_array($paymentMethodMeta, ['credit_card', 'credit_card_token', 'cc'], true);
	// Fallback: charge payload already has card data but meta not remapped yet.
	if (!$isCreditCardMethod && $isHostedCheckout) {
		$isCreditCardMethod = in_array($chargeType, ['CREDIT_CARD', 'CREDIT_CARD_3DS'], true);
	}
	if ($isCreditCardMethod):
		$cardBrand = (string) (
			OrderMeta::get($order, '_card_brand')
			?: OrderMeta::get($order, '_payment_company_code')
			?: ''
		);
		$cardFirst = (string) (
			OrderMeta::get($order, '_card_first_digits')
			?: ''
		);
		$cardLast = (string) (
			OrderMeta::get($order, '_card_last_digits')
			?: OrderMeta::get($order, 'card_last4')
			?: ''
		);
		$cardHolder = (string) (
			OrderMeta::get($order, '_card_holder')
			?: OrderMeta::get($order, '_card_holder_name')
			?: ''
		);
		$cardInstallments = (int) (
			OrderMeta::get($order, 'card_installments')
			?: OrderMeta::get($order, '_card_installments')
			?: 0
		);
		// Fallback from persisted charge (webhook / force update).
		if (is_array($chargePm['card'] ?? null)) {
			$c = $chargePm['card'];
			if ($cardBrand === '') {
				$cardBrand = (string) ($c['brand'] ?? ($c['payment_company']['code'] ?? ''));
			}
			if ($cardFirst === '' && !empty($c['first_digits'])) {
				$cardFirst = (string) $c['first_digits'];
			}
			if ($cardLast === '' && !empty($c['last_digits'])) {
				$cardLast = (string) $c['last_digits'];
			}
			if ($cardHolder === '' && !empty($c['holder']['name'])) {
				$cardHolder = (string) $c['holder']['name'];
			}
		}
		if ($cardInstallments < 1 && !empty($chargePm['installments'])) {
			$cardInstallments = max(1, (int) $chargePm['installments']);
		}
		// Trial token response stored on the order
		$recurringCard = OrderMeta::get($order, 'order_recurring_card');
		if (is_array($recurringCard)) {
			if ($cardBrand === '' && !empty($recurringCard['brand'])) {
				$cardBrand = (string) $recurringCard['brand'];
			}
			if ($cardFirst === '' && !empty($recurringCard['first_digits'])) {
				$cardFirst = (string) $recurringCard['first_digits'];
			}
			if ($cardLast === '' && !empty($recurringCard['last_digits'])) {
				$cardLast = (string) $recurringCard['last_digits'];
			}
			if ($cardHolder === '' && !empty($recurringCard['holder']['name'])) {
				$cardHolder = (string) $recurringCard['holder']['name'];
			}
		}
		?>
		<span class="form-field form-field-wide ps-pagbank-info">
			<?php if($cardInstallments > 1):?>
				<?php _e('Cartão de Crédito em', 'pagbank-connect');?> <?php echo esc_html((string) $cardInstallments);?>x
			<?php else: ?>
				<?php _e('Cartão de Crédito', 'pagbank-connect');?>
			<?php endif;?>
            <?php
            $_3dsst = OrderMeta::get($order, '_card_3ds_status');
            $_3dsid = (string) OrderMeta::get($order, '_card_3ds_id');
            if (!$_3dsst) {
                $payloadRaw = OrderMeta::get($order, '_card_3ds_payload');
                $payload = is_string($payloadRaw) ? json_decode($payloadRaw, true) : null;
                if (is_array($payload) && !empty($payload['status'])) {
                    $_3dsst = (string) $payload['status'];
                }
                if ($_3dsid === '' && is_array($payload) && !empty($payload['authentication_transaction_id'])) {
                    $_3dsid = (string) $payload['authentication_transaction_id'];
                }
                if (!$_3dsst) {
                    $chargesMeta = OrderMeta::get($order, 'order_charges');
                    $chargeType = is_array($chargesMeta) && isset($chargesMeta[0]['payment_method']['type'])
                        ? (string) $chargesMeta[0]['payment_method']['type']
                        : '';
                    if ($chargeType === 'CREDIT_CARD_3DS') {
                        $_3dsst = 'AUTHENTICATED';
                    }
                }
            }
            ?>
            <?php if($_3dsst):?>
                <span class="3dstatus" title="<?php
                    echo esc_attr(
                        $_3dsid !== ''
                            ? sprintf('Status Autenticação 3D (id: %s)', $_3dsid)
                            : 'Status Autenticação 3D'
                    );
                ?>">(3DS: <?php echo esc_html($_3dsst);?>)</span>
			<?php endif;?>
			<?php if($cardBrand !== '' || $cardLast !== ''):
				$brand_url = Functions::getCcFlagUrl($cardBrand);
                $brandLabel = $cardBrand !== '' ? wc_strtoupper(str_replace('_', ' ', $cardBrand)) . ' - ' : '';
				if ($brand_url) {
					$brandLabel = '<img src="' . esc_url($brand_url) . '" style="width: 30px; height: auto; margin-right: 10px; float: left;" alt="' . esc_attr($cardBrand) . '"/>';
				}
				$masked = ($cardFirst !== '' ? $cardFirst : '******') . 'xx xxxx' . ($cardLast !== '' ? $cardLast : '****');
				?>
				<br/><?php echo esc_html($masked); ?> <?php echo $brandLabel; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php if ($cardHolder !== ''): ?>
				<br/>Titular: <?php echo esc_html($cardHolder);?>
				<?php endif;?>
			<?php endif;?>
		</span>
	<?php endif;?>

	<?php if($charge_id || $bill_id):
		$portalUrl = $branding->adminPortalUrl(
			$bill_id ? (string) $bill_id : null,
			$charge_id ? (string) $charge_id : null,
			$isSandbox
		);
		$viewLabel = sprintf(
			/* translators: %s = PagBank or Vindi */
			__('Ver na %s', 'pagbank-connect'),
			$partnerName
		);
		// Keep legacy PagBank wording ("Ver no PagBank") for that partner.
		if ($partner !== Partner::VINDI) {
			$viewLabel = __('Ver no PagBank', 'pagbank-connect');
		}

		if ($portalUrl) {
			$linkTagHtml = '<a href="' . esc_url($portalUrl) . '" target="_blank" rel="noopener noreferrer" title="' . esc_attr__('Segure Ctrl ou Cmd para abrir em outra aba.', 'pagbank-connect') . '">' . esc_html($viewLabel) . '</a><span class="dashicons dashicons-external"></span>';
		} else {
			$disabledTitle = __('Pedidos feitos em ambiente de testes não estão disponíveis no painel do PagBank.', 'pagbank-connect');
			$linkTagHtml = '<span title="' . esc_attr($disabledTitle) . '">' . esc_html($viewLabel) . '</span><span class="dashicons dashicons-external" title="' . esc_attr($disabledTitle) . '"></span>';
		}
		$yapayTxn = trim((string) OrderMeta::get($order, 'yapay_transaction_id'));
		$yapayUrl = $branding->yapaySaleUrl($yapayTxn, $isSandbox);
		if ($yapayUrl) {
			$linkTagHtml .= '<br/><a href="' . esc_url($yapayUrl) . '" target="_blank" rel="noopener noreferrer" title="' . esc_attr__('Segure Ctrl ou Cmd para abrir em outra aba.', 'pagbank-connect') . '">' . esc_html__('Ver na Yapay', 'pagbank-connect') . '</a><span class="dashicons dashicons-external"></span>';
		}
		?>
		<span class="form-field form-field-wide ps-pagbank-info">
            <?php echo $linkTagHtml;?>
			<br/>
			<?php
			if ($charge_id) {
				echo esc_html(sprintf(
					/* translators: %s = charge id */
					__('Charge ID: %s', 'pagbank-connect'),
					(string) $charge_id
				));
			}
			if ($bill_id) {
				echo ($charge_id ? '<br/>' : '') . esc_html(sprintf(
					/* translators: %s = bill id */
					__('Bill ID: %s', 'pagbank-connect'),
					(string) $bill_id
				));
			}
			?>
		</span>
	<?php endif;?>

    <?php if ($pbOrderId):
		$forceLabel = sprintf(
			/* translators: %s = PagBank or Vindi */
			__('Forçar atualização %s', 'pagbank-connect'),
			$partnerName
		);
		$forceTitle = sprintf(
			/* translators: %s = PagBank or Vindi */
			__('Irá buscar mudanças no pedido e processá-las se houver. Use somente se tiver dificuldades em receber as notificações da %s por conta de bloqueios ou indisponibilidade.', 'pagbank-connect'),
			$partnerName
		);
		?>
        <span class="form-field form-field-wide ps-pagbank-info">
            <a href="<?php echo esc_url(get_site_url() . '/?wc-api=pagbank_force_order_update&order_id=' . $order->get_id() . '&pagbank_order_id=' . $pbOrderId)?>" title="<?php echo esc_attr($forceTitle); ?>"><?php echo esc_html($forceLabel); ?></a><span class="dashicons dashicons-update"></span>
        </span>
    <?php endif;?>
</p>
