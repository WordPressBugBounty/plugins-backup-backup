<?php
// Exit on direct access
if (!defined('ABSPATH')) exit;

use BMI\Plugin\Dashboard as Dashboard;
use BMI\Plugin\BMI_Logger as Logger;
use BMI\Plugin\Backup_Migration_Plugin as BMP;

if (get_option('bmi_site_local_unreachable_dismiss_issue', false) == true) {
  return;
}

if (get_option('bmi_cron_site_local_or_unreachable', false) ) {
  if (Dashboard\bmi_get_config('CRON:ENABLED') == false) {
    return; // If cron is disabled, no need to show the notice
  }
  $notices[] = sprintf(
    __('%sWarning:%s Your site appears to be hosted locally or is currently unreachable by the BackupBliss ping service. Scheduled backups rely on incoming traffic and may not execute reliably in this state. Please ensure your site is publicly accessible and review any security configurations that may block incoming requests. Once your site is accessible, you can %sresync with the ping server%s to restore normal operation. If the issue persists, contact %ssupport%s for further assistance.', 'backup-backup'),
    '<strong>', '</strong>',
    '<a href="#" class="resync-with-ping-server">', '</a>',
    '<a href="mailto:' . esc_attr(BMI_SUPPORT_EMAIL) . '">', '</a>'
  );
}
if (empty($notices)) {
  return; // No issues to display
}


?>
<div class="error-noticer warn" id="site-local-unreachable-notice">
  <div class="error-header">
    <div class="cf">
      <div class="left">
        <?php esc_html_e('We have some issue(s) regarding Scheduled Backups.', 'backup-backup'); ?>
      </div>
      <div class="right hoverable">
        <span class="bmi-error-toggle" data-expand="<?php esc_attr_e('Expand', 'backup-backup'); ?>" data-collapse="<?php esc_attr_e('Collapse', 'backup-backup'); ?>">
          <?php esc_html_e('Expand', 'backup-backup'); ?>
        </span> |
        <span class="bmi-error-dismiss" issue-type="site-local-unreachable" onclick="document.getElementById('site-local-unreachable-notice').remove()">
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