<?php
class ShareASale_WC_Tracker_Pixel {

	/**
	* @var WC_Order $order WooCommere order object https://docs.woothemes.com/wc-apidocs/class-WC_Order.html
	* @var float $version Plugin version
	*/

	private $order, $version;

	public function __construct( $version ) {
		$this->version = $version;
	}

	public function woocommerce_thankyou( $order_id ) {
		$options     = get_option( 'shareasale_wc_tracker_options' );
		$merchant_id = $options['merchant-id'];
		$store_id    = @$options['store-id'];
		$xtype       = @$options['xtype'];

		if ( ! $order_id || ! $merchant_id ) {
			return;
		}

		if ( $store_id ) {
			$store_id = '&storeID=' . $store_id;
		}

		$this->order = new WC_Order( $order_id );

		switch ( $xtype ) {
			case 'customer_billing_country_code':
				$xtype = '&xtype=' . (string) $this->order->billing_country;
				break;

			case 'customer_billing_state_code':
				$xtype = '&xtype=' . (string) $this->order->billing_state;
				break;

			case 'customer_billing_city_code':
				$xtype = '&xtype=' . (string) $this->order->billing_city;
				break;

			case 'customer_shipping_country_code':
				$xtype = '&xtype=' . (string) $this->order->shipping_country;
				break;

			case 'customer_shipping_state_code':
				$xtype = '&xtype=' . (string) $this->order->shipping_state;
				break;

			case 'customer_shipping_city_code':
				$xtype = '&xtype=' . (string) $this->order->shipping_city;
				break;

			case 'customer_id':
				$xtype = '&xtype=' . (string) $this->order->get_user_id();
				break;

			case 'customer_device_type':
				$xtype = '&xtype=' . ( wp_is_mobile() ? 'mobile' : 'desktop' );
				break;

			case 'payment_type':
				$xtype = '&xtype=' . (string) $this->order->payment_method_title;
				break;

			case 'payment_shipping':
				$xtype = '&xtype=' . (string) $this->order->get_shipping_method();
				break;

			default:
				$xtype = '';
		}

		$product_data = $this->get_product_data();

		$params = array(
				'amount'       => $this->get_order_amount(),
				'tracking'     => $this->order->get_order_number(),
				'transtype'    => 'sale',
				'merchantID'   => $merchant_id,
				'skulist'      => $product_data->skulist,
				'quantitylist' => $product_data->quantitylist,
				'pricelist'    => $product_data->pricelist,
				'couponcode'   => $this->get_coupon_codes(),
				'currency'     => $this->get_currency(),
				'newcustomer'  => $this->get_customer_status(),
				'v'            => $this->version,
			);

		$query_string = '?' . http_build_query( $params );

		$pixel = '<img src = "https://shareasale.com/sale.cfm' . $query_string . $store_id . $xtype . '" width="1" height="1">';

		echo wp_kses( $pixel, array(
									'img' => array(
										'src'    => true,
										'width'  => true,
										'height' => true,
									),
								)
		);
	}

	private function get_order_amount() {

		$grand_total    = $this->order->get_total();
		$total_shipping = $this->order->get_total_shipping();
		$total_taxes    = $this->order->get_total_tax();
		$subtotal       = $grand_total - ( $total_shipping + $total_taxes );

		if ( $subtotal < 0 ) {
			$subtotal = 0;
		}

		return $subtotal;
	}

	private function get_product_data() {

		$product_data = new stdClass();

		$items = $this->order->get_items();
		$last_index = array_search( end( $items ), $items, true );

		foreach ( $items as $index => $item ) {

			$delimiter = $index === $last_index ? '' : ',';

			$id      = $item['product_id'];
			$product = new WC_Product( $id );
			$sku     = $product->get_sku();

			isset( $product_data->skulist ) ? $product_data->skulist .= $sku . $delimiter : $product_data->skulist = $sku . $delimiter;

			isset( $product_data->pricelist ) ? $product_data->pricelist .= round( ( $item['line_total'] / $item['qty'] ), 2 ) . $delimiter : $product_data->pricelist = round( ( $item['line_total'] / $item['qty'] ), 2 ) . $delimiter;

			isset( $product_data->quantitylist ) ? $product_data->quantitylist .= $item['qty'] . $delimiter : $product_data->quantitylist = $item['qty'] . $delimiter;
		}

		return $product_data;

	}

	private function get_customer_status() {
		$newcustomer = '';
		if ( method_exists( $this->order, 'get_user_id' ) ) {

			$customer_user_id = $this->order->get_user_id();
			if ( 0 !== $customer_user_id ) {
				$user_orders = get_posts(
					array(
						'post_type'      => 'shop_order',
						'meta_key'       => '_customer_user',
						'meta_value'     => $customer_user_id,
						'posts_per_page' => -1,
						'post_status'    => array_keys( wc_get_order_statuses() ),
					)
				);
				$order_count = count( $user_orders );

				$newcustomer = ($order_count > 1 ? 0 : 1);
			}
		}

		return $newcustomer;

	}

	private function get_coupon_codes() {

		$couponcode = implode( ', ', $this->order->get_used_coupons() );

		return $couponcode;
	}

	private function get_currency() {

		$currency = $this->order->get_order_currency();

		return $currency;
	}
}