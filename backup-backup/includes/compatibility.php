<?php

  // Exit on direct access
  if (!defined('ABSPATH')) exit;

  // Fix PclZip error when php_uname is disabled
  if (PHP_MAJOR_VERSION >= 8 && !function_exists('php_uname')) {
    function php_uname() {
      if (DIRECTORY_SEPARATOR === '/') {
        return 'linux';
      } else return 'windows';
    }
  }

  if (!function_exists('bmi_generate_auth_token')) {
    function bmi_generate_auth_token($byteLength = 36) {
      if (function_exists('random_bytes')) {
        return bin2hex(random_bytes($byteLength));
      }

      if (function_exists('openssl_random_pseudo_bytes')) {
        $cryptoStrong = false;
        $bytes = openssl_random_pseudo_bytes($byteLength, $cryptoStrong);
        if ($bytes !== false && $cryptoStrong) {
          return bin2hex($bytes);
        }
      }

      $seed = wp_generate_password(max(64, $byteLength * 2), true, true);
      $token = '';
      $counter = 0;
      while (strlen($token) < ($byteLength * 2)) {
        $token .= hash('sha256', $seed . ':' . $counter);
        $counter++;
      }

      return substr($token, 0, $byteLength * 2);
    }
  }

  if (!function_exists('bmi_zip_encryption_supported')) {
    function bmi_zip_encryption_supported() {
      return class_exists('ZipArchive')
        && method_exists('ZipArchive', 'setEncryptionName')
        && defined('ZipArchive::EM_AES_256');
    }
  }
