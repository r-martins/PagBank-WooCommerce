<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/** @var WC_ORDER $order */

use RM_PagBank\Connect;
use RM_PagBank\Helpers\Functions;
use RM_PagBank\Helpers\OrderMeta;
use RM_PagBank\Helpers\Recurring;
use RM_PagBank\Helpers\TaxId;
use RM_PagBank\Partner\Branding;
use RM_PagBank\Partner\Partner;

if (OrderMeta::get($order, 'payment_method') == ''){
	return;
}
$charge_id = OrderMeta::get($order, 'charge_id');
$bill_id = OrderMeta::get($order, 'partner_bill_id');
$isRecurringOrder = OrderMeta::get($order, '_is_recurring') > 0 || $order->get_meta('_recurring_cycle') > 0;
$pbOrderId = OrderMeta::get($order, 'order_id');
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

$paymentMethodMeta = (string) OrderMeta::get($order, 'payment_method');
$isCreditCardMethod = in_array($paymentMethodMeta, ['credit_card', 'credit_card_token', 'cc'], true);
$hasSplit = (bool) OrderMeta::get($order, '_split_applied');

// Blocks (and classic when posted) store CPF/CNPJ here — not in Woo _billing_cpf.
$customerDocument = \RM_PagBank\Connect\OrderMetaBoxes::getCustomerDocument($order);
$customerDocumentDisplay = $customerDocument !== '' ? TaxId::formatForDisplay($customerDocument) : '';
$docType = $customerDocument !== '' ? TaxId::detectType($customerDocument) : '';
$docLabel = $docType === 'cnpj'
	? __('CNPJ', 'pagbank-connect')
	: __('CPF', 'pagbank-connect');

$actionsHtml = '';
$metaRows = [];

if ($paymentMethodMeta === 'boleto') {
	$boletoPdf = OrderMeta::get($order, 'boleto_pdf');
	if ($boletoPdf) {
		$actionsHtml .= '<a class="pagbank-order-info__action" href="' . esc_url($boletoPdf) . '" title="' . esc_attr__('Baixar Boleto em PDF', 'pagbank-connect') . '">'
			. '<span class="dashicons dashicons-download" aria-hidden="true"></span>'
			. esc_html__('Baixar Boleto', 'pagbank-connect')
			. '</a>';
	}
	if ($partner !== Partner::VINDI && OrderMeta::get($order, 'boleto_png')) {
		$actionsHtml .= '<a class="pagbank-order-info__action" href="' . esc_url(OrderMeta::get($order, 'boleto_png')) . '" title="' . esc_attr__('Ver imagem do boleto', 'pagbank-connect') . '">'
			. '<span class="dashicons dashicons-format-image" aria-hidden="true"></span>'
			. esc_html__('Ver Boleto', 'pagbank-connect')
			. '</a>';
	}
}

if ($paymentMethodMeta === 'pix') {
	$actionsHtml .= '<a class="pagbank-order-info__action" href="' . esc_url(\RM_PagBank\Helpers\PixQr::getDisplayUrl($order)) . '" target="_blank" rel="noopener noreferrer" title="' . esc_attr__('Abrir QrCode Pix em nova aba.', 'pagbank-connect') . '">'
		. '<span class="dashicons dashicons-external" aria-hidden="true"></span>'
		. esc_html__('Ver QrCode Pix', 'pagbank-connect')
		. '</a>';
}

if ($customerDocument !== '') {
	$docValueHtml = '<span class="pagbank-order-info__doc-value">'
		. esc_html($customerDocumentDisplay !== '' ? $customerDocumentDisplay : $customerDocument);
	if ($docType === 'cnpj') {
		$docValueHtml .= '<a href="#"'
			. ' class="pagbank-order-info__cnpj-btn pagbank-view-cnpj-details"'
			. ' data-order-id="' . esc_attr((string) $order->get_id()) . '"'
			. ' data-cnpj="' . esc_attr($customerDocument) . '"'
			. ' title="' . esc_attr__('Mais Informações', 'pagbank-connect') . '">'
			. '<span class="dashicons dashicons-media-document" aria-hidden="true"></span>'
			. '<span class="screen-reader-text">' . esc_html__('Mais Informações', 'pagbank-connect') . '</span>'
			. '</a>';
	}
	$docValueHtml .= '</span>';
	$metaRows[] = [$docLabel, $docValueHtml];
}

if ($isCreditCardMethod) {
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

	$installments = OrderMeta::get($order, 'card_installments');
	$methodLabel = $installments
		? sprintf(
			/* translators: %s = installment count */
			__('Cartão de Crédito em %sx', 'pagbank-connect'),
			esc_html((string) $installments)
		)
		: __('Cartão de Crédito', 'pagbank-connect');

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

	$cardHtml = esc_html($methodLabel);
	if ($_3dsst) {
		$cardHtml .= ' <span class="pagbank-order-info__3ds" title="' . esc_attr(
			$_3dsid !== ''
				? sprintf('Status Autenticação 3D (id: %s)', $_3dsid)
				: 'Status Autenticação 3D'
		) . '">(3DS: ' . esc_html((string) $_3dsst) . ')</span>';
	}

	if ($cardBrand !== '' || $cardLast !== '') {
		$brand_url = Functions::getCcFlagUrl($cardBrand);
		$brandLabel = $cardBrand !== '' ? wc_strtoupper(str_replace('_', ' ', $cardBrand)) . ' - ' : '';
		if ($brand_url) {
			$brandLabel = '<img class="pagbank-order-info__card-brand" src="' . esc_url($brand_url) . '" alt="' . esc_attr($cardBrand) . '"/>';
		}
		$masked = ($cardFirst !== '' ? $cardFirst : '******') . 'xx xxxx' . ($cardLast !== '' ? $cardLast : '****');
		$cardHtml .= '<br/>' . $brandLabel . esc_html($masked);
		if ($cardHolder !== '') {
			$cardHtml .= '<br/>' . esc_html(sprintf(
				/* translators: %s = card holder name */
				__('Titular: %s', 'pagbank-connect'),
				$cardHolder
			));
		}
	}

	$metaRows[] = [__('Pagamento', 'pagbank-connect'), $cardHtml];
}

if ($charge_id || $bill_id) {
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
		$actionsHtml .= '<a class="pagbank-order-info__action" href="' . esc_url($portalUrl) . '" target="_blank" rel="noopener noreferrer" title="' . esc_attr__('Segure Ctrl ou Cmd para abrir em outra aba.', 'pagbank-connect') . '">'
			. '<span class="dashicons dashicons-external" aria-hidden="true"></span>'
			. esc_html($viewLabel)
			. '</a>';
	} else {
		$disabledTitle = __('Pedidos feitos em ambiente de testes não estão disponíveis no painel do PagBank.', 'pagbank-connect');
		$actionsHtml .= '<span class="pagbank-order-info__action is-disabled" title="' . esc_attr($disabledTitle) . '">'
			. '<span class="dashicons dashicons-external" aria-hidden="true"></span>'
			. esc_html($viewLabel)
			. '</span>';
	}

	$yapayTxn = trim((string) OrderMeta::get($order, 'yapay_transaction_id'));
	$yapayUrl = $branding->yapaySaleUrl($yapayTxn, $isSandbox);
	if ($yapayUrl) {
		$actionsHtml .= '<a class="pagbank-order-info__action" href="' . esc_url($yapayUrl) . '" target="_blank" rel="noopener noreferrer" title="' . esc_attr__('Segure Ctrl ou Cmd para abrir em outra aba.', 'pagbank-connect') . '">'
			. '<span class="dashicons dashicons-external" aria-hidden="true"></span>'
			. esc_html__('Ver na Yapay', 'pagbank-connect')
			. '</a>';
	}

	if ($charge_id) {
		$metaRows[] = [__('Charge ID', 'pagbank-connect'), esc_html((string) $charge_id)];
	}
	if ($bill_id) {
		$metaRows[] = [__('Bill ID', 'pagbank-connect'), esc_html((string) $bill_id)];
	}
}
?>
<div class="pagbank-order-info">
	<div class="pagbank-order-info__header">
		<img
			class="pagbank-order-info__logo"
			src="<?php echo esc_url(plugins_url('public/images/' . $logoRel, WC_PAGSEGURO_CONNECT_PLUGIN_FILE)); ?>"
			alt="<?php echo esc_attr($branding->orderAdminLogoAlt()); ?>"
		/>
		<div class="pagbank-order-info__badges">
			<?php if ($isSandbox): ?>
				<span class="sandbox-label">
					<span class="sandbox-icon"></span>
					<span class="sandbox" title="<?php esc_attr_e('Ambiente de Testes', 'pagbank-connect'); ?>"><?php esc_html_e('Sandbox', 'pagbank-connect'); ?></span>
				</span>
			<?php endif; ?>

			<?php if ($hasSplit): ?>
				<a href="#pagbank_split_details" class="split-label" title="<?php esc_attr_e('Pedido com Divisão de Pagamento', 'pagbank-connect'); ?>">
					<span class="dashicons dashicons-share split-icon"></span>
					<span class="split-text"><?php esc_html_e('Divisão de Pagamento', 'pagbank-connect'); ?></span>
				</a>
			<?php endif; ?>

			<?php if ($isRecurringOrder): ?>
				<a href="<?php echo esc_url(Recurring::getAdminSubscriptionDetailsUrl($order)); ?>" class="recurring-label">
					<span class="recurring-icon"></span>
					<span class="recurring"><?php echo esc_html__('Pedido Recorrente', 'pagbank-connect'); ?></span>
				</a>
			<?php endif; ?>
		</div>
	</div>

	<div class="pagbank-order-info__body">
		<?php if ($actionsHtml !== ''): ?>
			<div class="pagbank-order-info__actions">
				<?php echo $actionsHtml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_* above ?>
			</div>
		<?php endif; ?>

		<?php if ($metaRows !== []): ?>
			<dl class="pagbank-order-info__meta">
				<?php foreach ($metaRows as [$label, $valueHtml]): ?>
					<div class="pagbank-order-info__row">
						<dt><?php echo esc_html($label); ?></dt>
						<dd><?php echo $valueHtml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_* above ?></dd>
					</div>
				<?php endforeach; ?>
			</dl>
		<?php endif; ?>

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
			<div class="pagbank-order-info__footer">
				<a
					class="pagbank-order-info__force"
					href="<?php echo esc_url(get_site_url() . '/?wc-api=pagbank_force_order_update&order_id=' . $order->get_id() . '&pagbank_order_id=' . $pbOrderId); ?>"
					title="<?php echo esc_attr($forceTitle); ?>"
				>
					<span class="dashicons dashicons-update" aria-hidden="true"></span>
					<?php echo esc_html($forceLabel); ?>
				</a>
			</div>
		<?php endif; ?>
	</div>
</div>
