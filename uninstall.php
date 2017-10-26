<?php

namespace AW\WSS;

if (!defined('ABSPATH') || !defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

use AW\WSS\Settings;

if (current_user_can('manage_options')) {
    // Note, the Schedule Submitted 'SUBMITTED_TRANSIENT' values are NOT deleted.
    // If the plugin is reinstalled quickly, it may lead to multiple submissions.
    // The transient expires relatively quickly after 1 Day.
    delete_option(Settings::NAME);
    delete_transient(Settings::RESULTS_TRANSIENT);
}
