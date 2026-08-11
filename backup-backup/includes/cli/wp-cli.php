<?php

namespace BMI\Plugin\CLI;

use BMI\Plugin\Backup_Migration_Plugin as BMP;
use WP_CLI;

if (!defined('ABSPATH')) exit;

/**
 * Manage backups, restores, and migrations via WP-CLI for Backup Migration plugin.
 */
class BMI_WP_CLI_Command {

  /**
   * Run a backup via WP-CLI.
   *
   * ## OPTIONS
   *
   * [<name>]
   * : Optional custom backup file name.
   *
   * [--name=<name>]
   * : Optional custom backup file name.
   *
   * ## EXAMPLES
   *
   *     wp bmi backup my_custom_backup_2026-10-31_16-41-24.zip
   *     wp bmi backup --name=my_custom_backup.zip
   */
  public function backup($args = [], $assoc_args = []) {
    if (!defined('BMI_USING_CLI_FUNCTIONALITY')) {
      define('BMI_USING_CLI_FUNCTIONALITY', true);
    }
    if (!defined('BMI_CLI_FUNCTION')) {
      define('BMI_CLI_FUNCTION', 'bmi_backup');
    }

    $name = null;
    if (isset($args[0]) && !empty($args[0])) {
      $name = $args[0];
    } elseif (isset($assoc_args['name']) && !empty($assoc_args['name'])) {
      $name = $assoc_args['name'];
    }

    if ($name && !defined('BMI_CLI_ARGUMENT')) {
      define('BMI_CLI_ARGUMENT', $name);
    }

    $_SERVER['REQUEST_METHOD'] = 'CLI';
    $_SERVER['HTTP_X_REQUESTED_WITH'] = 'xmlhttprequest';
    $_POST['f'] = 'create-backup';

    $bmi = new \BMI\Plugin\Backup_Migration_Plugin();
    $bmi->ajax(true);
  }

  /**
   * Run a scheduled cron backup via WP-CLI.
   *
   * ## OPTIONS
   *
   * [<name>]
   * : Optional custom backup file name.
   *
   * [--name=<name>]
   * : Optional custom backup file name.
   *
   * ## EXAMPLES
   *
   *     wp bmi backup_cron my_custom_backup_2026-10-31_16-41-24.zip
   *     wp bmi backup_cron --name=my_custom_backup.zip
   */
  public function backup_cron($args = [], $assoc_args = []) {
    if (!defined('BMI_DOING_SCHEDULED_BACKUP')) {
      define('BMI_DOING_SCHEDULED_BACKUP', true);
    }
    if (!defined('BMI_DOING_SCHEDULED_BACKUP_VIA_CLI')) {
      define('BMI_DOING_SCHEDULED_BACKUP_VIA_CLI', true);
    }
    if (!defined('BMI_USING_CLI_FUNCTIONALITY')) {
      define('BMI_USING_CLI_FUNCTIONALITY', true);
    }
    if (!defined('BMI_CLI_FUNCTION')) {
      define('BMI_CLI_FUNCTION', 'bmi_backup_cron');
    }

    $name = null;
    if (isset($args[0]) && !empty($args[0])) {
      $name = $args[0];
    } elseif (isset($assoc_args['name']) && !empty($assoc_args['name'])) {
      $name = $assoc_args['name'];
    }

    if ($name && !defined('BMI_CLI_ARGUMENT')) {
      define('BMI_CLI_ARGUMENT', $name);
    }

    $_SERVER['REQUEST_METHOD'] = 'CLI';
    $_SERVER['HTTP_X_REQUESTED_WITH'] = 'xmlhttprequest';
    $_POST['f'] = 'create-backup';

    $bmi = new \BMI\Plugin\Backup_Migration_Plugin();
    $bmi->ajax(true);
  }

  /**
   * Restore a backup via WP-CLI.
   *
   * ## OPTIONS
   *
   * <file>
   * : The backup zip file name located in the backups directory.
   *
   * [<remote>]
   * : Whether the backup is remote ('true' or 'false').
   *
   * [--file=<file>]
   * : The backup zip file name.
   *
   * [--remote=<remote>]
   * : Whether the backup is remote ('true' or 'false').
   *
   * ## EXAMPLES
   *
   *     wp bmi restore 2026-10-31_16-41-24.zip --remote=false
   *     wp bmi restore --file=backup_file_name.zip --remote=false
   */
  public function restore($args = [], $assoc_args = []) {
    $file = null;
    if (isset($args[0]) && !empty($args[0])) {
      $file = $args[0];
    } elseif (isset($assoc_args['file']) && !empty($assoc_args['file'])) {
      $file = $assoc_args['file'];
    }

    if (empty($file)) {
      if (class_exists('WP_CLI')) {
        \WP_CLI::error('Please specify backup file name to restore. Example: wp bmi_restore <backup_name.zip>');
      }
      return;
    }

    $remote = false;
    if (isset($args[1])) {
      $remote = $args[1];
    } elseif (isset($assoc_args['remote'])) {
      $remote = $assoc_args['remote'];
    }

    if (!defined('BMI_USING_CLI_FUNCTIONALITY')) {
      define('BMI_USING_CLI_FUNCTIONALITY', true);
    }
    if (!defined('BMI_CLI_FUNCTION')) {
      define('BMI_CLI_FUNCTION', 'bmi_restore');
    }
    if (!defined('BMI_CLI_ARGUMENT')) {
      define('BMI_CLI_ARGUMENT', $file);
    }
    if (!defined('BMI_CLI_ARGUMENT_2')) {
      define('BMI_CLI_ARGUMENT_2', $remote);
    }

    $_SERVER['REQUEST_METHOD'] = 'CLI';
    $_SERVER['HTTP_X_REQUESTED_WITH'] = 'xmlhttprequest';
    $_POST['f'] = 'restore-backup';
    $_POST['file'] = $file;
    $_POST['remote'] = $remote;

    $bmi = new \BMI\Plugin\Backup_Migration_Plugin();
    $bmi->ajax(true);
  }

  /**
   * Perform quick migration from a backup URL via WP-CLI.
   *
   * ## OPTIONS
   *
   * <url>
   * : The URL of the backup zip file to download and migrate.
   *
   * ## EXAMPLES
   *
   *     wp bmi quick_migration https://example.com/backup.zip
   */
  public function quick_migration($args = [], $assoc_args = []) {
    $url = null;
    if (isset($args[0]) && !empty($args[0])) {
      $url = $args[0];
    } elseif (isset($assoc_args['url']) && !empty($assoc_args['url'])) {
      $url = $assoc_args['url'];
    }

    if (empty($url)) {
      if (class_exists('WP_CLI')) {
        \WP_CLI::error('Please specify backup URL. Example: wp bmi_quick_migration <backup_url>');
      }
      return;
    }

    if (!defined('BMI_USING_CLI_FUNCTIONALITY')) {
      define('BMI_USING_CLI_FUNCTIONALITY', true);
    }
    if (!defined('BMI_CLI_FUNCTION')) {
      define('BMI_CLI_FUNCTION', 'bmi_quick_migration');
    }
    if (!defined('BMI_CLI_ARGUMENT')) {
      define('BMI_CLI_ARGUMENT', $url);
    }

    $_SERVER['REQUEST_METHOD'] = 'CLI';
    $_SERVER['HTTP_X_REQUESTED_WITH'] = 'xmlhttprequest';
    $_POST['f'] = 'download-backup';
    $_POST['url'] = $url;

    $bmi = new \BMI\Plugin\Backup_Migration_Plugin();
    $bmi->ajax(true);
  }

}

if (class_exists('WP_CLI')) {
  \WP_CLI::add_command('bmi', BMI_WP_CLI_Command::class);
}
