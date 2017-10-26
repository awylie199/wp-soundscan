<?php
namespace AW\WSS;

if (!defined('ABSPATH')) {
    exit;
}

if (!interface_exists('\AW\WSS\ISchedule')) {
    /**
     * Defines Behaviour for Soundscan Schedulers
     */
    interface ISchedule
    {
        /**
         * Activate WP Schedule Hook
         * @return void
         */
        public function activate();

        /**
         * Deactivate WP Scheduled Hook
         * @return void
         */
        public function deactivate();

        /**
         * Get Singleton Instance of the Schedule
         * @return \AW\WSS\ISchedule            Singleton Instance of the Scheduler
         */
        public static function instance(): ISchedule;

        /**
         * Get Start Date of the Scheduled Report
         * @return \DateTimeImmutable
         */
        public function getStartDate(): \DateTimeImmutable;

        /**
         * Get End Date of the Scheduled Report
         * @return \DateTimeImmutable
         */
        public function getEndDate(): \DateTimeImmutable;
    }
} else {
    throw new \Exception('Woocommerce Soundscan ISchedule interface exists');
}

