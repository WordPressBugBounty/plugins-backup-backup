<?php

  // Namespace
  namespace BMI\Plugin\Dashboard;

  // Exit on direct access
  if (!defined('ABSPATH')) exit;

?>

<div class="bmi-modal bmi-modal-no-close" id="password-request-modal">

  <div class="bmi-modal-wrapper bmi-restore-password-wrapper">
    <a href="#" class="bmi-modal-close">×</a>
    <div class="bmi-modal-content">

      <div class="center">
        <div class="cf center flexcenter flex bmi-restore-password-header">
          <div class="flex flexcenter bmi-restore-password-icon-box" id="bmi-password-modal-icon-box">
            <svg class="bmi-restore-password-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
              <path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
            </svg>
          </div>
          <div class="left f24 bold lh50" id="bmi-password-modal-title"><?php esc_html_e("Enter Backup Password", 'backup-backup'); ?></div>
        </div>
      </div>

      <div class="f19 center lh30 bmi-restore-password-body">
        <span class="bmi-restore-password-desc" id="bmi-password-modal-desc"><?php esc_html_e("Please provide the password for this backup to proceed with the restoration.", 'backup-backup'); ?></span>
        
        <div class="bmi-encryption-warning-wrapper" id="bmi-password-modal-warning-wrapper" style="display: none; margin-top: 15px; margin-bottom: 15px;">
          <div class="bmi-encryption-warning mbl mtl f16" style="line-height: 22px; margin-left: 0px; margin-right: 0px;">
            <svg class="warning-icon" viewBox="0 0 24 24" fill="#D47F02" xmlns="http://www.w3.org/2000/svg">
              <path fill-rule="evenodd" clip-rule="evenodd" d="M9.401 3.003c1.155-2 4.043-2 5.197 0l7.355 12.748c1.154 2-.29 4.5-2.599 4.5H4.645c-2.309 0-3.752-2.5-2.598-4.5L9.4 3.003zM12 8.25a.75.75 0 01.75.75v3.75a.75.75 0 01-1.5 0V9a.75.75 0 01.75-.75zm0 8.25a.75.75 0 100-1.5.75.75 0 000 1.5z" />
            </svg>
            <div class="warning-text" id="bmi-password-modal-warning-text">
            </div>
          </div>
        </div>
        <div class="bmi-password-request-modal-input" style="margin: 15px 0;">
          <input type="password" autocomplete="off" id="bmi-backup-password-input" placeholder="<?php esc_attr_e('Enter password...', 'backup-backup'); ?>" class="bmi-text-input"/>
        </div>
      </div>

      <div class="flex flexcenter">
        <div class="bmi-restore-password-actions">
          <div class="inline bmi-restore-password-inline">
            <a href="#" id="backup-password-verify-confirm" class="btn mm60 center block bold nodec bmi-restore-password-confirm-btn">
              <?php esc_html_e("Verify & Restore", 'backup-backup'); ?>
            </a>
          </div>
          <div class="center f19">
            <a href="#" class="bold bmi-modal-closer hoverable text-muted">
              <?php esc_html_e("Cancel", 'backup-backup'); ?>
            </a>
          </div>
        </div>
      </div>

    </div>
  </div>

</div>