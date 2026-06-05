<?php

// Namespace
namespace BMI\Plugin\CRON;

// Exit on direct access
if (!defined('ABSPATH')) exit;

/**
 * TaskManager
 *
 * Static registry for AbstractTask instances. The manager owns three
 * responsibilities:
 *
 *   1. Registry   – tasks are keyed by their hook name; duplicates replace.
 *   2. Lifecycle  – boot, activate, deactivate methods map to WP events.
 *   3. Intervals  – injects custom cron_schedules entries declared by tasks.
 *
 * ── Typical usage ────────────────────────────────────────────────────────────
 *
 *   // bootstrap.php  (required once, from the 'init' action)
 *   TaskManager::register(new CloudSyncTask());
 *   TaskManager::boot();
 *
 *   // activation hook
 *   TaskManager::register(new CloudSyncTask());
 *   TaskManager::on_activation();
 *
 *   // deactivation hook
 *   TaskManager::register(new CloudSyncTask());
 *   TaskManager::on_deactivation();
 *
 * ─────────────────────────────────────────────────────────────────────────────
 */
class TaskManager {

  /**
   * Hook-slug => task instance map.
   *
   * @var AbstractTask[]
   */
  private static $tasks = array();

  /**
   * Guards against calling boot() more than once per request.
   * Prevents duplicate add_action() registrations.
   *
   * @var bool
   */
  private static $booted = false;

  // ── Registry ───────────────────────────────────────────────────────────────

  /**
   * Register a task. If a task with the same hook is already registered,
   * it is silently replaced (last-write-wins).
   *
   * @param AbstractTask $task
   * @return void
   */
  public static function register(AbstractTask $task) {
    self::$tasks[$task->get_hook()] = $task;
  }

  /**
   * Return all registered tasks, keyed by hook name.
   *
   * @return AbstractTask[]
   */
  public static function get_tasks() {
    return self::$tasks;
  }

  // ── Lifecycle ──────────────────────────────────────────────────────────────

  /**
   * Boot all registered tasks.
   *
   * What this does:
   *   - Attaches custom interval registration to the 'cron_schedules' filter.
   *   - Binds each task's run() method to its WP action hook.
   *   - Schedules every task that has no pending event (no-op otherwise).
   *
   * Safe to call multiple times; subsequent calls are silently ignored.
   * Recommended priority: 20 (after the plugin's own initializer at 15).
   *
   * @return void
   */
  public static function boot() {
    if (self::$booted) {
      return;
    }
    self::$booted = true;

    // Register custom cron intervals before any scheduling calls.
    add_filter('cron_schedules', array(__CLASS__, 'filter_cron_schedules'));

    foreach (self::$tasks as $task) {
      $task->register(); // adds the WP action hook
      $task->schedule(); // no-op when event is already queued
    }
  }

  /**
   * Force-(re)schedule every task.
   *
   * Clears any stale schedule first so the start time is always reset to
   * get_schedule_start() at the moment of activation. Call this from the
   * plugin's register_activation_hook callback.
   *
   * @return void
   */
  public static function on_activation() {
    // Ensure custom intervals are available before scheduling.
    add_filter('cron_schedules', array(__CLASS__, 'filter_cron_schedules'));

    foreach (self::$tasks as $task) {
      $task->unschedule(); // remove any stale event from a previous activation
      $task->schedule();   // re-queue with a fresh start time
    }
  }

  /**
   * Unschedule every registered task.
   *
   * Call from register_deactivation_hook so WordPress never fires ghost
   * cron events after the plugin has been deactivated.
   *
   * @return void
   */
  public static function on_deactivation() {
    foreach (self::$tasks as $task) {
      $task->unschedule();
    }
  }

  // ── cron_schedules filter ──────────────────────────────────────────────────

  /**
   * WordPress 'cron_schedules' filter callback.
   *
   * Appends any custom interval definitions declared by registered tasks.
   * Tasks that rely on a built-in interval return null from
   * get_custom_interval_args() and are silently skipped here.
   *
   * @param  array $schedules Existing WP cron schedules.
   * @return array            Schedules extended with task-defined intervals.
   */
  public static function filter_cron_schedules(array $schedules) {
    foreach (self::$tasks as $task) {
      $args = $task->get_custom_interval_args();
      if ($args === null) {
        continue;
      }

      $slug = $task->get_interval();
      if (!isset($schedules[$slug])) {
        $schedules[$slug] = $args;
      }
    }

    return $schedules;
  }

}
