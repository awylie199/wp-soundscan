<?php
namespace AW\WSS;

if (!defined('ABSPATH')) {
    exit;
}

use AW\WSS\Submission;
use AW\WSS\PhysicalFormatter;
use AW\WSS\DigitalFormatter;

if (!class_exists('\AW\WSS\Schedule')) {
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
        protected $context = [
            'source'    =>  'soundscan'
        ];

        /**
         * Short Name of Called Schedule Class
         * @var string
         */
        private $className = '';

        protected function __construct()
        {
            $this->logger = wc_get_logger();
            $this->className = (new \ReflectionClass(static::class))->getShortName();
        }

        /**
         * Schedule Submission of Soundscan Report
         * @return void
         */
        public function scheduleSubmission()
        {
            $this->logger->debug('Executing scheduled submission');
            try {
                if ($this->isTimeToSubmit()) {
                    $formatter = $this->createFormatter();

                    if ($formatter->hasNecessaryOptions()) {
                        $submitter = new Submission($formatter);

                        if ($submitter->hasNecessaryOptions()) {
                            if (!$submitter->isAlreadyUploaded()) {
                                $submitter->upload();
                            } else {
                                $this->logger->info(
                                    __(
                                        'Soundscan Schedule: Submitter has already made a successful upload for this period.',
                                        'woocommerce-soundscan'
                                    ),
                                    $this->context
                                );
                            }
                        } else {
                            throw new \Exception('Submitter lacks necessary setting details to upload.');
                        }
                    } else {
                        throw new \Exception('Formatter lacks necessary setting details to upload.');
                    }
                }
            } catch (\Exception $err) {
                $this->logger->error(
                    sprintf(
                        __(
                            'Error in Soundscan Schedule: %s',
                            'woocommerce-soundscan'
                            ),
                        $err->getMessage()
                        ),
                    $this->context
                );
            }
        }

        /**
         * Is It Time to Submit the Report?
         *
         * Physical reports are from Tue to Mon, and submitted before 1PM Tue EST.
         * Digital reports are from Mon to Sun, and submitted before 1PM Mon EST.
         *
         * @throws \Exception if invalid schedule type
         * @return bool                 True if Time to Submit Report
         */
        private function isTimeToSubmit(): bool
        {
            $now = new \DateTimeImmutable('now');
            $nowEST = new \DateTimeImmutable(
                'now',
                new \DateTimeZone('America/New_York')
            );
            $day = $now->format('l');
            $dayEST = $nowEST->format('l');
            $hourEST = $nowEST->format('G');

            return ($day === static::SUBMIT_DAY &&
                $dayEST === static::SUBMIT_DAY && $hourEST < 13);
        }

        /**
         * Create the Nielsen Soundscan Report for Submission
         * @throws \Exception if invalid schedule type
         * @return string                       Formatted Soundscan Report
         */
        private function createFormatter(): IFormatter
        {
            switch ($this->className) {
            case 'PhysicalSchedule':
                return new PhysicalFormatter(
                    $this->getStartDate(),
                    $this->getEndDate()
                );
                break;
            case 'DigitalSchedule':
                return new DigitalFormatter(
                    $this->getStartDate(),
                    $this->getEndDate()
                );
                break;
            default:
                throw new \Exception('Invalid WooCommerce Soundscan schedule class');
            }
        }
    }
} else {
    throw new \Exception('Woocommerce Soundscan Schedule class exists');
}
