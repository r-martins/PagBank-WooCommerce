<?php
namespace RM_PagBank\Connect\Recurring\Admin\Subscriptions;

use RM_PagBank\Helpers\Recurring;
use WP_List_Table;

if ( ! class_exists ( 'SubscriptionDetails' ) ) {
    require_once(dirname(__FILE__) . '/SubscriptionDetails.php');
}

/**
 * Page that shows the subscription details in the admin area
 *
 * @author    Ricardo Martins <ricardo@magenteiro.com>
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank\Connect\Recurring\Admin\Subscriptions
 */
class SubscriptionEdit extends SubscriptionDetails
{
    public function display() {
        $singular = $this->_args['singular'];

        $this->display_tablenav( 'top' );

        $this->screen->render_screen_reader_content( 'heading_list' );
        $action = WC()->api_request_url('rm-pagbank-subscription-edit'). '?action=edit&id=' . $this->subscription->id;
        ?>
        <form method="post" action="<?php echo $action ?>">
            <table class="wp-list-table <?php echo esc_attr(implode(' ', $this->get_table_classes())); ?>">
                <?php $this->print_table_description(); ?>
                <thead>
                <tr>
                    <?php $this->print_column_headers(); ?>
                </tr>
                </thead>

                <tbody id="the-list"
                    <?php if ($singular) {?>
                        data-wp-lists="list:<?php echo esc_attr($singular) ?>"
                        <?php
                    }
                    ?>>
                <?php $this->display_rows_or_placeholder(); ?>
                </tbody>
            </table>
            <button type="submit" class="button">
                <?php _e('Salvar', 'rm-pagbank') ?>
            </button>
        </form>
        <?php
        $this->display_tablenav( 'bottom' );
    }

    private function get_edit_colluns() {
        return [
                'recurring_amount' => 'Valor Recorrente',
            ];
    }

    public function column_value($item)
    {
        if ($item['name'] === 'Pedido Inicial') {
            $order = wc_get_order($item['value']);
            return '<a href="' . $order->get_edit_order_url() . '">' . $item['value'] . '</a>';
        }

        if (!array_key_exists($item['name'], array_flip($this->get_edit_colluns()))) {
            return $item['value'];
        }

        $name = array_search($item['name'], $this->get_edit_colluns());
        $value = number_format($item['value'], 2, ',', '.');
        return "<input type='text' name='{$name}' value='{$value}'>";
    }
}