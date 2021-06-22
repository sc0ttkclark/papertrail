<?php
/**
 * Plugin Name: Papertrail Logging API
 * Plugin URI:  https://github.com/sc0ttkclark/papertrail
 * Description: Papertrail Logging API for WordPress
 * Version:     0.5
 * Author:      Scott Kingsley Clark
 * Author URI:  http://scottkclark.com/
 */

// See https://papertrailapp.com/account/destinations
// define( 'WP_PAPERTRAIL_DESTINATION', 'logs4.papertrailapp.com:12345' );

class WP_Papertrail_API {

	/**
	 * Socket resource for reuse
	 *
	 * @var resource
	 */
	protected static $socket;

	/**
	 * An array of error codes and their equivalent string value
	 *
	 * @var array
	 */
	protected static $codes = [
		E_ERROR             => 'E_ERROR',
		E_WARNING           => 'E_WARNING',
		E_PARSE             => 'E_PARSE',
		E_NOTICE            => 'E_NOTICE',
		E_CORE_ERROR        => 'E_CORE_ERROR',
		E_CORE_WARNING      => 'E_CORE_WARNING',
		E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
		E_COMPILE_WARNING   => 'E_COMPILE_WARNING',
		E_USER_ERROR        => 'E_USER_ERROR',
		E_USER_WARNING      => 'E_USER_WARNING',
		E_USER_NOTICE       => 'E_USER_NOTICE',
		E_STRICT            => 'E_STRICT',
		E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
		E_DEPRECATED        => 'E_DEPRECATED',
		E_USER_DEPRECATED   => 'E_USER_DEPRECATED',
	];

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

		$destination = array_combine( [ 'hostname', 'port' ], explode( ':', WP_PAPERTRAIL_DESTINATION ) );
		$program     = parse_url( is_multisite() ? network_site_url() : site_url(), PHP_URL_HOST );
		$json        = json_encode( $data );

		if ( empty( $destination ) || 2 !== count( $destination ) || empty( $destination['hostname'] ) ) {
			return new WP_Error( 'papertrail-invalid-destination', sprintf( __( 'Invalid Papertrail destination (%s >> %s:%s).', 'papertrail' ), WP_PAPERTRAIL_DESTINATION, $destination['hostname'], $destination['port'] ) );
		}

		if ( defined( 'WP_PAPERTRAIL_LOG_LEVEL' ) && WP_PAPERTRAIL_LOG_LEVEL && false !== ( $code = self::codify_error_string( $component ) ) && ! ( WP_PAPERTRAIL_LOG_LEVEL & $code ) ) {
			return new WP_Error( 'papertrail-log-level-off', esc_html( sprintf( __( 'The log level %s has been turned off in this configuration. Current log level: %d', 'papertrail' ), self::stringify_error_code( $code ), WP_PAPERTRAIL_LOG_LEVEL ) ) );
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
	public static function get_page_info( $page_info = [] ) {
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
	 * Turn a string representation of an error type into an error code
	 *
	 * If the error code doesn't exist in our array, this will return false. $type will get run through basename, so
	 * component strings from error logs will get handled without any changes necessary to the type value.
	 *
	 * @param string $type
	 *
	 * @return false|int
	 */
	protected static function codify_error_string( $type ) {
		return array_search( basename( $type ), self::$codes );
	}

	protected static function stringify_error_code( $code ) {
		return isset( self::$codes[ $code ] ) ? self::$codes[ $code ] : 'unknown';
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
		$type = self::stringify_error_code( $id );

		$page_info = [
			'error' => sprintf( '%s | %s | %s:%s', $type, $message, $file, $line ),
		];

		$page_info = self::get_page_info( $page_info );

		if ( 'E_ERROR' !== $type ) {
			unset( $page_info['$_POST'] );
			unset( $page_info['$_GET'] );
		}

		self::log( $page_info, 'WP_Papertrail_API/Error/' . $type );
	}

}

// Setup error handler
if ( defined( 'WP_PAPERTRAIL_ERROR_HANDLER' ) && WP_PAPERTRAIL_ERROR_HANDLER ) {
	set_error_handler( [ 'WP_Papertrail_API', 'error_handler' ] );
}
