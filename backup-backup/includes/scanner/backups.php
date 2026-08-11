<?php

  // Namespace
  namespace BMI\Plugin\Scanner;

  // Use
  use BMI\Plugin\BMI_Logger AS Logger;
  use BMI\Plugin\Zipper\BMI_Zipper AS Zipper;
  use BMI\Plugin\Zipper\Zip AS Zip;
  use BMI\Plugin\External\BMI_External_Storage as ExternalStorage;
  use BMI\Plugin\Backup_Migration_Plugin as BMP;
  use BMI\Plugin\Services\FileHasher;

  // Exit on direct access
  if (!defined('ABSPATH')) exit;

  /**
   * Main Backup Scanner Logic
   *
   * Responsible for scanning local and cloud backup directories, extracting and
   * caching backup manifests, verifying integrity via hashing utilities, and formatting
   * backup metadata for compatibility with UI and AJAX handlers.
   *
   */
  class BMI_BackupsScanner {

    /**
     * Singleton instance of the backups scanner.
     * @var BMI_BackupsScanner|null
     */
    private static $instance;

    /**
     * Zip utility service used for checking locks and reading inner manifest files.
     * @var Zipper
     */
    private $zipper;

    /**
     * External storage interface used for downloading/retrieving cloud backups.
     * @var ExternalStorage
     */
    private $externalStorage;

    /**
     * Cache of scanned local backups to prevent multiple filesystem scans during one execution cycle.
     * @var array
     */
    private $localBackups = [];

    /**
     * In-memory cache map linking local ZIP files to their calculated hash strings.
     * Maps `[filename => hash]`.
     * @var array
     */
    private $hashSummary = [];

    /**
     * BMI_BackupsScanner constructor.
     *
     * Initializes dependencies (optionally injected for testing) and loads the
     * hash summary cache file into memory.
     *
     * @param Zipper|null          $zipper          Optional Zipper instance.
     * @param ExternalStorage|null $externalStorage Optional ExternalStorage instance.
     */
    public function __construct($zipper = null, $externalStorage = null) {
      $this->loadRequiredClasses();
      $this->zipper = is_null($zipper) ? new Zipper() : $zipper;
      $this->externalStorage = is_null($externalStorage) ? new ExternalStorage() : $externalStorage;

      $this->hashSummary = $this->getHashSummary();
    }

    /**
     * Get or instantiate the singleton instance of the scanner.
     *
     * Allows custom dependency injection if any parameters are supplied, forcing
     * instantiation of a new instance.
     *
     * @param Zipper|null          $zipper          Optional Zipper instance.
     * @param ExternalStorage|null $externalStorage Optional ExternalStorage instance.
     * @return BMI_BackupsScanner
     */
    public static function getInstance($zipper = null, $externalStorage = null) {

      if(is_null(self::$instance) || !is_null($zipper) || !is_null($externalStorage)) {
        self::$instance = new self($zipper, $externalStorage);
      }

      return self::$instance;
    }

    /**
     * Dynamically requires dependencies if they haven't been loaded yet.
     *
     * @return void
     */
    private function loadRequiredClasses() {
      if (!class_exists('BMI_Zipper')) require_once BMI_INCLUDES . '/zipper/zipping.php';
      if (!class_exists('ExternalStorage')) require_once BMI_INCLUDES . '/external/controller.php';
      if (!class_exists('FileHasher')) require_once BMI_INCLUDES . '/services/class-file-hasher.php';
    }

    /**
     * Retrieve the cache summary mapping ZIP files to computed hashes.
     *
     * Loads the serialized array stored inside `hash_summary.php`.
     *
     * @return array Hash summary array mapping `[zip_filename => hash]`.
     */
    private function getHashSummary() {

      $hashsummaryFilePath = BMI_BACKUPS . DIRECTORY_SEPARATOR. 'hash_summary.php';

      $hashsummary = [];
      if (file_exists($hashsummaryFilePath)) {
        $hashsummary = file_get_contents($hashsummaryFilePath);
        $hashsummary = substr($hashsummary, 18, -2);
        if (is_serialized($hashsummary)) {
          $hashsummary = maybe_unserialize($hashsummary);
        }
      }
      return $hashsummary;
    }

    /**
     * Update the hash summary file.
     *
     * @param object $manifest The manifest object.
     * @param string $manifestFilePath The path to the manifest file.
     * @return void
     */
    private function updateManifest($manifest, $manifestFilePath) {
      file_put_contents($manifestFilePath, json_encode($manifest));
    }

    /**
     * Clear the hash summary cache.
     */
    public function clearCache() {
      $this->hashSummary = [];
      $this->updateHashSummaryFile();
    }

    /**
     * Update/Persist the hash summary file.
     *
     * Serializes the current hash summary mapping and writes it to `hash_summary.php` with php exit code.
     *
     * @return void
     */
    private function updateHashSummaryFile() {
      $hashSummaryFilePath = BMI_BACKUPS . DIRECTORY_SEPARATOR. 'hash_summary.php';
      $cacheMd5String = "<?php exit; \$x = '" . serialize($this->hashSummary) . "';";
      
      $fp = @fopen($hashSummaryFilePath, 'c');
      if ($fp && flock($fp, LOCK_EX)) {
        ftruncate($fp, 0);
        fwrite($fp, $cacheMd5String);
        fflush($fp);
        flock($fp, LOCK_UN);
      }
      if ($fp) fclose($fp);
    }

    /**
     * Delete Backup Manifest.
     *
     * @param string $backupName the name of the backup
     * @param string $backupHash the hash of the backup
     * @return true
     */
    public function deleteBackupManifest($backupName, $backupHash = null) {

      if ($backupHash && file_exists(BMI_BACKUPS . DIRECTORY_SEPARATOR . $backupHash . '.json')) {
        unlink(BMI_BACKUPS . DIRECTORY_SEPARATOR . $backupHash . '.json');
      }

      $hash = isset($this->hashSummary[$backupName]) ? $this->hashSummary[$backupName] : null;
      if ($hash == null) return true;
      
      $manifestFile = BMI_BACKUPS . DIRECTORY_SEPARATOR . $hash . '.json';

      if (file_exists($manifestFile)) {
        unlink($manifestFile);
        unset($this->hashSummary[$backupName]);
        $this->updateHashSummaryFile();
      }

      return true;
    }

    /**
     * Extract the manifest object from the backup Zip file.
     *
     * @param string $backupPath Path to the backup zip file.
     * @return object|bool Manifest object on success, or false if failed.
     */
    public function getManifestFromZip($backupPath) {
      return $this->zipper->getZipFileContent($backupPath, 'bmi_backup_manifest.json');
    }

    /**
     * Computes the hash of a backup file.
     *
     * If the backup is marked as streamed in the manifest, it computes the chained hash (v2).
     * Otherwise, it computes the standard hash (v1).
     *
     * @param string $backupPath The path to the backup file.
     * @param object $manifest The manifest object containing backup information.
     * @return string The computed hash of the backup file.
     */
    public function getBackupHash($backupPath, $manifest) {
      if ($manifest && property_exists($manifest, 'is_streamed') && $manifest->is_streamed) {
        return FileHasher::compute($backupPath, FileHasher::CHAINED);
      } else {
        return FileHasher::compute($backupPath);
      }
    }

    /**
     * Scans the given directory for backups.
     *
     * Memory-optimized using a PHP Generator to yield items sequentially.
     * Filters files keeping only those with zip, tar, or gz extensions.
     *
     * @param string $path Absolute path to the directory to scan.
     * @return \Generator Yields arrays containing:
     *                     - 'filename' => string
     *                     - 'size' => int
     */
    public function scanBackupDir($path) {

      $dirs = new \DirectoryIterator($path);
      foreach ($dirs as $fileInfo) {

        if (!$fileInfo->isFile()) continue;
        if (in_array($fileInfo->getExtension(), ['zip', 'tar', 'gz'])) {

          yield [
            'filename' => $fileInfo->getFilename(),
            'size' => $fileInfo->getSize()
          ];
        }

      }
    }

    /**
     * Retrieve the structured manifest array for a specific backup file.
     *
     * Resolves the manifest by checking the `hash_summary.php` cache first,
     * fallback to extracting from ZIP and computing the file hash. Cache files are written to
     * `{hash}.json` under the backups directory.
     *
     * @param string $backupPath Absolute path to the backup ZIP file.
     * @return array|false Formatted manifest array on success, false on failure.
     *                     Array indices:
     *                     - 0 => Name with Zip Name (string: "name#%&zipname")
     *                     - 1 => Backup creation date (string: "Y-m-d H:i:s")
     *                     - 2 => Total number of files included (int)
     *                     - 3 => Manifest detail string (string)
     *                     - 4 => File size of the backup (int)
     *                     - 5 => Lock status (string: 'locked' | 'unlocked')
     *                     - 6 => Scheduled/cron backup (bool)
     *                     - 7 => Checksum hash (string)
     *                     - 8 => Domain origin (string)
     */
    public function getManifest($backupPath) {

      if (!file_exists($backupPath)) {
        Logger::error("Can't get manifest from backup path: " . $backupPath . " its already deleted");
        return false;
      }

      // Get manifest content
      $backupName = basename($backupPath);

      $hash = '';
      if (isset($this->hashSummary[$backupName])) {
        $hash = $this->hashSummary[$backupName];
        $manifestFilePath = BMI_BACKUPS . DIRECTORY_SEPARATOR. $hash . '.json';
        if (file_exists($manifestFilePath)) {
          $manifest = json_decode(file_get_contents($manifestFilePath));
          if (!$manifest) {
            $this->deleteBackupManifest($backupName);
            return $this->getManifest($backupPath);
          }
        } else {
          unset($this->hashSummary[$backupName]);
          $this->updateHashSummaryFile();
          return $this->getManifest($backupPath);
        }
      } else {
        $manifest = $this->getManifestFromZip($backupPath);
        if (!$manifest) {
          if (!self::isBackupRunning($backupPath)) {
            Logger::error("Failed to extract manifest from backup: " . $backupPath);
          }
          return false;
        }
        $hash = $this->getBackupHash($backupPath, $manifest);
        $manifestFilePath = BMI_BACKUPS . DIRECTORY_SEPARATOR . $hash . '.json';
        if (!file_exists($manifestFilePath)) {
          file_put_contents($manifestFilePath, json_encode($manifest));
        }
        $this->hashSummary[$backupName] = $hash;
        $this->updateHashSummaryFile();
      }

      return $this->formatManifest($manifest, $hash, $backupPath);
    }

    /**
     * Formats raw manifest objects to match the legacy array format expected by the plugin.
     *
     * Touches/refreshes the JSON manifest file access time on disk.
     *
     * @param object $manifest   The manifest JSON object.
     * @param string $hash       The calculated hash string of the backup.
     * @param string $backupPath Path to the backup zip file.
     * @return array Legacy formatted manifest array.
     */
    private function formatManifest($manifest, $hash, $backupPath) {
      $backupName = basename($backupPath);
      $manifestFilePath = BMI_BACKUPS . DIRECTORY_SEPARATOR. $hash . '.json';
      
      $res = [];
      $res[] = $manifest->name . '#%&' . $backupName;
      $res[] = $manifest->date;
      $res[] = $manifest->files;
      $res[] = $manifest->manifest;
      $res[] = @filesize($backupPath);
      if (!isset($manifest->is_locked)) {
        $manifest->is_locked = $this->zipper->is_locked_zip($backupPath) ? 'locked' : 'unlocked';
        $this->updateManifest($manifest, $manifestFilePath);
        $res[] = $manifest->is_locked;
      } else {
        $res[] = $manifest->is_locked;
      }
      $res[] = $manifest->cron;
      $res[] = $hash;
      $res[] = sanitize_text_field($manifest->domain);
      $res['is_encrypted'] = isset($manifest->is_encrypted) ? (bool) $manifest->is_encrypted : false;

      touch($manifestFilePath);
      
      return $res;
    }

    /**
     * Checks if a backup is currently running by looking for a `.running` file in the backups directory.
     *
     * @param string $backupPath The path to the backup file.
     * @return bool True if the backup is running, false otherwise.
     */
    public static function isBackupRunning($backupPath) {
      $runningFilePath = BMI_BACKUPS . DIRECTORY_SEPARATOR . '.running';
      if (!file_exists($runningFilePath)) return false;

      $runningBackupFilename = trim(file_get_contents($runningFilePath));
      return $runningBackupFilename === basename($backupPath);
    }

    /**
     * Scans and compiles all available backups.
     *
     * Includes option to filter by local scope only.
     * Integrates each backup with its upload status check.
     *
     * @param string $scope The scan scope ("all" or "local").
     * @return array Multi-dimensional array:
     *               - 'local'    => [filename => manifest_array, ...]
     *               - 'external' => [cloud_manifests, ...] (only if scope is "all")
     */
    public function getAvailableBackups($scope = "all") {

      if (!empty($this->localBackups) && $scope === 'local') return [ 'local' => $this->localBackups ];
      
      $manifests = [];
      $external = [];

      $uploadedBackupStatus = get_option('bmi_uploaded_backups_status', []);
      foreach($this->scanBackupDir(BMI_BACKUPS) as $backup) {

        $manifest = $this->getManifest(BMI_BACKUPS . DIRECTORY_SEPARATOR . $backup['filename']);
        if (!$manifest) continue;
        $hash = $manifest[7];
        if (isset($uploadedBackupStatus[$hash])) {
          $manifest[] = $uploadedBackupStatus[$hash];
        } else {
          $manifest[] = [];
        }
        $manifests[$backup['filename']] = $manifest;
      }

      $cacheUpdated = false;
      foreach ($this->hashSummary as $cachedFilename => $cachedHash) {
        if (!isset($manifests[$cachedFilename])) {
          unset($this->hashSummary[$cachedFilename]);
          $cacheUpdated = true;
        }
      }

      if ($cacheUpdated) {
        $this->updateHashSummaryFile();
      }

      if (empty($this->localBackups)) {
        $this->localBackups = $manifests; 
      }

      if ($scope == "local") {
        return [ 'local' => $this->localBackups ];
      }

      $external = $this->externalStorage->getExternalBackups();

      return [ 'local' => $this->localBackups, 'external' => $external ];

    }

    /**
     * Delete a single backup file from local and/or cloud storage, and remove its manifest.
     *
     * @param string $fileName
     * @param array $data
     * @param bool $deleteCloud
     * @return void
     */
    public function deleteBackup($fileName, $data, $deleteCloud) {
      Logger::log("Deleting the following backup: " . $fileName);
      $fileName = sanitize_file_name($fileName);
      $fileHash = isset($data['hash']) ? $data['hash'] : '';
      $existInCloud = isset($data['isCloud']) && ($data['isCloud'] === 'true' || $data['isCloud'] === true);
      $fileHash = preg_match('/^[a-f0-9]{32}$/i', $fileHash) ? $fileHash : false;

      if ($deleteCloud && $fileHash) {
        if (!class_exists('BMI\Plugin\External\BMI_External_Storage_Manager')) {
          require_once BMI_INCLUDES . '/external/external-storage-manager.php';
        }
        $externalStorageManager = \BMI\Plugin\External\BMI_External_Storage_Manager::getInstance();
        try {
          $externalStorageManager->deleteBackup($fileHash);
        } catch (\Throwable $e) {
          Logger::log("Error deleting cloud backup: " . $e->getMessage());
        }
        $this->deleteBackupManifest($fileName,$fileHash);
      }

      if (file_exists(BMI_BACKUPS . '/' . $fileName)) {
        Logger::log('Local backup file is being deleted: ' . $fileName);
        unlink(BMI_BACKUPS . '/' . $fileName);
      }

      if (!$existInCloud && $fileHash) {
        $this->deleteBackupManifest($fileName, $fileHash);
      }
    }

}

