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
         * Data Manager for Handling Soundscan Records
         * @var null|\AW\WSS\Data
         */
        private $data = null;

        /**
         * Minimum Qualifying Digital Track (Single) Price
         * @var float
         */
        private $minTrackPrice = 0.0;

        /**
         * Minimum Qualifying Digital Album Price
         * @var float
         */
        private $minAlbumPrice = 0.0;

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
            $this->minTrackPrice = round(
                $this->options[Settings::DIGITAL_TRACK_MIN_PRICE] ?? 0,
                2
            );
            $this->minAlbumPrice = round(
                $this->options[Settings::DIGITAL_ALBUM_MIN_PRICE] ?? 0,
                2
            );
        }

        /**
         * Get Formatted Sales Results for Soundscan Submission
         * @param mixed[] $rows         WooCommerce Data to Format into Report1
         * @return string[]             Formatted Rows of Submission Data
         */
        public function getFormattedResults(): array
        {
            $rows = $this->data->getResults($this->startDate, $this->endDate);

            try {
                $this->addHeader();

                foreach ($rows as $order) {
                    $itemCount = 1;
                    $items = $order->get_items();
                    $zip = $this->getZip($order);

                    foreach ($items as $item) {
                        $product = $order->get_product_from_item($item);
                        $id = $product->get_id();

                        if ($this->isMusic($id) && $this->isDigital($product)) {
                            $price = $order->get_line_total($item, false, false);
                            $ean = $this->getEAN($product);
                            $type = $this->getType($id);
                            $isrc = $this->getISRC($product);
                            $valid = $this->isDigitalValid(
                                $item,
                                $price,
                                $type,
                                $ean,
                                $zip,
                                $isrc
                            );

                            if ($valid) {
                                for ($i = 0; $i < $item['qty']; $i++) {
                                    $this->addRecord(
                                        $ean,
                                        $zip,
                                        $order->get_status(),
                                        $price,
                                        $itemCount,
                                        $type,
                                        $isrc
                                    );
                                    $itemCount++;
                                }
                            }
                        }

                        $itemCount++;
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
            return !(
                empty($this->isrcAttribute) || empty($this->musicCategory) ||
                empty($this->idAttribute) || empty($this->accountNo) ||
                empty($this->chainNo)
            );
        }

        /**
         * Add a Sales Detail Record for Each Record Purchase to the Submission
         *
         * Record Number (D3)
         * UPC Number of Selection - Required for Albums
         * Buyer Zip Code
         * Trans Code (S/R) Sales/Return
         * Item No. in this transaction - Sequential Number of Item Within Order
         * ISRC Code - Required for Singles
         * Formatted Price (no decimals)
         * Type of Sale ('album' or 'track')
         * Strata (PC = P or Mobile = M)
         *
         * @param string $ean=''           Product EAN / UPC Number
         * @param string $zip              Customer ZIP Code
         * @param string $status           WooCommerce Order Status
         * @param string $price            Line Item Price
         * @param int    $itemCount        Sequential Order of Item Within Order
         * @param string $type             Type of Item (Album / Track)
         * @return void
         */
        protected function addRecord(
            string $ean = '',
            string $zip,
            string $status,
            string $price,
            int $itemCount,
            string $type,
            string $isrc
        ) {
            $complete = ($status === 'completed');

            $detailRecord = self::RECORD_NO . self::TRAILER_DELIMETER;
            $detailRecord .= ($type === 'album' ? $ean : '') . self::TRAILER_DELIMETER;
            $detailRecord .= $zip . self::TRAILER_DELIMETER;
            $detailRecord .= ($complete ? 'S' : 'R') . self::TRAILER_DELIMETER;
            $detailRecord .= $itemCount . self::TRAILER_DELIMETER;
            $detailRecord .= ($type === 'track' ? $isrc : '') . self::TRAILER_DELIMETER;
            $detailRecord .= $this->formatPrice($price) . self::TRAILER_DELIMETER;
            $detailRecord .= ($type === 'album' ? 'A' : 'S') . self::TRAILER_DELIMETER;
            $detailRecord .= 'P';

            if ($complete) {
                $this->sales++;
            } else {
                $this->refunds++;
            }

            $this->submission[] = trim($detailRecord);
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

        /**
         * Get ISRC (or Internal Identifier) For Product
         * @param \WC_Product_Simple $item  Line Item Sold as Part of Order
         * @return string                   ISRC (or Internal ID) for Product
         */
        private function getISRC(\WC_Product_Simple $item): string
        {
            $isrc = $item->get_attribute($this->isrcAttribute);

            // Leave empty string if the product doesn't have a value for this
            // attribute. Products without an ID are ignored.
            if ($isrc) {
                $isrc = str_pad(preg_replace(
                    "/[^A-Za-z0-9]/",
                    '',
                    $isrc
                ), 12, '0', STR_PAD_LEFT);
            }

            return $isrc;
        }

        /**
         * Is the Record Valid for Including in the Report?
         * @param \WC_Order_Item_Product  $item     Current Product Item
         * @param float     $price                  Current Product Sale Price
         * @param string    $type                   Current Product Album or Track
         * @param string    $ean                    Current Product EAN Number
         * @param string    $zip                    Current Product Custom ZIP
         * @param string    $isrc                   Current Product ISRC / ID
         * @return bool                             True if Valid
         */
        protected function isDigitalValid(
            \WC_Order_Item_Product $item,
            float $price,
            string $type,
            string $ean,
            string $zip,
            string $isrc
        ): bool {
            if ($type === '') {
                $this->invalids[] = [
                    'record'    =>  $item,
                    'reason'    =>  printf(
                        __(
                            'The item does not have an assigned type - album or track. Set the WooCommerce attribute in the %1$ssettings%2$s.',
                            'woocommerce-soundscan'
                        ),
                        '<a href="' . esc_url(Notifications::getSettingsURL()) . '">',
                        '</a>'
                    )
                ];
            } elseif ($type === 'album' && !$this->isValidEAN($ean)) {
                $this->invalids[] = [
                    'record'    =>  $item,
                    'reason'    =>  printf(
                        __(
                            'The item does not have a valid EAN or UPC. Set the WooCommerce attribute in the %1$ssettings%2$s.',
                            'woocommerce-soundscan'
                        ),
                        '<a href="' . esc_url(Notifications::getSettingsURL()) . '">',
                        '</a>'
                    )
                ];
                return false;
            } else if ($type === 'track' && !$this->isValidISRC($isrc)) {
                $this->invalids[] = [
                    'record'    =>  $item,
                    'reason'    =>  printf(
                        __(
                            'The item does not have a valid ISRC. Set the WooCommerce attribute in the %1$ssettings%2$s.',
                            'woocommerce-soundscan'
                        ),
                        '<a href="' . esc_url(Notifications::getSettingsURL()) . '">',
                        '</a>'
                    )
                ];
                return false;
            }

            return parent::isValid($zip, $price, $type);
        }

        /**
         * Is the Product ISRC Valid?
         * @see http://isrc.ifpi.org/en/ for more information
         * @param string $isrc          The Product ISRC
         * @return bool                 True if Valid ISRC
         */
        private function isValidISRC(string $isrc): bool
        {
            return (ctype_alnum($isrc) && mb_strlen($isrc, 'utf-8') === 12);
        }
    }
} else {
    throw new \Exception('Woocommerce Soundscan DigitalFormatter class exists');
}
