<?php

  // Namespace
  namespace BMI\Plugin\Dashboard;
  use BMI\Plugin\Backup_Migration_Plugin AS BMP;

  // Exit on direct access
  if (!defined('ABSPATH')) exit;

?>

<?php if (get_transient('bmip_display_quota_issues')): ?>

<div class="error-noticer" id="quota-issues">
  <div class="error-header">
    <div class="cf">
      <div class="left">
        <?php _e('We have some error regarding most recent backup upload process.', 'backup-backup'); ?>
      </div>
      <div class="right hoverable">
        <span class="bmi-error-toggle" data-expand="<?php _e('Expand', 'backup-backup'); ?>" data-collapse="<?php _e('Collapse', 'backup-backup'); ?>">
          <?php _e('Expand', 'backup-backup'); ?>
        </span> |
        <span id="bmip-quota-issues-dismiss">
          <?php _e('Dismiss', 'backup-backup'); ?>
        </span>
      </div>
    </div>
  </div>
  <div class="error-body">
    <?php echo get_transient('bmip_display_quota_issues'); ?>
  </div>
</div>

<?php endif; ?>
