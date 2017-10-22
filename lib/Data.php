<?php
namespace AW\WSS;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('AW\WSS\Data')) {
    /**
     * Manages Soundscan Data in Database
     */
    class Data
    {
        /**
         * Type of Data to Manage (Physical|Digital)
         * @var string
         */
        public $type = 'physical';

        /**
         * WooCommerce Logger
         * @var null|\WC_Logger
         */
        private $logger = null;

        /**
         * WooCommerce Logger Context for Soundscan
         * @var string[]
         */
        private $context = [
            'source'    =>  'soundscan'
        ];

        /**
         * @param string $type          Type of Sales (Physical|Digital)
         */
        public function __construct(string $type = 'physical')
        {
            $this->logger = wc_get_logger();
            $this->type = $type;
        }

        /**
         * Get Soundscan Orders from Posts Table and Get WC Order Meta Data
         * @param \DateTimeImmutable $start         Start Date for Orders
         * @param \DateTimeImmutable $end           End Date for Orders
         * @return mixed[]          Rows of WooCommerce Post IDs of Orders
         */
        public function getResults(
            \DateTimeImmutable $start,
            \DateTimeImmutable $end
        ): array {
            global $wpdb;

            if ($start > $end) {
                $tempStart = $start;
                $start = $end;
                $end = $tempStart;
            }

            $results = [];

            try {
                $query = "
                    SELECT p.ID
                    FROM {$wpdb->prefix}posts p
                    WHERE p.post_status IN ('wc-completed', 'wc-refunded')
                        AND p.post_type = 'shop_order'
                        AND p.post_modified_gmt BETWEEN %s AND %s
                ";

                $preparedQuery = $wpdb->prepare(
                    $query,
                    $start->format('Y-m-d H:i:s'),
                    $end->format('Y-m-d H:i:s')
                );

                $orders = $wpdb->get_results($preparedQuery);

                foreach ($orders as $order) {
                    $results[] = new \WC_Order((int)$order->ID);
                }
            } catch (\Exception $err) {
                $this->logger->error(
                    sprintf(
                        __(
                            'Error in Soundscan Orders Fetcher: %1$s',
                            'woocommerce-soundscan'
                        ),
                        $err->getMessage()
                    ),
                    $this->context
                );
            } finally {
                if (!is_array($results)) {
                    $results = [];
                }

                return $results;
            }
        }
    }
} else {
    throw new \Exception('Woocommerce Soundscan Data class exists');
}

