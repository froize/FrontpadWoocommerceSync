<?php

/*
 * Writed according with Wordpress Coding Standarts:
 * https://make.wordpress.org/core/handbook/best-practices/coding-standards/php/
 *
 *
 * Код написан в соотвествии со стандартами кодирования PHP для Wordpress:
 * https://codex.wordpress.org/Стандарты_кодирования_PHP
 */



/*
 * Send order data to Frontpad
 * Отправка данных заказа во Frontpad
 */

add_action( 'woocommerce_thankyou', 'send_order_to_frontpad' );

function send_order_to_frontpad( $order_id ) {
    $order         = wc_get_order( $order_id );
    $api_password  = '2Z4t7YbhRDQFDARsGYb79d53ZiNKE9FrZyKSeQ4YdEnG562BR3yQ6nERAer4dYf4eSGitA7A4h7YdThYHEzES4he7A7ahd4rZADeZzFQGBzNfFyS9ShSHt2FSTF32zdHYGQF5fhAGFaRayzabGSK3FdFnKya7BHS7kk3tyn43nG3H54KAGhkbnTr3drYE3BBaQny8DkZRTNkNDZ997kah7r89GTDyh4zZdTYF6GiQfznKfnKdBNnz5kyA6';
    $email         = $order->billing_email;
    $phone         = $order->billing_phone;
    $shipping_type = $order->get_shipping_method();
    if ( $order->get_payment_method_title() == 'Оплата при получении' ) {
        $shipping_type = '';
    } else {
        $shipping_type = 947;
    }
    $shipping_cost = $order->get_total_shipping();

    // get product details
    // получение данных товара

    $items      = $order->get_items();
    $item_name  = array();
    $item_qty   = array();
    $item_price = array();
    $item_sku   = array();

    // set the address fields
    // установка полей адреса

    $user_id        = $order->user_id;
    $address_fields = array(
        'country',
        'title',
        'given_name',
        'surname',
        'street',
        'house',
        'pod',
        'et',
        'apart',
    );

    $order_details = array();
    foreach ( $order->get_meta_data() as $item ) {
        $order_details[ $item->jsonSerialize()['key'] ] = $item->jsonSerialize()['value'];
    }
    $address = array();
    if ( is_array( $address_fields ) ) {
        foreach ( $address_fields as $field ) {
            $address[ 'billing_' . $field ]  = get_user_meta( $user_id, 'billing_' . $field, true );
            $address[ 'shipping_' . $field ] = get_user_meta( $user_id, 'shipping_' . $field, true );
            $address[ 'billing_' . $field ]  = $order_details[ '_billing_' . $field ];
            $address[ 'shipping_' . $field ] = $order_details[ '_shipping_' . $field ];
        }
    }
    $address['pre_order'] = get_post_meta( $order_id, 'pre_order', true );

    foreach ( $items as $item_id => $item ) {

        $item_name[]          = $item['name'];
        $item_qty[]           = $item['qty'];
        $item_price[]         = $item['line_total'];
        $item_id              = $item['product_id'];
        $product              = new WC_Product( $item['product_id'] );
        $product_variation_id = $item['variation_id'];
        $product              = $order->get_product_from_item( $item );
        // Get SKU
        // Получение артикула
        $item_sku[] = $product->get_sku();

    }


    // setup the data which has to be sent
    // сбор данных для отправки
    $data = array(
        'secret'   => $api_password,
        'street'   => $address['billing_street'],
        'home'     => $address['billing_house'],
        'pod'      => $address['billing_pod'],
        'et'       => $address['billing_et'],
        'apart'    => $address['billing_apart'],
        'phone'    => $phone,
        'mail'     => $email,
        'descr'    => $order->get_customer_note(),
        'name'     => $address['billing_given_name'] . ' ' . $address['billing_surname'],
        'pay'      => $shipping_type,
        'datetime' => date( 'Y-m-d G:i:s', $order->get_date_created()->getOffsetTimestamp() ),
    );
    //get data from shipping address if is not empty
    // если указан адрес доставки, берем данные оттуда, если нет, то из платежного адреса
    if ( $address['shipping_street'] != "" ) {
        $data['street'] = $address['shipping_street'];
    }
    if ( $address['shipping_house'] != "" ) {
        $data['home'] = $address['shipping_house'];
    }
    if ( $address['shipping_pod'] != "" ) {
        $data['pod'] = $address['shipping_pod'];
    }
    if ( $address['shipping_et'] != "" ) {
        $data['et'] = $address['shipping_et'];
    }
    if ( $address['shipping_apart'] != "" ) {
        $data['apart'] = $address['shipping_apart'];
    }
    if ( ( $address['shipping_given_name'] != "" ) && ( $address['shipping_surname'] != "" ) ) {
        $data['name'] = $address['shipping_given_name'] . ' ' . $address['shipping_surname'];
    }
    if ( $address['pre_order'] != '' ) {
        $data['datetime'] = date( 'Y-m-d G:i:s', strtotime( $address['pre_order'] ) );
    }
    foreach ( $order->get_items( 'shipping' ) as $item_id => $shipping_item_obj ) {
        $data['descr'] = $data['descr'] . ' Доставка: ' . $shipping_item_obj->get_method_title();
    }

    $query = '';
    // request preparation
    // подготовка запроса
    foreach ( $data as $key => $value ) {
        $query .= "&" . $key . "=" . $value;
    }

    // order contents
    // содержимое заказа
    foreach ( $item_sku as $key => $value ) {
        $query .= "&product[" . $key . "]=" . $value . "";
        $query .= "&product_kol[" . $key . "]=" . $item_qty[ $key ] . "";
    }

    // send API request via cURL
    // отправка запроса API через cURL
    $ch = curl_init();

    curl_setopt( $ch, CURLOPT_URL, "https://app.frontpad.ru/api/index.php?new_order" );
    curl_setopt( $ch, CURLOPT_FAILONERROR, 1 );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
    curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
    curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
    curl_setopt( $ch, CURLOPT_TIMEOUT, 30 );
    curl_setopt( $ch, CURLOPT_POST, 1 );
    curl_setopt( $ch, CURLOPT_HEADER, false );
    curl_setopt( $ch, CURLOPT_POSTFIELDS, $query );

    $response = curl_exec( $ch );

    curl_close( $ch );

    $response = json_decode( $response, true );

    if ( $response['result'] == 'success' ) {
        update_post_meta( $order_id, 'Frontpad Order ID', sanitize_text_field( $response['order_id'] ) );
        update_post_meta( $order_id, 'Frontpad Order Number', sanitize_text_field( $response['order_number'] ) );
        // sending SMS to restaurant administrator
        // отправка запроса администратору ресторана
        $message = 'Новый онлайн-заказ №' . $response['order_number'] . '. Проверьте FrontPad. ';
        $message = urlencode( $message );
        $curl    = curl_init();
        curl_setopt( $curl, CURLOPT_URL, "https://sms.ru/sms/send?api_id=A9E14A13-D771-2D94-0E03-827654F39624&to=79923050626&msg=$message&json=1" );
        curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
        $out = curl_exec( $curl ); // delivery data of SMS (данные до доставке SMS)
        curl_close( $curl );
    } else {
        switch ( $response['error'] ) {
            case 'cash_close' :
                echo 'Cмена закрыта';
                echo "<script>jQuery(document).ready(function ($) {
    var popup_id = 948;
    MasterPopups.open(popup_id);
    //Or using a jQuery selector
    $('.mpp-popup-' + popup_id).MasterPopups();
});
</script>";
                $order->update_status( 'failed' );
                break;
            case 'invalid_product_keys' :
                echo 'Неверный массив товаров';
                $order->update_status( 'failed' );
                break;
        }
    }

}
/*
 * Check order status button in order page
 * Проверка статуса заказа через кнопку на странице просмотра заказа
 */

add_action( 'woocommerce_view_order', 'check_order_status_button' );
function check_order_status_button( $order_id ) {
    $order = wc_get_order( $order_id );
    echo <<<EOT
    <form method="post" action="">
    <input type="submit" value="Проверить статус заказа">
    <input type="hidden" name="check_order_status_frontpad" value="yes">
    </form>
EOT;
    $orders = wc_get_orders( array(
        'status' => 'pending',
    ) );
    if ( $_POST['check_order_status_frontpad'] == 'yes' ) {
        $data  = array(
            'secret'   => '2Z4t7YbhRDQFDARsGYb79d53ZiNKE9FrZyKSeQ4YdEnG562BR3yQ6nERAer4dYf4eSGitA7A4h7YdThYHEzES4he7A7ahd4rZADeZzFQGBzNfFyS9ShSHt2FSTF32zdHYGQF5fhAGFaRayzabGSK3FdFnKya7BHS7kk3tyn43nG3H54KAGhkbnTr3drYE3BBaQny8DkZRTNkNDZ997kah7r89GTDyh4zZdTYF6GiQfznKfnKdBNnz5kyA6',
            'order_id' => get_post_meta( $order_id, 'Frontpad Order ID', true )
        );
        $query = '';
        // request preparation
        // подготовка запроса
        foreach ( $data as $key => $value ) {
            $query .= "&" . $key . "=" . $value;
        }
        // send API request via cURL
        // отправка запроса API через cURL
        $ch = curl_init();

        curl_setopt( $ch, CURLOPT_URL, "https://app.frontpad.ru/api/index.php?get_status" );
        curl_setopt( $ch, CURLOPT_FAILONERROR, 1 );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
        curl_setopt( $ch, CURLOPT_TIMEOUT, 30 );
        curl_setopt( $ch, CURLOPT_POST, 1 );
        curl_setopt( $ch, CURLOPT_HEADER, false );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $query );

        $response = curl_exec( $ch );

        curl_close( $ch );
        $response = json_decode( $response, true );

        if ( $response['result'] == 'success' ) {
            switch ( $response['status'] ) {
                case 'Новый' :
                    $order->update_status( 'new' );
                    break;
                case 'В производстве' :
                    $order->update_status( 'in-manufacturing' );
                    break;
                case 'Произведен' :
                    $order->update_status( 'manufactured' );
                    break;
                case 'В пути' :
                    $order->update_status( 'in-a-way' );
                    break;
                case 'Выполнен' :
                    $order->update_status( 'completed' );
                    break;
                case 'Списан' :
                    $order->update_status( 'decomissioned' );
                    break;
            }
            echo 'Статус заказа обновлен.';
        } else {
            switch ( $response['status'] ) {
                case 'invalid_order_id' :
                    echo 'Неверный или несуществующий id заказа';
                    break;
                case 'invalid_client_phone' :
                    echo 'Неверный или несуществующий номер телефона';
                    break;
            }
        }


    }
}

/*
 * Register 10 min interval for order tracking
 * Регистрация 10-минутного интервала для отслеживания заказа
 */

add_filter( 'cron_schedules', 'cron_add_ten_min' );
function cron_add_ten_min( $schedules ) {
    $schedules['ten_min'] = array(
        'interval' => 60 * 10,
        'display'  => 'Раз в 10 минут'
    );

    return $schedules;
}

// добавляем запланированный хук
add_action( 'wp', 'my_activation' );
function my_activation() {
    if ( ! wp_next_scheduled( 'my_ten_min_event' ) ) {
        wp_schedule_event( time(), 'ten_min', 'my_ten_min_event' );
    }
}

// добавляем функцию к указанному хуку
add_action( 'my_ten_min_event', 'do_every_ten_min' );
function do_every_ten_min() {

    $orders = wc_get_orders( array(
        'orderby' => 'date',
        'order'   => 'DESC',
        'status'  => 'processing'
    ) );
    //works fine!!!!!
    foreach ( $orders as $order ) {
        $order_id = $order->get_order_number();
        $data     = array(
            'secret'   => '2Z4t7YbhRDQFDARsGYb79d53ZiNKE9FrZyKSeQ4YdEnG562BR3yQ6nERAer4dYf4eSGitA7A4h7YdThYHEzES4he7A7ahd4rZADeZzFQGBzNfFyS9ShSHt2FSTF32zdHYGQF5fhAGFaRayzabGSK3FdFnKya7BHS7kk3tyn43nG3H54KAGhkbnTr3drYE3BBaQny8DkZRTNkNDZ997kah7r89GTDyh4zZdTYF6GiQfznKfnKdBNnz5kyA6',
            'order_id' => get_post_meta( $order_id, 'Frontpad Order ID', true )
        );
        $query    = "";
        //подготовка запроса
        foreach ( $data as $key => $value ) {
            $query .= "&" . $key . "=" . $value;
        }
        // send API request via cURL
        $ch = curl_init();

        // set the complete URL, to process the order on the external system. Let’s consider http://example.com/buyitem.php is the URL, which invokes the API
        curl_setopt( $ch, CURLOPT_URL, "https://app.frontpad.ru/api/index.php?get_status" );
        curl_setopt( $ch, CURLOPT_FAILONERROR, 1 );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
        curl_setopt( $ch, CURLOPT_TIMEOUT, 30 );
        curl_setopt( $ch, CURLOPT_POST, 1 );
        curl_setopt( $ch, CURLOPT_HEADER, false );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $query );

        $response = curl_exec( $ch );

        curl_close( $ch );
        $response = json_decode( $response, true );
        if ( $response['result'] == 'success' ) {
            switch ( $response['status'] ) {
                case 'Новый' :
                    $order->update_status( 'new' );
                    break;
                case 'В производстве' :
                    $order->update_status( 'in-manufacturing' );
                    break;
                case 'Произведен' :
                    $order->update_status( 'manufactured' );
                    break;
                case 'В пути' :
                    $order->update_status( 'in-a-way' );
                    break;
                case 'Выполнен' :
                    $order->update_status( 'completed' );
                    break;
                case 'Списан' :
                    $order->update_status( 'decomissioned' );
                    break;
            }
            echo 'Статус заказа обновлен.';
        } else {
            switch ( $response['status'] ) {
                case 'invalid_order_id' :
                    $order->update_status( 'failed' );
                    break;
                case 'invalid_client_phone' :
                    $order->update_status( 'failed' );
                    break;
            }
        }

    }
}

/*
 * Prohibition of an odd number of halves of pizza
 * Запрет нечетных половинок пиццы
 */

add_filter( 'woocommerce_order_button_html', 'prohibition_odd_number_pizza_halves' );

function prohibition_odd_number_pizza_halves( $button_html ) {
    $half_pizza = array(
        154,
        155,
        156,
        157,
        158,
        159,
        160,
        161,
        162,
        163,
        164,
        165,
        166,
        167,
        168,
        169,
        170,
        171,
        172
    );
    $count      = 0;
    global $woocommerce;
    foreach ( $woocommerce->cart->get_cart_contents() as $key => $value ) {
        $product = wc_get_product( $value['data']->get_id() );
        foreach ( $half_pizza as $item ) {
            if ( $product->get_sku() == $item ) {
                $count += $value['quantity'];
            }
        }
    }
    if ( $count % 2 == 1 ) {
        $button_html = str_replace( 'type="submit"', 'type="reset"', $button_html );
        echo '<div style="color:red">Заказ нечетного количества половинок пиццы невозможен!</div>';
    }

    return $button_html;
}


/*
 * Add shhipping informaition on cart page
 * Добавление информации о доставке на странице корзины
 */

add_action( 'woocommerce_cart_totals_before_shipping', 'shipping_information_cart_page' );

function shipping_information_cart_page() {
    echo '<div style="float: left;position: relative;top: 5px;"><i class="fas fa-info"></i></div><div style="font-style: italic;display: block;width: 94%;float: right;">Бесплатная доставка по Тюмени на сумму в заказе от 1000 рублей. Доставку по остальным адресам уточняйте у администратора.</div>';
}


/*
 * Remove or set quantity to 1 in promotion product
 * Удаление или установка количества = 1 в подарочном товаре
 */

add_action( 'woocommerce_after_calculate_totals', 'gift_overflow_check' );

function gift_overflow_check( $cart ) {
    $student_pizza        = array();
    $student_pizza_added  = 0;
    $pizzas_count         = 0;
    $free_pizzas_in_order = array();
    $pizzas               = array(
        370,
        371,
        372,
        373,
        374,
        375,
        376,
        377,
        378,
        379,
        380,
        381,
        382,
        383,
        384,
        385,
        386,
        387,
        388,
        389
    );
    $free_pizzas          = array( 569, 956, 957 );
    foreach ( $cart->cart_contents as $key => $value ) {
        if ( in_array( $value['product_id'], $pizzas ) ) {
            $pizzas_count ++;
        }
        if ( in_array( $value['product_id'], $free_pizzas ) ) {
            $free_pizzas_in_order[]['key'] = $key;
        }
        if ( $value['product_id'] == 569 ) {
            $student_pizza[]['key'] = $key;
            $student_pizza_added    = 1;
        }
    }
    if ( ( $cart->calculate_shipping()[0]->get_label() != 'Самовывоз' || $cart->get_totals()['subtotal'] <= 1500 ) && $student_pizza_added == 1 ) {
        foreach ( $cart->cart_contents as $key => $value ) {
            if ( $value['product_id'] == 569 ) {
                $cart->remove_cart_item( $key, 1 );
            }
        }

    }
    foreach ( $cart->cart_contents as $key => $value ) {
        if ( count( $free_pizzas_in_order ) == 2 ) {
            $cart->remove_cart_item( $free_pizzas_in_order[0]['key'], 1 );
        }

        if ( in_array( $value['product_id'], $free_pizzas ) && $value['quantity'] > 1 ) {
            $cart->set_quantity( $key, 1 );
        }
    }
}


/*
 * Add a promotion product for subtotal higher than 1500 and local pickup
 * Добавление подарочного товара для заказов на сумму от 1500 и самовывозе
 */

add_action( 'woocommerce_calculate_totals', 'add_gift_condition' );

function add_gift_condition( $cart ) {
    $student_pizza_added = 0;
    $free_pizzas         = array( 569, 956, 957 );
    foreach ( $cart->cart_contents as $key => $value ) {
        if ( in_array( $value['product_id'], $free_pizzas ) ) {
            $student_pizza_added = 1;
        }
    }
    $method_id = $cart->calculate_shipping()[0]->get_label();
    $subtotal  = $cart->get_totals()['subtotal'];

    if ( $method_id == 'Самовывоз' && $subtotal >= 1500 && ! $student_pizza_added ) {
        $cart->add_to_cart( 569, 1 );
    }

}


/*
 * Add short description in shop loop pages
 * Добавление краткого описания в цикле вывода товаров
 */

add_action( 'woocommerce_after_shop_loop_item_title', 'add_brief_in_main_page', 15 );

function add_brief_in_main_page() {
    global $product;
    $descr = $product->get_short_description();
    echo '<div style="margin-bottom:1em;">' . $descr . '</div>';
}


/*
 * adding a field "pre-order date"
 * Добавление поля "дата предзаказа"
 */

add_action( 'woocommerce_after_checkout_billing_form', 'my_custom_checkout_field' );

function my_custom_checkout_field( $checkout ) {
    echo '<div id="my_custom_checkout_field"><h3>' . __( 'Дата предзаказа' ) . '</h3>';
    woocommerce_form_field( 'pre_order', array(
        'type'  => 'datetime-local',
        'class' => array( 'my-field-class form-row-wide' ),
    ), $checkout->get_value( 'pre_order' ) );
    echo '</div>';
}

/*
 * adding pre-order date to current order
 * Добавление даты предзаказа к остальным данным заказа
 */

add_action( 'woocommerce_checkout_update_order_meta', function ( $order_id ) {
    $order = wc_get_order( $order_id );
    $order->update_meta_data( 'pre_order', sanitize_text_field( $_POST['pre_order'] ) );
    $order->save();
}, 10, 2 );


/*
 * Deleting label "optional" from checkout page
 * Удаление надписи "необязательно" из полей оформления заказа
 */

add_filter( 'woocommerce_form_field', 'remove_checkout_optional_fields_label', 10, 4 );
function remove_checkout_optional_fields_label( $field, $key, $args, $value ) {
    if ( is_checkout() && ! is_wc_endpoint_url() ) {
        $optional = '&nbsp;<span class="optional">(' . esc_html__( 'optional', 'woocommerce' ) . ')</span>';
        $field    = str_replace( $optional, '', $field );
    }

    return $field;
}


/*
 * JQuery: Needed for checkout fields to Remove "(optional)" from our non required fields
 * JQuery: Необходимый код для удаления "необязательно" из полей оформления заказа
 */

add_filter( 'wp_footer', 'remove_checkout_optional_fields_label_script' );
function remove_checkout_optional_fields_label_script() {
    if ( ! ( is_checkout() && ! is_wc_endpoint_url() ) ) {
        return;
    }

    $optional = '&nbsp;<span class="optional">(' . esc_html__( 'optional', 'woocommerce' ) . ')</span>';
    ?>
    <script>
        jQuery(function ($) {
            // On "update" checkout form event
            $(document.body).on('update_checkout', function () {
                $('#billing_street_field label > .optional').remove();
                $('#billing_house_field label > .optional').remove();
                $('#billing_pod_field label > .optional').remove();
                $('#billing_et_field label > .optional').remove();
                $('#billing_apart_field label > .optional').remove();
            });
        });
    </script>
    <?php
}


/*
 * If order has been cancelled or failed, e-mails will be not sent
 * Если заказ отменен или не удался, не отправлять сообщения на e-mail
 */

add_action( 'woocommerce_order_status_changed', 'custom_send_email_notifications', 10, 4 );
function custom_send_email_notifications( $order_id, $old_status, $new_status, $order ) {
    if ( $new_status == 'cancelled' || $new_status == 'failed' ) {
        $wc_emails      = WC()->mailer()->get_emails(); // Get all WC_emails objects instances
        $customer_email = $order->get_billing_email(); // The customer email
    }

    if ( $new_status == 'cancelled' ) {
        // change the recipient of this instance
        $wc_emails['WC_Email_Cancelled_Order']->recipient = $customer_email;
        // Sending the email from this instance
        $wc_emails['WC_Email_Cancelled_Order']->trigger( $order_id );
    } elseif ( $new_status == 'failed' ) {
        // change the recipient of this instance
        $wc_emails['WC_Email_failed_Order']->recipient = $customer_email;
        // Sending the email from this instance
        $wc_emails['WC_Email_failed_Order']->trigger( $order_id );
    }
}


?>