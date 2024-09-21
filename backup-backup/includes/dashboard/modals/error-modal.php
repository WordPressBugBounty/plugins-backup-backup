<?php

  // Namespace
  namespace BMI\Plugin\Dashboard;
  use BMI\Plugin\Checker\Compatibility;


  // Exit on direct access
  if (!defined('ABSPATH')) exit;

  $debugSectionStatment = __('For more details, %s1see%s2 or %s3download%s4 the logs. Maybe you (or your webmaster) can fix it yourself based on those pointers?','backup-backup');
  $debugSectionStatment = str_replace(
    ['%s1', '%s2', '%s3', '%s4'],
    [
      '<a href="#" class="link open-logs-modal-url">',
      '</a>',
      '<a href="#" class="link download-log-url censored" download="secure-log.txt">',
      '</a>',
    ],
    $debugSectionStatment
  );

  $sharingLogStatment= __("%s1You'll share:%s2 Website URL, %s3logs%s4, our plugin logs & configuration, basic data about your site. No confidential data such as email gets shared.", 'backup-backup');
  $sharingLogStatment = str_replace(
    ['%s1','%s2','%s3','%s4'],
    [
      '<b>',
      '</b>',
      '<a href="#" class="nlink secondary hoverable open-logs-modal-url">',
      '</a>'
    ],
    $sharingLogStatment
  );

  $diskSpaceCheckStatment = __('Click %s1here%s2 to check if you have enough space to create your backup.', 'backup-backup');
  $diskSpaceCheckStatment = str_replace(
    ['%s1', '%s2'],
    [
      '<a href="#" class="link bmi-check-disk-space">',
      '</a>'
    ],
    $diskSpaceCheckStatment
  );


?>

<div class="bmi-modal" id="error-modal">

  <div class="bmi-modal-wrapper no-hpad" style="max-width: 780px; max-width: min(780px, 80vw); padding-top: 25px;">
    <a href="#" class="bmi-modal-close npt">×</a>
    <div class="bmi-modal-content center">

      <div class="mm60 f30 bold black mb flex flexcenter">
        <img src="<?php echo $this->get_asset('images', 'red-cross.svg'); ?>" alt="red-cross" width="78px">
        <span class="modal-title"></span>
      </div>

      <div class="failure-options-container">
        <div class="cf lh30">
          <div class="left mm30 f20" style="margin-bottom:20px">
            <?php _e("However, no reason to despair! Your option(s):","backup-backup"); ?>
          </div>
        </div>
          <!-- OPTION 1: Try it in a different way -->
          <div class="collapser failure-option shadow" group="failure-option">
            <div class="header f20 pointer transition flex bold">
              <span class="right-arrow"></span>
              <span class="option-title">
              <?php _e('Try it in a different way','backup-backup'); ?>
              </span>
            </div>
            <div class="content save-action f18 flex mm30 pbl" >
                <?php _e('The plugin also offers an alternative method. It might be a bit slower, but it’s worth trying.', 'backup-backup');?>
              <a class="try-in-different-way mtll btn"><?php echo __('Yes sure, please try!','backup-backup');?></a>
            </div>
          </div>

          <!-- OPTION 2: Debug it yourself -->
          <div class="collapser failure-option shadow" group="failure-option">
            <div class="header f20 pointer transition flex bold">
              <span class="right-arrow"></span>
              <span class="option-title">
              <?php _e('Debug it yourself','backup-backup'); ?>
              </span>
            </div>
            <div class="content save-action f18 flex mm30 pbl" >
              <span class="there-are-reasons"><?php _e('Based on what we observed, the issue could be one of those:','backup-backup');?></span>
              <?php if (current_user_can('manage_options') && current_user_can('administrator')): ?>
                <ul class="failure-reasons">
                </ul>
                <?php endif; ?>
                <!-- Disk space checking -->
                <div class="space-checking">
                  <?php echo $diskSpaceCheckStatment ?>
                  <div class="loading hide_verbose"> 
                    <div class="spinner-loader"></div>
                    <span><?php _e('Checking','backup-backup');?></span>
                  </div>
                  <div class="checking-result">
                    <div class="failed hide_verbose">
                      <span class="failure-icon">×</span>
                      <span><?php _e('Failed to check disk space, ','backup-backup');?></span>
                      <span><?php _e("We couldn't check available disk space, there may not be enough space for the backup, please double check.",'backup-backup');?></span>
                    </div>
                    <div class="not-enough-space hide_verbose">
                      <span class="failure-icon"></span>
                      <span><?php _e('The minimum required disk space is ','backup-backup'); ?><span class="required-space"></span><?php _e(' , while the available disk space is ','backup-backup'); ?><span class="available-space"></span></span>
                    </div>
                    <div class="enough-space hide_verbose">
                      <span class="success-icon"></span>
                      <span><?php _e('There is enough disk space on your server.','backup-backup');?></span>
                    </div>
                  </div>
                </div>
                <!-- End of space checking -->
              <?php echo $debugSectionStatment ?>
              <span class="there-are-reasons"><?php _e(' If not:','backup-backup'); ?></span>
              <span class="there-are-no-reasons"><br><br><?php _e('If you still struggle, feel free to contact us, we will help!','backup-backup'); ?></span>
            </div>
          </div>

          <!-- OPTION 3: Ask us! -->
          <div class="collapser failure-option shadow" group="failure-option">
            <div class="header f20 pointer transition flex bold">
              <span class="right-arrow"></span>
              <span class="option-title">
              <?php _e('Ask us!','backup-backup'); ?>
              </span>
              <span class="its-free"></span>
            </div>
            <div class="content save-action f18 flex mm30 pbl" >
                <?php _e('We’re happy to look into it for you! Please click on the button below to send us the required debug infos:','backup-backup');?>
                <a class="btn center bmi-send-troubleshooting-logs" href="#" ><?php _e('Share debug infos with BackupBliss team', 'backup-backup');?></a>
                <span class="f16"><?php echo $sharingLogStatment ?></span>
            </div>
          </div>
      </div>
    </div>
  </div>

</div>
