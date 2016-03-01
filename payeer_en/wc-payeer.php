<?php 
/*
  Plugin Name: Payeer
  Plugin URI: 
  Description: 
  Version: 1.0
  Author: Payeer
  Author URI: 
 */

if (!defined('ABSPATH'))
{
	exit;
}

add_action('plugins_loaded', 'woocommerce_payeer', 0);

function woocommerce_payeer()
{
	if (!class_exists('WC_Payment_Gateway'))
	{
		return;
	}
	
	if (class_exists('WC_PAYEER'))
	{
		return;
	}
		
class WC_PAYEER extends WC_Payment_Gateway
{
	public function __construct()
	{
		$plugin_dir = plugin_dir_url(__FILE__);

		global $woocommerce;

		$this->id = 'payeer';
		$this->icon = apply_filters('woocommerce_payeer_icon', $plugin_dir . 'payeer.png');
		$this->has_fields = false;

		$this->init_form_fields();
		$this->init_settings();

		$this->title = $this->get_option('title');
		$this->payeer_url = $this->get_option('payeer_url');
		$this->payeer_merchant = $this->get_option('payeer_merchant');
		$this->payeer_secret_key = $this->get_option('payeer_secret_key');
		$this->email_error = $this->get_option('email_error');
		$this->ip_filter = $this->get_option('ip_filter');
		$this->order_desc = $this->get_option('order_desc');
		$this->log_file = $this->get_option('log_file');
		$this->description = $this->get_option('description');

		add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		add_action('woocommerce_api_wc_' . $this->id, array($this, 'check_ipn_response'));

		if (!$this->is_valid_for_use())
		{
			$this->enabled = false;
		}
	}

	function is_valid_for_use()
	{
		return true;
	}

	public function admin_options() 
	{
		?><h3><?php _e('Payeer', 'woocommerce'); ?></h3>
		<p><?php _e('Configure the receive electronic payments via Payeer.', 'woocommerce'); ?></p>

		<?php if ( $this->is_valid_for_use() ) : ?>
			<table class="form-table">
			<?php    	
					$this->generate_settings_html();
			?>
			</table>
				
		<?php else : ?>
			<div class="inline error"><p><strong><?php _e('The gateway is disabled', 'woocommerce'); ?></strong>: <?php _e('Payeer does not support currency of Your store.', 'woocommerce' ); ?></p></div>
			<?php
		endif;
    }

	function init_form_fields()
	{
		$this->form_fields = array(
			'enabled' => array(
				'title' => __('Enable/Disable', 'woocommerce'),
				'type' => 'checkbox',
				'label' => __('Enabled', 'woocommerce'),
				'default' => 'yes'
			),
			'title' => array(
				'title' => __('Name', 'woocommerce'),
				'type' => 'text', 
				'description' => __( 'This is the name that the user sees when selecting the payment method.', 'woocommerce' ), 
				'default' => __('Payeer', 'woocommerce')
			),
			'payeer_url' => array(
				'title' => __('Merchant URL', 'woocommerce'),
				'type' => 'text',
				'description' => __('url to payment system Payeer', 'woocommerce'),
				'default' => '//payeer.com/merchant/'
			),
			'payeer_merchant' => array(
				'title' => __('Merchant ID', 'woocommerce'),
				'type' => 'text',
				'description' => __('The identity store registered in the system "PAYEER".<br/>Get it in <a href="http://www.payeer.com/account/">Payeer account</a>: the "Account -> My store -> Edit".', 'woocommerce'),
				'default' => ''
			),
			'payeer_secret_key' => array(
				'title' => __('Secret key', 'woocommerce'),
				'type' => 'password',
				'description' => __('The secret key notification that payment has been made,<br/>which is used to check the integrity of the information received<br/>and unambiguous identification of the sender.<br/>Must match the secret key specified in <a href="http://www.payeer.com/account/">Payeer account</a>: the "Account -> My store -> Edit".', 'woocommerce'),
				'default' => ''
			),
			'order_desc' => array(
				'title' => __('Payment description', 'woocommerce'),
				'type' => 'text',
				'description' => __('Explanation of order payment', 'woocommerce'),
				'default' => ''
			),
			'log_file' => array(
				'title' => __('Path to file to the payment journal through Payeer (for example, /payeer_orders.log)', 'woocommerce'),
				'type' => 'text',
				'description' => __('If the path is not specified, the log is not recorded', 'woocommerce'),
				'default' => ''
			),
			'ip_filter' => array(
				'title' => __('IP Filter', 'woocommerce'),
				'type' => 'text',
				'description' => __('The list of trusted ip addresses, you can specify a mask', 'woocommerce'),
				'default' => ''
			),
			'email_error' => array(
				'title' => __('Email for error', 'woocommerce'),
				'type' => 'text',
				'description' => __('Email to send payment errors', 'woocommerce'),
				'default' => ''
			),
			'description' => array(
				'title' => __( 'Description', 'woocommerce' ),
				'type' => 'textarea',
				'description' => __( 'The description of the method of payment which the customer will see on your website.', 'woocommerce' ),
				'default' => 'Payment via Payeer'
			)
		);
	}

	function payment_fields()
	{
		if ($this->description)
		{
			echo wpautop(wptexturize($this->description));
		}
	}

	public function generate_form($order_id)
	{
		global $woocommerce;

		$order = new WC_Order($order_id);

		$m_url = $this->payeer_url;

		$out_summ = number_format($order->order_total, 2, '.', '');
		
		$m_shop		= $this->payeer_merchant;
		$m_orderid	= $order_id;
		$m_amount	= $out_summ;
		$m_curr		= get_option('woocommerce_currency');
		$m_desc		= base64_encode($this->order_desc);
		$m_key		= $this->payeer_secret_key;
			
		$arHash = array
		(
			$m_shop,
			$m_orderid,
			$m_amount,
			$m_curr,
			$m_desc,
			$m_key
		);
		$sign = strtoupper(hash('sha256', implode(":", $arHash)));
		return
			'<form method="GET" action="' . $m_url . '">
				<input type="hidden" name="m_shop" value="' . $m_shop . '">
				<input type="hidden" name="m_orderid" value="' . $m_orderid . '">
				<input type="hidden" name="m_amount" value="' . $m_amount . '">
				<input type="hidden" name="m_curr" value="' . $m_curr . '">
				<input type="hidden" name="m_desc" value="' . $m_desc . '">
				<input type="hidden" name="m_sign" value="' . $sign . '">
				<input type="submit" name="m_process" value="Оплатить" />
			</form>';
	}
	
	function process_payment($order_id)
	{
		$order = new WC_Order($order_id);

		return array(
			'result' => 'success',
			'redirect'	=> add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay'))))
		);
	}
	
	function receipt_page($order)
	{
		echo '<p>'.__('Thank you for Your order, please click the button below to pay.', 'woocommerce').'</p>';
		echo $this->generate_form($order);
	}
	
	function check_ipn_response()
	{
		global $woocommerce;
		if (isset($_GET['payeer']) && $_GET['payeer'] == 'result')
		{
			if (isset($_POST["m_operation_id"]) && isset($_POST["m_sign"]))
			{
				$m_key = $this->payeer_secret_key;
				$arHash = array(
					$_POST['m_operation_id'],
					$_POST['m_operation_ps'],
					$_POST['m_operation_date'],
					$_POST['m_operation_pay_date'],
					$_POST['m_shop'],
					$_POST['m_orderid'],
					$_POST['m_amount'],
					$_POST['m_curr'],
					$_POST['m_desc'],
					$_POST['m_status'],
					$m_key);
					
				$sign_hash = strtoupper(hash('sha256', implode(":", $arHash)));
				
				if ($_POST["m_sign"] != $sign_hash)
				{
					if (!empty($this->email_error))
					{
						$to = $this->email_error;
						$subject = "Error payment";
						$message = "Failed to make the payment through Payeer for the following reasons:\n\n";
						$message .= " - Do not match the digital signature\n";
						$message .= "\n".$log_text;
						$headers = "From: no-reply@" . $_SERVER['HTTP_SERVER'] . "\r\nContent-type: text/plain; charset=utf-8 \r\n";
						mail($to, $subject, $message, $headers);
					}
					
					exit ($_POST['m_orderid'] . '|error');
				}
				
				// проверка принадлежности ip списку доверенных ip
				$list_ip_str = str_replace(' ', '', $this->ip_filter);
				
				if (!empty($list_ip_str)) 
				{
					$list_ip = explode(',', $list_ip_str);
					$this_ip = $_SERVER['REMOTE_ADDR'];
					$this_ip_field = explode('.', $this_ip);
					$list_ip_field = array();
					$i = 0;
					$valid_ip = FALSE;
					foreach ($list_ip as $ip)
					{
						$ip_field[$i] = explode('.', $ip);
						if ((($this_ip_field[0] ==  $ip_field[$i][0]) or ($ip_field[$i][0] == '*')) and
							(($this_ip_field[1] ==  $ip_field[$i][1]) or ($ip_field[$i][1] == '*')) and
							(($this_ip_field[2] ==  $ip_field[$i][2]) or ($ip_field[$i][2] == '*')) and
							(($this_ip_field[3] ==  $ip_field[$i][3]) or ($ip_field[$i][3] == '*')))
							{
								$valid_ip = TRUE;
								break;
							}
						$i++;
					}
				}
				else
				{
					$valid_ip = TRUE;
				}
				
				$log_text = 
					"--------------------------------------------------------\n".
					"operation id		".$_POST["m_operation_id"]."\n".
					"operation ps		".$_POST["m_operation_ps"]."\n".
					"operation date		".$_POST["m_operation_date"]."\n".
					"operation pay date	".$_POST["m_operation_pay_date"]."\n".
					"shop				".$_POST["m_shop"]."\n".
					"order id			".$_POST["m_orderid"]."\n".
					"amount				".$_POST["m_amount"]."\n".
					"currency			".$_POST["m_curr"]."\n".
					"description		".base64_decode($_POST["m_desc"])."\n".
					"status				".$_POST["m_status"]."\n".
					"sign				".$_POST["m_sign"]."\n\n";

				if (!empty($this->log_file))
				{	
					file_put_contents($_SERVER['DOCUMENT_ROOT'] . $this->log_file, $log_text, FILE_APPEND);
				}
				
				$order = new WC_Order($_POST['m_orderid']);
				
				if ($_POST['m_status'] == "success" && $valid_ip)
				{
					$order->update_status('processing', __('The payment is successfully paid', 'woocommerce'));
					WC()->cart->empty_cart();
					exit ($_POST['m_orderid'] . '|success');
				}
				else
				{
					$order->update_status('failed', __('The payment is not paid', 'woocommerce'));
					
					if (!empty($this->email_error))
					{
						$to = $this->email_error;
						$subject = "Error payment";
						$message = "Failed to make the payment through Payeer for the following reasons:\n\n";
						if ($_POST['m_status'] != "success")
						{
							$message .= " - The payment status is not success\n";
						}
						if (!$valid_ip)
						{
							$message .= " - ip address of the server is not trusted\n";
							$message .= "   trusted ip: " . $this->ip_filter . "\n";
							$message .= "   the ip of the current server: " . $_SERVER['REMOTE_ADDR'] . "\n";
						}
						$message .= "\n".$log_text;
						$headers = "From: no-reply@" . $_SERVER['HTTP_SERVER'] . "\r\nContent-type: text/plain; charset=utf-8 \r\n";
						mail($to, $subject, $message, $headers);
					}
					
					exit ($_POST['m_orderid'] . '|error');
				}
			}
			else
			{
				wp_die('IPN Request Failure');
			}
		}
		else if (isset($_GET['payeer']) && $_GET['payeer'] == 'calltrue')
		{
			WC()->cart->empty_cart();
			$order = new WC_Order($_GET['m_orderid']);
			wp_redirect($this->get_return_url($order));
		}
		else if (isset($_GET['payeer']) && $_GET['payeer'] == 'callfalse')
		{
			WC()->cart->empty_cart();
			$order = new WC_Order($_GET['m_orderid']);
			wp_redirect($this->get_return_url($order));
		}
	}
}

function add_payeer_gateway($methods)
{
	$methods[] = 'WC_PAYEER';
	return $methods;
}

add_filter('woocommerce_payment_gateways', 'add_payeer_gateway');
}
?>