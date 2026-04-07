<?php
/**
 * Orchestrator facade.
 *
 * In step 0 this class only exists as a single entry point for registering hooks.
 * Real detection and payload replacement will be implemented in later steps.
 *
 * @package MP_Replace_Receipt_Gift_Card
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MP_RRGC_Orchestrator {
	/** @var bool */
	private static $hooks_inited = false;

	public static function init_hooks(): void {
		if ( self::$hooks_inited ) {
			return;
		}

		self::$hooks_inited = true;

		if ( MP_RRGC_Settings::is_yk_enabled() && class_exists( 'MP_RRGC_YK_Replacer' ) ) {
			MP_RRGC_YK_Replacer::register_hooks();
		}
	}
}

