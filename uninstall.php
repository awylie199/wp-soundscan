<?php

namespace AW\WSS;

if (!defined('ABSPATH') || !defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

use AW\WSS\Settings;

if (current_user_can('manage_options')) {
    delete_option(Settings::NAME);
    delete_transient(Settings::RESULTS_TRANSIENT);
}
