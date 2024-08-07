<?php

namespace Shakir\WishlistQuotePriceAndNotifier\Frontend;

use Shakir\WishlistQuotePriceAndNotifier\Logger;

class WishlistButtonHandler
{
    //protected $logger;

    public function __construct()
    {

        //$this->logger = Logger::get_instance();
    }

    public static function wc_ajax_click_wishlist_button()
    {

        /*
            received data format array(6) {
            ["action"]=> string(21) "click_wishlist_button"
            ["product_id"]=> string(2) "78"
            ["_wpnonce"]=> string(10) "1093474ccf"
            ["wishlist_action"]=> string(15) "add_to_wishlist"
            ["woocommerce-login-nonce"]=> NULL
            ["woocommerce-reset-password-nonce"]=> NULL
        }

        If wishlist_action = "add_to_wishlist", then save this data.
        If wishlist_action = "remove_from_wishlist", then remove from wishlist.

        While saving to cookie, save all wishlisted products inside an array; that will be better, I guess.
        */

        // Verify nonce for security
        if (!isset($_REQUEST['_wpnonce']) || !wp_verify_nonce($_REQUEST['_wpnonce'], '_wishlist_quote_price_notify')) {
            wp_die(__('Bad attempt, invalid nonce for new wishlist request', 'wc-triplea-crypto-payment'));
        }

        // Retrieve product_id and wishlist_action from $_REQUEST
        $product_id = isset($_REQUEST['product_id']) ? intval($_REQUEST['product_id']) : 0;
        $wishlist_action = isset($_REQUEST['wishlist_action']) ? sanitize_text_field($_REQUEST['wishlist_action']) : '';
        $remove_class = $wishlist_action == "add_to_wishlist" ? "wqpn-wishlist-empty" : "wqpn-wishlist-full";
        $add_class = $wishlist_action != "remove_from_wishlist" ? "wqpn-wishlist-full" : "wqpn-wishlist-empty";

        //Ensure product_id and wishlist_action are valid
        if (empty($product_id) || !in_array($wishlist_action, ['add_to_wishlist', 'remove_from_wishlist'])) {
            wp_die(__('Invalid product ID or wishlist action', 'wc-triplea-crypto-payment'));
        }

        // Handle wishlist action based on wishlist_action value
        switch ($wishlist_action) {
            case 'add_to_wishlist':
                self::add_to_wishlist($product_id);
                break;
            case 'remove_from_wishlist':
                self::remove_from_wishlist($product_id);
                break;
            default:
                // Invalid wishlist_action, should not happen with the check above
                wp_die(__('Invalid wishlist action', 'wc-triplea-crypto-payment'));
        }
        // Handle wishlist action based on wishlist_action value using match
        // $response = match ($wishlist_action) {
        //     'add_to_wishlist' => self::add_to_wishlist($product_id),
        //     'remove_from_wishlist' => self::remove_from_wishlist($product_id),
        //     default => wp_die(__('Invalid wishlist action', 'wc-triplea-crypto-payment')),
        // };

        $response = [
            'status' => 201,
            'message' => 'Wishlist action processed successfully',
            'product_id' => $product_id,
            'wishlist_action' => $wishlist_action,
            'remove_class' => $remove_class,
            'add_class' => $add_class,
        ];
        //$this->logger->write_log("wishlist click button response", true);
        //self::logger->write_log($response, true);
        //$this->logger->write_log('webhook_update : - Body = ' . wc_print_r($request->get_body(), true), $debugLoged);

        Logger::get_instance()->write_log(wc_print_r($response, true), true);
        // Example response
        echo json_encode($response);

        // For demonstration purposes, you might want to log actions


        // Always exit after processing AJAX requests in WordPress
        wp_die();
    }

    private static function add_to_wishlist($product_id)
    {
        // Retrieve existing wishlist data from cookie or initialize an empty array
        $wishlist = isset($_COOKIE['wqpn_wishlist']) ? json_decode(stripslashes($_COOKIE['wqpn_wishlist']), true) : [];
        Logger::get_instance()->write_log("before adding item to wishlist in handler", true);
        Logger::get_instance()->write_log(wc_print_r($wishlist, $product_id, true), true);

        // Add product_id to the wishlist array if not already present
        // if (!in_array($product_id, $wishlist)) {
        //     $wishlist[] = $product_id;
        // }
        // Check if the product is already in the wishlist
        if (!array_key_exists($product_id, $wishlist)) {
            // Add the product with the current time
            $wishlist[$product_id] = [
                'product_id' => $product_id,
                'added_time' => time()
            ];

            // Set the updated wishlist cookie
            setcookie('wqpn_wishlist', json_encode($wishlist), time() + 3600 * 24 * 30, '/'); // 30 days expiration
        }
        Logger::get_instance()->write_log("after adding item to wishlist in handler", true);
        Logger::get_instance()->write_log(wc_print_r($wishlist, $product_id, true), true);
        // Save updated wishlist array to cookie
        setcookie('wqpn_wishlist', json_encode($wishlist), time() + 3600 * 24 * 30, '/'); // 30 days expiration
    }

    private static function remove_from_wishlist($product_id)
    {
        // Retrieve existing wishlist data from cookie or initialize an empty array
        $wishlist = isset($_COOKIE['wqpn_wishlist']) ? json_decode(stripslashes($_COOKIE['wqpn_wishlist']), true) : [];

        // Check if the product is in the wishlist and remove it
        if (isset($wishlist[$product_id])) {
            unset($wishlist[$product_id]);

            // Update the wishlist cookie
            setcookie('wqpn_wishlist', json_encode($wishlist), time() + 3600 * 24 * 30, '/'); // 30 days expiration
        }

        // Save updated wishlist array to cookie
        setcookie('wqpn_wishlist', json_encode($wishlist), time() + 3600 * 24 * 30, '/'); // 30 days expiration
    }



}
