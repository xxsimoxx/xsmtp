<?php

namespace XXSimoXX\XSMTP;

trait Helpers
{

	/**
	 * Security check before processing an action
	 *
	 * @param  string $action The action name.
	 * @return bool           Result of the checks.
	 */
	function before_action_checks($action) {
		if (!isset($_GET['action'])) {
			return false;
		}
		if ($_GET['action'] !== $action) {
			return false;
		}
		if (!check_admin_referer($action, '_xsmtp')) {
			return false;
		}
		if (!current_user_can('manage_options')) {
			return false;
		}
		return true;
	}

	/**
	 * Add an admin notice.
	 *
	 * @param string  $transient Transient name.
	 * @param string  $message   Notice message.
	 * @param boolean $failure   Is a failure? Default false.
	 * @return void
	 */
	function add_notice($transient, $message, $failure = false) {
		$kses_allowed = [
			'br' => [],
			'i'  => [],
			'b'  => [],
		];
		$other_notices = get_transient($transient);
		$notice = $other_notices === false ? '' : $other_notices;
		$failure_style = $failure ? 'notice-error ' : 'notice-success ';
		$notice .= '<div class="notice '.$failure_style.'is-dismissible">';
		$notice .= '    <p>'.wp_kses($message, $kses_allowed).'</p>';
		$notice .= '</div>';
		set_transient($transient, $notice, \HOUR_IN_SECONDS);
	}

	/**
	 * Displays all admin notices.
	 *
	 * @param string $transient Transient name.
	 * @return void
	 */
	function display_notices($transient) {
		$notices = get_transient($transient);
		if ($notices === false) {
			return;
		}
		// This contains html formatted from 'add_notice' function that uses 'wp_kses'.
		echo $notices; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		delete_transient($transient);
	}

}
