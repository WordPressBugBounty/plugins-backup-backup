<?php

  // Namespace
  namespace BMI\Plugin;

  // Exit on direct access
  if (!defined('ABSPATH')) exit;

  /**
   * Main Plugin Logger
   */
  class BMI_Logger {

    public static function append($type, $log) {

      $configDir = BMI_CONFIG_DIR . DIRECTORY_SEPARATOR . 'complete_logs.' . BMI_LOGS_SUFFIX . '.log';
      if (is_dir(dirname($configDir))) {
        $file = @fopen($configDir, 'a');
        if ($file === false) {
          return;
        }
        $date = '[' . date('Y-m-d H:i:s') . '] ';
        if (@flock($file, LOCK_EX)) {
          @fwrite($file, $date . $type . ' ' . $log . "\n");
          @flock($file, LOCK_UN);
        }
        fclose($file);
      }
    }

    public static function log($log) {

      BMI_Logger::append('[LOG]', $log);

    }

    public static function error($log) {

      BMI_Logger::append('[ERROR]', $log);

    }

    public static function debug($log) {

      if (BMI_DEBUG === TRUE) {
        BMI_Logger::append('[DEBUG]', $log);
      }

    }

  }
