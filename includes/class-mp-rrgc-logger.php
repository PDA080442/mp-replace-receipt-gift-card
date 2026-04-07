<?php
/**
 * Logger for MP Replace Receipt Gift Card.
 *
 * @package MP_Replace_Receipt_Gift_Card
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MP_RRGC_Logger {
	private const LEVEL_DEBUG = 'DEBUG';
	private const LEVEL_INFO  = 'INFO';
	private const LEVEL_ERROR = 'ERROR';

	/**
	 * @param string $level   DEBUG|INFO|ERROR
	 * @param int    $order_id
	 * @param string $action
	 * @param array  $context
	 */
	public static function log( string $level, int $order_id, string $action, array $context = array() ): void {
		$level = strtoupper( $level );
		if ( ! in_array( $level, array( self::LEVEL_DEBUG, self::LEVEL_INFO, self::LEVEL_ERROR ), true ) ) {
			$level = self::LEVEL_INFO;
		}

		if ( self::LEVEL_DEBUG === $level && ! MP_RRGC_Settings::is_debug_enabled() ) {
			return;
		}

		$line = sprintf(
			"[%s] %s order=%d action=%s context=%s\n",
			gmdate( 'Y-m-d H:i:s' ),
			$level,
			$order_id,
			self::sanitize_string( $action ),
			wp_json_encode( self::sanitize_context( $context ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
		);

		$path = self::get_log_file_path();
		if ( '' === $path ) {
			return;
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		@file_put_contents( $path, $line, FILE_APPEND | LOCK_EX );
	}

	public static function get_log_dir(): string {
		$uploads = wp_upload_dir();
		if ( empty( $uploads['basedir'] ) ) {
			return '';
		}

		return rtrim( $uploads['basedir'], '/\\' ) . DIRECTORY_SEPARATOR . 'mp-replace-receipt-gift-card' . DIRECTORY_SEPARATOR . 'logs';
	}

	public static function ensure_log_dir(): bool {
		$dir = self::get_log_dir();
		if ( '' === $dir ) {
			return false;
		}

		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		if ( ! is_dir( $dir ) || ! is_writable( $dir ) ) {
			return false;
		}

		$index_path = $dir . DIRECTORY_SEPARATOR . 'index.html';
		if ( ! file_exists( $index_path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $index_path, '' );
		}

		$htaccess_path = $dir . DIRECTORY_SEPARATOR . '.htaccess';
		if ( ! file_exists( $htaccess_path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $htaccess_path, "Deny from all\n" );
		}

		return true;
	}

	private static function get_log_file_path(): string {
		if ( ! self::ensure_log_dir() ) {
			return '';
		}

		return self::get_log_dir() . DIRECTORY_SEPARATOR . 'rrgc-' . gmdate( 'Y-m' ) . '.log';
	}

	private static function sanitize_string( string $value ): string {
		$value = sanitize_text_field( $value );
		if ( strlen( $value ) > 200 ) {
			return substr( $value, 0, 200 ) . '...';
		}

		return $value;
	}

	/**
	 * @param mixed $context
	 * @return mixed
	 */
	private static function sanitize_context( $context ) {
		if ( is_array( $context ) ) {
			$out = array();
			foreach ( $context as $key => $value ) {
				$key = (string) $key;
				if ( self::is_secret_key( $key ) ) {
					$out[ $key ] = '[masked]';
					continue;
				}
				$out[ $key ] = self::sanitize_context( $value );
			}
			return $out;
		}

		if ( is_object( $context ) ) {
			return '[object:' . get_class( $context ) . ']';
		}

		if ( is_string( $context ) ) {
			$value = trim( $context );
			if ( strlen( $value ) > 180 ) {
				return substr( $value, 0, 80 ) . '...[len=' . strlen( $value ) . ']';
			}
			return sanitize_text_field( $value );
		}

		if ( is_bool( $context ) || is_int( $context ) || is_float( $context ) || null === $context ) {
			return $context;
		}

		return '[unsupported]';
	}

	private static function is_secret_key( string $key ): bool {
		$k = strtolower( $key );
		return false !== strpos( $k, 'password' )
			|| false !== strpos( $k, 'secret' )
			|| false !== strpos( $k, 'token' )
			|| false !== strpos( $k, 'key' );
	}
}

