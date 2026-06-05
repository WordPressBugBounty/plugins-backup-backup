<?php
// Exit on direct access
if (!defined('ABSPATH')) exit;

use BMI\Plugin\Dashboard as Dashboard;
use BMI\Plugin\BMI_BackupMethodManager as MethodManager;

require_once BMI_INCLUDES . DIRECTORY_SEPARATOR . 'class-backup-method-mananger.php';

$currentConfig = Dashboard\bmi_get_config('STREAM:DIRECT_CLOUD_STREAMING:ENABLED');
if (!$currentConfig) {
  return; // No notice needed if the feature is not enabled
}

$storageStrategy = Dashboard\bmi_get_config('STORAGE:STRATEGY');
$backupMethodManager = new MethodManager();
$currentMethod = $backupMethodManager->currentMethod();
if (!defined('BMI_BACKUP_PRO') || !BMI_BACKUP_PRO) {
  $notices[] = __('The direct-to-cloud streaming feature is configured, but the Pro version is not active. Please activate the Pro version to enable direct-to-cloud backups.', 'backup-backup');
}

if ($currentConfig && $currentMethod == BMI_METHOD_DEFAULT) {
  $notices[] = __('The direct-to-cloud streaming feature requires switching the plugin to "Method - 3". Otherwise, the feature will be ignored, and backups will continue to be created locally before being transferred to the cloud.', 'backup-backup');
}

if ($storageStrategy && $storageStrategy !== 'cloud_only') {
  $notices[] = __('The direct-to-cloud streaming feature is set up, but your current backup strategy doesn’t store all backups in the cloud. To fully utilize this feature, please update your backup strategy to “Store all backups in the cloud.”', 'backup-backup');
}

if (empty($notices)) {
  return;
}

?>

<div class="error-noticer" id="stream-backup-notice">
  <div class="error-header">
    <div class="cf">
      <div class="left">
        <?php esc_html_e('We have some issue(s) regarding your configured settings.', 'backup-backup'); ?>
      </div>
      <div class="right hoverable">
        <span class="bmi-error-toggle" data-expand="<?php esc_attr_e('Expand', 'backup-backup'); ?>" data-collapse="<?php esc_attr_e('Collapse', 'backup-backup'); ?>">
          <?php esc_html_e('Expand', 'backup-backup'); ?>
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