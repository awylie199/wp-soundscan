<?php

namespace AW\WSS;

if (!defined('ABSPATH')) {
    exit;
}

use AW\WSS\Settings;

if (!class_exists('AW\WSS\Notifications')) {
    /**
     * Handle Admin Notifications in WooCommerce Soundscan
     */
    class Notifications
    {
        /**
         * WooCommerce Logger
         * @var null|\WC_Logger
         */
        private $logger = null;

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
            add_action('admin_notices', [$this, 'suggestIntegration']);
        }

        /**
         * Conditionally Display a Admin Notice to Suggest FTP Integration
         * @return void
         */
        public function suggestIntegration()
        {
            if (isset($_GET['page']) && $_GET['page'] === 'wc-settings' &&
                isset($_GET['tab']) && $_GET['tab'] === 'integration') {
                return;
            }

            $options = get_option(Settings::NAME, '');
            $ftpHost = '';
            $chainNo = '';

            try {
                if (is_string($options)) {
                    $options = unserialize($options);
                }

                $ftpHost = $options[Settings::FTP_HOST] ?? '';
                $chainNo = $options[Settings::CHAIN_NO] ?? '';
                $category = $options[Settings::MUSIC_CATEGORY] ?? '';
                $ean = $options[Settings::EAN_ATTRIBUTE] ?? '';
                $upc = $options[Settings::UPC_ATTRIBUTE] ?? '';
            } catch (\Exception $err) {
                $this->logger->error(
                    sprintf(
                        __(
                            'Error in Soundscan Notifications: %1$s',
                            'woocommerce-soundscan'
                        ),
                        $err->getMessage()
                    ),
                    $this->context
                );
            }

            if (!$ftpHost || !$chainNo || $category || (!$ean || !$upc)) {
                $url = self::getSettingsURL();

                ?>
                <div class="updated fade">
                    <p>
                        <?php
                        printf(
                            __(
                                '%1$s WooCommerce Soundscan is almost ready.%2$s Please add the %3$sdetails needed%4$s to start submitting record sales.',
                                'woocommerce-soundscan'
                            ),
                            '<strong>',
                            '</strong>',
                            '<a href="' . esc_url($url) . '">',
                            '</a>'
                        );
                        ?>
                    </p>
                </div>
                <?php
            }
        }

        /**
         * Generate a URL to our specific settings screen.
         * @return string                   Generated URL.
         */
        public static function getSettingsURL(): string
        {
            $url = admin_url('admin.php');
            $url = add_query_arg('page', 'wc-settings', $url);
            $url = add_query_arg('tab', 'integration', $url);

            return $url;
        }
    }
} else {
    throw new \Exception('Woocommerce Soundscan Notifications class exists');
}