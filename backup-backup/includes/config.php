<?php

  // Namespace
  namespace BMI\Plugin\Dashboard;

  // Exit on direct access
  if (!defined('ABSPATH')) exit;

  if (!function_exists('bmi_config_random_string')) {
    function bmi_config_random_string($max = 16) {
      $bank = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
      $bank .= 'abcdefghijklmnopqrstuvwxyz';
      $bank .= '0123456789';

      $str = str_shuffle($bank);

      while (is_numeric($str[0])) {
        $str = str_shuffle($bank);
      }

      $str = substr($str, 0, $max);

      return $str;
    }
  }

  require_once __DIR__ . '/config_v2.php';

  if (!function_exists('bmi_get_config')) {
    function bmi_get_config($setting, $configpath = false) {
      return BMI_Config_V2::get_instance()->get($setting);
    }
  }

  if (!function_exists('bmi_set_config')) {
    function bmi_set_config($setting, $value) {
      return BMI_Config_V2::get_instance()->set($setting, $value);
    }
  }

  // Download link token (BMI_BACKUP). Stored in plugin config, not a transient:
  // a persistent object cache can hide transients from front-end requests.
  if (!function_exists('bmi_create_download_token')) {
    /**
     * Saves a download token and its creation time.
     *
     * @param string|false $token   Token to store, a new one is generated when false.
     * @param int|false    $created Creation timestamp, defaults to now.
     * @return string|false The stored token, or false when saving failed.
     */
    function bmi_create_download_token($token = false, $created = false) {
      if (!$token) $token = wp_generate_password(32, false);
      if (!$created) $created = time();

      if (!bmi_set_config('REQUEST:DOWNLOAD_TOKEN', $token)) return false;
      if (!bmi_set_config('REQUEST:DOWNLOAD_TOKEN_TIME', intval($created))) return false;

      return $token;
    }
  }

  if (!function_exists('bmi_get_download_token')) {
    /**
     * Returns the current download token while it is younger than BMI_DOWNLOAD_TOKEN_EXPIRY.
     *
     * @return string|false Token, or false when missing or expired.
     */
    function bmi_get_download_token() {
      $token = bmi_get_config('REQUEST:DOWNLOAD_TOKEN');
      $created = intval(bmi_get_config('REQUEST:DOWNLOAD_TOKEN_TIME'));

      if (!is_string($token) || $token === '') return false;
      if ((time() - $created) >= BMI_DOWNLOAD_TOKEN_EXPIRY) return false;

      return $token;
    }
  }

  if (!function_exists('bmi_try_checked')) {
    function bmi_try_checked($setting, $reversed = false) {
      if (!$reversed) {
        if (bmi_get_config($setting) == 'true' || bmi_get_config($setting) === true) {
          echo ' checked';
        } else return '';
      } else {
        if (bmi_get_config($setting) == 'true' || bmi_get_config($setting) === true) {
          return '';
        } else {
          echo ' checked';
        }
      }
    }
  }

  if (!function_exists('bmi_try_value')) {
    function bmi_try_value($setting) {
      $res = bmi_get_config($setting);
      if ($res !== false) {
        echo ' value="' . esc_attr( sanitize_text_field($res) ) . '"';
      } else echo '';
    }
  }
