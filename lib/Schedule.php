<?php
namespace AW\WSS;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('AW\WSS\Schedule')) {
    /**
     * Manages Soundscan Cron Jobs
     */
    abstract class Schedule
    {
        /**
         * Frequency of Scheduled Submission WP Cron Checks
         * @var string
         */
        const FREQUENCY = 'hourly';

        /**
         * WooCommerce Logger
         * @var null|\WC_Logger
         */
        protected $logger = null;

        /**
         * WooCommerce Logger Context for Soundscan
         * @var string[]
         */
        private $context = [
            'source'    =>  'soundscan'
        ];

        public function __construct()
        {
            $this->logger = wc_get_logger();
        }
    }
} else {
    throw new \Exception('Woocommerce Soundscan Schedule class exists');
}
