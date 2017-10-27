<?php
namespace AW\WSS;

if (!defined('ABSPATH')) {
    exit;
}

use AW\WSS\Settings;
use AW\WSS\Notifications;
use AW\WSS\Menu;

if (!class_exists('AW\WSS\Setup')) {
    /**
     * Setup WooCommerce Soundscan WordPress Plugin
     */
    class Setup
    {
        /**
         * Woocommerce Soundscap Setup Singleton
         * @var null|AW\WSS\Setup
         */
        private static $instance = null;

        protected function __construct()
        {
            add_action('init', function () {
                load_plugin_textdomain(
                    'woocommerce-soundscan',
                    false,
                    WC_SOUNDSCAN_DIR . '/languages'
                );
            });
            add_filter('woocommerce_integrations', [$this, 'addIntegration']);
            add_filter('admin_footer_text', [$this, 'pluginFooter']);
            new Settings();
            new Notifications();
            new Menu();
        }

        /**
         * Return Singleton Setup Instance
         * @return AW\WSS\Setup
         */
        public static function getInstance()
        {
            if (is_null(self::$instance)) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        /**
         * Add WooCommerce Soundscan Integration
         * @param []string $integations  Existing WooCommerce Integrations
         * @return []string         Integration Classes w/ WooCommerce Soundscan
         */
        public function addIntegration(array $integrations): array
        {
            $integrations[] = Settings::class;
            return $integrations;
        }

        /**
         * Display WooCommerce Soundscan Footer Message
         * @return void
         */
        public function pluginFooter()
        {
            if (is_admin() && isset($_GET['page']) && $_GET['page'] === 'soundscan') {
                printf(
                    __(
                        '%3$sDeveloped by %1$sFuzzyBears%2$s. Thankyou for using this plugin. Please %5$scontact us%6$s if you notice a bug or could use some help.%4$s',
                        'woocommerce-soundscan'
                    ),
                    '<a href="https://fuzzybears.co.uk" target="_blank" rel="noopener noreferrer">',
                    '</a>',
                    '<i>',
                    '</i>',
                    '<a href="https://fuzzybears.co.uk/contact" target="_blank" rel="noopener noreferrer">',
                    '</a>'
                );
            }
        }

        protected function __sleep()
        {
        }

        protected function __serialize()
        {
        }

        protected function __wakeup()
        {
        }
    }
} else {
    throw new \Exception('Woocommerce Soundscan Setup exists.');
}
