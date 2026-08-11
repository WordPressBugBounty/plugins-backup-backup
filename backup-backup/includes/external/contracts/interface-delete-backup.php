<?php

namespace BMI\Plugin\External\Contracts;

  // Exit on direct access
  if (!defined('ABSPATH')) exit;

  interface DeleteBackup {
    /**
     * Delete a backup from the external storage.
     *
     * @param string $hash The hash of the backup file to delete.
     * @return bool True if the deletion request was successfully processed; false otherwise.
     *              Note: The return value is not a reliable indicator of whether the target was actually deleted,
     *              as some deletion methods may report failure even when the deletion completes successfully.
     * */
    public function deleteBackup($hash);
  }