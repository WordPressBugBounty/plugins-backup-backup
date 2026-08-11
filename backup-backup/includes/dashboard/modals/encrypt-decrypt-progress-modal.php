<?php

  // Namespace
  namespace BMI\Plugin\Dashboard;

  // Exit on direct access
  if (!defined('ABSPATH')) exit;

?>

<div class="bmi-modal bmi-modal-no-close" id="encrypt-decrypt-progress-modal">

  <div class="bmi-modal-wrapper" style="max-width: 700px; max-width: min(700px, 80vw)">
    <div class="bmi-modal-content center">

      <div class="mm60 f26 bold black title" id="encrypt_decrypt_progress_title"><?php esc_html_e('Processing backup...', 'backup-backup') ?></div>

      <div class="progress-bar-wrapper" style="margin: 30px 0;">
        <div class="progress-bar">
          <div class="progress-active-bar" style="width: 100%;"></div>
        </div>
      </div>

      <div class="step-progress cf center">
        <div class="f18 medium" style="margin-top: 20px;">
          <span id="encrypt_decrypt_current_step"><?php esc_html_e('Please wait while the backup is being processed...', 'backup-backup') ?></span>
        </div>
      </div>

      <div class="f16 semibold mtll text-muted">
        <?php esc_html_e('Please do not close this window while the process is running.', 'backup-backup') ?>
      </div>

    </div>
  </div>

</div>
