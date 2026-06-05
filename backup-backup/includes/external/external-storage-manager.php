<?php

// Namespace
namespace BMI\Plugin\External;

// Use
use BMI\Plugin\Dashboard as Dashboard;

// Exit on direct access
if (!defined('ABSPATH')) {
  exit;
}

/**
 * BMI_External_Storage_Manager
 * 
 * Centralized manager for all external storage services.
 * Provides clean methods to check enabled/configured storages without hardcoding option names.
 */
class BMI_External_Storage_Manager {

  private static $instance = null;
  private $storageConfig = [];
  private $enabledStorages = [];
  private $configuredStorages = [];

  /**
   * Get singleton instance
   */
  public static function getInstance() {
    if (self::$instance === null) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  /**
   * Constructor - Initialize storage configuration
   */
  private function __construct() {
    $this->initializeStorageConfig();
  }

  /**
   * Initialize the storage configuration array
   * Maps service names to their option keys, classes, and files
   */
  private function initializeStorageConfig() {
    $this->storageConfig = [
      'backupbliss' => [
        'option_key' => 'STORAGE::EXTERNAL::BACKUPBLISS',
        'class' => 'BMI_External_BackupBliss',
        'file' => BMI_INCLUDES . '/external/backupbliss.php',
        'label' => 'BackupBliss',
        'namespace' => '\\BMI\\Plugin\\External\\BMI_External_BackupBliss'
      ],
      'dropbox' => [
        'option_key' => 'STORAGE::EXTERNAL::DROPBOX',
        'class' => 'BMI_External_Dropbox',
        'file' => BMI_INCLUDES . '/external/dropbox.php',
        'label' => 'Dropbox',
        'namespace' => '\\BMI\\Plugin\\External\\BMI_External_Dropbox'
      ],
      'gdrive' => [
        'option_key' => 'STORAGE::EXTERNAL::GDRIVE',
        'class' => 'BMI_External_GDrive',
        'file' => BMI_INCLUDES . '/external/google-drive.php',
        'label' => 'Google Drive',
        'namespace' => '\\BMI\\Plugin\\External\\BMI_External_GDrive'
      ],
      'ftp' => [
        'option_key' => 'STORAGE::EXTERNAL::FTP',
        'class' => 'BMI_External_FTP',
        'file' => BMI_INCLUDES . '/external/ftp.php',
        'label' => 'FTP',
        'namespace' => '\\BMI\\Plugin\\External\\BMI_External_FTP'
      ],
      'aws' => [
        'option_key' => 'STORAGE::EXTERNAL::AWS',
        'class' => 'BMI_External_S3',
        'file' => BMI_INCLUDES . '/external/s3.php',
        'label' => 'Amazon S3',
        'namespace' => '\\BMI\\Plugin\\External\\BMI_External_S3',
        'constructor_args' => ['aws']
      ],
      'wasabi' => [
        'option_key' => 'STORAGE::EXTERNAL::WASABI',
        'class' => 'BMI_External_S3',
        'file' => BMI_INCLUDES . '/external/s3.php',
        'label' => 'Wasabi',
        'namespace' => '\\BMI\\Plugin\\External\\BMI_External_S3',
        'constructor_args' => ['wasabi']
      ]
    ];

    // Allow pro version or addons to register additional storages
    $this->storageConfig = apply_filters('bmi_external_storages', $this->storageConfig);
  }

  /**
   * Get all available storage services
   * 
   * @return array List of all registered storage services
   */
  public function getAvailableStorages() {
    return array_keys($this->storageConfig);
  }

  /**
   * Get all storage configurations
   * 
   * @return array Complete storage configuration array
   */
  public function getStorageConfigs() {
    return $this->storageConfig;
  }

  /**
   * Get configuration for a specific storage
   * 
   * @param string $serviceName The service name (e.g., 'dropbox', 'gdrive')
   * @return array|null Storage configuration or null if not found
   */
  public function getStorageConfig($serviceName) {
    return isset($this->storageConfig[$serviceName]) ? $this->storageConfig[$serviceName] : null;
  }

  /**
   * Check if a storage service is enabled (option is true)
   * 
   * @param string $serviceName The service name (e.g., 'dropbox', 'gdrive')
   * @return bool True if enabled, false otherwise
   */
  public function isStorageEnabled($serviceName) {
    $config = $this->getStorageConfig($serviceName);
    if (!$config) {
      return false;
    }

    $value = Dashboard\bmi_get_config($config['option_key']);
    return ($value === true || $value === 'true');
  }

  /**
   * Get list of all enabled storage services
   * 
   * @param bool $skipCache Whether to skip cache and force fresh check
   * @return array Array of enabled service names
   */
  public function getEnabledStorages($skipCache = false) {
    if (!$skipCache && !empty($this->enabledStorages)) {
      return $this->enabledStorages;
    }

    $enabled = [];
    foreach ($this->storageConfig as $serviceName => $config) {
      if ($this->isStorageEnabled($serviceName)) {
        $enabled[] = $serviceName;
      }
    }

    $this->enabledStorages = $enabled;
    return $this->enabledStorages;
  }

  /**
   * Check if ANY external storage is enabled
   * 
   * @param bool $skipCache Whether to skip cache and force fresh check
   * @return bool True if at least one storage is enabled
   */
  public function isAnyStorageEnabled($skipCache = false) {
    return count($this->getEnabledStorages($skipCache)) > 0;
  }

  /**
   * Get an instance of a storage class
   * 
   * @param string $serviceName The service name (e.g., 'dropbox', 'gdrive')
   * @return object|null Instance of the storage class or null if not found
   */
  public function getStorageInstance($serviceName) {
    $config = $this->getStorageConfig($serviceName);
    if (!$config) {
      return null;
    }

    // Require the file if it exists
    if (file_exists($config['file'])) {
      require_once $config['file'];
    } else {
      return null;
    }

    // Create instance with constructor args if specified
    $className = $config['namespace'];
    if (isset($config['constructor_args'])) {
      return new $className(...$config['constructor_args']);
    } else {
      return new $className();
    }
  }

  /**
   * Verify if a storage service is fully configured (enabled + connection verified)
   * 
   * @param string $serviceName The service name
   * @return bool True if storage is enabled and connection is verified
   */
  public function isStorageConfigured($serviceName) {
    $config = $this->getStorageConfig($serviceName);
    if (!$config) {
      return false;
    }

    $instance = $this->getStorageInstance($serviceName);
    if ($instance && method_exists($instance, 'verifyConnection')) {
      try {
        $result = $instance->verifyConnection();
        return $result === true || (is_array($result) && isset($result['result']) && $result['result'] === 'connected');
      } catch (\Exception $e) {
        return false;
      }
    }

    // For storages without verify method, just check if enabled
    return true;
  }

  /**
   * Get list of fully configured storage services
   * 
   * @param bool $skipCache Whether to skip cache and force fresh check
   * @return array Array of configured service names with details
   */
  public function getConfiguredStorages($skipCache = false) {
    if (!$skipCache && !empty($this->configuredStorages)) {
      return $this->configuredStorages;
    }

    $configured = [];
    $enabledStorages = $this->getEnabledStorages($skipCache);

    foreach ($enabledStorages as $serviceName) {
      if ($this->isStorageConfigured($serviceName)) {
        $config = $this->getStorageConfig($serviceName);
        $configured[$serviceName] = [
          'name' => $serviceName,
          'label' => $config['label'],
          'option_key' => $config['option_key']
        ];
      }
    }

    $this->configuredStorages = $configured;
    return $this->configuredStorages;
  }

  /**
   * Check if ANY external storage is fully configured
   * 
   * @param bool $skipCache Whether to skip cache and force fresh check
   * @return bool True if at least one storage is configured
   */
  public function isAnyStorageConfigured($skipCache = false) {
    return count($this->getConfiguredStorages($skipCache)) > 0;
  }

  /**
   * Get detailed status of all storages
   * 
   * @param bool $skipCache Whether to skip cache and force fresh check
   * @return array Array with each storage's status
   */
  public function getAllStoragesStatus($skipCache = false) {
    $status = [];
    
    foreach ($this->storageConfig as $serviceName => $config) {
      $enabled = $this->isStorageEnabled($serviceName);
      $configured = false;
      
      if ($enabled) {
        $configured = $this->isStorageConfigured($serviceName);
      }

      $status[$serviceName] = [
        'label' => $config['label'],
        'enabled' => $enabled,
        'configured' => $configured,
        'option_key' => $config['option_key']
      ];
    }

    return $status;
  }

  /**
   * Get count of enabled storages
   * 
   * @param bool $skipCache Whether to skip cache
   * @return int Number of enabled storages
   */
  public function getEnabledStoragesCount($skipCache = false) {
    return count($this->getEnabledStorages($skipCache));
  }

  /**
   * Get count of configured storages
   * 
   * @param bool $skipCache Whether to skip cache
   * @return int Number of configured storages
   */
  public function getConfiguredStoragesCount($skipCache = false) {
    return count($this->getConfiguredStorages($skipCache));
  }

  /**
   * Check if specific storages are enabled (OR condition)
   * 
   * @param array $serviceNames Array of service names to check
   * @return bool True if any of the specified storages is enabled
   */
  public function isAnyOfTheseEnabled($serviceNames) {
    foreach ($serviceNames as $serviceName) {
      if ($this->isStorageEnabled($serviceName)) {
        return true;
      }
    }
    return false;
  }

  /**
   * Check if all specific storages are enabled (AND condition)
   * 
   * @param array $serviceNames Array of service names to check
   * @return bool True if all specified storages are enabled
   */
  public function areAllEnabled($serviceNames) {
    foreach ($serviceNames as $serviceName) {
      if (!$this->isStorageEnabled($serviceName)) {
        return false;
      }
    }
    return true;
  }

  /**
   * Check available space for a specific storage service
   * 
   * @param string $serviceName The service name (e.g., 'dropbox', 'gdrive')
   * @return int|false Available space in bytes or false on failure
   */
  public function getAvailableSpace($serviceName) {
    $instance = $this->getStorageInstance($serviceName);
    if ($instance && method_exists($instance, 'getAvailableSpace')) {
      try {
        return $instance->getAvailableSpace();
      } catch (\Exception $e) {
        return false;
      }
    }
    return false;
  }

}
