# woocommerce-order-report
Query WooCommerce for a large number of orders are retrieve some data

The challenge was to create a report on past orders for a rather large webshop with 15k orders.
First I tried to use a shortcode defined in functions.php, but that causes two problems:
- The code runs for a very long time and causes timeouts
- The code eats up a huge amount of memory and eventually causes an out of memory exception

Increasing the timeouts or the memory limits would usually solve those issues, but even with 600 seconds max-execution-time and 1 GB of PHP memory it was still having issues. So I had to look for a more stable solution.

Conceptually, it turns out you have to do the following:
1. run the time-consuming task as a scheduled event using the WordPress scheduler
2. use a "paged" query to to spit it in less time consuming pieces
3. flush the WordPress object cache regulariy while looping through the query results

The flushing is necessary because the memory consumption is not caused by a memory leak or an issue with PHP garbage collection. It's caused by the WP object cache, caching the oWC_Order_Query or WP_Query objects or the orders / posts returned by them. Took me some time to find that...

This results in the following structure:

In functions.php:

// ### actual query function
function order_report_function() {
  // open output file for writing
  do {
    $query = new WC_Order_Query( array(
			'limit' => 100,
			'paged' => $paged,
			'page' => $paged,
			'return' => 'ids',
      // plus other criteria
		));
		$orders = $query->get_orders();
    if(count($orders)) {
      foreach ( $orders as $id ) {
        // do something with each order (like write a line to the output file)
      } 
      $paged++;
    } else {
      write_log("##### page " . $paged . " is empty, no more orders");
      $hasPosts = false;
    }
		wp_cache_flush();
  } while( $hasPosts)
}

// ### define an action, which can be scheduled
add_action('order_report_cronjob', 'order_report_function');

// ### function to schedule the report for execution after 30 seconds
function schedule_order_report() {
	echo "<h1>Scheduling one-time order report creation</h1>";
	write_log("Scheduling create_order_report");
	$res = wp_schedule_single_event( time() + 30, 'order_report_cronjob');
}

// ### shortcode that can be used on a page to trigger the scheduling
add_shortcode( 'create_order_report', 'schedule_order_report');

To schedule the report you can either add the shortcode [create_order_report] to a page, so that the event will be schedule when the page is loaded.
Or you use a plugin like WP Control to schedule the "order_report_cronjob" action for immediate execution

When there's time, I will write a plugin to make that easier.
