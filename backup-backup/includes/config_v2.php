<?php

namespace BMI\Plugin\Dashboard;

// Exit on direct access
if (!defined('ABSPATH')) exit;

class BMI_Config_V2 {

  private static $instance = null;
  private $settings = null;
  const OPTION_NAME = 'bmi_settings';

  private function __construct() {
    $this->load_settings();
  }

  public static function get_instance() {
    if (self::$instance == null) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  private function load_settings() {
    $db_settings = get_option(self::OPTION_NAME, false);

    if ($db_settings === false || empty($db_settings)) {
      if (!$this->migrate_old_config()) {
        $this->initialize_defaults();
      }
    } else {
      $this->settings = is_string($db_settings) ? json_decode($db_settings, true) : $db_settings;
      if (!is_array($this->settings)) {
        $this->settings = (array) $this->settings;
      }
      if (isset($this->settings['STORAGE::LOCAL::PATH'])) {
        $this->settings['STORAGE::LOCAL::PATH'] = $this->normalizePathSeparators($this->settings['STORAGE::LOCAL::PATH']);
      }
    }
  }

  private function migrate_old_config() {
    $config_to_migrate = [];
    $old_config_path = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'backup-migration-config.php';

    // Try modern PHP config file first
    if (file_exists($old_config_path)) {
      $bmi_config_contents = file_get_contents($old_config_path);
      if (strpos($bmi_config_contents, '<?php //') !== false) {
        $bmi_config_contents = substr($bmi_config_contents, 8);
      }
      $config_to_migrate = json_decode($bmi_config_contents, true);
      @unlink($old_config_path);
    } 

    if (is_array($config_to_migrate) && !empty($config_to_migrate)) {
      $this->settings = $config_to_migrate;
      $this->save_settings();
      return true;
    }
    
    return false;
  }

  public function initialize_defaults() {
    // Load default and additional
    if (defined('BMI_CONFIG_DEFAULT') && file_exists(BMI_CONFIG_DEFAULT)) {
      $defaults = json_decode(file_get_contents(BMI_CONFIG_DEFAULT), true);
    } else {
      $defaults = [];
    }

    if (!is_array($defaults)) {
      $defaults = [];
    }

    $this->settings = $defaults;

    $localStoragePath = $this->get('STORAGE::LOCAL::PATH');
    if ($localStoragePath == 'default') {
      $localStoragePath = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'backup-migration-' . bmi_config_random_string(10);
      $this->set('STORAGE::LOCAL::PATH', $localStoragePath);
    }

    $bmiLogFilesSuffix = $this->get('STORAGE::LOCAL::LOGS::SUFFIX');
    if (empty($bmiLogFilesSuffix)) {
      $bmiLogFilesSuffix = bmi_config_random_string(10);
      $this->set('STORAGE::LOCAL::LOGS::SUFFIX', $bmiLogFilesSuffix);
    }

    if (!$this->get('REQUEST:SECRET')) {
      $this->set('REQUEST:SECRET', bmi_config_random_string(16));
    }

    $this->save_settings();
  }

  public function get($setting) {
    if (isset($this->settings[$setting])) {
      return $this->settings[$setting];
    }
    return false;
  }

  public function normalizePathSeparators($path) {
    return str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path);
  }

  public function set($setting, $value) {
    // Allow empty
    $allow_empty = ['OTHER:CLI:PATH'];

    // Check if setting is not empty
    if (isset($value) && (!is_string($value) || (in_array($setting, $allow_empty) || strlen(trim($value)) > 0))) {
      
      // If the value is the same, no need to update DB, just return true
      if (isset($this->settings[$setting]) && $this->settings[$setting] === $value) {
        return true;
      }
      
      $this->settings[$setting] = $value;
      $this->save_settings();
      return true;
    }
    return false;
  }

  public function get_all() {
    return $this->settings;
  }

  private function save_settings() {
    update_option(self::OPTION_NAME, $this->settings);
    return true;
  }
}
