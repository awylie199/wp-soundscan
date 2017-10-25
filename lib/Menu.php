<?php
namespace AW\WSS;

if (!defined('ABSPATH')) {
    exit;
}

use AW\WSS\Notifications;
use AW\WSS\PhysicalFormatter;
use AW\WSS\DigitalFormatter;
use AW\WSS\Data;

if (!class_exists('AW\WSS\Menu')) {
    /**
     * Display Soundscan WooCommerce Sub-Menu
     */
    class Menu
    {
        /**
         * Admin JS Script Name of Enqueue
         * @var string
         */
        const ADMIN_SCRIPT = 'woocommerce-soundscan-js';

        /**
         * Admin JS Style Name of Enqueue
         * @var string
         */
        const ADMIN_STYLE = 'woocommerce-soundscan-css';

        /**
         * Data Formatter for Handling Soundscan Records
         * @var null|\AW\WSS\Formatter
         */
        private $formatter = null;

        /**
         * Current Period 'To' Date for Soundscan Report
         * @var null|\DateTimeImmutable
         */
        private $to = null;

        /**
         * Current Period 'From' Date for Soundscan Report
         * @var null|\DateTimeImmutable
         */
        private $from = null;

        /**
         * Type of Data to Manage / Display (Digital|Physical)
         * @var string
         */
        private $type = 'physical';

        public function __construct()
        {
            add_action('admin_menu', [$this, 'addSubMenu']);
            add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);

            if (isset($_GET['tab'])) {
                $this->type = $_GET['tab'];
            }

            $this->to = new \DateTimeImmutable();

            if ($this->type === 'physical') {
                $this->from = $this->to->modify('last Thursday');
                $this->formatter = new PhysicalFormatter($this->to, $this->from);
            } else {
                $this->from = $this->to->modify('last Monday');
                $this->formatter = new DigitalFormatter($this->to, $this->from);
            }
        }

        /**
         * Add SubMenu Page for WooCommerce Soundscan
         * @return void
         */
        public function addSubMenu()
        {
            add_submenu_page(
                'woocommerce',
                __('Soundscan', 'woocommerce-soundscan'),
                __('Soundscan', 'woocommerce-soundscan'),
                'manage_options',
                'soundscan',
                [$this, 'outputSubMenuHTML']
            );
        }

        /**
         * Enqueue Plugin Admin Assets
         * @return void
         */
        public function enqueueAssets()
        {
            $url = plugin_dir_url(WC_SOUNDSCAN_DIR);

            wp_enqueue_script(
                self::ADMIN_SCRIPT,
                $url . 'woocommerce-soundscan/dist/main.js',
                [
                    'jquery',
                    'jquery-ui-datepicker'
                ]
            );
            wp_register_style(
                'jquery-ui',
                'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css'
            );
            wp_enqueue_style('jquery-ui');

            wp_enqueue_style(
                self::ADMIN_STYLE,
                $url . 'woocommerce-soundscan/dist/main.css',
                [
                    'jquery-ui'
                ]
            );
        }

        /**
         * Output HTML for Soundscan WooCommerce SubMenu
         * @return void
         */
        public function outputSubMenuHTML()
        {
            ?>
            <div class="wrap woocommerce">
                <h1>
                    <?php _e('WooCommerce Soundscan', 'woocommerce-soundscan') ?>
                </h1>
                <nav class="nav-tab-wrapper woo-nav-tab-wrapper">
                    <a class="<?php echo esc_attr($this->getNavClass('physical')); ?>" href="<?php echo esc_url($this->getTabLink('physical')); ?>">
                        <?php _e('Physical Sales Preview', 'woocommerce-soundscan'); ?>
                    </a>
                    <a class="<?php echo esc_attr($this->getNavClass('digital')); ?>" href="<?php echo esc_url($this->getTabLink('digital')); ?>">
                        <?php _e('Digital Sales Preview', 'woocommerce-soundscan'); ?>
                    </a>
                    <a class="<?php echo esc_attr($this->getNavClass('logs')); ?>" href="<?php echo esc_url($this->getTabLink('logs')); ?>">
                        <?php _e('Submission Logs', 'woocommerce-soundscan'); ?>
                    </a>
                </nav>
                <?php $this->outputResultsHTML(); ?>
            </div>
            <?php
        }

        /**
         * Get Nav Classes (Based on POST Args)
         * @return string           Classes for Tab Element
         */
        private function getNavClass(string $tab): string
        {
            $class = 'nav-tab';

            if ($this->type === $tab) {
                $class .= ' nav-tab-active';
            }

            return $class;
        }

        /**
         * Get Tab (Physical vs Digital) Page Link
         * @return string            URL for Tab Admin Link
         */
        private function getTabLink(string $tab): string
        {
            $url = admin_url('admin.php');
            $url = add_query_arg('page', 'soundscan', $url);
            $url = add_query_arg('tab', $tab, $url);

            return $url;
        }

        /**
         * Output Date Pickers HTML
         * @return void
         */
        private function outputDatePickersHTML()
        {
            $dateFormat = get_option('date_format');
            ?>
            <div class="wss-dates">
                <label for="wss-to">
                    <?php _e('From', 'woocommerce-soundscan'); ?>
                </label>
                <input id="wss-from" type="text" class="datepicker" name="from" value="<?php echo $this->from->format($dateFormat); ?>" />
                <label for="wss-to">
                    <?php _e('To', 'woocommerce-soundscan'); ?>
                </label>
                <input id="wss-to" type="text" class="datepicker" name="to" value="<?php echo $this->to->format($dateFormat); ?>" />
            </div>
            <hr />
            <?php
        }

        /**
         * Output Results Table for Soundscan
         * @return void
         */
        private function outputResultsHTML()
        {
            $this->outputExplanationHTML();
            $this->outputDatePickersHTML();
            $this->outputTableHTML();
        }

        /**
         * Output HTML for Explanation of How the Rows are Made
         * @return void
         */
        private function outputExplanationHTML()
        {
            ?>
            <h3>
                <?php _e('Soundscan reports are done on a weekly basis.', 'woocommerce-soundscan'); ?>
            </h3>
            <p>
            <?php if ($this->type === 'physical') : ?>
                <?php _e('Physical reports are done from Thursday to Wednesday.', 'woocommerce-soundscan'); ?>
            <?php else : ?>
                <?php _e('Digital reports are done from Monday to Sunday.', 'woocommerce-soundscan'); ?>
            <?php endif; ?>
            </p>
            <?php
        }

        /**
         * Output HTML for the Formatted Soundscan Results (or Lack Of)
         * @return void
         */
        private function outputTableHTML()
        {
            $rows = $this->formatter->getFormattedResults();

            ?>
            <?php if (count($rows) || (count($this->formatter->invalids))) : ?>
            <table class="report-results">
                <thead>
                    <tr>
                        <th>
                            <?php _e('Soundscan Generated Record', 'woocommerce-soundscan') ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row) : ?>
                    <tr>
                        <td>
                            <?php echo esc_html($row); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td>
                            <strong>
                                <?php _e('Total Rows:', 'woocommerce-soundscan'); ?>
                            </strong>
                        </td>
                        <td>
                            <strong>
                                <?php esc_html_e(count($rows)); ?>
                            </strong>
                        </td>
                    </tr>
                </tfoot>
            </table>
            <hr />
            <h3>
                <?php _e('Ignored Order Products', 'woocommerce-soundscan'); ?>
            </h3>
            <p>
                <?php
                _e(
                    'Order items that do not meet the soundscan criteria are listed here with the reason why.',
                    'woocommerce-soundscan'
                );
                ?>
            </p>
            <?php if (count($this->formatter->invalids)) : ?>
            <table class="invalid-results">
                <thead>
                    <tr>
                        <th>
                        <?php _e('Product Name', 'woocommerce-soundscan') ?>
                        </th>
                        <th>
                        <?php _e('Reason', 'woocommerce-soundscan') ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($this->formatter->invalids as $row) : ?>
                    <tr>
                        <td>
                            <?php
                                printf(
                                    esc_html__('%s', 'woocommerce-soundscan'),
                                    $row['record']->get_name()
                                );
                            ?>
                        </td>
                        <td>
                        <?php echo $row['reason']; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
            <?php else : ?>
                <?php $this->outputNoResultsHTML(); ?>
                <?php if (!$this->formatter->hasNecessaryOptions()) : ?>
                    <p>
                        <?php
                            printf(
                                __(
                                    'Add your %1$sdetails%2$s to get started',
                                    'woocommerce-soundscan'
                                ),
                                '<a href="' . Notifications::getSettingsURL() . '">',
                                '</a>'
                            );
                        ?>
                    </p>
                <?php endif; ?>
            <?php endif; ?>
            <?php
        }

        /**
         * Output HTML for When There are No Results to Display
         * @return void
         */
        private function outputNoResultsHTML()
        {
            ?>
            <div class="woocommerce-BlankState">
                <h2 class="woocommerce-BlankState-message">
                    <?php
                        printf(
                            __(
                                'There are no reportable %1$s sales in this period.',
                                'woocommerce-soundscan'
                            ),
                            $this->type
                        )
                    ?>
                </h2>
            </div>
            <?php
        }
    }
} else {
    throw new \Exception('Woocommerce Soundscan Menu class exists');
}
