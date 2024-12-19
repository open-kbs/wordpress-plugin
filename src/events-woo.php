<?php

/**
 * Event Handlers
 * 
 * Each handler captures relevant data and sends it to OpenKBS via the openkbs_publish function.
 * Handlers provide detailed information about the event that will be processed by the AI Agent.
 * 
 * openkbs_publish($event, $title) expects:
 *   - $event: { event: wp_action_name, ...props }
 *   - $title: Chat display title for this event instance
 */

 if (!defined('ABSPATH')) {
     exit; // Exit if accessed directly
 }

require_once plugin_dir_path(__FILE__) . 'openkbs-utils.php';

// Register WooCommerce hooks
function openkbs_hook_woocommerce_events() {
    add_action('woocommerce_new_order', 'openkbs_handle_new_order', 10, 1);
    add_action('woocommerce_order_status_changed', 'openkbs_handle_order_status_change', 10, 4);
    add_action('woocommerce_update_product', 'openkbs_handle_product_update', 10, 1);
    add_action('before_delete_post', 'openkbs_handle_delete_product', 10, 1);
    add_action('woocommerce_created_customer', 'openkbs_handle_new_customer', 10, 3);
    add_action('woocommerce_add_to_cart', 'openkbs_handle_add_to_cart', 10, 6);
    add_action('woocommerce_cart_item_removed', 'openkbs_handle_cart_item_removed', 10, 2);
}

// Handler for new order
function openkbs_handle_new_order($order_id) {
    $order = wc_get_order($order_id);
    $items = [];

    // Get order items
    foreach ($order->get_items() as $item_id => $item) {
        $product = $item->get_product();
        $items[] = [
            'product_id' => $item->get_product_id(),
            'name' => $item->get_name(),
            'quantity' => $item->get_quantity(),
            'total' => $item->get_total(),
            'sku' => $product ? $product->get_sku() : '',
        ];
    }

    $event = array(
        'event' => 'woocommerce_new_order',
        'order_id' => $order_id,
        'status' => $order->get_status(),
        'total' => $order->get_total(),
        'subtotal' => $order->get_subtotal(),
        'tax_total' => $order->get_total_tax(),
        'shipping_total' => $order->get_shipping_total(),
        'payment_method' => $order->get_payment_method_title(),
        'items' => $items,
        'customer' => [
            'id' => $order->get_customer_id(),
            'first_name' => $order->get_billing_first_name(),
            'last_name' => $order->get_billing_last_name(),
            'email' => $order->get_billing_email(),
            'phone' => $order->get_billing_phone()
        ],
        'billing' => [
            'address_1' => $order->get_billing_address_1(),
            'address_2' => $order->get_billing_address_2(),
            'city' => $order->get_billing_city(),
            'state' => $order->get_billing_state(),
            'postcode' => $order->get_billing_postcode(),
            'country' => $order->get_billing_country()
        ],
        'shipping' => [
            'address_1' => $order->get_shipping_address_1(),
            'address_2' => $order->get_shipping_address_2(),
            'city' => $order->get_shipping_city(),
            'state' => $order->get_shipping_state(),
            'postcode' => $order->get_shipping_postcode(),
            'country' => $order->get_shipping_country()
        ]
    );

    openkbs_publish($event, 'order-create: ' . $order_id);
}

// Handler for order status change
function openkbs_handle_order_status_change($order_id, $old_status, $new_status, $order) {
    $event = array(
        'event' => 'woocommerce_order_status_changed',
        'order_id' => $order_id,
        'old_status' => $old_status,
        'new_status' => $new_status,
        'total' => $order->get_total(),
        'customer_note' => $order->get_customer_note(),
        'date_modified' => $order->get_date_modified()->format('Y-m-d H:i:s'),
        'payment_method' => $order->get_payment_method_title()
    );
    
    openkbs_publish($event, 'order-status: ' . $order_id);
}

// Handler for product update
function openkbs_handle_product_update($product_id) {
    $updating_product_id = 'update_product_' . $product_id;
    if ( false !== get_transient( $updating_product_id ) ) return;
    set_transient( $updating_product_id, $product_id, 2 );

    $product = wc_get_product($product_id);
    
    $event = array(
        'event' => 'woocommerce_update_product',
        'product_id' => $product_id,
        'name' => $product->get_name(),
        'type' => $product->get_type(),
        'status' => $product->get_status(),
        'featured' => $product->get_featured(),
        'catalog_visibility' => $product->get_catalog_visibility(),
        'description' => $product->get_description(),
        'short_description' => $product->get_short_description(),
        'sku' => $product->get_sku(),
        'price' => $product->get_price(),
        'regular_price' => $product->get_regular_price(),
        'sale_price' => $product->get_sale_price(),
        'stock_status' => $product->get_stock_status(),
        'stock_quantity' => $product->get_stock_quantity(),
        'categories' => wp_get_post_terms($product_id, 'product_cat', ['fields' => 'names']),
        'tags' => wp_get_post_terms($product_id, 'product_tag', ['fields' => 'names']),
        'modified_date' => get_post_modified_time('Y-m-d H:i:s', true, $product_id)
    );

    // If product is variable, add variations
    if ($product->is_type('variable')) {
        $variations = $product->get_available_variations();
        $variation_data = [];
        
        foreach ($variations as $variation) {
            $variation_product = wc_get_product($variation['variation_id']);
            $variation_data[] = [
                'variation_id' => $variation['variation_id'],
                'attributes' => $variation['attributes'],
                'price' => $variation_product->get_price(),
                'regular_price' => $variation_product->get_regular_price(),
                'sale_price' => $variation_product->get_sale_price(),
                'sku' => $variation_product->get_sku(),
                'stock_quantity' => $variation_product->get_stock_quantity(),
                'stock_status' => $variation_product->get_stock_status()
            ];
        }
        
        $event['variations'] = $variation_data;
    }
    
    openkbs_publish($event, 'product-update: ' . $event['name']);
}

function openkbs_handle_delete_product($product_id) {
    $product = wc_get_product($product_id);
    
    // Check if the product exists
    if (!$product) {
        error_log("Product with ID $product_id could not be found.");
        return;
    }
    
    // Get product data before it's deleted
    $event = array(
        'event' => 'before_delete_post',
        'product_id' => $product_id,
        'name' => $product->get_name(),
        'type' => $product->get_type(),
        'sku' => $product->get_sku(),
        'categories' => wp_get_post_terms($product_id, 'product_cat', ['fields' => 'names']),
        'tags' => wp_get_post_terms($product_id, 'product_tag', ['fields' => 'names']),
        'deletion_date' => current_time('Y-m-d H:i:s'),
        'last_modified' => get_post_modified_time('Y-m-d H:i:s', true, $product_id)
    );

    // If it's a variable product, include variation information
    if ($product->is_type('variable')) {
        $variations = $product->get_available_variations();
        $variation_data = [];
        
        foreach ($variations as $variation) {
            $variation_product = wc_get_product($variation['variation_id']);
            $variation_data[] = [
                'variation_id' => $variation['variation_id'],
                'sku' => $variation_product->get_sku(),
                'attributes' => $variation['attributes']
            ];
        }
        
        $event['variations'] = $variation_data;
    }

    openkbs_publish($event, 'product-delete: ' . $event['name']);
}


// Handler for new customer creation
function openkbs_handle_new_customer($customer_id, $new_customer_data, $password_generated) {
    $customer = new WC_Customer($customer_id);
    
    $event = array(
        'event' => 'woocommerce_created_customer',
        'customer_id' => $customer_id,
        'date_created' => $customer->get_date_created() ? $customer->get_date_created()->format('Y-m-d H:i:s') : '',
        'first_name' => $customer->get_first_name(),
        'last_name' => $customer->get_last_name(),
        'display_name' => $customer->get_display_name(),
        'email' => $customer->get_email(),
        'role' => $customer->get_role(),
        'username' => $customer->get_username(),
        'billing' => [
            'first_name' => $customer->get_billing_first_name(),
            'last_name' => $customer->get_billing_last_name(),
            'company' => $customer->get_billing_company(),
            'address_1' => $customer->get_billing_address_1(),
            'address_2' => $customer->get_billing_address_2(),
            'city' => $customer->get_billing_city(),
            'state' => $customer->get_billing_state(),
            'postcode' => $customer->get_billing_postcode(),
            'country' => $customer->get_billing_country(),
            'email' => $customer->get_billing_email(),
            'phone' => $customer->get_billing_phone()
        ],
        'shipping' => [
            'first_name' => $customer->get_shipping_first_name(),
            'last_name' => $customer->get_shipping_last_name(),
            'company' => $customer->get_shipping_company(),
            'address_1' => $customer->get_shipping_address_1(),
            'address_2' => $customer->get_shipping_address_2(),
            'city' => $customer->get_shipping_city(),
            'state' => $customer->get_shipping_state(),
            'postcode' => $customer->get_shipping_postcode(),
            'country' => $customer->get_shipping_country()
        ],
        'is_paying_customer' => $customer->get_is_paying_customer(),
        'password_generated' => $password_generated,
        'meta_data' => array_map(function($meta) {
            return [
                'key' => $meta->key,
                'value' => $meta->value
            ];
        }, $customer->get_meta_data())
    );

    openkbs_publish($event, 'customer-create: ' . $customer_id);
}

// Handler for add to cart
function openkbs_handle_add_to_cart($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
    $product = wc_get_product($product_id);
    
    $event = array(
        'event' => 'woocommerce_add_to_cart',
        'cart_item_key' => $cart_item_key,
        'product_id' => $product_id,
        'product_name' => $product->get_name(),
        'quantity' => $quantity,
        'price' => $product->get_price(),
        'variation_id' => $variation_id,
        'variation' => $variation,
        'cart_item_data' => $cart_item_data,
        'total_cart_items' => WC()->cart->get_cart_contents_count(),
        'cart_total' => WC()->cart->get_cart_total()
    );

    openkbs_publish($event, 'cart-add: ' . $event['product_name']);
}

// Handler for cart item removed
function openkbs_handle_cart_item_removed($cart_item_key, $cart) {
    $event = array(
        'event' => 'woocommerce_cart_item_removed',
        'cart_item_key' => $cart_item_key,
        'remaining_cart_items' => $cart->get_cart_contents_count(),
        'cart_total' => $cart->get_cart_total(),
        'removed_item' => array(
            'product_id' => $cart->removed_cart_contents[$cart_item_key]['product_id'] ?? '',
            'quantity' => $cart->removed_cart_contents[$cart_item_key]['quantity'] ?? 0,
            'variation_id' => $cart->removed_cart_contents[$cart_item_key]['variation_id'] ?? '',
        )
    );

    openkbs_publish($event, 'cart-remove: ' . $cart_item_key);
}