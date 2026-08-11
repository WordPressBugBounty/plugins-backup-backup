<?php

namespace BMI\Plugin\CRON\Tasks;

use BMI\Plugin\CRON\AbstractTask;

if (!defined('ABSPATH') && !defined('BMI_USING_CLI_FUNCTIONALITY')) {
    exit;
}

/**
 * ScheduleKeepaliveTask
 *
 * Runs hourly to ensure the bmip_keepalive_cron fallback event is scheduled.
 *
 * @package backup-backup
 */
class ScheduleKeepaliveTask extends AbstractTask
{
    /**
     * WP action hook that fires this task.
     *
     * @var string
     */
    const HOOK = 'bmi_schedule_keepalive_cron';

    /**
     * @inheritDoc
     */
    public function get_hook()
    {
        return self::HOOK;
    }

    /**
     * @inheritDoc
     */
    public function get_interval()
    {
        return 'hourly';
    }

    /**
     * Schedules the bmip_keepalive_cron event if it is not already scheduled.
     *
     * @return void
     */
    public function run()
    {
        if (!wp_next_scheduled('bmip_keepalive_cron')) {
            wp_schedule_single_event(time() + 15, 'bmip_keepalive_cron');
        }
    }
}
