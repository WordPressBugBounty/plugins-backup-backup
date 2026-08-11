<?php

  // Namespace
  namespace BMI\Plugin\Dashboard;

  // Exit on direct access
  if (!defined('ABSPATH')) exit;

?>

<div class="bmi-modal bmi-modal-no-close bmi-modal-not-allowed" id="delete-progress-modal">

  <div class="bmi-modal-wrapper no-hpad" style="max-width: 600px; max-width: min(600px, 80vw)">
    <div class="bmi-modal-content center">

      <div class="mm60 f30 bold black"><?php esc_html_e('Deletion in progress', 'backup-backup') ?></div>

      <div class="red-error-bg tml">
        <div class="red-warning mtl mbl f18" style="margin-left: 20px; margin-right: 20px;">
          <?php esc_html_e('Do not close this window as long as the deletion process is ongoing', 'backup-backup'); ?>
        </div>
      </div>

      <div class="mm60 progress-bar-wrapper">

        <div class="progress-bar">
          <div class="progress-active-bar" style="width: 0%;"></div>
          <div class="progress-percentage" style="left: 0%;">0%</div>
        </div>

      </div>

      <div class="mm60 step-progress cf">
        <div class="center f16 medium">
          <span id="delete_current_step"><?php esc_html_e('Preparing deletion...', 'backup-backup') ?></span>
        </div>
      </div>

      <div class="center f19 mtl">
        <a href="#" id="cancel_delete" class="btn bold red inline mm60">
          <?php esc_html_e("Cancel", 'backup-backup'); ?>
        </a>
      </div>

    </div>
  </div>

</div>
