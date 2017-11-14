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
            $this->accountNo = $this->options[Settings::ACCOUNT_NO_PHYSICAL] ?? '';
            $this->minTrackPrice = round(
                $this->options[Settings::PHYSICAL_TRACK_MIN_PRICE] ?? 0,
                2
            );
            $this->minAlbumPrice = round(
                $this->options[Settings::PHYSICAL_ALBUM_MIN_PRICE] ?? 0,
                2
            );
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
                    $status = $order->get_status();

                    foreach ($items as $item) {
                        $product = $order->get_product_from_item($item);
                        $id = $product->get_id();

                        if ($this->isMusic($id) && !$this->isDigital($product)) {
                            $price = $order->get_line_total($item, false, false);
                            $ean = $this->getEAN($product);
                            $type = $this->getType($id);
                            $valid = $this->isPhysicalValid(
                                $item,
                                $price,
                                $type,
                                $ean,
                                $zip
                            );

                            if ($valid) {
                                for ($i = 0; $i < $item['qty']; $i++) {
                                    $this->addRecord(
                                        $zip,
                                        $ean,
                                        $status
                                    );
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
                set_transient(
                    Settings::RESULTS_TRANSIENT,
                    $this->submission,
                    DAY_IN_SECONDS
                );
                return $this->submission;
            }
        }

        /**
         * True if Formatter Has Necessary Details to Work
         * @return bool                   True if setup correctly
         */
        public function hasNecessaryOptions(): bool
        {
            return !(
                empty($this->musicCategory) || empty($this->accountNo) ||
                empty($this->chainNo)
            );
        }

        /**
         * Add a Sales Detail Record for Each Record Purchase to the Submission
         *
         * Record Number (M3) (2)
         * EAN / UPC Number (13)
         * Buyer Zip Code (5)
         * Trans Code (S/R) Sales/Return (1)
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
            $complete = ($status === 'completed');

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
         * @param \WC_Order_Item_Product  $item     Current Product Item
         * @param float     $price                  Current Product Sale Price
         * @param string    $type                   Current Product Type - Album / Track
         * @param string    $ean                    Current Product EAN Number
         * @param string    $zip                    Current Product Custom ZIP
         * @return bool                             True if Valid
         */
        protected function isPhysicalValid(
            \WC_Order_Item_Product $item,
            float $price,
            string $type,
            string $ean,
            string $zip
        ): bool {
            // EAN Required if Album Sale
            if (!$this->isValidEAN($ean)) {
                $this->invalids[] = [
                    'record'    =>  $item,
                    'reason'    =>  sprintf(
                        __(
                            'The item does not have a valid EAN or UPC number. An EAN number is 12 digits long, and a UPC number is 13 digits. Check the value for the WooCommerce attribute set in the %1$ssettings%2$s.',
                            'woocommerce-soundscan'
                        ),
                        '<a href="' . esc_url(Notifications::getSettingsURL()) . '">',
                        '</a>'
                    )
                ];
                return false;
            }

            return parent::isValid($item, $zip, $price, $type);
        }
    }
} else {
    throw new \Exception('Woocommerce Soundscan PhysicalFormatter class exists');
}
