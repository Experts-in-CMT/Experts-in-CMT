<?php
namespace Bricks\Integrations\Query_Filters;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Fields {
	private static $instance = null;
	public static $providers = [];

	public function __construct() {
		$providers = [ 'acf', 'metabox' ];
		foreach ( $providers as $provider ) {
			$provider_class = 'Bricks\Integrations\Query_Filters\Field_' . ucfirst( $provider );
			if ( class_exists( $provider_class ) ) {
				self::$providers[ $provider ] = new $provider_class();
			}
		}
	}

	/**
	 * Singleton - Get the instance of this class
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new Fields();
		}

		return self::$instance;
	}

	public static function get_active_provider_list() {
		$active_providers = [];
		foreach ( self::$providers as $provider => $instance ) {
			if ( $instance::is_active() ) {
				$active_providers[ $provider ] = $instance->get_name();
			}
		}

		return $active_providers;
	}
}
