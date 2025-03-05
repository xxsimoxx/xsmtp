<?php
/**
 * Plugin Name:  X SMTP
 * Plugin URI:   https://software.gieffeedizioni.it
 * Description:  Easily configure Simple Mail Transport Protocol (SMTP).
 * Version:      1.0.0
 * License:      GPL2
 * License URI:  https://www.gnu.org/licenses/gpl-2.0.html
 * Author:       Gieffe edizioni srl
 * Author URI:   https://www.gieffeedizioni.it
 * Requires PHP: 7.4
 * Requires CP:  2.0
 * Text Domain:  xsmtp
 * Domain Path:  /languages
 */

namespace XXSimoXX\XSMTP;

if (!defined('ABSPATH')) {
	return;
}

require_once 'traits/Helpers.trait.php';

class XSMTP {

	use Helpers;

	private $screen  = '';
	private $options = false;

	const SLUG = 'xsmtp';
	const CANIMPORT = [
		'azrcrv-smtp'  => 'SMTP by azurecurve',
	];

	public function __construct() {
		add_action('phpmailer_init', 'phpmailer_settings', apply_filters('xsmtp-phpmailer-priority', 10));
		add_action('admin_menu', [$this, 'create_menu'], 100);
		add_action('admin_enqueue_scripts', [$this, 'scripts']);
		add_filter('plugin_action_links', [$this, 'settings_link'], 10, 2);
		add_action('plugins_loaded', [$this, 'text_domain']);
		register_uninstall_hook(__FILE__, [__CLASS__, 'uninstall']);
	}

	public function text_domain() {
		load_plugin_textdomain('xsmtp', false, basename(dirname(__FILE__)).'/languages');
	}

	public function settings_link($links, $plugin_file_name) {
		if (strpos($plugin_file_name, basename(__FILE__)) !== false) {
			$setting_link = '<a href="'.admin_url('options-general.php?page='.self::SLUG).'">'.esc_html__('Settings', 'xsmtp').'</a>';
			array_unshift($links, $setting_link);
		}
		return $links;
	}

	private function get_default_options() {
		return [
			'db'                      => 1,
			'smtp-host'               => '',
			'smtp-encryption-type'    => 'ssl',
			'smtp-port'               => 465,
			'smtp-username'           => '',
			'smtp-password'           => '',
			'from-email-address'      => '',
			'from-email-name'         => '',
			'test-email-address'      => '',
			'test-email-subject'      => '',
			'test-email-message'      => '',
		];
	}

	public function phpmailer_settings(&$phpmailer) {
		$opts = $this->get_options();
		if ($opts['smtp-host'] === '') {
			return;
		}

		$phpmailer->Mailer     = 'smtp';
		$phpmailer->SMTPAuth   = $opts['smtp-encryption-type'] !== 'none';
		$phpmailer->Host       = $opts['smtp-host'];
		$phpmailer->Port       = (int) $opts['smtp-port'];
		$phpmailer->Username   = $opts['smtp-username'];
		$phpmailer->Password   = $opts['smtp-password'];
		$phpmailer->From       = $opts['from-email-address'] === '' ? get_bloginfo('admin_email') : $opts['from-email-address'];
		$phpmailer->FromName   = $opts['from-email-name'] === '' ? get_bloginfo('name') : $opts['from-email-name'];
		if ($opts['smtp-encryption-type'] === 'none') {
			return;
		}
		$phpmailer->SMTPSecure = $opts['smtp-encryption-type'];
		$phpmailer->SMTPOptions = [
			'ssl' => [
				'verify_peer' => false,
				'verify_peer_name' => false,
				'allow_self_signed' => true
			],
		];
	}

	public function get_options() {
		if ($this->options !== false) {
			return $this->options;
		}
		return get_option(self::SLUG.'_settings', $this->get_default_options());
	}

	public function set_options($options) {
		update_option(self::SLUG.'_settings', $options);
	}

	public function create_menu() {
		if (!current_user_can('manage_options')) {
			return;
		}

		$this->screen = add_submenu_page(
			'options-general.php',
			'X SMTP',
			'X SMTP',
			'manage_options',
			self::SLUG,
			[$this, 'render_page']
		);

		add_action('load-'.$this->screen, [$this, 'save_action']);
		add_action('load-'.$this->screen, [$this, 'test_action']);
		add_action('load-'.$this->screen, [$this, 'import_action']);
		add_action('load-'.$this->screen, [$this, 'help']);
	}

	private function maybe_suggest_import() {
		$options = $this->get_options();
		if ($options['smtp-host'] !== '') {
			return;
		}

		$string = '';
		foreach (self::CANIMPORT as $opt => $name) {
			if (get_option($opt, false) === false) {
				continue;
			}
			$url = add_query_arg([
				'page' => self::SLUG,
				'action' => 'import',
				'settings' => $opt,
				'_xsmtp' => wp_create_nonce('import'),
			]);
			$string .= '<a href ="'.$url.'" >';
			// Translators: %s is the plugin name
			$string .= sprintf(esc_html__('Import settings from %s', 'xsmtp'), esc_html($name));
			$string .= '</a> | ';
		}

		if ($string === '') {
			return;
		}

		echo '<div class="xsmtp-import">'.esc_html__('Seems that a configuration can be imported.', 'xsmtp').'<br>'.wp_kses_post(trim($string, ' |')).'</div>';
	}

	public function scripts($hook) {
		if ($hook !== $this->screen) {
			return;
		}
		wp_enqueue_script(self::SLUG.'-js', plugin_dir_url(__FILE__).'js/xsmtp-settings.js', [], '1.0.0', false);
	}

	public function render_page() {
		echo '<div class="wrap"><h1>'.esc_html(get_admin_page_title()).'</h1>';
		$this->display_notices('xsmtp_notices');
		$this->maybe_suggest_import();
		$tabs = [
				'tab1' => esc_html__('SMTP Settings', 'xsmtp'),
				'tab2' => esc_html__('Test', 'xsmtp'),
			];
		$current_tab = isset($_GET['tab']) && isset($tabs[$_GET['tab']]) ? sanitize_key(wp_unslash($_GET['tab'])) : array_key_first($tabs); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<nav class="nav-tab-wrapper">';
		foreach ($tabs as $tab => $name) {
			$current = $tab === $current_tab ? ' nav-tab-active' : '';
			$url = add_query_arg(['page' => self::SLUG, 'tab' => $tab], '');
			echo '<a class="nav-tab'.esc_attr($current).'" href="'.esc_url_raw($url).'">'.esc_html($name).'</a>';
		}
		echo '</nav>';
		if ($current_tab === 'tab1') {
			echo '<form action="'.esc_url_raw(add_query_arg(['action' => 'save', 'tab' => 'tab1'], admin_url('options-general.php?page='.self::SLUG))).'" method="POST">';
			wp_nonce_field('save', '_xsmtp');
			$this->render_settings();
			echo '<input type="submit" class="button button-primary" id="submit_button" value="'.esc_html__('Save', 'xsmtp').'"></input></form>';
		} elseif ($current_tab === 'tab2') {
			echo '<form action="'.esc_url_raw(add_query_arg(['action' => 'test', 'tab' => 'tab2'], admin_url('options-general.php?page='.self::SLUG))).'" method="POST">';
			wp_nonce_field('test', '_xsmtp');
			$this->render_test();
			echo '<input type="submit" class="button button-primary" id="submit_button" value="'.esc_html__('Send', 'xsmtp').'"></input></form>';
		}
		echo '</form>';
		$this->maybe_render_conflicts();
		echo '</div>';
	}

	public function save_action() {
		if ($this->before_action_checks('save') !== true) {
			return;
		}

		$options = $this->get_options();
		foreach ($options as $key => $value) {
			if (!isset($_POST[$key])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				continue;
			}
			$options[$key] = sanitize_text_field(wp_unslash($_POST[$key])); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}

		$this->set_options($options);

		$this->add_notice('xsmtp_notices', esc_html__('Saved.', 'xsmtp'), false);
		$sendback = remove_query_arg(['action', '_xsmtp'], wp_get_referer());
		wp_safe_redirect($sendback);
		exit;
	}

	public function test_action() {
		if ($this->before_action_checks('test') !== true) {
			return;
		}

		$options = $this->get_options();
		foreach ($options as $key => $value) {
			if (!isset($_POST[$key])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				continue;
			}
			$options[$key] = sanitize_text_field(wp_unslash($_POST[$key])); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}

		$this->set_options($options);
		$this->send_test();

		$sendback = remove_query_arg(['action', '_xsmtp'], wp_get_referer());
		wp_safe_redirect($sendback);
		exit;
	}

	public function import_action() {
		if ($this->before_action_checks('import') !== true) {
			return;
		}

		if (!isset($_REQUEST['settings'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$from = sanitize_key(wp_unslash($_REQUEST['settings'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if (!array_key_exists($from, self::CANIMPORT)) {
			return;
		}

		$options = $this->get_options();

		switch ($from) {
			case 'azrcrv-smtp':
				$azrcrv_smtp = get_option('azrcrv-smtp', []);
				foreach ($options as $key => $value) {
					if (!isset($azrcrv_smtp[$key])) {
						continue;
					}
					$options[$key] = $azrcrv_smtp[$key];
				}
				break;

		}

		$this->set_options($options);

		$this->add_notice('xsmtp_notices', esc_html__('Options imported. Please check the new settings.', 'xsmtp'), false);
		$sendback = remove_query_arg(['action', 'settings', '_xsmtp'], wp_get_referer());
		wp_safe_redirect($sendback);
		exit;
	}

	private function send_test() {
		$options = $this->get_options();
		require_once ABSPATH.WPINC.'/PHPMailer/PHPMailer.php';
		require_once ABSPATH.WPINC.'/PHPMailer/SMTP.php';
		require_once ABSPATH.WPINC.'/PHPMailer/Exception.php';
		$phpmailer = new \PHPMailer\PHPMailer\PHPMailer();
		$this->phpmailer_settings($phpmailer);

		$phpmailer->addAddress($options['test-email-address']);
		$phpmailer->Subject = $options['test-email-subject'];
		$phpmailer->Body    = $options['test-email-message'];

		$phpmailer->SMTPDebug   = 1;
		$phpmailer->Timeout     = 15;
		$phpmailer->SMTPOptions = [
			'ssl' => [
				'verify_peer' => false,
				'verify_peer_name' => false,
				'allow_self_signed' => true
			],
		];

		$phpmailer->Debugoutput = function($str) use (&$error) {
			$error .= $str.'<br>';
		};

		if ($phpmailer->send()) {
			$this->add_notice('xsmtp_notices', esc_html__('Sent.', 'xsmtp'), false);
		} else {
			$this->add_notice('xsmtp_notices', '<b>'.esc_html__('An error occurred.', 'xsmtp').'</b><br>'.$error, true);
		}
	}

	private function render_settings() {
		$options = $this->get_options();
		echo '
<table class="form-table">
<tr><th scope="row"><label for="smtp-host">'.esc_html__('SMTP Host', 'xsmtp').'</label></th>
<td><input name="smtp-host" type="text" id="smtp-host" value="'.esc_attr($options['smtp-host']).'" class="regular-text"><p class="description">'.esc_html__('Your mail server address.', 'xsmtp').'</p></td></tr>
<tr><th scope="row"><label for="smtp-encryption-type">'.esc_html__('SMTP Encryption Type', 'xsmtp').'</label></th>
<td><select name="smtp-encryption-type">
	<option value="none" '.($options['smtp-encryption-type'] === 'none' ? 'selected' : '').'>None</option>
	<option value="ssl"  '.($options['smtp-encryption-type'] === 'ssl' ? 'selected' : '').'>SSL/TLS</option>
	<option value="tls"  '.($options['smtp-encryption-type'] === 'tls' ? 'selected' : '').'>StartTLS</option>
</select><p class="description">'.esc_html__('For most servers SSL/TLS is the recommended encryption type.', 'xsmtp').'</p></td></tr>
<tr><th scope="row"><label for="smtp-port">'.esc_html__('SMTP Port', 'xsmtp').'</label></th>
<td><input name="smtp-port" type="number" step="1" min="1" id="smtp-port" value="'.esc_attr($options['smtp-port']).'" class="small-text">
<p class="description">'.esc_html__('The port to your mail server (Standards are 25 for no encryption, 465 for SSL/TLS and 587 for StartTLS).', 'xsmtp').'</p></td></tr>
<tr><th scope="row"><label for="smtp-username">'.esc_html__('SMTP Username', 'xsmtp').'</label></th>
<td><input name="smtp-username" type="text" id="smtp-username" autocomplete="do-not-autofill" value="'.esc_attr($options['smtp-username']).'" class="regular-text">
<p class="description">'.esc_html__('The username to login to your mail server.', 'xsmtp').'</p></td></tr>
<tr><th scope="row"><label for="smtp-password">'.esc_html__('SMTP Password', 'xsmtp').'</label></th>
<td><input name="smtp-password" type="password" id="smtp-password" autocomplete="new-password" value="'.esc_attr($options['smtp-password']).'" class="regular-text">
<span><i id="password-toggle-icon" class="dashicons dashicons-visibility"></i></span>
<p class="description">'.esc_html__('The password to login to your mail server. The password is stored in plain text in the database.', 'xsmtp').'</p></td></tr>
<tr><th scope="row"><label for="from-email-address">'.esc_html__('From Email Address', 'xsmtp').'</label></th>
<td><input name="from-email-address" type="email" id="from-email-address" value="'.esc_attr($options['from-email-address']).'" class="regular-text">
<p class="description">'.sprintf(esc_html__('This will be used as the "From" email address; leave blank to use "%s".', 'xsmtp'), esc_html(get_bloginfo('admin_email'))).'</p></td></tr>
<tr><th scope="row"><label for="from-email-name">'.esc_html__('From Email Name', 'xsmtp').'</label></th>
<td><input name="from-email-name" type="text" id="from-email-name" value="'.esc_attr($options['from-email-name']).'" class="regular-text">
<p class="description">'.sprintf(esc_html__('This will be used as the name for the "From" email address; leave blank to use "%s".', 'xsmtp'), esc_html(get_bloginfo('name'))).'</p></td></tr></table>';
	}

	private function render_test() {
		$options = $this->get_options();
		echo '
<table class="form-table"><tr><th scope="row" colspan="2"><label for="explanation">'.esc_html__('Test your configuration by sending a test email.', 'xsmtp').'</label></th></tr>
<tr><th scope="row"><label for="test-email-address">'.esc_html__('Email Address', 'xsmtp').'</label></th>
<td><input name="test-email-address" type="email" id="test-email-address" value="'.esc_attr($options['test-email-address']).'" class="regular-text"></td></tr>
<tr><th scope="row"><label for="test-email-subject">'.esc_html__('Email Subject', 'xsmtp').'</label></th>
<td><input name="test-email-subject" type="text" id="test-email-subject" value="'.esc_attr($options['test-email-subject']).'" class="regular-text"></td></tr>
<tr><th scope="row"><label for="test-email-message">'.esc_html__('Email Message', 'xsmtp').'</label></th>
<td><input name="test-email-message" type="text" id="test-email-message" value="'.esc_attr($options['test-email-message']).'" class="regular-text"></td></tr></table>';
	}

	private function maybe_render_conflicts() {
		global $wp_filter;
		$filters = $wp_filter['phpmailer_init']->callbacks;
		$fcount = 0;
		foreach ($filters as $filter) {
			$fcount += count($filter);
		}
		if ($fcount === 1) {
			return;
		}

		$plugins = [
			'azrcrv-smtp/azrcrv-smtp.php'   => 'SMTP',
			'wp-mail-smtp/wp-mail-smtp.php' => 'WP Mail SMTP',
			'post-smtp/postman-smtp.php'    => 'Post SMTP',
			'easy-wp-smtp/easy-wp-smtp.php' => 'Easy WP SMTP',
			'fluent-smtp/fluent-smtp.php'   => 'FluentSMTP',
			'wp-smtp/wp-smtp.php'           => 'Solid Mail',
			'smtp-mailer/main.php'          => 'SMTP Mailer',
			'gosmtp/gosmtp.php'             => 'GoSMTP',
		];

		$conflicts = [];
		foreach ($plugins as $slug => $name) {
			if (!is_plugin_active($slug)) {
				continue;
			}
			$conflicts[] = $name;
		}

		echo '<hr>';

		$conflicting_count = count($conflicts);
		if ($conflicting_count !== 0) {
			$msg = _n(
				'This plugin can cause conflicts:',
				'Those plugins can cause conflicts:',
				$conflicting_count,
				'xsmtp'
			);
			$msg .= ' '.implode(', ', $conflicts).'.';
			echo esc_html($msg);
			return;
		}

		$msg  = '<details><summary>'.esc_html__('Something can interfer with this plugin functionality. Debug.', 'xsmtp').'</summary><pre style="background-color: white;">';
		$msg .= print_r($filters, true); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		$msg .= '</pre></details>';

		echo wp_kses_post($msg);
	}

	public function help() {
		$general_content = wp_kses(
			__(
				'<b>Improve ClassicPress email deliverability.</b><br>
				Configure a SMTP server to send email from your site.<br>
				From this page you can configure the parameters of the SMTP server that will be used to send the e-mail.<br>
				Notice that servers using OAuth (like gmail.com) are not supported.<br>
				This plugin is multisite compatible. Each site should be configured.',
				'xsmtp'
			),
			[
				'b'  => [],
				'br' => [],
			]
		);

		$screen = get_current_screen();
		$screen->add_help_tab(
			[
				'id'	  => 'xsmtp_help_tab_general',
				'title'	  => esc_html__('Usage', 'xsmtp'),
				'content' => '<p>'.$general_content.'</p>',
			]
		);
	}

	public static function uninstall() {
		if (!is_multisite()) {
			delete_option(self::SLUG.'_settings');
		} else {
			global $wpdb;
			$ids     = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
			$current = get_current_site_id();
			foreach ($ids as $id) {
				switch_to_blog($id);
				delete_option(self::SLUG.'_settings');
			}
			switch_to_blog($current);
		}
	}

}

new XSMTP();
