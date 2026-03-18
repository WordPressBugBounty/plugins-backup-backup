<?php

  /**
   * Main renderer for the Backup Banner (floating popup with overlay)
   *
   * @category Child Plugin
   */

  // Namespace
  namespace Inisev\Subs;

  // Disallow direct access
  if (!defined('ABSPATH')) exit;

?>

<!-- Dark overlay -->
<div class="bmi-backup-banner__overlay" id="bmi-backup-banner-overlay"></div>

<!-- Wavy arrow pointing from banner to sidebar menu item -->
<img src="<?php $this->_asset('imgs/waved-arrow.svg'); ?>" alt="" class="bmi-backup-banner__waved-arrow" id="bmi-backup-banner-arrow" aria-hidden="true">

<!-- Floating popup banner -->
<div class="bmi-backup-banner" id="bmi-backup-banner">
  <div class="bmi-backup-banner__inner">
    <img src="<?php $this->_asset('imgs/bg-pattern.svg'); ?>" alt="" class="bmi-backup-banner__bg-pattern" aria-hidden="true">

    <button type="button" aria-label="Close banner" class="bmi-backup-banner__close">
      <img src="<?php $this->_asset('imgs/cross.svg'); ?>" alt="Close" width="20" height="20">
    </button>

    <div class="bmi-backup-banner__content">
      <div class="bmi-backup-banner__header">
        <img src="<?php $this->_asset('imgs/icon-alarm.svg'); ?>" alt="" class="bmi-backup-banner__alarm-icon" width="81" height="70">
        <h2 class="bmi-backup-banner__title"><span class="green bold">Make a backup of this site</span><br><span id="changeable-text">before it expires</span></h2>
      </div>

      <div class="bmi-backup-banner__body">
        <p class="bmi-backup-banner__subtitle">The <span class="semi-bold">best backup &amp; migration plugin</span> for WordPress is already installed:</p>

        <div class="bmi-backup-banner__features">
          <div class="bmi-backup-banner__feature-card" style="width: 291px;">
            <div class="bmi-backup-banner__feature-icon">
              <img src="<?php $this->_asset('imgs/icon-rocket.svg'); ?>" alt="" width="55" height="55">
            </div>
            <span class="bmi-backup-banner__feature-text"><b>Super-fast</b> backups &amp; migrations</span>
          </div>

          <div class="bmi-backup-banner__feature-card" style="width: 322px;">
            <div class="bmi-backup-banner__feature-icon">
              <img src="<?php $this->_asset('imgs/icon-free.svg'); ?>" style="width: 30px;height:31px;position:absolute;left:38px;bottom:35px;">
              <img src="<?php $this->_asset('imgs/icon-cloud.svg'); ?>" alt="" width="55" height="55">
            </div>
            <span class="bmi-backup-banner__feature-text"><b>Free cloud storage</b> for your <span class="nowrap">backups (1 GB)</span></span>
          </div>

          <div class="bmi-backup-banner__feature-card" style="width: 248px;">
            <div class="bmi-backup-banner__feature-icon">
              <img src="<?php $this->_asset('imgs/icon-support.svg'); ?>" alt="" width="55" height="55">
            </div>
            <span class="bmi-backup-banner__feature-text"><b>Free support</b> if you need help</span>
          </div>
        </div>
      </div>

      <div class="bmi-backup-banner__actions">
        <a href="<?php echo esc_url($this->plugin_menu_url); ?>" class="bmi-backup-banner__btn bmi-backup-banner__btn--primary bmi-backup-banner__cta">
          <img src="<?php $this->_asset('imgs/arrow-right.svg'); ?>" alt="" class="bmi-backup-banner__btn-arrow" width="28" height="28">
          <span>Create a backup now</span>
        </a>
        <a href="https://bit.ly/twplight" target="_blank" class="bmi-backup-banner__btn bmi-backup-banner__btn--secondary bmi-backup-banner__install-btn">
          <span>Install Backup Migration on your own site</span>
        </a>
      </div>
    </div>
  </div>
</div>
