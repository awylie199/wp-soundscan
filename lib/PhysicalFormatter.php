<?php
namespace AW\WSS;

if (!defined('ABSPATH')) {
    exit;
}

use AW\WSS\IFormatter;
use AW\WSS\Formatter;
use AW\WSS\Data;
use AW\WSS\Settings;
use AW\WSS\Notifications;

if (!class_exists('AW\WSS\PhysicalFormatter')) {
    /**
     * Manages Physical Formatting of Nielsen Soundscan Report
     */
    class PhysicalFormatter extends Formatter implements IFormatter
    {
        /**
         * Type of Data to Manage (Physical|Digital)
         * @var string
         */
        const FORMATTER_TYPE = 'physical';

        /**
         * Record Number (at Start of Each Line Record)
         * @var string
         */
        const RECORD_NO = 'M3';

        /**
         * Delimiter for Trailer Components
         * @var string
         */
        const TRAILER_DELIMETER = ' ';

        /**
         * Data Manager for Handling Soundscan Records
         * @var null|\AW\WSS\Data
         */
        protected $data = null;

        /**
         * Start Date of the Report
         * @var null|\DateTimeImmutable
         */
        protected $startDate = null;

        /**
         * End Date of the Report
         * @var null|\DateTimeImmutable
         */
        protected $endDate = null;

        /**
         * Assigned Account Number (XXXXX)
         * @var string
         */
        public $accountNo = '';

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

            if ($start > $end) {
                $tempStart = $start;
                $start = $end;
                $end = $tempStart;
            }

            $this->startDate = $start;
            $this->endDate = $end;
            $this->accountNo = $this->options[Settings::ACCOUNT_NO_PHYSICAL];
        }

        /**
         * Get Formatted Sales Results for Soundscan Submission
         * @return string[]             Formatted Rows of Submission Data
         */
        public function getFormattedResults(): array
        {
            $rows = $this->data->getResults($this->startDate, $this->endDate);

            try {
                $this->addHeader();

                foreach ($rows as $order) {
                    $items = $order->get_items();
                    $zip = $this->getZip($order);

                    foreach ($items as $item) {
                        $product = $order->get_product_from_item($item);
                        $id = $product->get_id();

                        if ($this->isMusic($id)) {
                            $price = $order->get_line_total($item, false, false);
                            $ean = $this->getEAN($product);

                            if ($this->isValid($item, $price, $ean, $zip)) {
                                for ($i = 0; $i < $item['qty']; $i++) {
                                    $this->addRecord($ean, $zip, $order->get_status());
                                }
                            }
                        }
                    }
                }

                $this->addTrailer();
            } catch (\Exception $err) {
                $this->logger->error(
                    sprintf(
                        __(
                            'Error in Soundscan Physical Report Generation: %1$s',
                            'woocommerce-soundscan'
                        ),
                        $err->getMessage()
                    ),
                    $this->context
                );
            } finally {
                return $this->submission;
            }
        }

        /**
         * True if Formatter Has Necessary Details to Work
         * @return bool                   True if setup correctly
         */
        public function hasNecessaryOptions(): bool
        {
            return (
                !empty($this->musicCategory) && !empty($this->idAttribute) &&
                !empty($this->accountNo) && !empty($this->chainNo)
            );
        }

        /**
         * Add a Sales Detail Record for Each Record Purchase to the Submission
         *
         * Record Number (M3)
         * EAN Number of Selection if bought as an Album
         * Buyer Zip Code
         * Trans Code (S/R) Sales/Return
         *
         * @param string $ean        Record EAN
         * @param string $zip        Order Zip Code
         * @return void
         */
        protected function addRecord(
            string $zip,
            string $ean,
            string $status
        ) {
            $complete = $status === 'completed';

            $detailRecord = self::RECORD_NO;
            $detailRecord .= $ean;
            $detailRecord .= $zip;
            $detailRecord .= ($complete ? 'S' : 'R');

            if ($complete) {
                $this->sales++;
            } else {
                $this->refunds++;
            }

            $this->submission[] = trim($detailRecord);
        }

        /**
         * Is the Record Valid for Including in the Report?
         * @param \WC_Order_Item_Product     $item      Current Product Item
         * @param float     $record                 Current Product Sale Price
         * @param string    $ean                    Current Product EAN Number
         * @return bool                             True if Valid
         */
        protected function isValid(
            \WC_Order_Item_Product $item,
            float $price,
            string $ean,
            string $zip
        ): bool {
            $valid = true;

            if (!$this->isExpensiveEnough($price)) {
                $this->invalids[] = [
                    'record'    =>  $item,
                    'reason'    =>  __('The item is not expensive enough (must cost at least $4.99)', 'woocommerce-soundscan')
                ];
                return $valid;
            }

            // EAN Required if Album Sale
            if (!$this->isValidEAN($ean)) {
                $this->invalids[] = [
                    'record'    =>  $item,
                    'reason'    =>  printf(
                        __(
                            'The item does not have a valid EAN or UPC number. Set the WooCommerce attribute in the %1$ssettings%2$s.',
                            'woocommerce-soundscan'
                        ),
                        '<a href="' . esc_url(Notifications::getSettingsURL()) . '">',
                        '</a>'
                    )
                ];
                return $valid;
            }

            if (!$this->isValidZIP($zip)) {
                $this->invalids[] = [
                    'record'    =>  $item,
                    'reason'    =>  __(
                        'The customer must have a valid U.S. zipcode for their billing or delivery addresses.',
                        'woocommerce-soundscan'
                    )
                ];
                return $valid;
            }

            return $valid;
        }
    }
} else {
    throw new \Exception('Woocommerce Soundscan PhysicalFormatter class exists');
}
