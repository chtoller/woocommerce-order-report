
if (!function_exists('write_log')) {
    function write_log($log) {
        if (true === WP_DEBUG_LOG) {
            if (is_array($log) || is_object($log)) {
                error_log(print_r($log, true));
            } else {
                error_log($log);
            }
        }
    }
}

function get_report_line($id) {
	//var_dump($order);
	//echo 'ID: ' . $order->get_id() . ', Datum: ' . $order->get_date_created() . ', Email: ' . $order->get_billing_email() . '<br>';

	$order = wc_get_order( $id );
	$adr = $order->get_address('shipping');
	$d = $order->get_date_created();
	$ot = $order->get_total();
	$st = $order->get_total_shipping();
	$tax = $order->get_total_tax();

	$line = $order->get_id() . ';' . 
		$d->date_i18n("d.m.Y") . ';' .
		$order->get_billing_email() . ';' .
		'"' . $adr["first_name"] . '";' .
		'"' . $adr["last_name"] . '";' .
		'"' . $adr["company"] . '";' .
		'"' . $adr["address_1"] . '";' .
		'"' . $adr["address_2"] . '";' .
		'"' . $adr["city"] . '";' .
		'"' . $adr["state"] . '";' .
		'"' . $adr["postcode"] . '";' .
		'"' . $adr["country"] . '";' .
		'"' . $adr["phone"] . '";' .
		number_format($tax, 2, ',', '') . ';' .
		number_format($st, 2, ',', '') . ';' .
		number_format($ot, 2, ',', '');
	//echo $line . '<br>';
	//var_dump($order->get_address('shipping'));
	//$msg = 'ID: ' . $order->get_id() . ', Datum: ' . $order->get_date_created() . ', Email: ' . $order->get_billing_email() . ', Status: ' . $order->get_status() . ', Summe: ' . $order->get_total() . ', Shipping Address: ' . $order->get_address('shipping') . '<br>';
	//write_log($msg);
	return $line;
}

function order_report_function() {
	
	$path = '/home/wp/disk/wordpress/wp-content/order.csv'; 
	//$path = '/var/www/vhosts/skinbeauty.at/httpdocs/wp-content/order.csv'; 
	$file = fopen( $path, "w" ) or die("##### Error opening order.csv for appending"); 
	$write = fputs( $file, pack("CCC",0xef,0xbb,0xbf)."ID;Datum;EMail;first_name;last_name;company;address_1;address_2;city;state;postcode;country;phone;tax;shipping;total\n");
	
	$from = "2023-01-01";
	$to = "2023-02-06";
	write_log("##### now querying for orders from " . $from . " to " . $to);
	
	$hasPosts = true;
	$paged = 0;
	

	do {
		
		$query = new WC_Order_Query( array(
			'limit' => 100,
			'type' => 'shop_order',
			'status' => array('wc-completed'),
			'paged' => $paged,
			'page' => $paged,
			'billing_country' => 'AT',
			'return' => 'ids',
		));

		$orders = $query->get_orders();
		
		if(count($orders)) {
		
			write_log("##### Now exporting the " . $paged . " page with " . count($orders) . " orders to wp-content/orders.csv");
			write_log("##### Memory used: " . (function_exists('memory_get_peak_usage') ? round(memory_get_peak_usage(TRUE) / 1024 / 1024, 2) : 0) . "MB");

			//echo "ID;Datum;EMail;first_name;last_name;company;address_1;address_2;city;state;postcode;country;phone;tax;shipping;total<br>";
			
			foreach ( $orders as $id ) {
				$write = fputs( $file, mb_convert_encoding( get_report_line($id), 'UTF-8') . "\n");
			} 
			$paged++;
		} else {
			write_log("##### page " . $paged . " is empty, no more orders");
			$hasPosts = false;
		}
		unset($query);
		unset($orders);
		wp_cache_flush();
	} while ( $hasPosts && ($paged < 600));
	
	fclose($file);
	
	write_log("##### Done #####");
}

add_action('order_report_cronjob', 'order_report_function');

function schedule_order_report() {
	echo "<h1>Scheduling one-time order report creation</h1>";
	write_log("Scheduling create_order_report");
	$res = wp_schedule_single_event( time() + 30, 'order_report_cronjob');
}
add_shortcode( 'create_order_report', 'schedule_order_report');

