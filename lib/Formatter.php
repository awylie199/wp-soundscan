<?php
namespace AW\WSS;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('AW\WSS\Formatter')) {
    /**
     * Responsible for Base Behaviour for Formatting a Soundscan Report
     */
    abstract class Formatter
    {
        /**
         * Nielsen Soundscan Submission Formatted Results
         * @var string []
         */
        public $submission = [];

        /**
         * Assigned Chain Number (040xx)
         * @var string
         */
        public $chainNo = '';

        /**
         * Invalid Records in the Report
         * @var mixed[]
         */
        public $invalids = [];

        /**
         * Assigned Account Number (XXXXX)
         * @var string
         */
        public $accountNo = '';

        /**
         * Start Date of the Report
         * @var null|\DateTimeImmutable
         */
        public $startDate = null;

        /**
         * End Date of the Report
         * @var null|\DateTimeImmutable
         */
        public $endDate = null;

        /**
         * UPC WC Attribute Name
         * @var string
         */
        protected $upcAttribute = '';

        /**
         * EAN WC Attribute Name
         * @var string
         */
        protected $eanAttribute = '';

        /**
         * WC Category for Music
         * @var string
         */
        protected $musicCategory = '';

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
         * Count of Sales in Report
         * @var int
         */
        protected $sales = 0;

        /**
         * Count of Refunds in Report
         * @var int
         */
        protected $refunds = 0;

        /**
         * WooCommerce Soundscan Integration Settings
         * @var mixed[]
         */
        protected $options = [];

        /**
         * Minimum Qualifying Physical Track (Single) Price
         * @var float
         */
        protected $minTrackPrice = 0.0;

        /**
         * Minimum Qualifying Physical Album Price
         * @var float
         */
        protected $minAlbumPrice = 0.0;

        /**
         * Data Manager for Handling Soundscan Records
         * @var null|\AW\WSS\Data
         */
        protected $data = null;

        /**
         * WooCommerce SubCategory Name for Albums
         * @var string
         */
        private $albumCategory = '';

        /**
         * WooCommerce SubCategory Name for Singles (Tracks)
         * @var string
         */
        private $trackCategory = '';

        protected function __construct()
        {
            $this->logger = wc_get_logger();

            try {
                $this->options = get_option(Settings::NAME, '');

                if (is_string($this->options)) {
                    $this->options = unserialize($this->options);
                }

                $this->chainNo = $this->options[Settings::CHAIN_NO];
                $this->eanAttribute = $this->options[Settings::EAN_ATTRIBUTE] ?? '';
                $this->upcAttribute = $this->options[Settings::UPC_ATTRIBUTE] ?? '';
                $this->musicCategory = $this->options[Settings::MUSIC_CATEGORY] ?? '';
                $this->albumCategory = $this->options[Settings::ALBUM_CATEGORY] ?? '';
                $this->trackCategory = $this->options[Settings::TRACK_CATEGORY] ?? '';
            } catch (\Exception $err) {
                $this->logger->error(
                    sprintf(
                        __(
                            'Error in Soundscan Formatter: %1$s',
                            'woocommerce-soundscan'
                        ),
                        $err->getMessage()
                    ),
                    $this->context
                );
            }
        }

        /**
         * Get Invalid Results
         * @return mixed[]          Invalid Records with Reasons
         */
        public function getInvalidResults(): array
        {
            return $this->invalids;
        }

        /**
         * Add a Header Row to the Submission Report
         * @throws \Exception if the header row record is not the right length
         * @return void
         */
        protected function addHeader()
        {
            $headerRecord = '92';
            $headerRecord .= trim($this->chainNo);
            $headerRecord .= trim($this->accountNo);
            $headerRecord .= trim($this->endDate->format('ymd'));

            if (mb_strlen($headerRecord, 'UTF-8') !== 17) {
                throw new \Exception(
                    'Sales Header Record is Not 17 Characters Long'
                );
            }

            $this->submission[] = trim($headerRecord);
        }

        /**
         * Get EAN / UPC Number for Album
         * @param  \WC_Product_Simple $item     Line Item Sold as Part of Order
         * @return string                       EAN / UPC for Album
         */
        protected function getEAN(\WC_Product_Simple $item): string
        {
            $ean = preg_replace("/[^0-9]/", '', $item->get_attribute($this->eanAttribute));

            if (mb_strlen($ean, 'utf-8') === 13) {
                return $ean;
            } else {
                $upc = preg_replace("/[^0-9]/", '', $item->get_attribute($this->upcAttribute));

                if (mb_strlen($upc, 'utf-8') === 12) {
                    return str_pad($upc, 13, '0', STR_PAD_LEFT);
                } else {
                    return '';
                }
            }
        }

        /**
         * Only records of value 4.99 or more are reportable
         *
         * @param float  $price       Price For Line Item
         * @return bool               True if price is greater than $4.99
         */
        protected function isExpensiveEnough(float $price, string $type): bool
        {
            if ($type === 'album') {
                return (round($price, 2) > $this->minAlbumPrice);
            } elseif ($type === 'track') {
                return (round($price, 2) > $this->minTrackPrice);
            }
        }

        /**
         * Is the EAN Valid?
         *
         * @param string $ean        Product EAN Number
         * @return bool              True if EAN is 13 Characters Long
         */
        protected function isValidEAN(string $ean): bool
        {
            return (mb_strlen($ean, 'utf-8') === 13);
        }

        /**
         * Get ZipCode of Non AlphaNumeric Characters
         * @param \WC_Order  $order     WooCommerce Order
         * @return string               Sanitized Zip Code
         */
        protected function getZip(\WC_Order $order): string
        {
            $zip = '';
            $country = $order->get_shipping_country();

            if ($country === 'US') {
                $zip = substr(trim(preg_replace(
                    "/[^A-Za-z0-9]/",
                    '',
                    $order->get_shipping_postcode()
                )), 0, 5);
            }

            if ($country !== 'US' || !$this->isValidZip($zip)) {
                $country = $order->get_billing_country();

                if ($country === 'US') {
                    $zip = substr(trim(preg_replace(
                        "/[^A-Za-z0-9]/",
                        '',
                        $order->get_billing_postcode()
                    )), 0, 5);
                }
            }

            return $zip;
        }

        /**
         * Checks if a Valid Zip
         *
         * ZIP codes can now have 9 digits: XXXXX-XXXX. getZip takes the first 5
         *
         * @see Formatter::getZip
         * @return bool             True if a Valid US Zip Code
         */
        protected function isValidZip(string $zip): bool
        {
            return (mb_strlen($zip, 'utf-8') === 5 && ctype_digit($zip));
        }

        /**
         * Add a Sales Trailer Record (1 Per File) to the Submission
         *
         * Record Number (94) 2 1 - 2
         * Number of Transactions Sent 5 3 - 7
         * Net Units Sold 7 8 - 14
         *
         * @throws \Exception if trailer record is wrong length
         * @return void
         */
        protected function addTrailer()
        {
            $trailerRecord = '94' . static::TRAILER_DELIMETER;
            $trailerRecord .= trim((string)($this->sales + $this->refunds)) . static::TRAILER_DELIMETER;
            $trailerRecord .= trim((string)($this->sales));

            if (mb_strlen($trailerRecord, 'UTF-8') < 6) {
                // Least it could be: 94 0 0
                throw new \Exception('Trailer Record is Less Than 6 Characters Long');
            }

            $this->submission[] = $trailerRecord;
        }

        /**
         * Checks Whether Item is Music From Category
         * @param int  $id                       WooCommerce Product ID
         * @return bool                          True if Music Item
         */
        protected function isMusic(int $id): bool
        {
            $itemCategoryTerms = wp_get_post_terms(
                $id,
                'product_cat'
            );

            if (!is_array($itemCategoryTerms)) {
                $itemCategoryTerms = [];
            }

            return in_array(
                $this->musicCategory,
                array_column($itemCategoryTerms, 'name')
            );
        }

        /**
         * True if Product Item is Digital
         * @param \WC_Product $product  WC Order Product
         * @return bool                 True if the Item is Digital
         */
        protected function isDigital(\WC_Product $product): bool
        {
            return $product->is_virtual();
        }

        /**
         * Is the Record Valid for Including in the Report?
         * @param \WC_Order_Item_Product  $item     Current Product Item
         * @param string    $zip                    Current Product Custom ZIP
         * @param float     $price                  Current Product Sale Price
         * @param string    $type                   Current Product Album or Track
         * @return bool                             True if Valid
         */
        protected function isValid(
            \WC_Order_Item_Product $item,
            string $zip,
            float $price,
            string $type
        ): bool {
            if (!$this->isValidZIP($zip)) {
                $this->invalids[] = [
                    'record'    =>  $item,
                    'reason'    =>  __(
                        'The customer must have a valid U.S. zipcode for their billing or delivery addresses.',
                        'woocommerce-soundscan'
                    )
                ];

                return false;
            }

            if (!$this->isExpensiveEnough($price, $type)) {
                $this->invalids[] = [
                    'record'    =>  $item,
                    'reason'    =>  sprintf(
                        __(
                            'The item (sold for $%3$s) does not qualify as expensive enough. Adjust the minimum price %1$ssettings%2$s if required.',
                            'woocommerce-soundscan'
                        ),
                        '<a href="' . esc_url(Notifications::getSettingsURL()) . '">',
                        '</a>',
                        number_format($price, 2)
                    )
                ];

                return false;
            }

            return true;
        }

        /**
         * Gets the Item's Type - Album or Track
         * @param int  $id      WooCommerce Product ID
         * @return string       The type - 'album' or 'track', or '' of neither
         */
        protected function getType(int $id): string
        {
            $itemCategoryTerms = wp_get_post_terms(
                $id,
                'product_cat'
            );

            if (!is_array($itemCategoryTerms) || empty($itemCategoryTerms)) {
                return '';
            } else {
                $names = array_column($itemCategoryTerms, 'name');

                if (in_array($this->albumCategory, $names)) {
                    return 'album';
                } elseif (in_array($this->trackCategory, $names)) {
                    return 'track';
                } else {
                    return '';
                }
            }
        }
    }
} else {
    throw new \Exception('Woocommerce Soundscan Formatter class exists');
}
