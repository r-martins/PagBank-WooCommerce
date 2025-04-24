<?php

namespace RM_PagBank\Connect\Payments;

use RM_PagBank\Helpers\Params;
use RM_PagBank\Object\Amount;
use RM_PagBank\Object\QrCode;
use WC_Data_Exception;
use WC_Order;

/**
 * Class Pix
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank\Connect\Payments
 */
class Pix extends Common
{
    public string $code = 'pix';

	/**
	 * Prepares PIX params to be sent to PagSeguro
	 *
	 * @return array
	 * @throws WC_Data_Exception
	 */
    public function prepare() :array
    {
        $return = $this->getDefaultParameters();
        $qr_code = new QrCode();

        $amount = new Amount();
        $orderTotal = $this->order->get_total();
        $discountExcludesShipping = Params::getPixConfig('pix_discount_excludes_shipping', false) == 'yes';

        if (($discountConfig = Params::getPixConfig('pix_discount', 0)) && ! is_wc_endpoint_url('order-pay')) {
            $discount = Params::getDiscountValue($discountConfig, $this->order, $discountExcludesShipping);
            $orderTotal = $orderTotal - $discount;

            $fee = new \WC_Order_Item_Fee();
            $fee->set_name(__('Desconto para pagamento com PIX', 'rm-pagbank'));

            // Define the fee amount, negative number to discount
            $fee->set_amount(-$discount);
            $fee->set_total(-$discount);

            // Define the tax class for the fee
            $fee->set_tax_class('');
            $fee->set_tax_status('none');

            // Add the fee to the order
            $this->order->add_item($fee);

            // Recalculate the order
            $this->order->calculate_totals();
        }

        $amount->setValue(Params::convertToCents($orderTotal));
        $qr_code->setAmount($amount);
        //calculate expiry date based on current time + expiry days using ISO 8601 format
        $qr_code->setExpirationDate(gmdate('c', strtotime('+' . Params::getPixConfig('pix_expiry_minutes', 1440) . 'minute')));

        $return['qr_codes'] = [$qr_code];
        return $return;
    }

	/**
	 * Set some variables and requires the template with pix instructions for the success page
	 *
	 * @param $order_id
	 *
	 * @return void
	 * @noinspection SpellCheckingInspection
	 **/
	public function getThankyouInstructions($order_id){
        $order = new WC_Order($order_id);
        $qr_code = $order->get_meta('pagbank_pix_qrcode');
        $qr_code_text = $order->get_meta('pagbank_pix_qrcode_text');
        $qr_code_exp = $order->get_meta('pagbank_pix_qrcode_expiration');
        require_once dirname(__FILE__) . '/../../templates/pix-instructions.php';
        parent::getThankyouInstructions($order_id);
    }

    /**
     * Show the discount on the product page
     * @param mixed $price
     * @param mixed $product
     */
    public static function showPriceDiscountPixProduct($price, $product) {

        $enable_show_discount = Params::getPixConfig('pix_show_price_discount', 'no') == 'yes';
        // Pega o percentual de desconto do Pix
        $discount = Params::getPixConfig('pix_discount', 0);
        
        $page = is_product() ? 'product' : (is_shop() || is_product_category() ? 'category' : '');
        $where = Params::getPixConfig('pix_show_price_locations');
        $showPage = is_array($where) && in_array($page, $where);

        if (!$enable_show_discount || !$discount || !$showPage) {
            return $price;
        }

        // Evita exibir o desconto em produtos variáveis na página do produto
        if ($product->is_type('variable') && is_product()) {
            return $price;
        }

        // Define o template
        $template_name = 'product-discount-pix.php';
        // Verifica se o template existe no tema
        $template_path = locate_template('pagbank-connect/' . $template_name);

        // Se o template não existir no tema, tenta carregar o template do plugin
        if (!$template_path) {
            $template_path = plugin_dir_path(__FILE__) . '../../templates/product/' . $template_name;
            if (!file_exists($template_path)) {
                return $price;
            }
        }

        ob_start();
        load_template($template_path, false, [
            'discount' => $discount,
            'product' => $product,
        ]);

        $pix_discount_html = ob_get_clean();

        return $pix_discount_html . $price;
    }
}
