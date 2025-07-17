<?php

// Namespace
namespace BMI\Plugin;

// Uses
use BMI\Plugin\Backup_Migration_Plugin as BMP;
use BMI\Plugin\BMI_Logger as Logger;
use BMI\Plugin\BMI_Pro_Core;
use BMI\Plugin\BMProAjax as BMProAjax;
use BMI\Plugin\Scanner\BMI_BackupsScanner as Backups;
use BMI\Plugin\External\BMI_External_BackupBliss as BackupBliss;
use BMI\Plugin\Dashboard as Dashboard;

// Exit on direct access
if (!defined('ABSPATH')) exit;

/**
 * Ajax Offline (unauthorized) Handler for BMI
 */
class BMI_Ajax_Offline
{

  public $post;
  public $backupbliss;
  public $proajax;
  
  public function __construct($post)
  {

    // $POST is sanitized by BMI Basic Version
    // Do not call this class anywhere else [!]
    $this->post = $post;

    // Active offline ajax premium side
    if (defined('BMI_PRO_INC')) {
      if (BMI_DEBUG)
        Logger::error("PREMIUM CHECK");

      require_once BMI_PRO_INC . 'ajax_offline.php';
      $this->proajax = new BMI_Ajax_Offline_Premium($post);
    }
    
    require_once BMI_INCLUDES . '/external/backupbliss.php';
    $this->backupbliss = new BackupBliss();

    if (is_user_logged_in() && current_user_can('manage_options')) {
        if ($this->post['f'] == 'check-not-uploaded-backups') {

          $this->checkForBackupsToUpload();

          if ($this->proajax)
            $this->proajax->checkForBackupsToUpload();

          BMP::res(['status' => 'success']);
        }
    }

    if ($this->post['f'] == 'refresh') {
        BMP::res($this->keepAliveUnAuthorizedRefresh());
    }
    
  }

  public function getBackupBlissConnectionStatus()
  {

    $res = $this->backupbliss->getSecret();
    return $res !== false;

  }

  public function checkForBackupsToUpload() {
    $toBeUploaded = $this->fetchToBeUploaded();

    $task = $toBeUploaded['current_upload'];
    $queue = $toBeUploaded['queue'];

    //If there's no task or queue present, then check for backups to upload
    if (sizeof($task) == 0 && sizeof($queue) == 0) {
        $this->backupbliss->checkForBackupsToUpload();

        //Check for backups premium
        if ($this->proajax)
          $this->proajax->checkForBackupsToUpload();
    }

    //Remove failed tasks if the local backup is deleted
    if (isset($toBeUploaded['failed'])) {
      // Local Backups
      require_once BMI_INCLUDES . DIRECTORY_SEPARATOR . 'scanner' . DIRECTORY_SEPARATOR . 'backups.php';
      $backups = new Backups();
      $backupsAvailable = $backups->getAvailableBackups("local");
      $localBackups = $backupsAvailable['local'];
      $localBackups = array_reverse($localBackups);
      
      $failed = $toBeUploaded['failed'];
      foreach($failed as $failed_task => $failed_count) {
        $data = explode("_", $failed_task);
      

        if (count($data) == 2) {
          $cloudtype = $data[0];
          $md5 = $data[1];
          
          $md5s = array_map(function($backup) { return $backup[7]; }, $localBackups);

          if (!in_array($md5, $md5s)) {
            unset($toBeUploaded["failed"][$failed_task]);
            update_option('bmip_to_be_uploaded', $toBeUploaded);
          }
        }
      }
    }
  }

  /**
   * keepAliveUnAuthorizedRefresh - Unauthorized Keep Alive Request
   * DO NOT RESPONSE WITH ANY SENSITIVE DATA, ONLY SUCCESS OR FAIL
   * THIS CAN BE ACCESSED BY ANYONE WITHOUT ANY AUTH
   *
   * @return string[] success/fail
   */
  public function keepAliveUnAuthorizedRefresh()
  {
    //Atomic locking to prevent race conditions
    $lock_file = BMI_CONFIG_DIR . DIRECTORY_SEPARATOR . '.keep_alive.lock';

    // Open the lock file
    $fp = fopen($lock_file, 'c');

    // Try to acquire an exclusive lock
    if (flock($fp, LOCK_EX | LOCK_NB)) {
        if (BMI_DEBUG)
          Logger::error("Lock acquired.");

        $ret = $this->keepAliveUnAuthorizedRefreshExec();

        // Release the lock
        flock($fp, LOCK_UN);
        if (BMI_DEBUG)
          Logger::error("Lock released.");
        return $ret;
    } else {
        return ['status' => 'success']; // Lock is already held
    }
  }

  private function removeCurrentTask($toBeUploaded) {
    $toBeUploaded["current_upload"] = []; //Removes the current ttask
    update_option("bmip_to_be_uploaded", $toBeUploaded);

    return ['status' => 'no_tasks'];
  }

  private function fetchToBeUploaded() {
    //Get the option without any caching when used with get_option which prevvents stale data from being retreived.
    //This is implemented after observing and debugging the issue that sometimes the same batch is uploaded again causing issues.
    global $wpdb;
    $bmip_to_be_uploaded = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM $wpdb->options WHERE option_name = %s", 'bmip_to_be_uploaded' ) );
    if ($bmip_to_be_uploaded !== null) {
      $toBeUploaded = maybe_unserialize($bmip_to_be_uploaded);
      if (!isset($toBeUploaded['current_upload']))
        $toBeUploaded['current_upload'] = [];
    } else {
      $toBeUploaded = [
        'current_upload' => [],
        'queue' => [],
        'failed' => []
      ];
    }

    return $toBeUploaded;
  }

  private function checkIfBackupCanBeUploaded($type, $taskname) {
    
    $backupPath = BMI_BACKUPS . DIRECTORY_SEPARATOR . $taskname;
    $backupSize = file_exists($backupPath) ? filesize($backupPath) : -1;

    switch($type) {

      case "backupbliss": {
        $storageInfo = $this->backupbliss->getStorageInfo();

        if ($storageInfo["used_space_percent"] > 80 && $storageInfo["used_space_percent"] <= 100) {
          $error_message_notice = 'It seems you already used more than 80% of your space. <a href="'.BMI_AUTHOR_URI . 'pricing'.'">Get more storage now.</a>';
    
          $this->backupbliss->showNotice("storage_warn", $error_message_notice, 60 * 60);
        } elseif($storageInfo["used_space_percent"] > 100) {
          $error_message_notice = 'You’re using more space than allowed. No new backups will be moved to your storage and some of the <b>existing backups will be deleted very soon</b>. ';
  
          $this->backupbliss->showNotice("upload_issue_space", $error_message_notice, 60 * 60);
        } else {
          $this->backupbliss->removeNotice("storage_warn");
          $this->backupbliss->removeNotice("upload_issue_space");
        }

        if (!$this->getBackupBlissConnectionStatus()) {
          return false;
        }

        

        if (!$this->backupbliss->getNotice("upload_issue_space")) {

          if (isset($storageInfo["remaining_space"]))
          {
            $remaining = $storageInfo["remaining_space"];


            if ($backupSize != -1)
            {
              if ($remaining < $backupSize)
              {
                $error_message_notice = 'Moving backups to your storage is failing or will fail because you don’t have enough space.';

                add_option("bmip_backupbliss_required_space", $backupPath);
                $this->backupbliss->showNotice("upload_issue_space", $error_message_notice, 60 * 60);
                //Triggering the server, so that an alert also get sent
                $this->backupbliss->initiateUploadSession($backupPath);
                return false;
              }
            }
            
          }
          else {
            Logger::error("[BMI] Couldn't fetch quota from BackupBliss!");
          }
        }

        if ($this->backupbliss->getNotice("upload_issue")) {
          return false;
        }

        break;
      }
    }

    if ($this->proajax)
      return $this->proajax->checkIfBackupCanBeUploaded($type, $backupSize);

    return true;
  }

  private function _removeTasksFromDeactivatedClouds($cltype, $toBeUploaded, $task, $queue, $failed) {
    if (sizeof($task) > 0 && isset($task['task'])) {
      $taskname = $task['task'];
      $type = explode('_', $taskname)[0];
      if ($type == $cltype)
        $task = [];
    }

    if (sizeof($queue) > 0) {
      $tasks = array_keys($queue);
      foreach($tasks as $taskname) {
        $type = explode('_', $taskname)[0];
        if ($type == $cltype)
          unset($queue[$taskname]);
      }
    }

    if (sizeof($failed) > 0) {
      $tasks = array_keys($failed);
      foreach($tasks as $taskname) {
        $type = explode('_', $taskname)[0];
        if ($type == $cltype)
          unset($failed[$taskname]);
      }
    }

    $toBeUploaded['current_upload'] = $task;
    $toBeUploaded['queue'] = $queue;
    $toBeUploaded['failed'] = $failed;
    update_option("bmip_to_be_uploaded", $toBeUploaded);
  }

  public function getDeactivatedClouds() {
    $deactivatedClouds = [];
    if (!$this->getBackupBlissConnectionStatus()) $deactivatedClouds[] = "backupbliss";

    if ($this->proajax)
      $deactivatedClouds = array_merge($this->proajax->getDeactivatedClouds(), $deactivatedClouds);

    return $deactivatedClouds;
  }
  
  public function keepAliveUnAuthorizedRefreshExec() {

    $isOnGoing = get_transient('bmip_upload_ongoing');
    if ($isOnGoing === '1') return ['status' => 'success']; //Returning success so that the auto pinger will keep on pinging


    $toBeUploaded = $this->fetchToBeUploaded();

    

    $task = $toBeUploaded['current_upload'];
    $queue = $toBeUploaded['queue'];
    $failed = isset($toBeUploaded['failed']) ? $toBeUploaded['failed'] : [];

    foreach ($this->getDeactivatedClouds() as $cloudType)
      $this->_removeTasksFromDeactivatedClouds($cloudType, $toBeUploaded, $task, $queue, $failed);

    
    //Check for uploads 
    if (get_transient('bmip_check_for_backups_to_upload') !== "wait") {
      set_transient("bmip_check_for_backups_to_upload", "wait", 10);
      $this->checkForBackupsToUpload();
      //Refresh variables after checking for backups to upload
      $toBeUploaded = $this->fetchToBeUploaded();
      $task = $toBeUploaded['current_upload'];
      $queue = $toBeUploaded['queue'];
    }

    $shouldBeQueued = false;

    if (sizeof($task) > 0 && isset($task['task'])) {
      $taskname = $task['task'];
      $type = explode('_', $taskname)[0];


      if (!$this->checkIfBackupCanBeUploaded($type, $task['name'])) {
        $this->removeCurrentTask($toBeUploaded);
        $type = null; //Set type as null so that no actions will be taken
        $shouldBeQueued = true; //Set it to queue the next task
      }

      // BackupBliss
      if ($type == 'backupbliss') {

        if (!isset($task['uploadSession'])) {

          $backupPath = BMI_BACKUPS . DIRECTORY_SEPARATOR . $task['name'];
          $manifestPath = BMI_BACKUPS . DIRECTORY_SEPARATOR . $task['json'];
          $uploadSession = $this->backupbliss->initiateUploadSession($backupPath);
          if (!$uploadSession)
          {
            $this->removeCurrentTask($toBeUploaded);
            return ['status' => 'success'];
          }

          $availableMemory = BMP::getAvailableMemoryInBytes();
          $bytesPerRequest = intval($availableMemory / 4);

          $toBeUploaded['current_upload']['bytesPerRequest'] = $bytesPerRequest;
          $toBeUploaded['current_upload']['uploadSession'] = $uploadSession;
          $toBeUploaded['current_upload']['manifestPath'] = $manifestPath;
          $toBeUploaded['current_upload']['backupPath'] = $backupPath;
          $toBeUploaded['current_upload']['batch'] = 1;

          update_option('bmip_to_be_uploaded', $toBeUploaded);

          if (!file_exists($backupPath)) delete_option('bmip_to_be_uploaded');
          return ['status' => 'success'];
        } else {

          if (!file_exists($task['backupPath'])) {
            delete_option('bmip_to_be_uploaded');
            return ['status' => 'success'];
          }

          $this->backupbliss->uploadFile($task['uploadSession'], $task['backupPath'], $task['manifestPath'], $task['md5'], $task['batch'], $task['bytesPerRequest']);
          return ['status' => 'success'];
        }
      } elseif ($this->proajax) {
        $ret = $this->proajax->processClouds($type, $task, $toBeUploaded, $taskname);
        if ($ret["status"] !== "no_tasks")
          return $ret;
      }

    } else {
      $shouldBeQueued = true;
    }

    if ($shouldBeQueued && sizeof($queue) > 0) {

      $tasks = array_keys($queue);
      if (sizeof($tasks) > 0) {

        $selectedTask = $tasks[0];
        $cloudType = explode("_", $selectedTask)[0];
        $toBeProcessed = $queue[$selectedTask];

        if ($this->checkIfBackupCanBeUploaded($cloudType, $toBeProcessed['name'])) {
          $toBeUploaded['current_upload'] = [
            'task' => $selectedTask,
            'name' => $toBeProcessed['name'],
            'md5' => $toBeProcessed['md5'],
            'json' => $toBeProcessed['json'],
            'progress' => '0%'
          ];
        } else {
          if (isset($toBeUploaded['failed']))
            $toBeUploaded['failed'][$selectedTask] = 1; //Mark the task as failed
        }
        
        unset($toBeUploaded['queue'][$selectedTask]);
        update_option('bmip_to_be_uploaded', $toBeUploaded);

        //Return success if there are more tasks in the queue, so auto pinger can ping rapidly
        return ['status' => sizeof($queue) > 0 ? 'success' : 'no_tasks'];
      } else return ['status' => 'no_tasks'];
    } else return ['status' => 'no_tasks'];

    return ['status' => 'no_tasks'];
  }
}
