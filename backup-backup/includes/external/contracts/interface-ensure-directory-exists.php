<?php

namespace BMI\Plugin\External\Contracts;

// Exit on direct access
if (!defined('ABSPATH')) exit;

interface EnsureDirectoryExists {
  /**
   * Ensure the configured remote directory exists.
   *
   * @return bool
   */
  public function ensureDirectoryExists();
}
