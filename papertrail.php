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
define( 'WP_PAPERTRAIL_COLORIZE', true );

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

		if ( defined( 'WP_PAPERTRAIL_COLORIZE' ) && WP_PAPERTRAIL_COLORIZE ) {
			$json = self::colorize_json( $json );
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
		}

		$result = socket_sendto( self::$socket, $syslog_message, strlen( $syslog_message ), 0, $destination['hostname'], $destination['port'] );
		//socket_close( self::$socket );

		$success = false;

		if ( false !== $result ) {
			$success = true;
		}

		return $success;

	}

	/**
	 * Colorize JSON string.
	 *
	 * @param string $json JSON string.
	 *
	 * @return string Colorized JSON string.
	 */
	protected static function colorize_json( $json ) {

		$seq = array(
			'reset' => "\033[0m",
			'color' => "\033[1;%dm",
			'bold'  => "\033[1m",
		);

		$fcolor = array(
			'black'   => "\033[30m",
			'red'     => "\033[31m",
			'green'   => "\033[32m",
			'yellow'  => "\033[33m",
			'blue'    => "\033[34m",
			'magenta' => "\033[35m",
			'cyan'    => "\033[36m",
			'white'   => "\033[37m",
		);

		$bcolor = array(
			'black'   => "\033[40m",
			'red'     => "\033[41m",
			'green'   => "\033[42m",
			'yellow'  => "\033[43m",
			'blue'    => "\033[44m",
			'magenta' => "\033[45m",
			'cyan'    => "\033[46m",
			'white'   => "\033[47m",
		);

		$output = $json;
		$output = preg_replace( '/(":)([0-9]+)/', '$1' . $fcolor['magenta'] . '$2' . $seq['reset'], $output );
		$output = preg_replace( '/(":)(true|false)/', '$1' . $fcolor['magenta'] . '$2' . $seq['reset'], $output );
		$output = str_replace( '{"', '{' . $fcolor['green'] . '"', $output );
		$output = str_replace( ',"', ',' . $fcolor['green'] . '"', $output );
		$output = str_replace( '":', '"' . $seq['reset'] . ':', $output );
		$output = str_replace( ':"', ':' . $fcolor['green'] . '"', $output );
		$output = str_replace( '",', '"' . $seq['reset'] . ',', $output );
		$output = str_replace( '",', '"' . $seq['reset'] . ',', $output );
		$output = $seq['reset'] . $output . $seq['reset'];

		return $output;

	}

}
