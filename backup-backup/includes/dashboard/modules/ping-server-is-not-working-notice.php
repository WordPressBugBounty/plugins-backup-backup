<?php
// Exit on direct access
if (!defined('ABSPATH')) exit;

use BMI\Plugin\Dashboard as Dashboard;
use BMI\Plugin\BMI_Logger as Logger;
use BMI\Plugin\Backup_Migration_Plugin as BMP;

// Either the notice has been dismissed, or the site is marked as local/unreachable, in both cases we don't need to show the notice
if (get_option('bmi_ping_server_dismiss_issue', false) == true || get_option('bmi_cron_site_local_or_unreachable', false)) {
  return;
}

$lastPingTime = get_option('bmi_cron_last_ping_time', false);
$currentTime = current_time('timestamp');

if ($lastPingTime !== false && $currentTime - $lastPingTime > HOUR_IN_SECONDS) {
  if (Dashboard\bmi_get_config('CRON:ENABLED') == false) {
    return; // If cron is disabled, no need to show the notice
  }
  $notices[] = sprintf(
    __('%sWarning:%s The BackupBliss ping server is currently unable to reach your site. The last successful ping request was %s ago. This may be due to the site being hosted locally, firewall or security restrictions blocking incoming requests, or a temporary issue with the ping service. As a result, scheduled cron jobs may not execute reliably. Please verify that your site is publicly accessible and review any security configurations. You may also try to %sresync with the cron server%s. If the issue persists, contact %ssupport%s for further assistance.', 'backup-backup'),
    '<strong>', '</strong>',
    human_time_diff($lastPingTime, $currentTime),
    '<a href="#" class="resync-with-ping-server">', '</a>',
    '<a href="mailto:' . esc_attr(BMI_SUPPORT_EMAIL) . '">', '</a>'
  );
}
if (empty($notices)) {
  return; // No issues to display
}


?>

<div class="error-noticer warn" id="ping-server-issues">
  <div class="error-header">
    <div class="cf">
      <div class="left">
        <?php esc_html_e('We have some issue(s) regarding Scheduled Backups.', 'backup-backup'); ?>
      </div>
      <div class="right hoverable">
        <span class="bmi-error-toggle" data-expand="<?php esc_attr_e('Expand', 'backup-backup'); ?>" data-collapse="<?php esc_attr_e('Collapse', 'backup-backup'); ?>">
          <?php esc_html_e('Expand', 'backup-backup'); ?>
        </span> |
        <span class="bmi-error-dismiss" issue-type="ping-server" onclick="document.getElementById('ping-server-issues').remove()">
          <?php esc_html_e('Dismiss', 'backup-backup'); ?>
        </span>
      </div>
    </div>
  </div>
  <div class="error-body">
    <?php
      echo wp_kses_post(implode("<br /><br />", $notices));
    ?>
  </div>
</div>