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

    <?php if(OrderMeta::get($order, 'payment_method') === 'boleto'):?>
        <span class="form-field form-field-wide ps-pagbank-info">
                <span class="dashicons dashicons-download small-text"></span><a href="<?php echo esc_url(OrderMeta::get($order, 'boleto_pdf'))?>" title="Baixar Boleto em PDF">Baixar Boleto</a>
                <?php if (OrderMeta::get($order, 'partner') !== 'vindi' && OrderMeta::get($order, 'boleto_png')): ?>
                <span class="dashicons dashicons-format-image small-text"></span><a href="<?php echo esc_url(OrderMeta::get($order, 'boleto_png'))?>" title="Ver imagem do boleto">Ver Boleto</a>
                <?php endif; ?>
        </span>
    <?php endif;?>

    <?php if(OrderMeta::get($order, 'payment_method') === 'pix'):?>
        <span class="form-field form-field-wide ps-pagbank-info">
            <a href="<?php echo esc_url(\RM_PagBank\Helpers\PixQr::getDisplayUrl($order))?>" target="_blank" rel="noopener noreferrer" title="<?php echo esc_attr__('Abrir QrCode Pix em nova aba.', 'pagbank-connect'); ?>">Ver QrCode Pix</a><span class="dashicons dashicons-external"></span>
        </span>
    <?php endif;?>

	<?php
	$paymentMethodMeta = (string) OrderMeta::get($order, 'payment_method');
	$isCreditCardMethod = in_array($paymentMethodMeta, ['credit_card', 'credit_card_token', 'cc'], true);
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
			<?php if(OrderMeta::get($order, 'card_installments')):?>
				<?php _e('Cartão de Crédito em', 'pagbank-connect');?> <?php esc_html_e(OrderMeta::get($order, 'card_installments'), 'pagbank-connect');?>x
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
