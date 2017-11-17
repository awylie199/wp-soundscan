<?php
namespace AW\WSS;

if (!defined('ABSPATH')) {
    exit;
}

use AW\WSS\Schedule;
use AW\WSS\ISchedule;

if (!class_exists('\AW\WSS\PhysicalSchedule')) {
    /**
     * Singleton for Managing Physical Soundscan Submission Schedules
     */
    class PhysicalSchedule extends Schedule implements ISchedule
    {
        /**
         * WP Hook Name for Soundscan Report Submission Cron Checks
         * @var string
         */
        const SCHEDULE_ACTION = 'woocommerce-soundscan-physical-schedule';

        /**
         * Day to Submit the Schedule (e.g. Monday)
         * @var string
         */
        const SUBMIT_DAY = 'Tuesday';

        /**
         * Singleton Instance of Self
         * @var null|\AW\WSS\Schedule
         */
        private static $instance = null;

        /**
         * Gate to Prevent Multiple Physical Scheduled Events
         * @var bool
         */
        private $activated = false;

        /**
         * Get Singleton Instance of the Physical Scheduler
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
         * Activate Physical WP Scheduled Events for Submitting Digital Soundscan Reports
         * {@inheritDoc}
         * @see \AW\WSS\Schedule::activate()
         * @return void
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
         * @return void
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
            return (new \DateTimeImmutable('last Tuesday'))->setTime(0, 0, 0);
        }

        /**
         * Get End Date of Schedule
         * @return \DateTimeImmutable
         */
        public function getEndDate(): \DateTimeImmutable
        {
            return (new \DateTimeImmutable('last Monday'))->setTime(23, 59, 59);
        }
    }
} else {
    throw new \Exception('Woocommerce Soundscan PhysicalSchedule class exists');
}
