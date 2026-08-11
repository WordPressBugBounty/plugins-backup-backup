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
