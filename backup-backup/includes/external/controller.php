<?php

  // Namespace
  namespace BMI\Plugin\External;

  // Use
  use BMI\Plugin\BMI_Logger as Logger;
  use BMI\Plugin\External\BMI_External_BackupBliss as BackupBliss;
  use BMI\Plugin\Dashboard as Dashboard;
  use BMI\Plugin\External\BMI_External_Storage_Premium as ExternalStoragePremium;

  // Exit on direct access
  if (!defined('ABSPATH')) {
    exit;
  }

  /**
   * BMI_External_Storage
   */
  class BMI_External_Storage {

    public $backupbliss;

    public function __construct() {

        require_once BMI_INCLUDES . '/external/backupbliss.php';
        $this->backupbliss = new BackupBliss();

    }

    public function getExternalBackups() {

      $backups = [];

      // Google Drive
      $backups['backupbliss'] = $this->getBackupBlissBackupsParsedForList();


      if (defined('BMI_BACKUP_PRO') && defined('BMI_PRO_INC')) {
        $proPath = BMI_PRO_INC . 'external/controller.php';
        if (file_exists($proPath)) {
          require_once $proPath;

          $externalStorage = new ExternalStoragePremium();
          $external = $externalStorage->getExternalBackups();         
          $backups = array_merge($backups, $external);
        }
      }


      //Here we check and remove if there are any failed tasks but backups are successfully uploaded.

      $toBeUploaded = get_option('bmip_to_be_uploaded', [
        'current_upload' => [],
        'queue' => [],
        'failed' => []
      ]);

      if (isset($toBeUploaded['failed'])) {
        foreach($backups as $cloudName => $cloudBackups) {
          $cloudName = strtolower($cloudName);
          foreach($cloudBackups as $md5 => $backupDetails) {
            if (isset($toBeUploaded['failed'][$cloudName . "_" . $md5]))
              unset($toBeUploaded['failed'][$cloudName . "_" . $md5]);
          }
        }

        //Remove failed tasks if any of the cloud storages are disabled or empty
        $failed = $toBeUploaded['failed'];
        foreach($failed as $failed_task => $failed_count) {
          $data = explode("_", $failed_task);
         

          if (count($data) == 2) {
            $cloudtype = $data[0];
            $cloudtype = isset($backups[$cloudtype]) ? $cloudtype : strtoupper($cloudtype);
            if (isset($backups[$cloudtype]) && count($backups[$cloudtype]) == 0) {
              unset($toBeUploaded["failed"][$failed_task]); //Removes the failed task as there's no backups found or disabled.
            }
          }
        }

        update_option('bmip_to_be_uploaded', $toBeUploaded);
      }


      return $backups;

    }

    private function getBackupBlissBackupsParsedForList() {


      $files = $this->backupbliss->parseFiles($this->backupbliss->getAllFiles());

      $parsedBackups = [];

      
      if ($files) {
        foreach ($files['manifests'] as $manifestFileName => $filedetail) {
          
          $localManifest = BMI_BACKUPS . DIRECTORY_SEPARATOR. $manifestFileName;

          if (file_exists($localManifest)) {

            $manifestData = file_get_contents($localManifest);
            $manifest = json_decode($manifestData);

          } else {

            $manifestData = $this->backupbliss->getFile($manifestFileName);
            if (is_array($manifestData) && $manifestData["file_data"]) {
              
              file_put_contents($localManifest, $manifestData["file_data"]);
              $manifest = json_decode($manifestData["file_data"]);

            } else continue;

          }

          if (!isset($manifest))
            continue;

          $md5 = pathinfo($manifestFileName, PATHINFO_FILENAME);
          $backupFileName = $manifest->name;

          if (!isset($files["backups"][$backupFileName]))
            continue;

          $parsedBackups[$md5] = [];
          $parsedBackups[$md5][] = $backupFileName;
          $parsedBackups[$md5][] = $manifest->date;
          $parsedBackups[$md5][] = $manifest->files;
          $parsedBackups[$md5][] = $manifest->manifest;
          $parsedBackups[$md5][] = $files["backups"][$backupFileName]["size"];
          $parsedBackups[$md5][] = $manifest->is_locked;
          $parsedBackups[$md5][] = $manifest->cron;
          $parsedBackups[$md5][] = $md5;
          $parsedBackups[$md5][] = $backupFileName;
          $parsedBackups[$md5][] = sanitize_text_field($manifest->domain);
        }
    }

      return $parsedBackups;
    }
  }
