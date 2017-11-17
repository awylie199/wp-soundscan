<?php

namespace AW\WSS;

if (!defined('ABSPATH')) {
    exit;
}

use AW\WSS\Settings;
use AW\WSS\PhysicalFormatter;
use AW\WSS\DigitalFormatter;
use AW\WSS\Submission;

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
            $this->options = get_option(Settings::NAME, '');

            if (is_string($this->options)) {
                $this->options = unserialize($this->options);
            }

            add_action('admin_notices', [$this, 'suggestIntegration']);
            // add_action('admin_notices', [$this, 'suggestActivation']);
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

            $ftpHost = $this->options[Settings::FTP_HOST] ?? '';
            $chainNo = $this->options[Settings::CHAIN_NO] ?? '';
            $musicCategory = $this->options[Settings::MUSIC_CATEGORY] ?? '';
            $albumCategory = $this->options[Settings::ALBUM_CATEGORY] ?? '';
            $trackCategory = $this->options[Settings::TRACK_CATEGORY] ?? '';
            $ean = $this->options[Settings::EAN_ATTRIBUTE] ?? '';
            $upc = $this->options[Settings::UPC_ATTRIBUTE] ?? '';

            if (!$ftpHost || !$chainNo || !$musicCategory || !$albumCategory ||
                !$trackCategory || (!$ean && !$upc)) {
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
         * Suggest Cron Activation for Automatic Submission of Soundscan Reports
         * @return void
         */
        public function suggestActivation()
        {
            if (isset($_GET['page']) && $_GET['page'] === 'wc-settings' &&
                isset($_GET['tab']) && $_GET['tab'] === 'integration') {
                return;
            }

            try {
                $digitalCron = $this->options[Settings::CRON_DIGITAL_SUBMISSIONS] ?? '';
                $physicalCron = $this->options[Settings::CRON_PHYSICAL_SUBMISSIONS] ?? '';

                if ($digitalCron !== 'yes' && $physicalCron !== 'yes') {
                    $url = self::getSettingsURL();
                    ?>
                    <div class="notice notice-warning">
                        <p>
                            <?php
                            printf(
                                __(
                                    'Weekly submission of Soundscan reports is currently %1$sdisabled%2$s. Update your %3$ssettings%4$s to automatically upload reports each week.',
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
                } else {
                    $date = new \DateTimeImmutable();

                    if ($digitalCron === 'yes') {
                        $digitalFormatter = new DigitalFormatter(
                            $date,
                            $date
                        );
                        $submitter = new Submission($digitalFormatter);

                        if (!$digitalFormatter->hasNecessaryOptions() ||
                            !$submitter->hasNecessaryOptions()) {
                            ?>
                            <div class="notice notice-error">
                                <p>
                                    <?php
                                    printf(
                                        __(
                                            'Weekly submission of digital Soundscan reports is currently %1$senabled%2$s. However, your settings are incomplete. Update your %3$ssettings%4$s to submit your reports automatically.',
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
                    if ($physicalCron === 'yes') {
                        $physicalFormatter = new PhysicalFormatter(
                            $date,
                            $date
                        );
                        $submitter = new Submission($physicalFormatter);

                        if (!$physicalFormatter->hasNecessaryOptions() ||
                            !$submitter->hasNecessaryOptions()) {
                            ?>
                            <div class="notice notice-error">
                                <p>
                                    <?php
                                    printf(
                                        __(
                                            'Weekly submission of physical Soundscan reports is currently %1$senabled%2$s. However, your settings are incomplete. Update your %3$ssettings%4$s to submit your reports automatically.',
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
                }
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
        }

        /**
         * Generate a URL to our specific settings screen.
         * @return string                   Generated URL.
         */
        public static function getSettingsURL(): string
        {
            return add_query_arg([
                'page'  =>  'wc-settings',
                'tab'   =>  'integration',
            ], admin_url('admin.php'));

        }
    }
} else {
    throw new \Exception('Woocommerce Soundscan Notifications class exists');
}