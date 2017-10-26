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
         * Upload Soundscan Submission to Nielsen FTP Server
         * @return bool                    True if Successful Upload
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

                $this->check($remoteFileName);
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

                return $successful;
            }
        }

        /**
         * Check SFTP Upload to Nielsen via Stat
         * @param string  $remoteFileName      Remote FileName
         * @throws \Exception  if the submission doesn't exist on Nielsen's SFTP Server
         * @return void
         */
        private function check(string $remoteFileName)
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
    }
} else {
    throw new \Exception('Woocommerce Soundscan Submission exists.');
}
