<?php
namespace AW\WSS;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('AW\WSS\IFormatter')) {
    /**
     * Defines Formatting Behaviour for Nielsen Soundscan
     */
    interface IFormatter
    {
        /**
         * Get Formatted Reports for Soundscan Submission
         * @return mixed[]                Rows of Formatted Results
         */
        public function getFormattedResults(): array;

        /**
         * True if Formatter Has Necessary Details to Work
         * @return bool                   True if setup correctly
         */
        public function hasNecessaryOptions(): bool;
    }
} else {
    throw new \Exception('Woocommerce Soundscan IFormatter interface exists');
}
