<?php

 if (!defined('ABSPATH')) exit;

function openkbs_get_available_wp_actions() {
    return [
        'WordPress' => [
            'Posts' => [
                'publish_post' => 'Post Published',
                'before_delete_post' => 'Post Deleted',
                'post_updated' => 'Post Updated'
            ],
            'Comments' => [
                'wp_insert_comment' => 'New Comment',
                'delete_comment' => 'Comment Deleted',
                'edit_comment' => 'Comment Edited'
            ],
            'Users' => [
                'user_register' => 'User Registered',
                'profile_update' => 'Profile Updated',
                'delete_user' => 'User Deleted'
            ],
            'Terms' => [
                'created_term' => 'Term Created',
                'edited_term' => 'Term Updated',
                'delete_term' => 'Term Deleted'
            ],
            'Media' => [
                'add_attachment' => 'Media Uploaded',
                'delete_attachment' => 'Media Deleted'
            ]
        ],
        'WooCommerce' => [
            'Orders' => [
                'woocommerce_new_order' => 'New Order',
                'woocommerce_order_status_changed' => 'Order Status Change',
            ],
            'Products' => [
                'woocommerce_update_product' => 'Product Update',
            ],
            'Customers' => [
                'woocommerce_created_customer' => 'New Customer Created'
            ],
            'Cart' => [
                'woocommerce_add_to_cart' => 'Item Added to Cart',
                'woocommerce_cart_item_removed' => 'Item Removed from Cart'
            ],
        ],
        'Contact Form 7' => [
            'Forms' => [
                'wpcf7_mail_failed' => 'Mail Failed',
                'wpcf7_mail_sent' => 'Mail Sent',
            ]
        ]
    ];
}