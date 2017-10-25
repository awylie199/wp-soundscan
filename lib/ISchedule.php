<?php
namespace AW\WSS;

if (!defined('ABSPATH')) {
    exit;
}

interface ISchedule
{
    /**
     * Activate WP Schedule Hook
     * @return void
     */
    public function activate();

    /**
     * Deactivate WP Scheduled Hook
     * @return void
     */
    public function deactivate();
}
