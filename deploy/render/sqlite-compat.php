<?php
/**
 * Plugin Name: Synergy – SQLite compatibility (Render deployment only)
 * Description: WooCommerce's checkout stock hold uses a MySQL-only query (INSERT … ON DUPLICATE KEY
 * UPDATE with a sub-select) that the SQLite driver cannot translate. Skip the 60-minute hold;
 * stock is still reduced when the order is placed.
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'woocommerce_hold_stock_for_checkout', '__return_false' );
