<?php
namespace AW\WSS;

if (!defined('ABSPATH')) {
    exit;
}

use AW\WSS\Schedule;
use AW\WSS\ISchedule;

if (!class_exists('AW\WSS\PhysicalSchedule')) {
    class PhysicalSchedule extends Schedule implements ISchedule
    {
        /**
         * WP Hook Name for Soundscan Report Submission Cron Checks
         * @var string
         */
        const SCHEDULE_ACTION = '';

        /**
         * Gate to Prevent Multiple Physical Scheduled Events
         * @var bool
         */
        private $activated = false;

        /**
         * Activate Physical WP Scheduled Events for Submitting Digital Soundscan Reports
         * {@inheritDoc}
         * @see \AW\WSS\Schedule::activate()
         */
        public function activate()
        {
            if (!$this->activated) {
                $active = wp_schedule_event(
                    time(),
                    parent::FREQUENCY,
                    self::SCHEDULE_ACTION
                );

                if ($active !== true) {
                    $this->logger->error(
                        __('Unable to Schedule Physical Soundscan Event', 'woocommerce-soundscan'),
                        $this->context
                    );
                } else {
                    $this->activated = true;
                }
            }
        }

        /**
         * Deactivate Physical WP Scheduled Events for Submitting Digital Soundscan Reports
         * {@inheritDoc}
         * @see \AW\WSS\Schedule::deactivate()
         */
        public function deactivate()
        {
            wp_clear_scheduled_hook(self::SCHEDULE_ACTION);
        }
    }
} else {
    throw new \Exception('Woocommerce Soundscan PhysicalSchedule class exists');
}
