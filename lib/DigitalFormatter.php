<?php
namespace AW\WSS;

if (!defined('ABSPATH')) {
    exit;
}

use AW\WSS\Formatter;
use AW\WSS\IFormatter;

if (!class_exists('AW\WSS\DigitalFormatter')) {
    /**
     * Manages Digital Formatting of Nielsen Soundscan Report
     */
    class DigitalFormatter extends Formatter implements IFormatter
    {
        /**
         * Type of Data to Manage (Physical|Digital)
         * @var string
         */
        const FORMATTER_TYPE = 'digital';

        /**
         * Record Number (at Start of Each Line Record)
         * @var string
         */
        const RECORD_NO = 'M3';

        /**
         * Delimiter for Trailer Components
         * @var string
         */
        const TRAILER_DELIMETER = '|';

        /**
         * Data Manager for Handling Soundscan Records
         * @var null|\AW\WSS\Data
         */
        private $data = null;

        /**
         * Assigned Account Number (XXXXX)
         * @var string
         */
        public $accountNo = '';

        /**
         * Product ISRC WC Attribute Name
         * @var string
         */
        public $isrcAttribute = '';

        /**
         * @param \DateTimeImmutable $start         Start Date of the Report
         * @param \DateTimeImmutable $end           End Date of the Report
         */
        public function __construct(
            \DateTimeImmutable $start,
            \DateTimeImmutable $end
        ) {
            parent::__construct();
            $this->data = new Data(self::FORMATTER_TYPE);
            $this->startDate = $start;
            $this->endDate = $end;
            $this->accountNo = $this->options[Settings::ACCOUNT_NO_DIGITAL] ?? '';
            $this->isrcAttribute = $this->options[Settings::ISRC_ATTRIBUTE] ?? '';
        }

        /**
         * Get Formatted Sales Results for Soundscan Submission
         * @param mixed[] $rows         WooCommerce Data to Format into Report1
         * @return string[]             Formatted Rows of Submission Data
         */
        public function getFormattedResults(): array
        {
            $results = [];

            try {
                $this->addHeader();
                foreach ($rows as $record) {
                    $ean = $this->getEANNumber($record);
                    $price = 0;

                    if ($this->isValid($price, $ean)) {
                        $this->addRecord($record, $ean);
                    }
                }
                $this->addTrailer();
            } catch (\Exception $err) {
                $this->logger->error(
                    sprintf(
                        __(
                            'Error in Soundscan Digital Report Generation: %1$s',
                            'woocommerce-soundscan'
                        ),
                        $err->getMessage()
                    ),
                    $this->context
                );
            } finally {
                return $results;
            }
        }

        /**
         * True if Formatter Has Necessary Details to Work
         * @return bool                   True if setup correctly
         */
        public function hasNecessaryOptions(): bool
        {
            return (
                !empty($this->isrcAttribute) && !empty($this->musicCategory) &&
                !empty($this->idAttribute)) && !empty($this->accountNo) &&
                !empty($this->chainNo);
        }

        /**
         * Add a Sales Detail Record for Each Record Purchase to the Submission
         *
         * Record Number (D3)
         * UPC Number of Selection
         * Buyer Zip Code
         * Trans Code (S/R) Sales/Return
         * Item No. in this transaction - Sequential Number of Item Within Order
         * ISRC Code - Always Blank
         * Price (no decimals)
         * Type of Sale (Track =S, Album=A)
         * Strata (PC = P or Mobile = M)
         *
         * @param array  $record     WooCommerce Record Order Meta Details
         * @param string $ean        Record EAN
         * @param float $price       Record Sale Line Item (excl. Tax) Price
         * @return void
         */
        protected function addRecord(array $record, string $ean, float $price)
        {
            $isComplete = $this->currentOrder->status === 'completed';

            $detailRecord = self::RECORD_NO . self::TRAILER_DELIMETER;
            $detailRecord .= $ean . self::TRAILER_DELIMETER;
            $detailRecord .= $this->getCleanZipCode() . self::TRAILER_DELIMETER;
            $detailRecord .= ($isComplete ? 'S' : 'R') . self::TRAILER_DELIMETER;
            $detailRecord .= $itemCount . self::TRAILER_DELIMETER;
            $detailRecord .= self::TRAILER_DELIMETER; // Blank, no ISRC for Albums
            $detailRecord .= $this->formatPrice($price) . self::TRAILER_DELIMETER;
            $detailRecord .= 'A' . self::TRAILER_DELIMETER; // Always Albums
            $detailRecord .= 'P';

            if ($isComplete) {
                $this->sales++;
            } else {
                $this->refunds++;
            }

            $this->submission .= trim($detailRecord) . PHP_EOL;
        }

        /**
         * Trim Prices, Return a String and Remove Decimals
         * @param  float      $price    Price, e.g. 14.99
         * @return string               Price without decimals and stringified
         */
        private function formatPrice(float $price): string
        {
            return str_pad(
                str_replace('.', '', (string)$price),
                4,
                '0'
                ,
                STR_PAD_LEFT
            );
        }
    }
} else {
    throw new \Exception('Woocommerce Soundscan DigitalFormatter class exists');
}
