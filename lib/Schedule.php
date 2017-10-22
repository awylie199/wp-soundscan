<?php
namespace AW\WSS;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('AW\WSS\Schedule')) {
    /**
     * Manages Soundscan Cron Jobs
     */
    class Schedule
    {
        public function __construct()
        {

        }
    }
} else {
    throw new \Exception('Woocommerce Soundscan Schedule class exists');
}
