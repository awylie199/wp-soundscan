<?php

/*
Plugin Name: WooCommerce Soundscan Plugin
Plugin URI: https://www.alexwylie.com/wp/woocommerce-soundscan
Description: Upload music sales from WooCommerce to Nielsen SoundScan
Version: 1.0.0
Author: Alex Wylie <awylie199@gmail.com>
Author URI: https://www.alexwylie.com
License: GPL 3.0
License URI: https://www.gnu.org/licenses/gpl-3.0.en.html
Text Domain: ws-checker
Domain Path: /languages
WC requires at least: 3.0.0
WC tested up to: 3.2.1
---

Copyright 2017 Alex Wylie.

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see http://www.gnu.org/licenses.
*/

if (! defined('ABSPATH')) {
    exit;
}

if (version_compare(PHP_VERSION, '7.0.0') < 0) {
    add_action('admin_notices', function () {
        ?>
        <div class="notice notice-error">
        	<p>
            	<?php __('WooCommerce requires PHP 7.0 to work.', 'woocommerce-soundscan'); ?>
        	</p>
        </div>
        <?php
    });
}

use AW\WSS\Setup;
use AW\WSS\Settings;

try {
    add_action('plugins_loaded', function () {
        $activePlugins = apply_filters('active_plugins', get_option('active_plugins'));

        if (in_array('woocommerce/woocommerce.php', $activePlugins)) {
            define('WC_SOUNDSCAN_DIR', dirname(__FILE__));
            require_once WC_SOUNDSCAN_DIR . DIRECTORY_SEPARATOR . 'vendor/autoload.php';

            Setup::getInstance();
        } else {
            add_action('admin_notices', function () {
                ?>
                <div class="notice notice-warning">
                    <p>
                    	<?php _e('WooCommerce Soundscan needs WooCommerce installed to work.', 'woocommerce-soundscan'); ?>
	                </p>
                </div>
                <?php
            });
        }

        // register_activation_hook(__FILE__, ['AW\WSS\Schedule', 'set']);
        register_deactivation_hook(__FILE__, function () {
            delete_transient(Settings::RESULTS_TRANSIENT);
        });
    });
} catch (\Exception $err) {
    throw new \Exception("WooCommerce Soundscan plugin failed to initialize: {$err->getMessage()}");
}
