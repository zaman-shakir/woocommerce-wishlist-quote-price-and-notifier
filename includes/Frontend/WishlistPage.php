<?php

namespace Shakir\WishlistQuotePriceAndNotifier\Frontend;

class WishlistPage
{
    public function __construct()
    {
        add_action('template_redirect', [$this, 'wqpn_add_wishlist_to_cart']);
        add_action('woocommerce_cart_calculate_fees', [$this,'apply_offered_price_discount']);
        add_action('wp_ajax_new_wishlist_from_scratch', [$this, 'new_wishlist_from_scratch']);
        add_action('wp_ajax_nopriv_new_wishlist_from_scratch', [$this, 'new_wishlist_from_scratch']);


        $this->wqpn_wishlist_page_contents_shortcode();

    }
    public static function new_wishlist_from_scratch()
    {

        //get wishlost for now logged in user
        if(!is_user_logged_in()) {
            wp_send_json_error('No user found');

        }

        global $wpdb;
        $user_id = get_current_user_id();
        $table_name = $wpdb->prefix . 'wqpn_wishlist';

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE archived = 0 AND user_id = %d",
                $user_id
            )
        );

        if ($count > 0) {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE $table_name SET archived = 1 WHERE archived = 0 AND user_id = %d",
                    $user_id
                )
            );
        }

        wp_send_json_success('Wishlist updated');

    }
    public function wqpn_add_wishlist_to_cart()
    {
        if (isset($_GET['action']) && $_GET['action'] === 'wqpn_add_wishlist_to_cart' && isset($_GET['unique_id']) && isset($_GET['nonce']) && wp_verify_nonce($_GET['nonce'], 'wqpn_add_wishlist_to_cart')) {
            $unique_id = sanitize_text_field($_GET['unique_id']);
            $user_id = sanitize_text_field($_GET['user_id']);

            // Fetch wishlist data
            $user_data = $this->get_loggedin_user_quote_data();

            if (isset($user_data)) {

                $data = $user_data;
                if ($data['unique_id'] === $unique_id) {
                    $user_data = $data;
                    $products = json_decode($user_data['products'], true);

                    // Clear the cart
                    WC()->cart->empty_cart();

                    // Add products to the cart
                    foreach ($products as $product_id => $product_data) {
                        WC()->cart->add_to_cart($product_id, $product_data['qty']);
                    }

                    // Redirect to the cart page
                    wp_safe_redirect(wc_get_cart_url());
                    exit;

                }
            }
        }
    }
    public function apply_offered_price_discount() {
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $offered_price = get_user_meta($user_id, 'wqpn_offered_price', true);

            global $wpdb;
            $table_name = $wpdb->prefix . 'wqpn_wishlist';

            // Fetch user data where archived = 0 and used = 0
            $user_data = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM $table_name WHERE archived = 0 AND used = 0 AND user_id = %d",
                    $user_id
                ),
                ARRAY_A
            );

            if ($offered_price) {
                // Calculate the discount based on the offered price
                $cart_total = WC()->cart->cart_contents_total;
                $discount = $cart_total - $offered_price;

                // Add the discount as a negative fee if the discount is valid
                if ($discount > 0) {
                    WC()->cart->add_fee(__('Offered Price Discount', 'text-domain'), -$discount);

                    // Mark the offer as used in user meta
                    update_user_meta($user_id, 'wqpn_discount_used', true);

                    // Update the wishlist table, marking the entry as archived and used
                    $wpdb->update(
                        $table_name,
                        [
                            'archived' => 1,
                            'used' => 1
                        ], // Data to update
                        ['user_id' => $user_id], // Where clause to match the user
                        ['%d', '%d'], // Data format for the values being updated (integers)
                        ['%d'] // Format for the where clause (user_id is an integer)
                    );
                }
            }
        }
    }
    public function wqpn_wishlist_page_contents_shortcode()
    {
        // Register shortcode to display wishlist
        add_shortcode('wqpn_wishlist', [$this, 'wqpn_display_wishlist']);
    }
    public function get_loggedin_user_quote_data()
    {
        global $wpdb;
        $user_id = get_current_user_id();
        $table_name = $wpdb->prefix . 'wqpn_wishlist';
        $all_users_data = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE archived = 0 AND user_id = %d",
                $user_id
            ),
            ARRAY_A
        );
        return $all_users_data;
    }
    public function wqpn_display_wishlist()
    {

        $user_id = get_current_user_id();

        $user_data = $this->get_loggedin_user_quote_data();
        if ($user_id && is_user_logged_in()) {
            //$user_data = $all_users_data[$user_id];

            $status = $user_data['status'] ?? null;

            /**
             * status : submitted, accepted, declined, archived
            */
            if ($status === 'submitted' || $status === 'accepted' || $status === "rejected") {
                return $this->display_submitted_wishlist($user_data);
            }
        }

        $wishlist = $this->get_wishlist_from_cookies();

        $user_applied = isset($_COOKIE['wqpn_user_applied_for_submit_form']) ? true : false;

        if (empty($wishlist)) {
            return '<p>Your wishlist is empty.</p>';
        }

        $currency_symbol = get_woocommerce_currency_symbol();
        $currency_position = get_option('woocommerce_currency_pos');

        ob_start();
        $this->display_wishlist_table($wishlist, $currency_symbol, $currency_position);
        $this->display_order_summary($currency_symbol, $currency_position);
        //$this->display_price_section($currency_symbol, $currency_position);
        return ob_get_clean();
    }
    private function display_submitted_wishlist($user_data)
    {

        $currency_symbol = get_woocommerce_currency_symbol();
        $currency_position = get_option('woocommerce_currency_pos');
        ob_start();
        // echo $this->format_status_tree($user_data['status']);
        if($user_data['status'] == 'rejected') {
            echo "<div style='display: flex; flex-direction:column;align-items:center; justify-content: center; margin-top: 20px;'>
                <h5 style='max-width: 62%; text-align: center; line-height: 1.5;'>
                    Your offer has been reviewed, and we regret to inform you that it has been declined. We appreciate your interest and encourage you to explore other opportunities with us. Thank you for your understanding.

                    </h5>
                    <span class='new-wishlist-from-scratch' id='new-wishlist-from-scratch' style='color:blue;cursor:pointer; margin-bottom:50px'>Please click here to continue a new wishlist.</span>
            </div>";
        } else {
            echo "<div style='display: flex; justify-content: center; margin-top: 20px;'>
            <h5 style='max-width: 60%; text-align: center; line-height: 1.5;'>
                Your offered price has been submitted successfully. Please wait while we review your offer. We will contact you shortly with our decision.
            </h5>
          </div>";
        }

        echo '<div class="wqpn-wishlist-container">';

        echo '<div class="wqpn-products"  style="width:58%"><table class="wqpn-wishlist-table"><tbody>';
        echo "<tr id='wqpn-row'>
        <td class='wqpn-img'><strong>Product</strong></td>
        <td class='wqpn-product-details'>
        &nbsp;
        </td>
        <td class='wqpn-product-qty'>  <strong>Qty</strong>

        </td>
        <td class='wqpn-product-subtotal' id='wqpn-subtotal'><strong>Subtotal</strong></td>
    </tr>";
        $products = json_decode($user_data['products'], true);

        foreach ($products as $product_id => $item) {
            $this->display_submitted_wishlist_item($item, $currency_symbol, $currency_position);
        }
        echo '</tbody></table></div>';


        echo '<div class="wqpn-order-summary" style="width:40%;height:fit-content">
                    <h4 style="text-align:center">Your Offered Price Summary</h4>
        <div class="wqpn-price-submit-grid" style="display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            row-gap: 0px;">
            <div style="text-align:right"><p>Wishlist Total :</p></div><div><p> ' . $this->format_price($user_data['wishlist_price'], $currency_symbol, $currency_position) . '</p></div>
            <div style="text-align:right"><p>Offered Price :</div><div><p>' . $this->format_price($user_data['quote_price'], $currency_symbol, $currency_position) . '</p></div>
            <div style="text-align:right"><p><p>Status :</div><div><p>' . esc_html($user_data['status']) . '</p></div>';



        /// if status is accepted. visible this link
        if($user_data['status'] == 'accepted') {
            ?>
             <a href="<?php echo esc_url(add_query_arg(['action' => 'wqpn_add_wishlist_to_cart', 'unique_id' => $user_data['unique_id'], 'user_id' => get_current_user_id(), 'nonce' => wp_create_nonce('wqpn_add_wishlist_to_cart')], home_url('/'))); ?>" class="button">Add Wishlist to Cart</a>
            <?php
        }
        echo  '</div></div>';

        return ob_get_clean();
    }

    private function format_status_tree($status)
    {
        $icons = [
            'review' => '<svg fill="currentColor" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20"><path d="M12 2L3.5 20.5h17L12 2zm0 3.84L18.58 18H5.42L12 5.84zM11 8h2v6h-2V8zm0 8h2v2h-2v-2z"/></svg>',
            'accepted' => '<svg fill="currentColor" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20"><path d="M10 15.27L4.55 9.81 3.14 11.22 10 18.07l10.36-10.36L18.94 6.27 10 15.27z"/></svg>',
            'rejected' => '<svg fill="currentColor" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20"><path d="M6 18L18 6M6 6l12 12"/></svg>',
            'submitted' => '<svg fill="currentColor" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20"><path d="M12 2L1.69 21.73a.99.99 0 001.31 1.27L12 17.9l8.99 5.1a.99.99 0 001.31-1.27L12 2z"/></svg>',
        ];

        $tree = '<div class="status-tree">';
        $tree .= '<div class="status-node' . ($status == 'submitted' ? ' active' : '') . '">';
        $tree .= $icons['submitted'] . ' Submitted';
        $tree .= '</div>';

        $tree .= '<div class="status-node' . ($status == 'review' ? ' active' : '') . '">';
        $tree .= $icons['review'] . ' Review';
        $tree .= '</div>';

        $tree .= '<div class="status-node' . ($status == 'accepted' ? ' active' : '') . '">';
        $tree .= $icons['accepted'] . ' Accepted';
        $tree .= '</div>';

        $tree .= '<div class="status-node' . ($status == 'rejected' ? ' active' : '') . '">';
        $tree .= $icons['rejected'] . ' Rejected';
        $tree .= '</div>';

        $tree .= '</div>';

        return $tree;
    }
    private function get_wishlist_from_cookies()
    {
        return isset($_COOKIE['wqpn_wishlist']) ? json_decode(stripslashes($_COOKIE['wqpn_wishlist']), true) : [];
    }

    private function display_wishlist_table($wishlist, $currency_symbol, $currency_position)
    {
        $action_url = admin_url('admin-post.php');
        $user_applied = isset($_COOKIE['wqpn_user_applied_for_submit_form']) ? true : false;

        if($user_applied) {
            echo '<form id="wqpn-wishlist-form-submit" method="post" action="' . esc_url($action_url) . '"><div class="wqpn-wishlist-container">';
            echo '<input type="hidden" name="action" value="wqpn_submit_quote_price">';
            echo '<div class="wqpn-wishlist-submit-table-container" style="width:50%">';
            echo '<table class="wqpn-wishlist-table"><tbody>';
            echo "<tr id='wqpn-row'>
            <td class='wqpn-img'><strong>Product</strong></td>
            <td class='wqpn-product-details'>
            &nbsp;
            </td>
            <td class='wqpn-product-qty'>  <strong>Qty</strong>

            </td>
            <td class='wqpn-product-subtotal' id='wqpn-subtotal'><strong>Subtotal</strong></td>
        </tr>";
        } else {
            echo '<form id="wqpn-wishlist-form" method="post" action="' . esc_url($action_url) . '"><div class="wqpn-wishlist-container">';
            echo '<input type="hidden" name="action" value="wqpn_submit_price">';
            echo '<div class="wqpn-wishlist-table-container">';
            echo '<table class="wqpn-wishlist-table"><tbody>';
        }
        foreach ($wishlist as $item) {
            $this->display_wishlist_item($item, $currency_symbol, $currency_position);
        }

        echo '</tbody></table>';
        echo '</form></div>';
    }

    private function display_wishlist_item($item, $currency_symbol, $currency_position)
    {
        $user_applied = isset($_COOKIE['wqpn_user_applied_for_submit_form']) ? true : false;

        $product_id = $item['product_id'];
        $added_time = $item['added_time'];

        $product = wc_get_product($product_id);
        if (!$product) {
            return;
        }

        $product_title = $product->get_title();
        $product_price_html = $product->get_price_html();
        $product_price = $product->get_price();
        $product_image = $product->get_image();
        $product_qty = 1; // Default quantity for wishlist, adjust as needed
        $added_time_formatted = date('F j, Y', $added_time);

        $stock_status = $product->is_in_stock() ? 'In stock' : 'Out of stock';
        $stock_class = $product->is_in_stock() ? 'wqpn-in-stock' : 'wqpn-out-stock';

        $subtotal = $product_price * $product_qty;

        $nonce = wp_create_nonce('_wishlist_quote_price_notify');
        $url = esc_url(admin_url('admin-ajax.php'));
        $remove_btn = $this->get_remove_button($product_id, $nonce, $url);

        $formatted_subtotal = $this->format_price($subtotal, $currency_symbol, $currency_position);
        if($user_applied) {
            echo "<tr id='wqpn-row-{$product_id}'>
            <td class='wqpn-img'>{$product_image}</td>
            <td class='wqpn-product-details'>
                <strong><a target='_blank' href='{$product->get_permalink()}'>{$product_title}</a></strong><br>
                {$product_price_html}<br>
                {$added_time_formatted}<br>
                <span class='wqpn-product-stock {$stock_class}'>{$stock_status}</span>
            </td>
            <td class='wqpn-product-qty'>{$item['qty']}
            </td>
            <td class='wqpn-product-subtotal' id='wqpn-subtotal-{$product_id}' data-product-id='{$product_id}' data-product-price='{$product_price}'>". $formatted_subtotal ."</td>
        </tr>";
        } else {
            echo "<tr id='wqpn-row-{$product_id}'>
            <td class='wqpn-img'>{$product_image}</td>
            <td class='wqpn-product-details'>
                <strong><a target='_blank' href='{$product->get_permalink()}'>{$product_title}</a></strong><br>
                {$product_price_html}<br>
                {$added_time_formatted}<br>
                <span class='wqpn-product-stock {$stock_class}'>{$stock_status}</span>
            </td>
            <td class='wqpn-product-qty'>
                <input type='number' class='wqpn-product-qty-input' name='qty[{$product_id}]' value='{$product_qty}' min='1' data-product-id='{$product_id}' data-product-price='{$product_price}'/>
            </td>
            <td class='wqpn-product-subtotal' id='wqpn-subtotal-{$product_id}' data-product-id='{$product_id}' data-product-price='{$product_price}'>". $formatted_subtotal ."</td>
            <td class='wqpn-product-remove'>{$remove_btn}</td>
        </tr>";
        }

    }
    private function display_submitted_wishlist_item($item, $currency_symbol, $currency_position)
    {

        $product_id = $item['product_id'];
        $added_time = $item['added_time'];

        $product = wc_get_product($product_id);
        if (!$product) {
            return;
        }

        $product_title = $product->get_title();
        $product_price_html = $product->get_price_html();
        $product_price = $product->get_price();
        $product_image = $product->get_image();
        $product_qty = 1; // Default quantity for wishlist, adjust as needed
        $added_time_formatted = date('F j, Y H:m a', $added_time);

        $stock_status = $product->is_in_stock() ? 'In stock' : 'Out of stock';
        $stock_class = $product->is_in_stock() ? 'wqpn-in-stock' : 'wqpn-out-stock';

        $subtotal = $product_price * $product_qty;

        $nonce = wp_create_nonce('_wishlist_quote_price_notify');
        $url = esc_url(admin_url('admin-ajax.php'));
        $remove_btn = $this->get_remove_button($product_id, $nonce, $url);

        $formatted_subtotal = $this->format_price($subtotal, $currency_symbol, $currency_position);

        echo "<tr id='wqpn-row-{$product_id}'>
            <td class='wqpn-img'>{$product_image}</td>
            <td class='wqpn-product-details'>
                <strong><a target='_blank' href='{$product->get_permalink()}'>{$product_title}</a></strong><br>
                {$product_price_html}<br>
                {$added_time_formatted}<br>
                <span class='wqpn-product-stock {$stock_class}'>{$stock_status}</span>
            </td>
            <td class='wqpn-product-qty'>{$product_qty}
            </td>
            <td class='wqpn-product-subtotal' id='wqpn-subtotal-{$product_id}' data-product-id='{$product_id}' data-product-price='{$product_price}'>". $formatted_subtotal ."</td>
        </tr>";


    }

    private function get_remove_button($product_id, $nonce, $url)
    {
        $svg = '<svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"></path>
        </svg>';
        return "<div class='wqpn-wishlist-remove wqpn-wishlish-page-remove' data-product-id='{$product_id}' data-nonce='{$nonce}' data-url='{$url}'>" . $svg . "</div>";
    }

    private function display_order_summary($currency_symbol, $currency_position)
    {
        $user_applied = isset($_COOKIE['wqpn_user_applied_for_submit_form']) ? true : false;
        $wishlist_total = isset($_COOKIE['wqpn_total_price']) ? floatval($_COOKIE['wqpn_total_price']) : 0;

        if ($user_applied) {
            echo '<div class="wqpn-order-summary" style="width:47%">
                <h4 style="text-align:center">Offer Your Price</h4>
                <div class="wqpn-price-submit-grid">
                    <div>Wishlist Total:</div>
                    <div id="wqpn-wishlist-total">' . $this->format_price_for_submit($wishlist_total, $currency_symbol, $currency_position) . '</div>
                    <div>Your Price:</div>
                    <div><input name="quoteprice" type="number" value ="'.$wishlist_total.'" id="wqpn-price-input" step="0.01" /></div>
                    <div style="display:none">Email:</div>
                    <input name="email" hidden type="email" id="wqpn-email-input" value=" " />
                    <div>WhatsApp:</div>
                    <div><input name="whatsapp" type="tel" id="wqpn-whatsapp-input" /></div>
                    <div>Telegram:</div>
                    <div><input name="telegram" type="tel" id="wqpn-telegram-input" /></div>
                    <div style="grid-column: span 2; text-align: center;">
                        <button type="submit" id="">Submit Price</button>
                    </div>
                </div>
              </div>';
        } else {

            if (is_user_logged_in()) {
                echo '<div class="wqpn-order-summary">
                <h3>Wishlist Summary</h3>
                <p>Wishlist Total: <span id="wqpn-total" data-currency-position="' . esc_attr($currency_position) . '" data-currency-symbol="' . esc_attr($currency_symbol) . '"></span></p>
                <button id="wqpn-submit-price" class="wqpn-submit-price">Submit a Price for these wishlist products?</button>
            </div>';
            } else {
                echo '<div class="wqpn-order-summary">
                <h3>Wishlist Summary</h3>
                <p>Wishlist Total: <span id="wqpn-total" data-currency-position="' . esc_attr($currency_position) . '" data-currency-symbol="' . esc_attr($currency_symbol) . '"></span></p>

                <a href="/login" id="" >Please login first to offer a custom price</p>
            </div>';
            }

        }

    }
    private function format_price_for_submit($price, $currency_symbol, $currency_position)
    {
        if ($currency_position === 'before') {
            return $currency_symbol . number_format($price, 2);
        } else {
            return number_format($price, 2) . $currency_symbol;
        }
    }
    private function format_price($price, $currency_symbol, $currency_position)
    {
        switch ($currency_position) {
            case 'left':
                return $currency_symbol . number_format($price, 2);
            case 'right':
                return number_format($price, 2) . $currency_symbol;
            case 'left_space':
                return $currency_symbol . ' ' . number_format($price, 2);
            case 'right_space':
                return number_format($price, 2) . ' ' . $currency_symbol;
            default:
                return $currency_symbol . number_format($price, 2);
        }
    }

    ////////////////////////////////////////////////////////////////
    private function display_price_section($currency_symbol, $currency_position)
    {
        echo '<div id="wqpn-price-section" style="display: none;">
            <div class="wqpn-price-submit-grid">
                <div class="wqpn-price-submit-title" style="grid-column: span 2; text-align: center;">Quote a Price</div>
                <div>Wishlist Total:</div>
                <div id="wqpn-wishlist-total">' . $this->format_price(0, $currency_symbol, $currency_position) . '</div>
                <div>Your Price:</div>
                <div><input type="number" id="wqpn-price-input" step="0.01" /></div>
                <div>Email:</div>
                <div><input type="email" id="wqpn-email-input" /></div>
                <div>WhatsApp:</div>
                <div><input type="text" id="wqpn-whatsapp-input" /></div>
                <div>Telegram:</div>
                <div><input type="text" id="wqpn-telegram-input" /></div>
                <div style="grid-column: span 2; text-align: center;">
                    <button id="wqpn-price-submit">Submit Price</button>
                </div>
            </div>
        </div>';
    }



    public static function wqpn_is_user_logged_in()
    {

        $response = false;
        if (is_user_logged_in()) {
            $response = true;
            echo json_encode($response);
        } else {
            echo json_encode($response);
        }
        wp_die(); // This is required to terminate immediately and return a proper response
    }
    public static function handle_wishlist_form_submission()
    {
        // Ensure the request is valid
        if (!isset($_POST['action']) || $_POST['action'] !== 'wqpn_submit_price') {
            wp_die('Invalid form submission');
        }

        // Retrieve the current wishlist from the cookie
        $wishlist = isset($_COOKIE['wqpn_wishlist']) ? json_decode(stripslashes($_COOKIE['wqpn_wishlist']), true) : [];

        // Update the wishlist with the submitted quantities
        if (isset($_POST['qty']) && is_array($_POST['qty'])) {
            $total_price = 0;

            foreach ($_POST['qty'] as $product_id => $qty) {
                $product_id = intval($product_id);
                $qty = intval($qty);

                // Assume a function `get_product_price($product_id)` that returns the product price
                $product_price = self::get_product_price($product_id);
                $subtotal = $product_price * $qty;

                // Update the wishlist item
                if (isset($wishlist[$product_id])) {
                    $wishlist[$product_id]['qty'] = $qty;
                    $wishlist[$product_id]['subtotal'] = $subtotal;
                }

                // Calculate the total price
                $total_price += $subtotal;
            }

            // Update the wishlist in the cookie
            setcookie('wqpn_wishlist', json_encode($wishlist), time() + 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
            setcookie('wqpn_total_price', $total_price, time() + 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
            setcookie('wqpn_user_applied_for_submit_form', 'true', time() + 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);

            // Redirect after submission
            wp_redirect(home_url('/my-wishlist?wishlist_updated=true'));
            exit;
        } else {
            wp_die('No quantities submitted');
        }
    }

    private static function get_product_price($product_id)
    {
        // Replace this with the actual logic to get the product price
        // For example, using WooCommerce:
        $product = wc_get_product($product_id);
        return $product ? $product->get_price() : 0;
    }
    public static function handle_wqpn_submit_quote_price()
    {
        global $wpdb;

        // Collect POST data
        $user_id = get_current_user_id();
        $current_user = wp_get_current_user();
        $user_email = $current_user->user_email;
        $quote_price = $_POST['quoteprice'];
        $email = $user_email;
        $whatsapp = $_POST['whatsapp'];
        $telegram = $_POST['telegram'];

        // Retrieve current wishlist from the cookie
        $wishlist = isset($_COOKIE['wqpn_wishlist']) ? json_decode(stripslashes($_COOKIE['wqpn_wishlist']), true) : [];
        $wishlist_total = isset($_COOKIE['wqpn_total_price']) ? $_COOKIE['wqpn_total_price'] : 0;

        //db col id, user_id , status , wishlist price , quote price , products (all products will be here, can we use serialize or json to store data for this col? I am not sure what to use)
        //submitted_time, status , ip_address , unique_id, email, whatsapp, telegram,
        // Prepare the data to be saved in the transient
        $transient_data = [
            'wishlist_price' => $wishlist_total,
            'quote_price' => $quote_price,
            'products' => $wishlist,
            'submitted_time' => current_time('mysql'),
            'status' => 'submitted',
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'unique_id' => wp_generate_uuid4(),
            'email' => $email,
            'whatsapp' => $whatsapp,
            'telegram' => $telegram
        ];
        // Prepare the data to be saved in the database
        $data = [
           'user_id' => $user_id,
           'wishlist_price' => $wishlist_total,
           'quote_price' => $quote_price,
           'products' => json_encode($wishlist), // Save the products array as JSON
           'submitted_time' => current_time('mysql'),
           'status' => 'submitted',
           'ip_address' => $_SERVER['REMOTE_ADDR'],
           'unique_id' => wp_generate_uuid4(),
           'email' => $email,
           'whatsapp' => $whatsapp,
           'telegram' => $telegram
        ];

        $table_name = $wpdb->prefix . 'wqpn_wishlist';
        $wpdb->insert($table_name, $data);
        setcookie('wqpn_user_applied_for_submit_form', '', time() - 3600, '/'); // Expire the old cookie
        setcookie('wqpn_wishlist', '', time() - 3600, '/'); // Expire the old cookie
        setcookie('wqpn_total_price', '', time() - 3600, '/'); // Expire the old cookie
        setcookie('wqpn_user_offered_custom_price', 'true', time() + (30 * DAY_IN_SECONDS), '/');

        // Redirect back to the wishlist page or any desired page
        wp_redirect(home_url('/my-wishlist')); // Replace '/wishlist-page-url' with the actual URL
        exit;
    }
}
