<?php

namespace BMI\Plugin\Dashboard;

// Exit on direct access
if (!defined('ABSPATH')) exit;

/**
 * Loads, validates, repairs, persists, and reports plugin configuration.
 *
 * The primary configuration is read and written directly through wpdb so it
 * does not depend on the WordPress option cache. A WordPress option containing
 * the last verified configuration is maintained separately for recovery.
 */
class BMI_Config_V2 {

  private static $instance = null;
  private $settings = null;
  const OPTION_NAME = 'bmi_settings';
  const FAILURE_OPTION_NAME = 'bmi_config_failure';
  const RECOVERY_OPTION_NAME = 'bmi_settings_last_known_good';
  const MAX_FAILURES = 50;
  const MAX_FAILURE_LENGTH = 512;
  const FAILURE_CORRUPTED_CONFIGURATION = 1001;
  const FAILURE_MISSING_SETTING = 1002;
  const FAILURE_MIGRATION_FAILED = 1003;
  const FAILURE_LEGACY_CONFIGURATION_CORRUPTED = 1004;
  const FAILURE_CONFIGURATION_RECOVERED = 1005;
  const FAILURE_INVALID_ARRAY_SETTING = 1006;
  const FAILURE_INVALID_LOCAL_PATH = 1007;
  const FAILURE_INVALID_LOG_SUFFIX = 1008;
  const FAILURE_INVALID_REQUEST_SECRET = 1009;
  const FAILURE_INVALID_SETTING = 1010;
  const FAILURE_SETTING_SAVE_FAILED = 1011;
  const FAILURE_CONFIGURATION_SAVE_FAILED = 1012;

  /**
   * Creates the singleton and loads its configuration into memory.
   */
  private function __construct() {
    $this->load_settings();
  }

  /**
   * Returns the shared configuration instance for the current request.
   *
   * @return BMI_Config_V2 Loaded configuration service.
   */
  public static function get_instance() {
    if (self::$instance == null) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  /**
   * Loads the primary configuration directly from the options table.
   *
   * Serialized arrays and legacy JSON strings are supported. Missing defaults,
   * non-array values for array settings and invalid required settings are repaired and
   * persisted. If the primary option is absent or unusable, legacy migration,
   * recovery, and default initialization are attempted in that order.
   *
   * @return void
   */
  private function load_settings() {
    global $wpdb;

    $db_settings_raw = $wpdb->get_var(
      $wpdb->prepare("SELECT `option_value` FROM $wpdb->options WHERE `option_name` = %s LIMIT 1", self::OPTION_NAME)
    );

    if ($db_settings_raw === null) {
      if (!$this->migrate_old_config()) {
        $this->initialize_defaults();
      }
      return;
    }

    $db_settings = maybe_unserialize($db_settings_raw);
    if (is_string($db_settings)) {
      $json_settings = json_decode($db_settings, true);
      if (is_array($json_settings)) {
        $db_settings = $json_settings;
      }
    }

    if (!is_array($db_settings) || empty($db_settings)) {
      self::record_failure(self::FAILURE_CORRUPTED_CONFIGURATION, self::format_failure_value($db_settings));
      if (!$this->migrate_old_config()) {
        $this->initialize_defaults();
      }
      return;
    }

    $defaults = $this->get_defaults();
    $missing_settings = array_diff_key($defaults, $db_settings);
    $this->settings = array_merge($defaults, $db_settings);

    foreach ($missing_settings as $setting => $value) {
      self::record_failure(self::FAILURE_MISSING_SETTING, 'setting: ' . $setting);
    }

    $settings_repaired = !empty($missing_settings);
    if ($this->repair_settings(true)) {
      $settings_repaired = true;
    }

    if ($settings_repaired) {
      if (!$this->save_settings()) {
        $this->settings = $db_settings;
      }
    } else {
      $this->save_recovery_settings();
    }
  }

  /**
   * Migrates the legacy wp-content configuration file into the options table.
   *
   * Defaults are merged before validation. The legacy file is removed only
   * after the new option has been written and read back successfully.
   *
   * @return bool True when a valid legacy configuration was saved successfully.
   */
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
    } 

    if (is_array($config_to_migrate) && !empty($config_to_migrate)) {
      $this->settings = array_merge($this->get_defaults(), $config_to_migrate);
      $this->repair_settings();
      if ($this->save_settings()) {
        @unlink($old_config_path);
        return true;
      }
      self::record_failure(self::FAILURE_MIGRATION_FAILED);
    }

    if (file_exists($old_config_path) && (!is_array($config_to_migrate) || empty($config_to_migrate))) {
      self::record_failure(self::FAILURE_LEGACY_CONFIGURATION_CORRUPTED, self::format_failure_value($bmi_config_contents));
    }

    return false;
  }

  /**
   * Loads and caches the configuration defaults for the current request.
   *
   * @return array Parsed defaults, or an empty array when the defaults file is
   *               unavailable or invalid.
   */
  private function get_defaults() {
    static $cachedDefaults = null;
    if ($cachedDefaults !== null) {
      return $cachedDefaults;
    }

    if (defined('BMI_CONFIG_DEFAULT') && file_exists(BMI_CONFIG_DEFAULT)) {
      $defaults = json_decode(file_get_contents(BMI_CONFIG_DEFAULT), true);
    } else {
      $defaults = [];
    }

    $cachedDefaults = is_array($defaults) ? $defaults : [];
    return $cachedDefaults;
  }

  /**
   * Initializes configuration from recovery data or packaged defaults.
   *
   * A complete last-known-good configuration takes precedence over defaults.
   * Required settings are then recovered or generated. If persistence fails,
   * the in-memory configuration is cleared to avoid using volatile settings.
   *
   * @return bool True when the initialized configuration was persisted.
   */
  public function initialize_defaults() {
    // Load default and additional
    $recoverySettings = get_option(self::RECOVERY_OPTION_NAME, []);
    if (is_array($recoverySettings) && !empty($recoverySettings)) {
      $this->settings = array_merge($this->get_defaults(), $recoverySettings);
      self::record_failure(self::FAILURE_CONFIGURATION_RECOVERED);
    } else {
      $this->settings = $this->get_defaults();
    }
    $this->repair_settings();

    if (!$this->save_settings()) {
      $this->settings = [];
      return false;
    }

    return true;
  }

  /**
   * Repairs non-array values for array settings and required string settings.
   *
   * Array settings are restored from packaged defaults. Valid required values
   * from the last-known-good configuration are preferred, and a new value is
   * generated only when neither the current nor recovery value can be used.
   *
   * @param bool $recordFailures Whether detected invalid values should be recorded.
   * @return bool True when at least one setting was replaced.
   */
  private function repair_settings($recordFailures = false) {
    $repaired = false;
    foreach ($this->get_defaults() as $setting => $defaultValue) {
      $value = isset($this->settings[$setting]) ? $this->settings[$setting] : null;
      if (is_array($defaultValue) && !is_array($value)) {
        if ($recordFailures) {
          self::record_failure(self::FAILURE_INVALID_ARRAY_SETTING, 'setting: ' . $setting);
        }
        $this->settings[$setting] = $defaultValue;
        $repaired = true;
      }
    }

    $recoverySettings = get_option(self::RECOVERY_OPTION_NAME, []);
    if (!is_array($recoverySettings)) {
      $recoverySettings = [];
    }

    $localStoragePath = $this->get('STORAGE::LOCAL::PATH');
    if (!is_string($localStoragePath) || trim($localStoragePath) === '' || $localStoragePath === 'default') {
      if ($recordFailures) {
        self::record_failure(self::FAILURE_INVALID_LOCAL_PATH, self::format_failure_value($localStoragePath));
      }
      if (isset($recoverySettings['STORAGE::LOCAL::PATH']) && is_string($recoverySettings['STORAGE::LOCAL::PATH']) &&
          trim($recoverySettings['STORAGE::LOCAL::PATH']) !== '' && $recoverySettings['STORAGE::LOCAL::PATH'] !== 'default') {
        $this->settings['STORAGE::LOCAL::PATH'] = $recoverySettings['STORAGE::LOCAL::PATH'];
      } else {
        $this->settings['STORAGE::LOCAL::PATH'] = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'backup-migration-' . bmi_config_random_string(10);
      }
      $repaired = true;
    }

    $bmiLogFilesSuffix = isset($this->settings['STORAGE::LOCAL::LOGS::SUFFIX']) ? $this->settings['STORAGE::LOCAL::LOGS::SUFFIX'] : null;
    if (!is_string($bmiLogFilesSuffix) || trim($bmiLogFilesSuffix) === '') {
      if ($recordFailures) {
        self::record_failure(self::FAILURE_INVALID_LOG_SUFFIX, self::format_failure_value($bmiLogFilesSuffix));
      }
      if (isset($recoverySettings['STORAGE::LOCAL::LOGS::SUFFIX']) && is_string($recoverySettings['STORAGE::LOCAL::LOGS::SUFFIX']) &&
          trim($recoverySettings['STORAGE::LOCAL::LOGS::SUFFIX']) !== '') {
        $this->settings['STORAGE::LOCAL::LOGS::SUFFIX'] = $recoverySettings['STORAGE::LOCAL::LOGS::SUFFIX'];
      } else {
        $this->settings['STORAGE::LOCAL::LOGS::SUFFIX'] = bmi_config_random_string(10);
      }
      $repaired = true;
    }

    $requestSecret = isset($this->settings['REQUEST:SECRET']) ? $this->settings['REQUEST:SECRET'] : null;
    if (!is_string($requestSecret) || trim($requestSecret) === '') {
      if ($recordFailures) {
        self::record_failure(self::FAILURE_INVALID_REQUEST_SECRET, self::format_failure_value($requestSecret));
      }
      if (isset($recoverySettings['REQUEST:SECRET']) && is_string($recoverySettings['REQUEST:SECRET']) &&
          trim($recoverySettings['REQUEST:SECRET']) !== '') {
        $this->settings['REQUEST:SECRET'] = $recoverySettings['REQUEST:SECRET'];
      } else {
        $this->settings['REQUEST:SECRET'] = bmi_config_random_string(16);
      }
      $repaired = true;
    }

    return $repaired;
  }

  /**
   * Retrieves one configuration value from the in-memory settings.
   *
   * @param string $setting Configuration key.
   * @return mixed|false Stored value, or false when the key is unavailable.
   */
  public function get($setting) {
    if (is_array($this->settings) && isset($this->settings[$setting])) {
      return $this->settings[$setting];
    }
    return false;
  }
  /**
   * Validates and persists one configuration value.
   *
   * Required strings and array-shaped settings are checked before the in-memory
   * value is changed. If database verification fails, the previous in-memory
   * configuration is restored and the failure is recorded.
   *
   * @param string $setting Configuration key.
   * @param mixed  $value   Value to validate and store.
   * @return bool True when unchanged or persisted successfully; otherwise false.
   */
  public function set($setting, $value) {
    // Allow empty
    $allow_empty = ['OTHER:CLI:PATH', 'STORAGE::EXTERNAL::ONEDRIVE::DIRNAME', 'STORAGE::EXTERNAL::DROPBOX::DIRNAME', 'STORAGE::EXTERNAL::PCLOUD::DIRNAME'];
    $required_strings = ['STORAGE::LOCAL::PATH'];

    // Check if setting is not empty
    $defaults = $this->get_defaults();
    if (!isset($value) || (is_string($value) && !in_array($setting, $allow_empty) && strlen(trim($value)) === 0) ||
        (in_array($setting, $required_strings, true) && (!is_string($value) || trim($value) === '' || $value === 'default')) ||
        (isset($defaults[$setting]) && is_array($defaults[$setting]) && !is_array($value))) {
      self::record_failure(self::FAILURE_INVALID_SETTING, 'setting: ' . $setting . '; ' . self::format_failure_value($value));
      return false;
    }

    // If the value is the same, no need to update DB, just return true
    if (isset($this->settings[$setting]) && $this->settings[$setting] === $value) {
      return true;
    }

    $previousSettings = $this->settings;
    $this->settings[$setting] = $value;
    if ($this->save_settings()) {
      return true;
    }
    $this->settings = $previousSettings;

    self::record_failure(self::FAILURE_SETTING_SAVE_FAILED, 'setting: ' . $setting . '; ' . self::format_failure_value($value));

    return false;
  }

  /**
   * Returns the complete in-memory configuration.
   *
   * @return array Current configuration settings.
   */
  public function get_all() {
    return $this->settings;
  }

  /**
   * Formats recorded configuration failures for troubleshooting submission.
   *
   * Every entry includes its code, bounded diagnostic value, first and latest
   * occurrence timestamps, and occurrence count. Malformed entries are skipped.
   *
   * @return string Comma-separated failure details, or an empty string.
   */
  public static function get_failures_as_string() {
    $failureList = get_option(self::FAILURE_OPTION_NAME, []);
    if (!is_array($failureList)) {
      return '';
    }

    $failures = [];
    foreach ($failureList as $code => $stat) {
      if (!is_array($stat)) continue;
      $failures[] = 'code: ' . $code . '; data: ' . (isset($stat['data']) ? $stat['data'] : '') .
        '; first_time: ' . (isset($stat['first_time']) ? $stat['first_time'] : '') .
        '; last_time: ' . (isset($stat['last_time']) ? $stat['last_time'] : '') .
        '; count: ' . (isset($stat['count']) ? $stat['count'] : '');
    }

    return implode(',', $failures);
  }

  /**
   * Removes all recorded configuration failures.
   *
   * @return void
   */
  public static function clear_failures() {
    delete_option(self::FAILURE_OPTION_NAME);
  }

  /**
   * Persists and verifies the complete in-memory configuration.
   *
   * Direct wpdb access intentionally bypasses the WordPress option cache. The
   * stored serialized value is read back for exact verification. Successful
   * writes also refresh the last-known-good recovery option.
   *
   * @return bool True only when the database contains the exact serialized value.
   */
  private function save_settings() {
    global $wpdb;

    $serializedSettings = maybe_serialize($this->settings);
    $query = $wpdb->prepare("SELECT `option_value` FROM $wpdb->options WHERE `option_name` = %s LIMIT 1", self::OPTION_NAME);
    $storedSettings = $wpdb->get_var($query);

    if ($storedSettings === null) {
      $result = $wpdb->insert(
        $wpdb->options,
        ['option_name' => self::OPTION_NAME, 'option_value' => $serializedSettings, 'autoload' => 'yes'],
        ['%s', '%s', '%s']
      );
    } else {
      $result = $wpdb->update(
        $wpdb->options,
        ['option_value' => $serializedSettings],
        ['option_name' => self::OPTION_NAME],
        ['%s'],
        ['%s']
      );
    }

    $savedSettings = $wpdb->get_var($query);
    if ($result === false || $savedSettings !== $serializedSettings) {
      $dbError = !empty($wpdb->last_error) ? $wpdb->last_error : 'Database write verification failed without an error message.';
      self::record_failure(self::FAILURE_CONFIGURATION_SAVE_FAILED, self::format_failure_value($dbError));
      return false;
    }

    $this->save_recovery_settings();
    return true;
  }

  /**
   * Adds or updates one bounded configuration-failure record.
   *
   * Repeated codes retain their first timestamp, increment their count, and are
   * moved to the newest position. The non-autoloaded list retains at most
   * MAX_FAILURES entries.
   *
   * @param int   $code Stable numeric failure identifier.
   * @param mixed  $data Diagnostic data to retain, bounded by MAX_FAILURE_LENGTH.
   * @return void
   */
  private static function record_failure($code, $data = Null) {
    $code = intval($code);
    $failureList = get_option(self::FAILURE_OPTION_NAME, []);
    if (!is_array($failureList)) {
      $failureList = [];
    }
    $now = time();
    $previousFailure = isset($failureList[$code]) && is_array($failureList[$code]) ? $failureList[$code] : [];
    $firstTime = isset($previousFailure['first_time']) ? $previousFailure['first_time'] : $now;
    $count = isset($previousFailure['count']) ? intval($previousFailure['count']) + 1 : 1;
    unset($failureList[$code]);

    $failureList[$code] = [
      'data' => self::limit_failure_data($data),
      'first_time' => $firstTime,
      'last_time' => $now,
      'count' => $count,
    ];

    update_option(self::FAILURE_OPTION_NAME, array_slice($failureList, -self::MAX_FAILURES, self::MAX_FAILURES, true), false);

  }

  /**
   * Converts diagnostic data to text and enforces the per-entry size limit.
   *
   * Non-scalar values include their PHP type and printable value.
   *
   * @param mixed $data Diagnostic value.
   * @return string Diagnostic text no longer than MAX_FAILURE_LENGTH bytes.
   */
  private static function limit_failure_data($data) {
    if ($data === null) {
      return '';
    }
    if (!is_scalar($data)) {
      $data = self::format_failure_value($data);
    }
    $data = (string) $data;
    return strlen($data) > self::MAX_FAILURE_LENGTH ? substr($data, 0, self::MAX_FAILURE_LENGTH) : $data;
  }

  /**
   * Formats a diagnostic value without hashing or redacting its contents.
   *
   * @param mixed $value Value associated with a configuration failure.
   * @return string PHP type followed by the printable value.
   */
  private static function format_failure_value($value) {
    return 'type: ' . gettype($value) . '; value: ' . print_r($value, true);
  }

  /**
   * Stores the current configuration as the last-known-good recovery copy.
   *
   * The recovery option is non-autoloaded and is updated only when all required
   * settings are valid.
   *
   * @return bool True when the configuration was eligible for recovery storage;
   *              false when required settings were invalid.
   */
  private function save_recovery_settings() {
    if (!$this->has_valid_required_settings($this->settings)) {
      return false;
    }

    update_option(self::RECOVERY_OPTION_NAME, $this->settings, false);
    return true;
  }

  /**
   * Checks whether a settings array contains all valid required strings.
   *
   * @param mixed $settings Candidate configuration.
   * @return bool True when path, log suffix, and request secret are usable.
   */
  private function has_valid_required_settings($settings) {
    return is_array($settings) &&
      isset($settings['STORAGE::LOCAL::PATH']) && is_string($settings['STORAGE::LOCAL::PATH']) &&
      trim($settings['STORAGE::LOCAL::PATH']) !== '' && $settings['STORAGE::LOCAL::PATH'] !== 'default' &&
      isset($settings['STORAGE::LOCAL::LOGS::SUFFIX']) && is_string($settings['STORAGE::LOCAL::LOGS::SUFFIX']) &&
      trim($settings['STORAGE::LOCAL::LOGS::SUFFIX']) !== '' &&
      isset($settings['REQUEST:SECRET']) && is_string($settings['REQUEST:SECRET']) &&
      trim($settings['REQUEST:SECRET']) !== '';

  }

}
