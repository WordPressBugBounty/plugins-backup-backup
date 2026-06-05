<?php

// Namespace
namespace BMI\Plugin\CRON;

// Exit on direct access
if (!defined('ABSPATH')) exit;

/**
 * AbstractTask
 *
 * Base contract for all background tasks in this plugin.
 *
 * Each concrete subclass represents exactly one WP-Cron scheduled event.
 * Subclasses declare *what* to do (hook name, interval, and run() logic);
 * the infrastructure (scheduling, unscheduling, hook registration) is fully
 * handled here so subclasses never call wp_schedule_event() directly.
 *
 * Minimal implementation:
 *
 *   class MyTask extends AbstractTask {
 *     public function get_hook()     { return 'bmi_my_task'; }
 *     public function get_interval() { return 'daily'; }
 *     public function run()          { // do work }
 *   }
 *
 *   TaskManager::register(new MyTask());
 */
abstract class AbstractTask {

  // ── Abstract interface ─────────────────────────────────────────────────────

  /**
   * Unique WP action hook name that fires this task.
   * Must not collide with any other hook in the WordPress installation.
   *
   * @return string
   */
  abstract public function get_hook();

  /**
   * Recurrence interval slug passed to wp_schedule_event().
   *
   * Built-in slugs (no extra setup needed):
   *   'hourly', 'twicedaily', 'daily'
   *
   * Note: 'weekly' was added in WP 5.4; use a custom slug for broader
   * compatibility (see get_custom_interval_args()).
   *
   * Custom slugs must be accompanied by a non-null return from
   * get_custom_interval_args() so TaskManager can register them.
   *
   * @return string
   */
  abstract public function get_interval();

  /**
   * The work to perform each time the event fires.
   *
   * @return void
   */
  abstract public function run();

  // ── Optional overrides ─────────────────────────────────────────────────────

  /**
   * Arguments required to register a custom cron interval.
   *
   * Return null when the interval slug returned by get_interval() is one
   * of WordPress's built-in intervals.
   *
   * Return an array for a custom interval:
   *   array('interval' => <seconds (int)>, 'display' => '<Human-readable label>')
   *
   * @return array|null
   */
  public function get_custom_interval_args() {
    return null;
  }

  /**
   * UTC timestamp for the first occurrence of this event.
   *
   * Defaults to one minute from the current time, giving WordPress a chance
   * to finish its current request before the first execution.
   *
   * @return int Unix timestamp
   */
  public function get_schedule_start() {
    return time() + MINUTE_IN_SECONDS;
  }

  // ── Final infrastructure methods ───────────────────────────────────────────

  /**
   * Bind this task's run() method to its WP action hook.
   * Called once per request by TaskManager::boot().
   *
   * @return void
   */
  final public function register() {
    add_action($this->get_hook(), array($this, 'run'));
  }

  /**
   * Schedule the recurring event unless it is already queued.
   * Silently no-ops when the event is already scheduled.
   *
   * @return void
   */
  final public function schedule() {
    if (!$this->is_scheduled()) {
      wp_schedule_event(
        $this->get_schedule_start(),
        $this->get_interval(),
        $this->get_hook()
      );
    }
  }

  /**
   * Remove every pending occurrence of this event from the cron queue.
   *
   * @return void
   */
  final public function unschedule() {
    $timestamp = wp_next_scheduled($this->get_hook());
    if ($timestamp !== false) {
      wp_unschedule_event($timestamp, $this->get_hook());
    }
    wp_clear_scheduled_hook($this->get_hook());
  }

  /**
   * Return true when at least one occurrence of this event is queued.
   *
   * @return bool
   */
  final public function is_scheduled() {
    return (bool) wp_next_scheduled($this->get_hook());
  }

}
