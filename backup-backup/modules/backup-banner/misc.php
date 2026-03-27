<?php


  /**
   * File for handling the "Make a backup" banner
   *
   * @category Child Plugin
   * @version v0.1.0
   * @since v0.1.0
   */

  // Namespace
  namespace Inisev\Subs;

  // Disallow direct access
  if ( ! defined( 'ABSPATH' ) ) exit;

  /**
   * Main class for handling the Backup Banner
   */
  if (!class_exists('Inisev\Subs\BMI_Backup_Banner')) {
    class BMI_Backup_Banner {

      /**
       * Local variables
       */
      private $root; // __ROOT__ of plugin's root
      private $file; // __FILE__ of plugin's root
      private $slug; // Plugin's slug
      private $name; // Display name

      /**
       * Local URLs
       */
      private $root_url; // Root URL for plugin's dir
      private $assets_url; // Root URL for banner assets
      private $plugin_menu_url; // Plugin's settings menu
      private $option_name = '_bmi_backup_banner'; // Option name for this module

      public $expiring_at = null; // Expiration timestamp for the site, if available

      /**
       * __construct:
       * Compile some variables for "future use"
       *
       * @param  string $root_file       __FILE__ of plugin's main file
       * @param  string $root_dir        __DIR__ of plugin's main file
       * @param  string $individual_slug Individual slug - mostly plugin's slug
       * @param  string $display_name    The name that will be displayed in the banner
       * @param  string $plugin_menu_url Plugin menu slug
       */
      function __construct($root_file, $root_dir, $individual_slug, $display_name, $plugin_menu_url) {

        $this->file = $root_file;
        $this->root = $root_dir;
        $this->slug = $individual_slug;
        $this->name = $display_name;

        $this->plugin_menu_url = admin_url('admin.php?page=' . $plugin_menu_url);

        $this->root_url = plugin_dir_url($this->file);
        $this->assets_url = $this->root_url . 'modules/backup-banner/assets/';

        $option = get_option($this->option_name);
        // Set the first time this code is loaded
        if ($option === false) {
          update_option($this->option_name, [
            'dismissed' => false,
            'dismissed_at' => null,
            'first_loaded_at' => time()
          ]);
        } else if ($option['dismissed'] === false && (!isset($option['first_loaded_at']))) {
          $option['first_loaded_at'] = time();
          update_option($this->option_name, $option);
        }


        // Add handler for Ajax request
        $request_method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper(sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD']))) : '';
        if ('POST' === $request_method) {

          // Check if token is defined
          if ( isset( $_POST['token'] ) ) {
            $token = sanitize_text_field( wp_unslash( $_POST['token'] ) );
            if ( $token === 'bmi_backup_banner' ) {

              // Handle the request
              add_action('wp_ajax_dismiss_bmi_backup_banner', [&$this, 'dismiss_banner']);
            }
          }

          // Stop for POST
          return;

        }

        add_action('wp_loaded', [&$this, 'init_backup_banner']);

      }

      /**
       * __asset - Loads assets
       *
       * @param  string $file path relative
       * @return string       file URL
       */
      private function __asset($file) {

        return $this->assets_url . $file;

      }

      /**
       * __dir_asset - Loads assets
       *
       * @param  string $file path relative
       * @return string       absolute path
       */
      private function __dir_asset($file) {

        return __DIR__ . '/assets' . '/' . $file;

      }

      /**
       * _asset - Loads assets and automatically echo
       *
       * @param  string $file path relative
       * @echo   string       file URL
       */
      private function _asset($file) {

        echo esc_url($this->assets_url . $file);

      }

      /**
       * can_be_displayed - check if the banner should be displayed
       *
       * @return bool true if banner can be displayed
       */
      private function can_be_displayed() {

        $site_url = get_site_url();
        $option = get_option($this->option_name);

        if (isset($option['dismissed']) && $option['dismissed'] === true) {
          if ($this->is_site_near_expiration()) {
            return true;
          }
            
          return false;
        }

        if (strpos($site_url, 'tastewp') === false || $option['first_loaded_at'] > time() - 5 * MINUTE_IN_SECONDS) {
          return false;
        }

        if (!is_plugin_active('backup-backup/backup-backup.php')) {
          return false;
        }

        return true;
      }

      /**
       * add_assets - adds required assets by the banner
       *
       * @return void
       */
      public function add_assets() {

        if (!defined('BMI_BACKUP_BANNER_ASSETS_LOADED')) {
          define('BMI_BACKUP_BANNER_ASSETS_LOADED', true);
          wp_enqueue_script('bmi-backup-banner-script', $this->__asset('js/script.js'), [], filemtime($this->__dir_asset('js/script.js')), true);
          wp_enqueue_style('bmi-backup-banner-style', $this->__asset('css/style.css'), [], filemtime($this->__dir_asset('css/style.css')));
          wp_localize_script('bmi-backup-banner-script', 'bmi_backup_banner', [
            'dismiss_nonce' => wp_create_nonce('bmi_backup_banner_dismiss'),
            'plugin_url' => $this->plugin_menu_url,
            'expiring_at' => $this->expiring_at
          ]);
        }

      }

      /**
       * display_banner - loads the HTML and prints it in the header only once
       *
       * @return void
       */
      public function display_banner() {

        if (!defined('BMI_BACKUP_BANNER_HTML_LOADED')) {
          define('BMI_BACKUP_BANNER_HTML_LOADED', true);
          include_once __DIR__ . '/views/banner.php';
        }

      }

      /**
       * dismiss_banner - Handles all POST actions
       *
       * @return void returns JSON response to browser
       */
      public function dismiss_banner() {

        if (check_ajax_referer('bmi_backup_banner_dismiss', 'nonce', false) === false) {
          wp_send_json_error();
        }

        if (!current_user_can('manage_options')) {
          wp_send_json_error();
        }

        if (isset($_POST['shouldRedirect'])) $shouldRedirect = sanitize_text_field($_POST['shouldRedirect']);
        else $shouldRedirect = false;

        update_option($this->option_name, [
          'dismissed' => true,
          'dismissed_at' => time(),
        ]);

        if ($shouldRedirect == 'true') {
          wp_send_json_success([
            'redirect' => $this->plugin_menu_url
          ]);
        } else {
          wp_send_json_success();
        }

      }

      /**
       * init_backup_banner - initialization when the user is authenticated already
       *
       * @return void
       */
      public function init_backup_banner() {

        if ($this->can_be_displayed()) {
          add_action('admin_enqueue_scripts', [&$this, 'add_assets']);
          add_action('admin_notices', [&$this, 'display_banner']);
        }

      }

      /**
       * Check if site is near expiration.
       *
       * Checks if the site is expiring in less than 5 hours.
       *
       * @return bool True if the site is expiring in less than 5 hours, false otherwise.
       */
      public function is_site_near_expiration() {

        $site_url = get_site_url();
        if (strpos($site_url, 'tastewp') === false) {
          return false;
        }

        $api_url = 'https://tastewp.com/api/z';
        $headers = [
          'Origin' => home_url(),
          'Referer' => home_url(),
          'Accept' => 'application/json'
        ];

        $response = wp_remote_get($api_url, [
          'headers' => $headers,
          'timeout' => 5
        ]);

        if (is_wp_error($response)) {
          return false;
        }

        if (wp_remote_retrieve_response_code($response) !== 200) {
          return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if (!is_array($data) || !isset($data['e'])) {
          return false;
        }
        $expiration_timestamp  = strtotime($data['e']);
        if ($expiration_timestamp === false) {
          return false;
        }
        $time_until_expiration   = $expiration_timestamp - time();
        if ($time_until_expiration >= 0 && $time_until_expiration <= 5 * HOUR_IN_SECONDS) {
          $this->expiring_at = $expiration_timestamp;
          return true;
        }

        return false;
      }

    }
  }
