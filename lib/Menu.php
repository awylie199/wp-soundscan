<?php
namespace AW\WSS;

if (!defined('ABSPATH')) {
    exit;
}

use AW\WSS\Notifications;
use AW\WSS\PhysicalFormatter;
use AW\WSS\DigitalFormatter;
use AW\WSS\PhysicalSchedule;
use AW\WSS\DigitalSchedule;
use AW\WSS\Data;
use AW\WSS\Submission;

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
         * Download Report WordPress Hook Action
         * @var string
         */
        const DOWNLOAD_REPORT_ACTION = 'woocommerce_soundscan_export';

        /**
         * WooCommerce Soundscan Menu Admin Post NONCE Name
         * @var string
         */
        const MENU_NONCE_NAME = 'woocommerce_soundscan';

        /**
         * Download Report WordPress Hook Action
         * @var string
         */
        const DATES_CHANGE_ACTION = 'woocommerce_soundscan_dates_change';

        /**
         * Download Report WordPress Hook Action
         * @var string
         */
        const UPLOAD_REPORT_ACTION = 'woocommerce_soundscan_upload';

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

        /**
         * WooCommerce Logger
         * @var null|\WC_Logger
         */
        private $logger = null;

        /**
         * WordPress Sub Menu Tab (From $_GET)
         * @var string
         */
        private $tab = '';

        /**
         * WooCommerce Logger Context for Soundscan
         * @var string[]
         */
        private $context = [
            'source'    =>  'soundscan'
        ];

        public function __construct()
        {
            $this->logger = wc_get_logger();

            add_action('admin_post_' . self::DOWNLOAD_REPORT_ACTION, [$this, 'handleDownload']);
            add_action('admin_post_' . self::UPLOAD_REPORT_ACTION, [$this, 'handleUpload']);
            add_action('wp_ajax_' . self::DATES_CHANGE_ACTION, [$this, 'handleDatesChange']);
            add_action('admin_menu', [$this, 'addSubMenu']);
            add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);

            $this->tab = $_GET['tab'] ?? '';

            if ($this->tab === 'physical' || $this->tab === 'digital') {
                $this->type = $_GET['tab'];
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
                    <a class="<?php echo esc_attr($this->getNavClass('about')); ?>" href="<?php echo esc_url($this->getTabLink('about')); ?>">
                        <?php _e('About', 'woocommerce-soundscan'); ?>
                    </a>
                </nav>
                <?php
                if (empty($this->tab) || $this->tab === 'physical' || $this->tab === 'digital') {
                    $this->outputResultsHTML();
                } elseif ($this->tab === 'logs') {
                    $this->outputLogsHTML();
                } else {
                    $this->outputAboutHTML();
                }
                ?>
            </div>
            <?php
        }

        /**
         * Handle Download of Most Recent WooCommerce Soundscan
         * @return void
         */
        public function handleDownload()
        {
            if (current_user_can('manage_options')) {
                $name = $_GET[self::MENU_NONCE_NAME] ?? '';

                if (wp_verify_nonce($name, self::DOWNLOAD_REPORT_ACTION)) {
                    $results = get_transient(Settings::RESULTS_TRANSIENT);
                    $report = implode(PHP_EOL, $results);

                    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
                    header("Content-Type: application/force-download");
                    header("Content-Type: application/octet-stream");
                    header("Content-Type: application/download");
                    header('Content-Disposition: attachment; filename="soundscan-report.txt"');
                    header('Content-Length: ' . strlen($report));
                    header("Content-Transfer-Encoding: binary");

                    exit($report);
                } else {
                    exit;
                }
            } else {
                exit;
            }
        }

        /**
         * Handle Dates Change Requests for Report
         * @return void
         */
        public function handleDatesChange()
        {
            if (current_user_can('manage_options')) {
                $name = $_GET[self::MENU_NONCE_NAME] ?? '';

                if (wp_verify_nonce($name, self::DATES_CHANGE_ACTION)) {
                    $from = \DateTimeImmutable::createFromFormat(
                        'Ymd',
                        $_GET['from'] ?? ''
                    );
                    $to = \DateTimeImmutable::createFromFormat(
                        'Ymd',
                        $_GET['to'] ?? ''
                    );
                    $type = $_GET['type'] ?? '';

                    if ($from !== false && $to !== false && !empty($type)) {
                        $this->type = $type;
                        $this->setFormatterAndDates($from, $to);
                        ob_start();
                        $this->outputTableHTML();
                        $html = ob_get_clean();
                        ob_end_clean();
                        wp_send_json_success($html);
                    } else {
                        $this->logger->error(
                            __(
                                'Error in Soundscan Menu: Unable to parse reports dates change request.',
                                'woocommerce-soundscan'
                            ),
                            $this->context
                        );
                        wp_send_json_error();
                    }
                } else {
                    wp_send_json_error();
                }
            } else {
                wp_send_json_error();
            }
        }

        /**
         * Handle Manual Upload of Report
         * @return void
         */
        public function handleUpload()
        {
            if (current_user_can('manage_options')) {
                $name = $_GET[self::MENU_NONCE_NAME] ?? '';
                $errorCode = 0;

                if (wp_verify_nonce($name, self::UPLOAD_REPORT_ACTION)) {
                    $successful = false;
                    $this->setFormatterAndDates();

                    try {
                        if ($this->formatter->hasNecessaryOptions()) {
                            $submitter = new Submission($this->formatter);

                            if ($submitter->hasNecessaryOptions()) {
                                if (!$submitter->isAlreadyUploaded()) {
                                    $successful = $submitter->upload();
                                } else {
                                    throw new \Exception('An upload has already been made for this week.', 1);
                                }
                            } else {
                                throw new \Exception('The report lacks the necessary details.', 2);
                            }
                        } else {
                            throw new \Exception('The report lacks the necessary details.', 2);
                        }
                    } catch (\Exception $err) {
                        $this->logger(
                            sprintf(
                                __(
                                    'Error in Soundscan Manual Upload: %s',
                                    'woocommerce-soundscan'
                                ),
                                $err->getMessage()
                            ),
                            $this->context
                        );
                        $errorCode = $err->getCode();
                    } finally {
                        $url = add_query_arg([
                            'page'          =>  'soundscan',
                            'tab'           =>  $this->tab,
                            'successful'    =>  $successful,
                            'errorCode'       =>  $errorCode
                        ], esc_url(admin_url('admin.php')));
                        wp_safe_redirect($url);
                    }
                } else {
                    exit;
                }
            } else {
                exit;
            }
        }

        /**
         * Get Nav Classes (Based on POST Args)
         * @param string $tab       Tab Slug
         * @return string           Classes for Tab Element
         */
        private function getNavClass(string $tab): string
        {
            $class = 'nav-tab';

            if ($this->tab === $tab) {
                $class .= ' nav-tab-active';
            }

            return $class;
        }

        /**
         * Get Tab (Physical vs Digital) Page Link
         * @param string $tab        Tab Slug
         * @return string            URL for Tab Admin Link
         */
        private function getTabLink(string $tab): string
        {
            return add_query_arg([
                'page'  =>  'soundscan',
                'tab'   =>  $tab
            ], admin_url('admin.php'));
        }

        /**
         * Output Date Pickers HTML
         * @return void
         */
        private function outputDatePickersHTML()
        {
            $this->setFormatterAndDates();
            $url = wp_nonce_url(
                admin_url('admin-ajax.php'),
                self::DATES_CHANGE_ACTION, self::MENU_NONCE_NAME
            );
            $url = add_query_arg('action', self::DATES_CHANGE_ACTION, $url);
            $dateFormat = get_option('date_format');
            $momentFormat = $this->convertPHPToMomentFormat($dateFormat);
            $jqueryFormat = $this->convertPHPTojQueryFormat($dateFormat);

            ?>
            <div id="wss-dates" class="wss-dates" data-url="<?php echo esc_url($url); ?>" data-jquery-date-format="<?php echo esc_attr($jqueryFormat); ?>" data-moment-date-format="<?php echo esc_attr($momentFormat); ?>" data-type="<?php echo esc_attr($this->type); ?>">
                <label for="wss-to">
                    <?php _e('From', 'woocommerce-soundscan'); ?>
                </label>
                <input id="wss-from" type="text" class="datepicker" name="from" value="<?php echo $this->from->format($dateFormat); ?>" />
                <label for="wss-to">
                    <?php _e('To', 'woocommerce-soundscan'); ?>
                </label>
                <input id="wss-to" type="text" class="datepicker" name="to" value="<?php echo $this->to->format($dateFormat); ?>" />
            	<div id="wss-dates-spinner" class="spinner wss-dates-spinner"></div>
            </div>
            <p id="wss-dates-error" class="wss-dates-error">
                <span class="wss-dates-error__server">
                     <?php _e('An error has ocurred trying to change the dates. Please check your woocommerce logs.', 'woocommerce-soundscan'); ?>
                </span>
                <span class="wss-dates-error__dates">
                     <?php _e('Your dates are invalid. The start date can\'t be after the end date. If the problem persists, please check your WordPress date format setting and try a different date format.', 'woocommerce-soundscan'); ?>
                </span>
            </p>
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
                <?php _e('Physical reports are done from Tuesday to Monday.', 'woocommerce-soundscan'); ?>
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
        private function outputTableHTML() {
            $rows = $this->formatter->getFormattedResults();
            $downloadURL = add_query_arg(
                'action',
                self::DOWNLOAD_REPORT_ACTION,
                admin_url('admin-post.php')
            );
            $downloadURL = wp_nonce_url(
                $downloadURL,
                self::DOWNLOAD_REPORT_ACTION,
                self::MENU_NONCE_NAME
            );
            $uploadURL = add_query_arg(
                'action',
                self::UPLOAD_REPORT_ACTION,
                admin_url('admin-post.php')
            );
            $uploadURL = wp_nonce_url(
                $uploadURL,
                self::UPLOAD_REPORT_ACTION,
                self::MENU_NONCE_NAME
            );

            ?>
            <div id="wss-menu">
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
                <a class="button button-primary button-large wss-export-btn" href="<?php echo esc_url($downloadURL); ?>">
                	<?php _e('Export', 'woocommerce-soundscan'); ?>
                </a>
                <hr />
                <h3>
                    <?php _e('Ineligible Order Products', 'woocommerce-soundscan'); ?>
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
                            <?php _e('Order ID', 'woocommerce-soundscan'); ?>
                            </th>
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
                                   esc_html__('%d', 'woocommerce-soundscan'),
                                   $row['record']->get_order_id()
                               );
                               ?>
                            </td>
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
           </div>
           <hr />
           <div>
                <h3>
                <?php _e('Manual Upload', 'woocommerce-soundscan'); ?>
                </h3>
                <p>
                <?php
                printf(
                    __(
                        'Upload this week\'s report? To undo this action you must %1$smanually delete%2$s the file from Nielsen\'s FTP server.',
                        'woocommerce-soundscan'
                    ),
                    '<strong>',
                    '</strong>'
                );
                ?>
                </p>
                <p>
                <?php _e('If the upload is successful, it will not be uploaded automatically for this week.', 'woocommerce-soundscan'); ?>
                </p>
                <a class="button button-primary" href="<?php echo esc_url($uploadURL); ?>">
                <?php _e('Upload', 'woocommerce-soundscan') ?>
                </a>
                <p class="wss-upload-error <?php echo (isset($_GET['errorCode']) && (int)$_GET['errorCode'] === 2 ? 'wss-upload-error--visible' : ''); ?>">
                <?php
                printf(
                    __(
                        'The report was not uploaded. Please complete your Soundscan %1$ssettings%2$s to upload successfully.',
                        'woocommerce-soundscan'
                    ),
                    '<a href="' . Notifications::getSettingsURL() . '">',
                    '</a>'
                );
                ?>
                </p>
                <p class="wss-upload-error <?php echo (isset($_GET['errorCode']) && (int)$_GET['errorCode'] === 1 ? 'wss-upload-error--visible' : ''); ?>">
                <?php _e('The report was not uploaded. It has already been uploaded for this period.', 'woocommerce-soundscan'); ?>
                </p>
                <p class="wss-upload-success <?php echo (isset($_GET['errorCode']) && (int)$_GET['errorCode'] === 0 ? 'wss-upload-success--visible' : ''); ?>">
                <?php _e('Success!', 'woocommerce-soundscan'); ?>
                </p>
           </div>
            <?php
        }

        /**
         * Set Formatter and Relevant Report Dates
         * @param \DateTimeImmutable $to=null   To Date for Soundscan Report
         * @param \DateTimeImmutable $from=null From Date for Soundscan Report
         * @return void
         */
        private function setFormatterAndDates(
            \DateTimeImmutable $from = null,
            \DateTimeImmutable $to = null
        ) {
            $this->to = $to ?? new \DateTimeImmutable();

            if ($this->type === 'physical') {
                $from = $from ?? $this->to->modify('last ' . PhysicalSchedule::SUBMIT_DAY);
                $this->from = $from->setTime(0, 0, 0);
                $this->formatter = new PhysicalFormatter($this->to, $this->from);
            } else {
                $from = $from ?? $this->to->modify('last ' . DigitalSchedule::SUBMIT_DAY);
                $this->from = $from->setTime(0, 0, 0);
                $this->formatter = new DigitalFormatter($this->to, $this->from);
            }
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

        /**
         * Convert PHP Date Format to jQuery UI Format
         * @link https://stackoverflow.com/questions/16702398/convert-a-php-date-format-to-a-jqueryui-datepicker-date-format
         * @param string $format        PHP Date Format to Convert
         * @return string               Equivalent jQuery UI Date Format
         */
        private function convertPHPTojQueryFormat(string $format): string
        {
            $symbols = array(
                // Day
                'd' => 'dd',
                'D' => 'D',
                'j' => 'd',
                'l' => 'DD',
                'N' => '',
                'S' => '',
                'w' => '',
                'z' => 'o',
                // Week
                'W' => '',
                // Month
                'F' => 'MM',
                'm' => 'mm',
                'M' => 'M',
                'n' => 'm',
                't' => '',
                // Year
                'L' => '',
                'o' => '',
                'Y' => 'yy',
                'y' => 'y',
                // Time
                'a' => '',
                'A' => '',
                'B' => '',
                'g' => '',
                'G' => '',
                'h' => '',
                'H' => '',
                'i' => '',
                's' => '',
                'u' => ''
            );
            $jqueryFormat = "";
            $escaping = false;

            for ($i = 0; $i < strlen($format); $i++) {
                $char = $format[$i];

                // PHP date format escaping character
                if($char === '\\') {
                    $i++;

                    if ($escaping) {
                        $jqueryFormat .= $format[$i];
                    } else {
                        $jqueryFormat .= '\'' . $format[$i];
                    }

                    $escaping = true;
                } else {
                    if ($escaping) {
                        $jqueryFormat .= "'";
                        $escaping = false;
                    }

                    if (isset($symbols[$char])) {
                        $jqueryFormat .= $symbols[$char];
                    } else {
                        $jqueryFormat .= $char;
                    }
                }
            }

            return $jqueryFormat;
        }

        /**
         * Convert PHP to Moment JS Format
         * @param string $format        PHP Date Format
         * @return string               Equivalent Moment JS Format
         */
        private function convertPHPToMomentFormat(string $format): string
        {
            $replacements = [
                'd' => 'DD',
                'D' => 'ddd',
                'j' => 'D',
                'l' => 'dddd',
                'N' => 'E',
                'S' => 'o',
                'w' => 'e',
                'z' => 'DDD',
                'W' => 'W',
                'F' => 'MMMM',
                'm' => 'MM',
                'M' => 'MMM',
                'n' => 'M',
                't' => '', // no equivalent
                'L' => '', // no equivalent
                'o' => 'YYYY',
                'Y' => 'YYYY',
                'y' => 'YY',
                'a' => 'a',
                'A' => 'A',
                'B' => '', // no equivalent
                'g' => 'h',
                'G' => 'H',
                'h' => 'hh',
                'H' => 'HH',
                'i' => 'mm',
                's' => 'ss',
                'u' => 'SSS',
                'e' => 'zz', // deprecated since version 1.6.0 of moment.js
                'I' => '', // no equivalent
                'O' => '', // no equivalent
                'P' => '', // no equivalent
                'T' => '', // no equivalent
                'Z' => '', // no equivalent
                'c' => '', // no equivalent
                'r' => '', // no equivalent
                'U' => 'X',
            ];
            $momentFormat = strtr($format, $replacements);

            return $momentFormat;
        }

        /**
         * Output Logs HTML for Soundscan Submissions via FTP
         * @return void
         */
        private function outputLogsHTML()
        {
            $logs = get_option(Submission::LOGS_OPTION_NAME, []);
            $dateFormat = get_option('date_format', 'm-d-Y');
            $timeFormat = get_option('time_format', 'H:i:s');

            if (is_string($logs)) {
                $logs = unserialize($logs);
            }
            ?>
            <h2>
            <?php _e('Soundscan Submission Logs', 'woocommerce-soundscan'); ?>
            </h2>
            <hr />
            <?php if (count($logs)) : ?>
            <p>
            <?php _e('Check your WooCommerce logs for more detail on unsuccessful submissions', 'woocommerce-soundscan'); ?>
            </p>
            <table>
                <thead>
                    <tr>
                        <th>
                        <?php _e('Date', 'woocommerce-soundscan'); ?>
                        </th>
                        <th>
                        <?php _e('Time', 'woocommerce-soundscan'); ?>
                        </th>
                        <th>
                        <?php _e('Type', 'woocommerce-soundscan'); ?>
                        </th>
                        <th>
                        <?php _e('Result', 'woocommerce-soundscan'); ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log) : ?>
                    <?php $date = \DateTimeImmutable::createFromFormat(Submission::LOGS_DATE_FORMAT, $log['date']); ?>
                    <tr>
                        <td>
                        <?php printf(__('%s', 'woocommerce-soundscan'), $date->format($dateFormat)); ?>
                        </td>
                        <td>
                        <?php printf(__('%s', 'woocommerce-soundscan'), $date->format($timeFormat)); ?>
                        </td>
                        <td>
                        <?php printf(__('%s', 'woocommerce-soundscan'), $log['type']); ?>
                        </td>
                        <td>
                        <?php printf(__('%s', 'woocommerce-soundscan'), $log['result'] === true ? 'Successful' : 'Failed'); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else : ?>
            <p>
            <?php _e('You have not submitted any Soundscan reports to Nielsen.', 'woocommerce-soundscan'); ?>
            </p>
            <?php endif; ?>
            <?php
        }

        /**
         * Output About Tab HTML for Soundscan Submissions
         * @return void
         */
        private function outputAboutHTML()
        {
            ?>
            <h3>
            <?php _e('About', 'woocommerce-soundscan'); ?>
            </h3>
            <hr />
            <h4>
            <?php _e('WooCommerce Soundscan Plugin', 'woocommerce-soundscan'); ?>
            </h4>
            <p>
            <?php _e('The WooCommerce Soundscan plugin allows you to submit weekly digital and physical music sales to Nielsen from your WooCommerce shop.', 'woocommerce-soundscan');?>
            </p>
            <h4>
            <?php _e('Contact', 'woocommerce-soundscan'); ?>
            </h4>
            <p>
            <?php _e('The plugin was created by FuzzyBears, a UK web development team.', 'woocommerce-soundscan'); ?>
            </p>
            <p>
            <?php
               printf(
                   __(
                       'If we can help with this plugin, please do not hesitate to contact us at %1$shi@fuzzybears.co.uk%2$s. We\'re also available for work.',
                       'woocommerce-soundscan'
                   ),
                   '<a href="mailto:hi@fuzzybears.co.uk">',
                   '</a>'
               );
            ?>
            </p>
            <address>
                <a href="https://fuzzybears.co.uk">
                <?php _e('https://fuzzybears.co.uk', 'woocommerce-soundscan'); ?>
                </a>
            </address>
            <hr />
            <h4>
            <?php _e('Physical Format', 'woocommerce-soundscan'); ?>
            </h4>
            <p>
            <?php _e('The Nielsen Soundscan report for physical music sales must conform to the following format:', 'woocommerce-soundscan'); ?>
            </p>
            <dl>
                <dt>
                    <strong><?php _e('A Header Row Comprised Of:', 'woocommerce-soundscan'); ?></strong>
                </dt>
                <dd>
                    <ol>
                        <li><?php _e('Your Chain Number (Assigned by Nielsen)', 'woocommerce-soundscan'); ?></li>
                        <li><?php _e('Your Account Number (Assigned by Nielsen)', 'woocommerce-soundscan'); ?></li>
                        <li><?php _e('The current date (YYMMDD)', 'woocommerce-soundscan'); ?></li>
                    </ol>
                </dd>
                <dt>
                    <strong><?php _e('A Row Per Sold Item Comprised of:'); ?></strong>
                </dt>
                <dd>
                    <ol>
                        <li><?php _e('"M3", A Fixed Prefix', 'woocommerce-soundscan'); ?></li>
                        <li><?php _e('The Product EAN (or UPC)', 'woocommerce-soundscan'); ?></li>
                        <li><?php _e('The Customer\'s U.S. Zip Code (Truncated to 5 Digits)', 'woocommerce-soundscan'); ?></li>
                        <li><?php _e('"S", for Sale, or "R" for Refund', 'woocommerce-soundscan'); ?></li>
                    </ol>
                </dd>
                <dt>
                    <strong><?php _e('A Footer Row Comprised Of:', 'woocommerce-soundscan'); ?></strong>
                </dt>
                <dd>
                    <ol>
                        <li><?php _e('"94", A Fixed Prefix', 'woocommerce-soundscan'); ?></li>
                        <li><?php _e('Total Sales Plus Refunds', 'woocommerce-soundscan'); ?></li>
                        <li><?php _e('Total Sales', 'woocommerce-soundscan'); ?></li>
                    </ol>
                </dd>
            </dl>
            <hr />
            <h4>
            <?php _e('Digital Format', 'woocommerce-soundscan'); ?>
            </h4>
            <p>
            <?php _e('The Nielsen Soundscan report for digital music sales must conform to the following format:', 'woocommerce-soundscan'); ?>
            </p>
            <dl>
                <dt>
                    <strong><?php _e('A Header Row Comprised Of:', 'woocommerce-soundscan'); ?></strong>
                </dt>
                <dd>
                    <ol>
                        <li><?php _e('Your Chain Number (Assigned by Nielsen)', 'woocommerce-soundscan'); ?></li>
                        <li><?php _e('Your Account Number (Assigned by Nielsen)', 'woocommerce-soundscan'); ?></li>
                        <li><?php _e('The current date (YYMMDD)', 'woocommerce-soundscan'); ?></li>
                    </ol>
                </dd>
                <dt>
                    <strong><?php _e('A Row Per Sold Item Comprised of:', 'woocommerce-soundscan'); ?></strong>
                </dt>
                <dd>
                    <ol>
                        <li><?php _e('"D3", A Fixed Prefix', 'woocommerce-soundscan'); ?></li>
                        <li><?php _e('The Product EAN (or UPC) for Albums', 'woocommerce-soundscan'); ?></li>
                        <li><?php _e('The Customer\'s U.S. Zip Code (Truncated to 5 Digits)', 'woocommerce-soundscan'); ?></li>
                        <li><?php _e('"S", for Sale, or "R" for Refund', 'woocommerce-soundscan'); ?></li>
                        <li><?php _e('The Sequential Position of the Item Within the Order', 'woocommerce-soundscan'); ?></li>
                        <li><?php _e('The Product ISRC (or Internal ID) for Tracks', 'woocommerce-soundscan'); ?></li>
                        <li><?php _e('The Price Net of Tax and Deductions Without Decimal Points', 'woocommerce-soundscan'); ?></li>
                        <li><?php _e('"A" for Album and "S" for Single (Track)', 'woocommerce-soundscan'); ?></li>
                        <li><?php _e('"P" for PC or "M" for Mobile - Fixed at "P" Because WooCommerce Does Not Track the User\'s Device', 'woocommerce-soundscan'); ?></li>
                    </ol>
                </dd>
                <dt>
                    <strong><?php _e('A Footer Row Comprised Of:', 'woocommerce-soundscan'); ?></strong>
                </dt>
                <dd>
                    <ol>
                        <li><?php _e('"94", A Fixed Prefix', 'woocommerce-soundscan'); ?></li>
                        <li><?php _e('Total Sales Plus Refunds', 'woocommerce-soundscan'); ?></li>
                        <li><?php _e('Total Sales', 'woocommerce-soundscan'); ?></li>
                    </ol>
                </dd>
            </dl>
            <hr />
            <h4>
            <?php _e('Submission Schedule', 'woocommerce-soundscan'); ?>
            </h4>
            <p>
            <?php _e('This plugin is designed to submit reports weekly.', 'woocommerce-soundscan'); ?>
            </p>
            <p>
            <?php _e('WordPress Cron is used to submit reports on time. Therefore a user must visit on the due date before 1PM EST for the upload to be made.', 'woocommerce-soundscan'); ?>
            </p>
            <p>
            <?php _e('We always recommend manually checking the upload has been successful. The plugin authors cannot be held accountable for any missed uploads. It is highly recommended to check progress with Nielsen as their requirements may change.', 'woocommerce-soundscan'); ?>
            </p>
            <h4>
            <?php _e('Digital Sales', 'woocommerce-soundscan'); ?>
            </h4>
            <?php _e('The digital sales report ranges from Monday to Sunday, and is due before Monday 1PM EST.', 'woocommerce-soundscan'); ?>
            <h4>
            <?php _e('Physical Sales', 'woocommerce-soundscan'); ?>
            </h4>
            <p>
            <?php _e('The physical sales report ranges from Tuesday to Monday, and is due before Tuesday 1PM EST', 'woocommerce-soundscan'); ?>
            </p>
            <?php
        }
    }
} else {
    throw new \Exception('Woocommerce Soundscan Menu class exists');
}
