<?php
namespace AW\WSS;

if (!defined('ABSPATH')) {
    exit;
}

use phpseclib\Net\SFTP;
use AW\WSS\Data;
use AW\WSS\Settings;

if (!class_exists('\AW\WSS\Submission')) {
    /**
     * Manages Submission of Soundscan Report to Nielsen via FTP
     */
    class Submission
    {
        /**
         * Option Name for Logs
         * @var string
         */
        const LOGS_OPTION_NAME = 'woocommerce-soundscan-logs';

        /**
         * Date Format for Logs
         * @var string
         */
        const LOGS_DATE_FORMAT = 'Ymd H:i:s';

        /**
         * FTP Stream Connection
         * @var null|resource
         */
        private $sftpConnection = null;

        /**
         * Nielsen FTP Host
         * @var string
         */
        private $ftpHost = '';

        /**
         * Nielsen FTP Login
         * @var string
         */
        private $ftpLogin = '';

        /**
         * Nielsen FTP Pwd
         * @var string
         */
        private $ftpPwd = '';

        /**
         * Data Formatter for Handling Soundscan Records
         * @var null|\AW\WSS\IFormatter
         */
        private $formatter = null;

        /**
         * WooCommerce Logger
         * @var null|\WC_Logger
         */
        private $logger = null;

        /**
         * WooCommerce Logger Context for Soundscan
         */
        private $context = [
            'source'    =>  'soundscan'
        ];

        /**
         * @param \AW\WSS\IFormatter    WooCommerce Soundscan Report Formatter
         */
        public function __construct(IFormatter $formatter)
        {
            $this->logger = wc_get_logger();
            $this->formatter = $formatter;
            $this->setConnectionDetails();
        }

        /**
         * Whether Submitter Has Required WooCommerce Soundscan Settings
         * @return bool         True if Ready to Submit
         */
        public function hasNecessaryOptions(): bool
        {
            return (!empty($this->ftpLogin) && !empty($this->ftpLogin) &&
                !empty($this->ftpPwd) && !empty($this->accountNo));
        }

        /**
         * Check Whether the Report Has Already Been Uploaded For this Week
         * @return bool             True if Already Uploaded
         */
        public function isAlreadyUploaded(): bool
        {
            $logs = get_option(self::LOGS_OPTION_NAME, []);

            if (is_string($logs)) {
                $logs = unserialize($logs);
            }

            $logs = array_reverse($logs);

            foreach ($logs as $log) {
                if ($log['type'] === $this->formatter::FORMATTER_TYPE &&
                    $log['result'] === true) {
                        $logDate = \DateTimeImmutable::createFromFormat(
                            self::LOGS_DATE_FORMAT,
                            $log['date']
                            );

                        return ($logDate > $this->formatter->startDate &&
                            $logDate < $this->formatter->endDate);
                    }
            }

            return false;
        }

        /**
         * Upload Soundscan Submission to Nielsen FTP Server
         * @return bool                 True if Upload Appears Successful
         */
        public function upload(): bool
        {
            $successful = false;
            $submission = implode(PHP_EOL, $this->formatter->getFormattedResults());
            $this->logger->notice(
                __('Starting Soundscan upload', 'woocommerce-soundscan'),
                $this->context
                );

            try {
                $this->connect();
                $filePath = $this->getTempFile($submission);
                $remoteFileName = $this->getRemoteFileName();

                $this->sftpConnection->put(
                    $remoteFileName,
                    $filePath,
                    SFTP::SOURCE_LOCAL_FILE
                );

                $this->checkRemote($remoteFileName);
                $this->logger->notice(
                    __(
                        'Successfully uploaded new Nielsen Soundscan report',
                        'woocommerce-soundscan'
                    )
                );
                $successful = true;
            } catch (\Exception $err) {
                $this->logger->error(
                    sprintf(
                        __(
                            'Error in Soundscan Upload: %1$s',
                            'woocommerce-soundscan'
                            ),
                        $err->getMessage()
                        ),
                    $this->context
                );
            } finally {
                if (is_resource($this->sftpConnection)) {
                    $this->sftpConnection->disconnect();
                    unset($this->sftpConnection);
                }
                if (isset($filePath) && file_exists($filePath)) {
                    unlink($filePath);
                }

                $this->updateLogs($successful);

                return $successful;
            }
        }

        /**
         * Set Connection Details Based on Data and Type
         * @return void
         */
        private function setConnectionDetails()
        {
            $this->ftpHost = get_option(Settings::FTP_HOST, '');

            if ($this->formatter::FORMATTER_TYPE === 'physical') {
                $this->ftpLogin = get_option(Settings::LOGIN_PHYSICAL_NAME, '');
                $this->ftpPwd = get_option(Settings::LOGIN_PHYSICAL_PWD, '');
                $this->accountNo = get_option(Settings::ACCOUNT_NO_PHYSICAL, '');
            } else {
                $this->ftpLogin = get_option(Settings::LOGIN_DIGITAL_NAME, '');
                $this->ftpPwd = get_option(Settings::LOGIN_DIGITAL_PWD, '');
                $this->accountNo = get_option(Settings::ACCOUNT_NO_DIGITAL, '');
            }
        }

        /**
         * Connect to Nielsen Soundscan
         * @return void
         */
        private function connect()
        {
            $this->logger->info(
                __('Connecting to Nielsen Soundscan', 'woocommerce-soundscan'),
                $this->context
            );
            $this->sftpConnection = new SFTP($this->ftpHost);

            if (!$this->sftpConnection->login($this->ftpLogin, $this->ftpPwd)) {
                throw new \Exception(
                    'Unable to connect to Soundscan Nielsen FTP Server'
                );
            }
        }

        /**
         * Get FileName for Today's Uploaded Submission
         * @return string           FileName for Upload
         */
        private function getRemoteFileName(): string
        {
            return $this->ftpLogin . '.txt';
        }

        /**
         * Check SFTP Upload to Nielsen via Stat
         * @param string  $remoteFileName      Remote FileName
         * @throws \Exception if the submission doesn't exist on Nielsen's SFTP Server
         * @return void
         */
        private function checkRemote(string $remoteFileName)
        {
            $this->logger->debug(
                __(
                    'Checking new Soundscan report exists Nielsen remote server',
                    'woocommerce-soundscan'
                ),
                $this->context
            );
            if (!$this->sftpConnection->file_exists($remoteFileName)) {
                throw new \Exception(
                    'Remote File Submission doesn\'t exist on Nielsen SFTP Server.'
                );
            }
        }

        /**
         * Write Submission to a Temporary .txt File
         * @throws \Exception for problems reading and writing the temporary file
         * @param  string &$submission       Generated Soundscan Submission
         * @return string                   Temp File Path of Submission for Upload
         */
        private function getTempFile(string &$submission)
        {
            $this->logger->debug(
                __(
                    'Writing Soundscan report to temporary file',
                    'woocommerce-soundscan'
                ),
                $this->context
            );
            $tempName = tempnam(sys_get_temp_dir(), 'woocommerce-soundscan');

            if (!$tempName) {
                throw new \Exception(
                    'Unable to get temp directory for Soundscan submission file write.'
                );
            } elseif (! is_writeable($tempName)) {
                throw new \Exception(
                    'Unable to write to temp file for Soundscan submission.'
                );
            }

            $handle = fopen($tempName, 'w');
            fwrite($handle, $submission);
            fclose($handle);

            return $tempName;
        }

        /**
         * Update Logs for Uploaded Report with Result
         * @param bool $result      Result of Nielsen Upload (True for Success)
         * @return void
         */
        private function updateLogs(bool $result)
        {
            $date = new \DateTimeImmutable();
            $logs = get_option(self::LOGS_OPTION_NAME, []);

            if (is_string($logs)) {
                $logs = unserialize($logs);
            }

            $logs[] = [
                'date'  =>  $date->format(self::LOGS_DATE_FORMAT),
                'result'=>  $result,
                'type'  =>  $this->formatter::FORMATTER_TYPE
            ];

            update_option(self::LOGS_OPTION_NAME, $logs);
        }
    }
} else {
    throw new \Exception('Woocommerce Soundscan Submission exists.');
}
