<?php
/**
 * Plugin Name: Papertrail Logging API
 * Plugin URI:  https://github.com/sc0ttkclark/papertrail
 * Description: Papertrail Logging API for WordPress
 * Version:     0.3
 * Author:      Scott Kingsley Clark
 * Author URI:  http://scottkclark.com/
 */

// See https://papertrailapp.com/account/destinations
// define( 'WP_PAPERTRAIL_DESTINATION', 'logs4.papertrailapp.com:15100' );

class WP_Papertrail_API {

	/**
	 * Socket resource for reuse
	 *
	 * @var resource
	 */
	protected static $socket;

	/**
	 * Methods in this class are meant to be called statically
	 */
	private function __construct() {

		// Hulk smash

	}

	/**
	 * Log data to Papertrail.
	 *
	 * @author Troy Davis from the Gist located here: https://gist.github.com/troy/2220679
	 *
	 * @param string|array|object $data      Data to log to Papertrail.
	 * @param string              $component Component name to identify log in Papertrail.
	 *
	 * @return bool|WP_Error True if successful or an WP_Error object with the problem.
	 */
	public static function log( $data, $component = '' ) {

		if ( ! defined( 'WP_PAPERTRAIL_DESTINATION' ) || ! WP_PAPERTRAIL_DESTINATION ) {
			return new WP_Error( 'papertrail-no-destination', __( 'No Papertrail destination set.', 'papertrail' ) );
		}

		$destination = array_combine( array( 'hostname', 'port' ), explode( ':', WP_PAPERTRAIL_DESTINATION ) );
		$program     = parse_url( is_multisite() ? network_site_url() : site_url(), PHP_URL_HOST );
		$json        = json_encode( $data );

		if ( empty( $destination ) || 2 != count( $destination ) || empty( $destination['hostname'] ) ) {
			return new WP_Error( 'papertrail-invalid-destination', sprintf( __( 'Invalid Papertrail destination (%s >> %s:%s).', 'papertrail' ), WP_PAPERTRAIL_DESTINATION, $destination['hostname'], $destination['port'] ) );
		}

		$syslog_message = '<22>' . date_i18n( 'M d H:i:s' );

		if ( $program ) {
			$syslog_message .= ' ' . trim( $program );
		}

		if ( $component ) {
			$syslog_message .= ' ' . trim( $component );
		}

		$syslog_message .= ' ' . $json;

		if ( ! self::$socket ) {
			self::$socket = @socket_create( AF_INET, SOCK_DGRAM, SOL_UDP );

			@socket_connect( self::$socket, $destination['hostname'], $destination['port'] );
		}

		$result = socket_send( self::$socket, $syslog_message, strlen( $syslog_message ), 0 );

		//socket_close( self::$socket );

		$success = false;

		if ( false !== $result ) {
			$success = true;
		}

		return $success;

	}

	/**
	 * Get page info
	 *
	 * @param array $page_info
	 *
	 * @return array
	 */
	public static function get_page_info( $page_info = array() ) {

		// Setup URL
		$page_info['url'] = 'http://';

		if ( is_ssl() ) {
			$page_info['url'] = 'https://';
		}

		$page_info['url'] .= $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

		$page_info['url'] = explode( '?', $page_info['url'] );
		$page_info['url'] = $page_info['url'][0];
		$page_info['url'] = explode( '#', $page_info['url'] );
		$page_info['url'] = $page_info['url'][0];

		$page_info['$_GET']  = $_GET;
		$page_info['$_POST'] = $_POST;

		$page_info['DOING_AJAX'] = ( defined( 'DOING_AJAX' ) && DOING_AJAX );
		$page_info['DOING_CRON'] = ( defined( 'DOING_CRON' ) && DOING_CRON );

		// Remove potentially sensitive information from page info
		if ( isset( $page_info['$_GET']['password'] ) ) {
			unset( $page_info['$_GET']['password'] );
		}

		if ( isset( $page_info['$_GET']['pwd'] ) ) {
			unset( $page_info['$_GET']['pwd'] );
		}

		if ( isset( $page_info['$_POST']['password'] ) ) {
			unset( $page_info['$_POST']['password'] );
		}

		if ( isset( $page_info['$_POST']['pwd'] ) ) {
			unset( $page_info['$_POST']['pwd'] );
		}

		return $page_info;

	}

	/**
	 * Handle error logging to Papertrail
	 *
	 * @param int    $id      Error number
	 * @param string $message Error message
	 * @param string $file    Error file
	 * @param int    $line    Error line
	 * @param array  $context Error context
	 */
	public static function error_handler( $id, $message, $file, $line, $context ) {

		$type = 'unknown';

		switch ( $id ) {
			case E_ERROR: // 1 //
				$type = 'E_ERROR';

				break;
			case E_WARNING: // 2 //
				$type = 'E_WARNING';

				break;
			case E_PARSE: // 4 //
				$type = 'E_PARSE';

				break;
			case E_NOTICE: // 8 //
				$type = 'E_NOTICE';

				break;
			case E_CORE_ERROR: // 16 //
				$type = 'E_CORE_ERROR';

				break;
			case E_CORE_WARNING: // 32 //
				$type = 'E_CORE_WARNING';

				break;
			case E_COMPILE_ERROR: // 64 //
				$type = 'E_COMPILE_ERROR';

				break;
			case E_COMPILE_WARNING: // 128 //
				$type = 'E_COMPILE_WARNING';

				break;
			case E_USER_ERROR: // 256 //
				$type = 'E_USER_ERROR';

				break;
			case E_USER_WARNING: // 512 //
				$type = 'E_USER_WARNING';

				break;
			case E_USER_NOTICE: // 1024 //
				$type = 'E_USER_NOTICE';

				break;
			case E_STRICT: // 2048 //
				$type = 'E_STRICT';

				break;
			case E_RECOVERABLE_ERROR: // 4096 //
				$type = 'E_RECOVERABLE_ERROR';

				break;
			case E_DEPRECATED: // 8192 //
				$type = 'E_DEPRECATED';

				break;
			case E_USER_DEPRECATED: // 16384 //
				$type = 'E_USER_DEPRECATED';

				break;
		}

		$page_info = array(
			'error' => sprintf( '%s | %s | %s:%s', $type, $message, $file, $line ),
		);

		$page_info = self::get_page_info( $page_info );

		if ( 'E_ERROR' != $type ) {
			unset( $page_info['$_POST'] );
			unset( $page_info['$_GET'] );
		}

		self::log( $page_info, 'WP_Papertrail_API/Error/' . $type );

	}

}

// Setup error handler
if ( defined( 'WP_PAPERTRAIL_ERROR_HANDLER' ) && WP_PAPERTRAIL_ERROR_HANDLER ) {
	set_error_handler( array( 'WP_Papertrail_API', 'error_handler' ) );
}
