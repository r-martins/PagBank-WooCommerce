<?php
/**
 * Class CanceledSubscription file.
 *
 */
use RM_PagBank\Connect;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CanceledSubscription', false ) ) :

    /**
     * Canceled Subscription E-mail
     *  An email sent to the customer when a subscription is canceled.
     *
     * @author    Ricardo Martins
     * @copyright 2023 Magenteiro
     */
    class CanceledSubscription extends WC_Email {

		/**
		 * Constructor.
		 */
		public function __construct() {
			$this->id             = 'pagbank_canceled_sub';
			$this->title          = __( 'PagBank - Assinatura Cancelada', 'pagbank-connect' );
			$this->description    = __( 'Email enviado quando uma assinatura é cancelada.', 'pagbank-connect' );
            $this->template_base  = WC_PAGSEGURO_CONNECT_BASE_DIR . '/src/templates/';
			$this->template_html  = 'emails/canceled-subscription.php';
			$this->template_plain = 'emails/plain/canceled-subscription.php';
            $this->customer_email = true;
			$this->placeholders   = array(
				'{next_bill_at}'              => '',
				'{subscription_number}'            => '',
				'{order_billing_full_name}' => '',
			);

			// Triggers for this email.
			add_action( 'pagbank_recurring_subscription_canceled_by_failure_notification', array( $this, 'trigger' ), 10, 2 );
			add_action( 'pagbank_recurring_subscription_canceled_by_customer_notification', array( $this, 'trigger' ), 10, 2 );

			// Call parent constructor.
			parent::__construct();
            
		}

		/**
		 * Get email subject.
		 *
		 * @since  3.1.0
		 * @return string
		 */
		public function get_default_subject() {
			return $this->format_string(__( '[{site_title}]: Sua assinatura #{subscription_number} foi cancelada.', 'pagbank-connect' ));
		}

		/**
		 * Get email heading.
		 *
		 * @since  3.1.0
		 * @return string
		 */
		public function get_default_heading() {
			return __( 'Assinatura Cancelada: #{subscription_number}', 'pagbank-connect' );
		}

		/**
		 * Trigger the sending of this email.
		 *
		 * @param stdClass       $order_id The order ID.
		 * @param WC_Order|false $order Order object.
		 */
		public function trigger( $subscription, $order = false ) {
			$this->setup_locale();

			if ( $subscription && ! is_a( $order, 'WC_Order' ) ) {
				$order = wc_get_order( $subscription->initial_order_id );
			}

			if ( is_a( $order, 'WC_Order' ) ) {
				$this->object                                    = $order;
				$this->placeholders['{order_billing_full_name}'] = $this->object->get_formatted_billing_full_name();
                // Other settings.
                $this->recipient = $this->get_option( 'recipient', $this->object->get_billing_email() );
			}
            
            $this->placeholders['{subscription_id}'] = $subscription->id;
            $this->placeholders['{next_bill_at}'] = gmdate('d/m/Y', strtotime($subscription->next_bill_at));
            $this->placeholders['{canceled_at}'] = gmdate('d/m/Y', strtotime($subscription->canceled_at));
            $this->subscription = $subscription;

			if ( $this->is_enabled() && $this->get_recipient() ) {
				$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
			}

			$this->restore_locale();
		}

		/**
		 * Get content html.
		 *
		 * @return string
		 */
		public function get_content_html() {
			return wc_get_template_html(
				$this->template_html,
				array(
					'order'              => $this->object,
					'email_heading'      => $this->get_heading(),
					'additional_content' => $this->get_additional_content(),
					'sent_to_admin'      => true,
					'plain_text'         => false,
					'email'              => $this,
					'subscription'       => $this->subscription,
				),
                $this->template_base,
                $this->template_base
			);
		}

		/**
		 * Get content plain.
		 *
		 * @return string
		 */
		public function get_content_plain() {
			return wc_get_template_html(
				$this->template_plain,
				array(
					'order'              => $this->object,
					'email_heading'      => $this->get_heading(),
					'additional_content' => $this->get_additional_content(),
					'sent_to_admin'      => true,
					'plain_text'         => true,
					'email'              => $this,
                    'subscription'       => $this->subscription,
				),
                $this->template_base
			);
		}

		/**
		 * Default content to show below main email content.
		 *
		 * @since 3.7.0
		 * @return string
		 */
		public function get_default_additional_content() {
			return __( 'Obrigado pela preferência.', 'pagbank-connect' );
		}

		/**
		 * Initialise settings form fields.
		 */
		public function init_form_fields() {
			/* translators: %s: list of placeholders */
			$placeholder_text  = sprintf( __( 'Available placeholders: %s', 'woocommerce' ), '<code>' . esc_html( implode( '</code>, <code>', array_keys( $this->placeholders ) ) ) . '</code>' );
			$this->form_fields = array(
				'enabled'            => array(
					'title'   => __( 'Enable/Disable', 'woocommerce' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable this email notification', 'woocommerce' ),
					'default' => 'yes',
				),
				'recipient'          => array(
					'title'       => __( 'Recipient(s)', 'woocommerce' ),
					'type'        => 'text',
					/* translators: %s: admin email */
					'description' => sprintf( __( 'Enter recipients (comma separated) for this email. Defaults to %s.', 'woocommerce' ), '<code>' . esc_attr( get_option( 'admin_email' ) ) . '</code>' ),
					'placeholder' => '',
					'default'     => '',
					'desc_tip'    => true,
				),
				'subject'            => array(
					'title'       => __( 'Subject', 'woocommerce' ),
					'type'        => 'text',
					'desc_tip'    => true,
					'description' => $placeholder_text,
					'placeholder' => $this->get_default_subject(),
					'default'     => '',
				),
				'heading'            => array(
					'title'       => __( 'Email heading', 'woocommerce' ),
					'type'        => 'text',
					'desc_tip'    => true,
					'description' => $placeholder_text,
					'placeholder' => $this->get_default_heading(),
					'default'     => '',
				),
				'additional_content' => array(
					'title'       => __( 'Additional content', 'woocommerce' ),
					'description' => __( 'Text to appear below the main email content.', 'woocommerce' ) . ' ' . $placeholder_text,
					'css'         => 'width:400px; height: 75px;',
					'placeholder' => __( 'N/A', 'woocommerce' ),
					'type'        => 'textarea',
					'default'     => $this->get_default_additional_content(),
					'desc_tip'    => true,
				),
				'email_type'         => array(
					'title'       => __( 'Email type', 'woocommerce' ),
					'type'        => 'select',
					'description' => __( 'Choose which format of email to send.', 'woocommerce' ),
					'default'     => 'html',
					'class'       => 'email_type wc-enhanced-select',
					'options'     => $this->get_email_type_options(),
					'desc_tip'    => true,
				),
			);
		}
        
        public function initPlaceholders()
        {
            if(isset($this->subscription)){
                $this->placeholders['{subscription_number}'] = $this->subscription->id;
                $this->placeholders['{next_bill_at}'] = $this->subscription->next_bill_at;
            }
        }
	}

endif;

return new CanceledSubscription();
