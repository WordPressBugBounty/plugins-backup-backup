<?php

/**
 * Background Task Bootstrap
 *
 * Loads the task infrastructure and registers every concrete task.
 * This file is required from three call-sites in backup-backup.php:
 *
 *   init hook         → TaskManager::boot()             (normal page loads)
 *   activation hook   → TaskManager::on_activation()    (plugin activated)
 *   deactivation hook → TaskManager::on_deactivation()  (plugin deactivated)
 *
 * After requiring this file, call whichever lifecycle method is appropriate.
 * The file itself performs no side-effects beyond loading and registering so
 * it is safe to require_once from any call-site.
 *
 * ── Adding a new task ────────────────────────────────────────────────────────
 *
 *   1. Create `includes/cron/tasks/YourTask.php` extending AbstractTask.
 *   2. Add the two lines below (require_once + register call).
 *   3. Done – no other file needs to change.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 */

// Exit on direct access
if (!defined('ABSPATH')) exit;

// ── Infrastructure ────────────────────────────────────────────────────────────

require_once __DIR__ . '/class-abstract-task.php';
require_once __DIR__ . '/class-task-manager.php';

// ── Task registration ─────────────────────────────────────────────────────────
