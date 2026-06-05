<?php

namespace BMI\Plugin\External\Contracts;

  // Exit on direct access
  if (!defined('ABSPATH')) exit;

  interface GetAvailableSpace {
    /**
     * Get the available space in bytes.
     *
     * @return int|false Available space in bytes, or false on failure.
     */
    public function getAvailableSpace();
  }