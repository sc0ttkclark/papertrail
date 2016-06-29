<?php
/**
 * Plugin Name: Papertrail Logging API
 * Plugin URI:  https://github.com/sc0ttkclark/papertrail
 * Description: Papertrail Logging API for WordPress
 * Version:     0.2
 * Author:      Scott Kingsley Clark
 * Author URI:  http://scottkclark.com/
 */

// See https://papertrailapp.com/account/destinations
define( 'WP_PAPERTRAIL_DESTINATION', 'logs4.papertrailapp.com:15100' );

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
			self::$socket = socket_create( AF_INET, SOCK_DGRAM, SOL_UDP );

			socket_connect( self::$socket, $destination['hostname'], $destination['port'] );
		}

		$result = socket_send( self::$socket, $syslog_message, strlen( $syslog_message ), 0 );

		//socket_close( self::$socket );

		$success = false;

		if ( false !== $result ) {
			$success = true;
		}

		return $success;

	}

}
