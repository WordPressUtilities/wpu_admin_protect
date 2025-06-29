<?php

/*
Plugin Name: WPU Admin Protect
Plugin URI: https://github.com/WordPressUtilities/wpu_admin_protect
Update URI: https://github.com/WordPressUtilities/wpu_admin_protect
Description: Restrictive options for WordPress admin
Version: 3.2.3
Author: Darklg
Author URI: https://darklg.me/
Text Domain: wpu_admin_protect
Requires at least: 6.2
Requires PHP: 8.0
License: MIT License
License URI: https://opensource.org/licenses/MIT
*/

if (!defined('ABSPATH')) {
    exit();
}

if (file_exists(ABSPATH . '/.disable_wpu_admin_protect')) {
    if (!file_exists(ABSPATH . '/.maintenance')) {
        error_log('WPU Admin Protect is disabled');
    }
    return;
}

if (defined('DISABLE_WPU_ADMIN_PROTECT') && DISABLE_WPU_ADMIN_PROTECT) {
    error_log('WPU Admin Protect is disabled');
    return;
}

/* ----------------------------------------------------------
  Levels
---------------------------------------------------------- */

define('WPUTH_ADMIN_PLUGIN_VERSION', '3.2.3');
define('WPUTH_ADMIN_PLUGIN_NAME', 'WPU Admin Protect');
define('WPUTH_ADMIN_PLUGIN_OPT', 'wpu_admin_protect__v');
define('WPUTH_ADMIN_MIN_LVL', 'manage_categories');
define('WPUTH_ADMIN_MAX_LVL', 'manage_options');

/* ----------------------------------------------------------
  Block admin capabilities
---------------------------------------------------------- */

class WPUTHAdminProtectBlockAdmin {

    private $level_access_min_admin_access;
    private $level_access_can_update;

    public function __construct() {
        add_action('init', array(&$this, 'init'));
    }

    public function init() {
        if (!get_site_url()) {
            return;
        }

        $this->level_access_min_admin_access = apply_filters('wputh_admin_protect_block_admin__level_access_min_admin_access', WPUTH_ADMIN_MIN_LVL);
        $this->level_access_can_update = apply_filters('wputh_admin_protect_block_admin__level_access_can_update', WPUTH_ADMIN_MAX_LVL);

        if (apply_filters('wputh_admin_protect_block_admin__enabled', true)) {
            add_action('admin_init', array(&$this, 'wputh_block_admin'));
        }
        add_action('init', array(&$this, 'wputh_hide_errors'));
        add_action('admin_menu', array(&$this, 'wputh_remove_update_nag'));
    }

    /* if the user is not an administrator, kill WordPress execution and provide a message */
    public function wputh_block_admin() {
        $uri_ajax = '/wp-admin/admin-ajax.php';
        $len_ajax = strlen($uri_ajax);
        if (!current_user_can($this->level_access_min_admin_access) && substr($_SERVER['PHP_SELF'], 0 - $len_ajax) != $uri_ajax) {
            wp_die(__('You are not allowed to access this part of the site'));
        }
    }

    /* Hide Errors for non admins */
    public function wputh_hide_errors() {
        if (current_user_can($this->level_access_min_admin_access)) {
            return;
        }
        @error_reporting(0);
        @ini_set('display_errors', 0);
    }

    /* Hide Updates */
    public function wputh_remove_update_nag() {
        if (current_user_can($this->level_access_can_update)) {
            return;
        }
        remove_action('admin_notices', 'update_nag', 3);
        remove_action('network_admin_notices', 'update_nag', 3);
    }

}

$WPUTHAdminProtectBlockAdmin = new WPUTHAdminProtectBlockAdmin();

/* ----------------------------------------------------------
  Disable file modifications
---------------------------------------------------------- */

if (!defined('DISALLOW_FILE_EDIT')) {
    define('DISALLOW_FILE_EDIT', true);
}
if (!defined('DISALLOW_FILE_MODS')) {
    define('DISALLOW_FILE_MODS', true);
}

/* ----------------------------------------------------------
  Disable auto-update
---------------------------------------------------------- */

if (apply_filters('wputh_admin_protect_disable_update', true)) {

    /* Auto updates */
    add_filter('automatic_updater_disabled', '__return_true');
    add_filter('plugins_auto_update_enabled', '__return_false');
    add_filter('themes_auto_update_enabled', '__return_false');
    add_filter('auto_update_core', '__return_false');
    add_filter('auto_update_plugin', '__return_false');
    add_filter('auto_update_theme', '__return_false');
    add_filter('auto_update_translation', '__return_false');
    add_filter('allow_dev_auto_core_updates', '__return_false');
    add_filter('allow_minor_auto_core_updates', '__return_false');
    add_filter('allow_major_auto_core_updates', '__return_false');

    /* CORE UPDATE */
    add_filter('pre_site_transient_update_core', '__return_null');
    remove_action('admin_init', '_maybe_update_core');
    remove_action('wp_version_check', 'wp_version_check');

    /* PLUGINS UPDATE */
    remove_action('load-plugins.php', 'wp_update_plugins');
    remove_action('load-update.php', 'wp_update_plugins');
    remove_action('load-update-core.php', 'wp_update_plugins');
    remove_action('admin_init', '_maybe_update_plugins');
    remove_action('wp_update_plugins', 'wp_update_plugins');
    add_filter('pre_site_transient_update_plugins', '__return_null');

    /* THEME UPDATE */
    remove_action('load-themes.php', 'wp_update_themes');
    remove_action('load-update.php', 'wp_update_themes');
    remove_action('load-update-core.php', 'wp_update_themes');
    remove_action('admin_init', '_maybe_update_themes');
    remove_action('wp_update_themes', 'wp_update_themes');
    add_filter('pre_site_transient_update_themes', '__return_null');

}

/* ----------------------------------------------------------
  Users settings
---------------------------------------------------------- */

/* Thanks to https://blog.secupress.fr/ajoutez-point-securite-facilement-astuce-156.html */

/* Disable user registration */
add_filter('pre_option_users_can_register', 'wputh_admin_option_users_can_register');
function wputh_admin_option_users_can_register($value) {
    return '0';
}

/* Default role : subscriber */
add_filter('pre_option_default_role', 'wputh_admin_option_default_role');
function wputh_admin_option_default_role($value) {
    return 'subscriber';
}

/* ----------------------------------------------------------
  Block WordPress version info
---------------------------------------------------------- */

add_action('init', 'wputh_admin_protect_remove_versions');
function wputh_admin_protect_remove_versions() {
    remove_action('wp_head', 'wp_generator');
    remove_action('wp_head', 'qtranxf_wp_head_meta_generator');
    add_filter('update_footer', '__return_empty_string', 9999);
    add_filter('the_generator', '__return_empty_string', 9999);
    add_filter('update_right_now_text', '__return_empty_string', 9999);
    add_filter('style_loader_src', 'wputh_admin_protect__remove_ver', 9999, 1);
    add_filter('script_loader_src', 'wputh_admin_protect__remove_ver', 9999, 1);
}

// remove wp version param from any enqueued scripts
function wputh_admin_protect__remove_ver($src) {
    $ver = get_bloginfo('version');
    if (!defined('NONCE_SALT')) {
        define('NONCE_SALT', __FILE__);
    }
    $new_ver = substr(md5(NONCE_SALT . $ver), 0, 6);
    if (strpos($src, 'ver=' . $ver)) {
        $src = remove_query_arg('ver', $src);
        $src = add_query_arg('ver', $new_ver, $src);
    }
    return $src;
}

/* ----------------------------------------------------------
  Htaccess
---------------------------------------------------------- */

add_filter('mod_rewrite_rules', 'wputh_admin_protect_rewrite_rules', 10, 1);
function wputh_admin_protect_rewrite_rules($rules) {

    $excluded_directories = array(
        '.aws/',
        '.env',
        '.vscode/',
        '__Additional/',
        '_ignition/',
        '_profiler/',
        'actuator/',
        'administrator/',
        'api/',
        'app/',
        'auth/',
        'backend/',
        'backup/',
        'boaform/',
        'cgi-bin/',
        'cli/',
        'config/',
        'db/',
        'dbadmin/',
        'debug/',
        'dependencies/',
        'dev/',
        'developer/',
        'docker/',
        'filemanager/',
        'framework/',
        'HNAP1/',
        'jenkins/',
        'laravel/',
        'lib/',
        'login/',
        'myadmin/',
        'MyAdmin/',
        'mysql-admin',
        'mysqladmin',
        'nagios/',
        'opt/',
        'owa/',
        'phpinfo',
        'phpmy-admin',
        'phpmy/',
        'phpMyAdmin',
        'phpmyadmin',
        'phpunit/',
        'query',
        'resolve',
        'server-status',
        'shell/',
        'sql',
        'vendor/',
        'wp/',
        'wp-backup',
        'wp-admin/includes',
        'wp-includes/ID3',
        'wp-includes/IXR',
        'wp-includes/PHPMailer',
        'wp-includes/Requests',
        'wp-includes/SimplePie',
        'wp-includes/Text',
        'wp-includes/block-patterns',
        'wp-includes/block-supports',
        'wp-includes/blocks',
        'wp-includes/certificates',
        'wp-includes/customize',
        'wp-includes/html-api',
        'wp-includes/php-compat',
        'wp-includes/pomo',
        'wp-includes/rest-api',
        'wp-includes/sitemaps',
        'wp-includes/sodium_compat',
        'wp-includes/style-engine',
        'wp-includes/theme-compat',
        'wp-includes/widgets',
        'wp-content/upgrade',
        'wp-content/w3tc-config',
        'wp-content/wp-rocket-config'
    );

    $excluded_directories = apply_filters('wputh_admin_protect_rewrite_rules__excluded_directories', $excluded_directories);

    $excluded_extensions = array(
        'bak',
        'bak\.php',
        'conf',
        'dist',
        'crt',
        'DS_Store',
        'env',
        'git',
        'lock',
        'log',
        'mo',
        'pem',
        'phar',
        'po',
        'pot',
        'rb',
        'scss',
        'sh',
        'sql',
        'ts',
        'yml'
    );

    $excluded_extensions = apply_filters('wputh_admin_protect_rewrite_rules__excluded_extensions', $excluded_extensions);

    $excluded_files = array(
        /* git */
        '^.git',
        '^.gitignore',
        '^.gitmodules',
        /* Project */
        'gulpfile\.js',
        'Gruntfile\.js',
        'cypress\.json$',
        'composer\.json$',
        'package\.json$',
        'package-lock\.json$',
        'phpstan\.neon$',
        'composer\.lock$',
        'php\.ini',
        'phpunit\.xml$',
        /* Infos */
        'changelog\.txt$',
        'license\.html$',
        'license\.txt$',
        'readme\.html$',
        'readme\.md$',
        'README\.md$',
        'readme\.txt$',
        /* WordPress attacks */
        'alfa\.php$',
        'autoload_classmap\.php$',
        'chosen\.php$',
        'classwithtostring\.php$',
        'config\.php$',
        'cong\.php$',
        'error\.php$',
        'file\.php$',
        'index_sso\.php$',
        'infophp\.php$',
        'inputs\.php$',
        'old_phpinfo\.php$',
        'password\.php$',
        'php_info\.php$',
        'phpinfo\.php$',
        'phpversion\.php$',
        'pinfo\.php$',
        'radio\.php$',
        's_ne\.php$',
        'shell\.php$',
        'simple\.php$',
        'system\.php$',
        'timthumb\.php$',
        'wp\.php$',
        'xleet-shell\.php$',
        'xleet\.php$',
        'xleetshell\.php$',
        /* WordPress files */
        '^(wp-blog-header|wp-config|wp-config-sample|wp-load|wp-settings)\.php'
    );

    $excluded_files_root = array(
        'admin\.php',
        'config\.php',
        'error\.php',
        'index_sso\.php',
        'info\.php',
        'info\.php',
        'infophp\.php',
        'inputs\.php',
        'old_phpinfo\.php',
        'password\.php',
        'php_info\.php',
        'phpinfo\.php',
        'phpversion\.php',
        'pinfo\.php',
        's_ne\.php',
        'shell\.php',
        'simple\.php',
        'system\.php',
        'timthumb\.php',
        'upload\.php',
        'xleet-shell\.php',
        'xleet\.php',
        'xleetshell\.php'
    );

    if (function_exists('wputh_disable_comments_css') || function_exists('wputh_disable_comments_support')) {
        $excluded_files[] = 'wp-comments-post.php';
        $excluded_files[] = 'wp-trackback.php';
    }

    $excluded_files = apply_filters('wputh_admin_protect_rewrite_rules__excluded_files', $excluded_files);
    $excluded_files_root = apply_filters('wputh_admin_protect_rewrite_rules__excluded_files_root', $excluded_files_root);

    $wpuadminrules = "<IfModule mod_rewrite.c>
RewriteEngine On
# - Prevent bad requests
RewriteCond %{QUERY_STRING} (\<|%3C).*script.*(\>|%3E) [NC,OR]
RewriteCond %{QUERY_STRING} GLOBALS(=|\[|\%[0-9A-Z]{0,2}) [OR]
RewriteCond %{QUERY_STRING} _REQUEST(=|\[|\%[0-9A-Z]{0,2})
RewriteRule ^(.*)$ /index.php [R=404,L]
# - Stop WordPress username enumeration vulnerability
RewriteCond %{QUERY_STRING} author=d
RewriteRule ^ /? [L,R=301]
# - Prevent access to some subdirectories
RewriteRule ^.git - [F]
# - Disable directory browsing
Options All -Indexes
IndexIgnore *
</IfModule>";

    $wpuadminrules .= "
<IfModule mod_rewrite.c>
# - Protect extensions
RewriteRule ^.*\.(" . implode('|', $excluded_extensions) . ")$ - [F,L,NC]
# - Protect files
<FilesMatch (" . implode('|', $excluded_files) . ")>
Deny from all
</FilesMatch>";
    foreach ($excluded_directories as $excluded_dir) {
        $wpuadminrules .= "\nRewriteRule ^" . $excluded_dir . " - [R=404,L]";
    }
    $wpuadminrules .= "
</IfModule>";

    $wpuadminrules .= "
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_URI} ^/(" . implode('|', $excluded_files_root) . ")
    RewriteRule ^.*$ - [F]
</IfModule>";

    $wpuadminrules .= "
# - Block the include-only files ( cf WP.org )
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteBase /
RewriteRule ^wp-admin/includes/ - [R=404,L]
RewriteRule !^wp-includes/ - [S=3]
RewriteRule ^wp-includes/[^/]+\.php$ - [R=404,L]
RewriteRule ^wp-includes/js/tinymce/langs/.+\.php - [R=404,L]
RewriteRule ^wp-includes/theme-compat/ - [R=404,L]
</IfModule>";
    $wpuadminrules .= "
# - Avoid access to PHP files in plugins
<IfModule mod_rewrite.c>
RewriteRule wp-content/([^/\.]*\.php)$ - [R=404,L]
RewriteRule wp-content/themes/(.*\.php)$ - [R=404,L]
RewriteRule wp-content/plugins/(.*\.php)$ - [R=404,L]
RewriteRule wp-content/mu-plugins/(.*\.php)$ - [R=404,L]
RewriteRule wp-content/uploads/(.*\.php)$ - [R=404,L]
</IfModule>";
    $wpuadminrules .= "
# - Avoid access to PHP files in plugins
<IfModule mod_rewrite.c>
# - Disallow PHP Easter Egg
RewriteCond %{QUERY_STRING} \=PHP[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12} [NC]
RewriteRule .* - [F,L]
</IfModule>";
    $wpuadminrules .= "
<IfModule mod_headers.c>
# - Remove Server Signature
Header unset Server
ServerSignature Off
# - Disable mime sniffing
Header always set X-Content-Type-Options \"nosniff\"
# - Block if XSS detected
Header always set X-XSS-Protection \"1; mode=block\"
</IfModule>
";

    if (apply_filters('wputh_admin_protect_disallow_subfolder_admin', true)) {
        $wpuadminrules .= "
# Prevent admin in subfolder
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteBase /
RewriteRule ^[^/]+/wp-admin/ - [R=404,L]
</IfModule>
# End Prevent\n
";
    }

    if (apply_filters('wputh_admin_protect_disallow_xframe_options', true)) {
        $wpuadminrules .= "
# Prevent external iframe embedding
<IfModule mod_headers.c>
Header always set X-FRAME-OPTIONS \"SAMEORIGIN\"
</IfModule>
# End Prevent\n
";
    }
    if (apply_filters('wputh_admin_protect_disallow_style_css', true)) {
        $wpuadminrules .= "
# Avoid access to style.css file
<IfModule mod_rewrite.c>
RewriteRule wp-content/themes/(.*)/style.css$ - [R=404,L]
</IfModule>
# END Avoid access to style.css file\n
";
    }

    $wpuadminrules = apply_filters('wputh_admin_protect_rewrite_rules__wpuadminrules', $wpuadminrules);

    $new_rules = "# BEGIN " . WPUTH_ADMIN_PLUGIN_NAME . " - v " . WPUTH_ADMIN_PLUGIN_VERSION . "\n" . $wpuadminrules . "\n" . "# END " . WPUTH_ADMIN_PLUGIN_NAME . "\n";
    # Remove on deactivation
    if (defined('WPUTH_ADMIN_PROTECT_DEACTIVATION')) {
        $new_rules = '';
    }
    return $new_rules . $rules;
}

/* ----------------------------------------------------------
  Block malicious requests
---------------------------------------------------------- */

/* Inspired by : https://perishablepress.com/block-bad-queries/ */

function wputh_admin_protect_die_bad_request() {
    do_action('wputh_admin_protect_die_bad_request__custom_action');
    @header('HTTP/1.1 403 Forbidden');
    @header('Status: 403 Forbidden');
    @header('Connection: Close');
    die;
}

add_action('wp', 'wputh_admin_protect_bad_requests');
function wputh_admin_protect_bad_requests() {
    $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';

    $invalid_strings = array(
        'eval(',
        'CONCAT',
        'UNION+SELECT',
        '(null)',
        '.css(',
        '<script',
        '%3c%73%63%72%69%70%74%3e',
        '</script',
        '%3c%2f%73%63%72%69%70%74%3e',
        '/&&'
    );

    foreach ($invalid_strings as $string) {
        if (stripos($request_uri, $string)) {
            wputh_admin_protect_die_bad_request();
        }
    }

    /* Empty author in comments */
    if (!is_admin() && isset($_SERVER['REQUEST_URI'])) {
        if (preg_match('/(wp-comments-post)/', $_SERVER['REQUEST_URI']) === 0 && !empty($_REQUEST['author'])) {
            wp_die('forbidden');
        }
    }
}

add_action('template_redirect', 'wputh_admin_protect_badrequests_lastchance', 9999);
function wputh_admin_protect_badrequests_lastchance() {
    @header_remove("X-Powered-By");
    @header_remove("Link");
}

/* ----------------------------------------------------------
  Warn from user admin creation
---------------------------------------------------------- */

add_filter('illegal_user_logins', function ($forbidden_usernames) {
    $forbidden_usernames[] = 'admin';
    $forbidden_usernames[] = 'administrator';
    $stylesheet = get_stylesheet();
    if ($stylesheet) {
        $forbidden_usernames[] = $stylesheet;
    }
    return $forbidden_usernames;
}, 10, 1);

add_action('admin_head', 'wputh_admin_protect_invalidusername');
function wputh_admin_protect_invalidusername() {
    $screen = get_current_screen();
    if (!is_object($screen) || $screen->base != 'user' || $screen->action != 'add') {
        return;
    }

    $forbidden_usernames = apply_filters('illegal_user_logins', array());
    echo "<script>jQuery(document).ready(function($) {
    var forbidden_usernames = " . json_encode($forbidden_usernames) . ",
        alert_message = '" . esc_attr(__('The ”%s” username is forbidden, because it is a potential security breach.', 'wputh')) . "';
    function wputh_admin_protect_invalidusername(e){
        var val = $('#user_login').val();
        if($.inArray(val,forbidden_usernames) >= 0){
            e.preventDefault();
            alert(alert_message.replace(/%s/g,val));
            $('#user_login').val('').focus();
        }
    }
    $('#createuser').on('blur', '#user_login', wputh_admin_protect_invalidusername);
    $('#createuser').on('submit', wputh_admin_protect_invalidusername);
});</script>";
}

/* ----------------------------------------------------------
  Plugin
---------------------------------------------------------- */

/* Version update
-------------------------- */

add_action('init', 'wputh_admin_init');
function wputh_admin_init() {
    if (!get_site_url()) {
        return;
    }
    $ver = get_option(WPUTH_ADMIN_PLUGIN_OPT);
    if ($ver != WPUTH_ADMIN_PLUGIN_VERSION) {
        flush_rewrite_rules(true);
        update_option(WPUTH_ADMIN_PLUGIN_OPT, WPUTH_ADMIN_PLUGIN_VERSION);
    }
}

/* Activation
-------------------------- */

register_deactivation_hook(__FILE__, 'wputh_admin_protect_activate');
register_activation_hook(__FILE__, 'wputh_admin_protect_activate');
function wputh_admin_protect_activate() {
    flush_rewrite_rules(true);
}

/* Deactivation
-------------------------- */

register_deactivation_hook(__FILE__, 'wputh_admin_protect_deactivate');
function wputh_admin_protect_deactivate() {
    define('WPUTH_ADMIN_PROTECT_DEACTIVATION', 1);
    flush_rewrite_rules(true);
}

/*
'wputh_admin_protect_block_admin__level_access_min_admin_access', 'manage_categories'
'wputh_admin_protect_block_admin__level_access_can_update', 'manage_options'
'wputh_admin_protect_block_admin__enabled', false
'wputh_admin_protect_disallow_xframe_options', false
'wputh_admin_protect_htaccess_file', $root_path . '.htaccess'
'wputh_admin_protect_subfolders', array('wp-cms/')
 */

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('wpu-admin-protect-dump', function ($args = array()) {
        echo wputh_admin_protect_rewrite_rules('');
    }, array(
        'shortdesc' => 'Dump WPU Admin protect rules',
        'synopsis' => array()
    ));
    WP_CLI::add_command('wpu-admin-protect-update-rules', function ($args = array()) {
        $new_rules = wputh_admin_protect_rewrite_rules('');
        if (!$new_rules) {
            WP_CLI::error('No rules are available');
        }
        $ht = ABSPATH . '/.htaccess';
        if (!is_readable($ht)) {
            WP_CLI::error('htaccess file is not available');
        }
        $ht_content = file_get_contents($ht);

        preg_match('/# BEGIN WPU Admin Protect(.*)# END WPU Admin Protect/isU', $ht_content, $matches);
        if (!isset($matches[0]) || !$matches[0]) {
            WP_CLI::error('htaccess file does not contains the rules');
        }
        $ht_content = str_replace($matches[0], $new_rules, $ht_content);
        file_put_contents($ht, $ht_content);
        WP_CLI::success('Rules have been updated');

    }, array(
        'shortdesc' => 'Update WPU Admin protect rules directly in the .htaccess file',
        'synopsis' => array()
    ));
}
