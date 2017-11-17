<?php
namespace AW\WSS;

if (!defined('ABSPATH')) {
    exit;
}

use AW\WSS\Schedule;
use AW\WSS\ISchedule;

if (!class_exists('\AW\WSS\DigitalSchedule')) {
    /**
     * Singleton for Managing Digital Soundscan Submission Schedules
     */
    class DigitalSchedule extends Schedule implements ISchedule
    {
        /**
         * WP Hook Name for Soundscan Report Submission Cron Checks
         * @var string
         */
        const SCHEDULE_ACTION = 'woocommerce-soundscan-digital-schedule';

        /**
         * Day to Submit the Schedule (e.g. Monday)
         * @var string
         */
        const SUBMIT_DAY = 'Monday';

        /**
         * Singleton Instance of Self
         * @var null|\AW\WSS\Schedule
         */
        private static $instance = null;

        /**
         * Gate to Prevent Multiple Digital Scheduled Events
         * @var bool
         */
        private $activated = false;

        /**
         * Get Singleton Instance of the Digital Scheduler
         * @return \AW\WSS\ISchedule         Singleton Instance of the Scheduler
         */
        public static function instance(): ISchedule
        {
            if (gettype(self::$instance) === 'NULL') {
                self::$instance = new self();
            }

            return self::$instance;
        }

        protected function __construct()
        {
            parent::__construct();
        }

        protected function __sleep()
        {
        }

        protected function __wakeup()
        {
        }

        protected function __serialize()
        {
        }

        /**
         * Activate Digital WP Scheduled Events for Submitting Digital Soundscan Reports
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

                if ($active === false) {
                    $this->logger->error(
                        __('Unable to Schedule Digital Soundscan Event', 'woocommerce-soundscan'),
                        $this->context
                    );
                } else {
                    $this->activated = true;
                }
            }
        }

        /**
         * Deactivate Digital WP Scheduled Events for Submitting Digital Soundscan Reports
         * {@inheritDoc}
         * @see \AW\WSS\Schedule::deactivate()
         */
        public function deactivate()
        {
            wp_clear_scheduled_hook(self::SCHEDULE_ACTION);
        }

        /**
         * Get Start Date of Schedule
         * @return \DateTimeImmutable
         */
        public function getStartDate(): \DateTimeImmutable
        {
            return (new \DateTimeImmutable('last Monday'))->setTime(0, 0, 0);
        }

        /**
         * Get End Date of Schedule
         * @return \DateTimeImmutable
         */
        public function getEndDate(): \DateTimeImmutable
        {
            return (new \DateTimeImmutable('last Sunday'))->setTime(23, 59, 59);
        }
    }
} else {
    throw new \Exception('Woocommerce Soundscan DigitalSchedule class exists');
}
