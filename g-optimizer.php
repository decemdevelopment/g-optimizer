<?php

/**
 * GOptimizer plugin for WordPress
 *
 * @package g-optimizer
 * @author Nikola Knežević <knezevicdev@gmail.com>
 * @copyright 2021
 * @license   GPL v2 or later
 *
 * Plugin Name:  GOptimizer
 * Description:  Google PageSpeed Optimization helper for WordPress.
 * Version:      1.0.0
 * Plugin URI:
 * Author:       Nikola Knežević
 * Author URI:   https://knezevic.dev/
 * Text Domain:  g-optimizer
 */

defined( 'ABSPATH' ) or die();

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

use Carbon_Fields\Container;
use Carbon_Fields\Field;
use Carbon_Fields\Carbon_Fields;

class GOptimizer {
	private $critical_scripts = [];

	private function __construct() {
		add_action( 'after_setup_theme', [ $this, 'crb_load' ] );
		add_action( 'carbon_fields_register_fields', [ $this, 'register_fields' ] );

		add_action( 'wp_enqueue_scripts', [ $this, 'load_critical_scripts' ], 100 );
		add_action( 'wp_enqueue_scripts', [ $this, 'customize_scripts_output' ], 101 );
		add_action( 'wp_head', [ $this, 'preload_links' ], 1 );
		add_filter( 'script_loader_tag', [ $this, 'add_defer_attribute' ], 10, 2 );
	}

	public function load_critical_scripts() {
		$this->critical_scripts = carbon_get_theme_option( 'critical_scripts' );
	}

	public function customize_scripts_output() {
		global $wp_scripts;

		foreach ( $this->critical_scripts as $critical_script ) {
			if ( $critical_script['move_to_footer'] ) {
				$wp_scripts->add_data( $critical_script['script_key'], 'group', 1 );
			}

			if ( $critical_script['replacement_url'] ) {
				$wp_scripts->registered[ $critical_script['script_key'] ]->src = $critical_script['replacement_url'];
			}

			if ( $critical_script['disable_on_speed_check'] && strpos( $_SERVER['HTTP_USER_AGENT'], 'Lighthouse' ) ) {
				wp_deregister_script( $critical_script['script_key'] );
			}
		}
	}

	public function add_defer_attribute( $tag, $handle ) {
		foreach ( $this->critical_scripts as $critical_script ) {
			if ( $critical_script['defer_loading'] && $critical_script['script_key'] === $handle ) {
				return str_replace( ' src', ' defer src', $tag );
			}
		}

		return $tag;
	}

	public function preload_links() {
		$preload_links = carbon_get_theme_option( 'preload_links' );

		foreach ( $preload_links as $key => $preload_link ) {
			$resource_url = $preload_link['resource_url'] ?? '';
			$type         = $preload_link['type'] ?? 'unknown';

			if ( $type !== 'unknown' ) {
				$type = "as='$type'";
			} else {
				$type = '';
			};

			if ( ! empty( $resource_url ) ) {
				echo "<link id='g-optimizer-preload-$key' rel='preload' href='$resource_url' $type />";
			}

		}
	}

	public function register_fields() {
		Container::make( 'theme_options', __( 'GOptimizer', 'g-optimizer' ) )
		         ->add_tab( __( 'Preload', 'g-optimizer' ), [
			         Field::make( 'complex', 'preload_links', __( 'Preload Content', 'g-optimizer' ) )
			              ->add_fields( [
				              Field::make( 'text', 'resource_url', __( 'Resource URL', 'g-optimizer' ) )
				                   ->set_width( 50 ),
				              Field::make( 'select', 'type', __( 'Type', 'g-optimizer' ) )
				                   ->add_options( [
					                   'unknown'  => __( 'Unknown', 'g-optimizer' ),
					                   'audio'    => __( 'Audio', 'g-optimizer' ),
					                   'document' => __( 'Document', 'g-optimizer' ),
					                   'embed'    => __( 'Embed', 'g-optimizer' ),
					                   'fetch'    => __( 'Fetch', 'g-optimizer' ),
					                   'font'     => __( 'Font', 'g-optimizer' ),
					                   'image'    => __( 'Image', 'g-optimizer' ),
					                   'object'   => __( 'Object', 'g-optimizer' ),
					                   'script'   => __( 'Script', 'g-optimizer' ),
					                   'style'    => __( 'Style', 'g-optimizer' ),
					                   'track'    => __( 'Track', 'g-optimizer' ),
					                   'worker'   => __( 'Worker', 'g-optimizer' ),
					                   'video'    => __( 'Video', 'g-optimizer' ),
				                   ] )
				                   ->set_width( 50 ),
			              ] )
		         ] )
		         ->add_tab( __( 'Critical Scripts', 'g-optimizer' ), [
			         Field::make( 'complex', 'critical_scripts', __( 'Critical Scripts', 'g-optimizer' ) )
			              ->add_fields( [
				              Field::make( 'text', 'script_key', __( 'Script Key', 'g-optimizer' ) )
				                   ->set_width( 50 ),
				              Field::make( 'text', 'replacement_url', __( 'Replacement URL', 'g-optimizer' ) )
				                   ->set_width( 50 ),
				              Field::make( 'checkbox', 'move_to_footer', __( 'Move To Footer', 'g-optimizer' ) )
				                   ->set_option_value( 'yes' )
				                   ->set_width( 33 ),
				              Field::make( 'checkbox', 'defer_loading', __( 'Defer Loading', 'g-optimizer' ) )
				                   ->set_option_value( 'yes' )
				                   ->set_width( 33 ),
				              Field::make( 'checkbox', 'disable_on_speed_check', __( 'Disable On Speed Check', 'g-optimizer' ) )
				                   ->set_option_value( 'yes' )
				                   ->set_width( 33 ),
			              ] )
		         ] );
	}

	public function crb_load() {
		Carbon_Fields::boot();
	}

	public static function init(): GOptimizer {
		static $instance = null;

		if ( ! $instance ) {
			$instance = new GOptimizer();
		}

		return $instance;
	}
}

GOptimizer::init();
