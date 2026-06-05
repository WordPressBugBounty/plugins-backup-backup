<?php
// Exit on direct access
if (!defined('ABSPATH')) exit;

use BMI\Plugin\Dashboard as Dashboard;

$currentConfig = Dashboard\bmi_get_config('STORAGE:STRATEGY');

if ($currentConfig === 'local_and_cloud') {
  return; // No notice needed if we're already in a safe state
}

if (!defined('BMI_BACKUP_PRO') || !BMI_BACKUP_PRO) {
  $notices[] = __('Your backup strategy is configured to use cloud storage, but the Pro version of the plugin is not active. Please activate the Pro version to enable cloud backup features.', 'backup-backup');
  Dashboard\bmi_set_config('STORAGE:STRATEGY', 'local_and_cloud');
} else {
  require_once BMI_PRO_INC . 'services/class-bmi-pro-backup-lifecycle-manager.php';
  require_once BMI_INCLUDES . '/external/external-storage-manager.php';
  $backupLifecycleManager = \BMI\Plugin\Services\BackupLifecycleManager::getInstance();
  $configuredOption = $backupLifecycleManager->getConfiguredOption();
  
  // Check if cloud-based strategy requires storage configuration
  if (in_array($configuredOption, [
    \BMI\Plugin\Services\BackupLifecycleManager::STORAGE_STRATEGY_CLOUD_ONLY,
    \BMI\Plugin\Services\BackupLifecycleManager::STORAGE_STRATEGY_HYBRID
  ]) && !\BMI\Plugin\External\BMI_External_Storage_Manager::getInstance()->isAnyStorageConfigured()) {
  
    $notices[] = __('Backups will henceforth be kept locally, because all cloud locations are disconnected.', 'backup-backup');
    Dashboard\bmi_set_config('STORAGE:STRATEGY', 'local_and_cloud');
  } else {
    return;
  }
}


?>

<div class="error-noticer" id="cloud-strategy-issue">
  <div class="error-header">
    <div class="cf">
      <div class="left">
        <?php esc_html_e('We have some issue(s) regarding your configured settings.', 'backup-backup'); ?>
      </div>
      <div class="right hoverable">
        <span class="bmi-error-toggle" data-expand="<?php esc_attr_e('Expand', 'backup-backup'); ?>" data-collapse="<?php esc_attr_e('Collapse', 'backup-backup'); ?>">
          <?php esc_html_e('Expand', 'backup-backup'); ?>
        </span> |
        <span class="bmi-error-dismiss">
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