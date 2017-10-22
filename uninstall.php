<?php

namespace AW\WSS;

if (!defined('ABSPATH') || !defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

use AW\WSS\Settings;

if (current_user_can('manage_options')) {
    delete_option(Settings::FTP_HOST);
    delete_option(Settings::CHAIN_NO);
    delete_option(Settings::LOGIN_PHYSICAL_NAME);
    delete_option(Settings::LOGIN_DIGITAL_NAME);
    delete_option(Settings::LOGIN_PHYSICAL_PWD);
    delete_option(Settings::LOGIN_DIGITAL_PWD);
    delete_option(Settings::ACCOUNT_NO_PHYSICAL);
    delete_option(Settings::ACCOUNT_NO_DIGITAL);
}
