<?php
/*
Plugin Name: Seasonal Pricing
Description: Modifies prices to specific amounts outside the months of July and August. (requires ACF)
Version: 1.1
Author: Alexios-Theocharis Koilias
*/
global $number_of_days;
$number_of_days = exist_cookie_value();

global $cost_div_days;
$cost_div_days= 0;


function remove_listing_prices_css() {
	echo '<style>.blockUI.blockOverlay { display: none !important; }</style>';
    echo '<style>.stm_rent_prices .stm_rent_price .total .amount { display: none; }</style>';
    echo '<style>.stm-template-car_rental .stm_single_class_car .stm_rent_prices .stm_rent_price .total { display: none; }</style>';
    echo '<style>.stm-template-car_rental .stm_rent_table table td:nth-child(3) { font-size: 0; }</style>';

}

add_action('wp_head', 'remove_listing_prices_css');



function prefix_add_discount_line($cart) {
    global $number_of_days;
    global $cost_div_days;
    $discount=0;
    $items_cost= 0;
    $product_id;
    $returned_values=[];
    $cart_items = $cart->get_cart();
    if (!(is_july_or_august()) && $number_of_days > 0) {
        foreach ($cart_items as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];
            if ($product->get_type()!= "variation") {
                continue; 
            }
            $product_price = $product->get_price();
            $items_cost+= $product_price;
            if (empty($product_id)){
                $product_id= $product->get_parent_id();
            }
        }
            
        $returned_values = select_show_product_values($product_id);
        //$other_costs= $cart->subtotal- $items_cost;
        $cost_div_days = $items_cost / $number_of_days;
        if ($number_of_days<=2)
        {
            $discount = $cost_div_days-$returned_values[0];
        }
        elseif ($number_of_days<=5)
        {
            $discount = $cost_div_days-$returned_values[1];
        }
        else {
            $discount = $cost_div_days-$returned_values[2];
        }
        $cart->add_fee(__('Discount', 'Low-Season'), -$discount*$number_of_days);

    }

}

add_action('woocommerce_cart_calculate_fees', 'prefix_add_discount_line');

function is_july_or_august() {
    if (isset($_COOKIE['stm_calc_pickup_date_1'])) {
        $selectedDate = $_COOKIE['stm_calc_pickup_date_1'];
        $dateParts = explode(' ', $selectedDate);
        $month = $dateParts[0];
        return ($month === '07' || $month === '08');
    }
    return false;
}

function exist_cookie_value(){
    if (isset($_COOKIE['stm_calc_pickup_date_1']) && isset($_COOKIE['stm_calc_return_date_1'])) {
        $selectedDate = $_COOKIE['stm_calc_pickup_date_1'];
        $selectedDate=str_replace(',', '', $selectedDate);
        $dateParts = explode(' ', $selectedDate);
        $month= $dateParts[0];
        $year= $dateParts[2];
        $day= $dateParts[1];
        $time= $dateParts[3];
        $date1=$year . '-' . $month.'-' . $day.' '.$time;
        $date1=strtotime($date1);
        $selectedRDate = $_COOKIE['stm_calc_return_date_1'];
        $selectedRDate = str_replace(',', '', $selectedRDate);
        $dateRParts = explode(' ', $selectedRDate);
        $monthR= $dateRParts[0];
        $yearR= $dateRParts[2];
        $dayR= $dateRParts[1];
		$timeR= $dateRParts[3];
		$date2=$yearR . '-' . $monthR.'-' . $dayR.' '.$timeR;
        $date2=strtotime($date2);
        //return ($date2-$date1) / (60 * 60 * 24) +1;
        return ceil((($date2-$date1)/(60*60*24)));
    }
    else return 0;
}


function unitprice_javascript($array) {
    global $number_of_days;

    if ($number_of_days>0){
        ?>
    <script type="text/javascript">
        var numberOfDays = <?php echo $number_of_days; ?>;
        var costDivDays = 0;
        var costNew = <?php echo json_encode($array) ?>;
        
        console.log("NUMBER TRANSFER: " + numberOfDays + " COST DIV DAYS: " + costDivDays);
        document.addEventListener('DOMContentLoaded', function() {
            var priceElement = document.querySelector('bdi');
            if (priceElement) {
                var formattedCost; 
                if (numberOfDays <= 2) {
                    costDivDays = parseFloat(costNew[0]); 
                } else if (numberOfDays <= 5) {
                    costDivDays = parseFloat(costNew[1]);
                } else {
                    costDivDays = parseFloat(costNew[2]);
                }
                formattedCost = costDivDays.toLocaleString('en-UK', {
                    style: 'currency',
                    currency: 'EUR',
                });
                priceElement.textContent = formattedCost + ' Promo';
            }
            var firstCell = document.querySelector('table tbody tr:first-child td:first-child');
            firstCell.textContent = numberOfDays + ' Days';
   
            if (firstCell) {
                console.log('Content of the first cell:', firstCell.innerText);
            }
        });
    </script>
        <?php
    }
}


function select_show_product_values($value){
    global $wpdb;

    $query3 = "SELECT meta_key FROM {$wpdb->prefix}postmeta WHERE meta_value = '{$value}'";
    $results3 = $wpdb->get_results($query3);

    if (!empty($results3))
    {
        $key= $results3[0]->meta_key;
        $key= explode('_', $key);
        $query = "SELECT meta_value FROM {$wpdb->prefix}postmeta WHERE meta_key IN (
        'seasonal_rates_".$key[2]."_price1_2',
        'seasonal_rates_".$key[2]."_price3_5',
        'seasonal_rates_".$key[2]."_price6_plus'
    ) ORDER BY meta_key ASC";
        $results = $wpdb->get_results($query);
        // echo 'Timi <=2 meres: '.$results[0]->meta_value. '<br>';
        // echo 'Timi <=5 meres: '.$results[1]->meta_value.'<br>';
        // echo 'Timi 6+ meres: '.$results[2]->meta_value.'<br>';
    }
    $resultsfinal[]=$results[0]->meta_value;
    $resultsfinal[]=$results[1]->meta_value;
    $resultsfinal[]=$results[2]->meta_value;
    unitprice_javascript ($resultsfinal);
    return $resultsfinal;
}
