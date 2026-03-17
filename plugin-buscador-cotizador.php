<?php
/**
 * Plugin Name:       Plugin Buscador Cotizador
 * Description:       Buscador y cotizador básico con shortcode de prueba para validar instalación.
 * Version:           1.0.0
 * Author:            Sebastián Pablo Galleano
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Text Domain:       plugin-buscador-cotizador
 *
 * @package PluginBuscadorCotizador
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PBC_PLUGIN_VERSION', '1.0.0' );
define( 'PBC_PLUGIN_FILE', __FILE__ );
define( 'PBC_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'PBC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once PBC_PLUGIN_PATH . 'includes/class-plugin-buscador-cotizador.php';

/**
 * Activación segura del plugin.
 *
 * @return void
 */
function pbc_activate_plugin() {
	if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die( esc_html__( 'Plugin Buscador Cotizador requiere PHP 7.4 o superior.', 'plugin-buscador-cotizador' ) );
	}

	global $wp_version;
	if ( version_compare( $wp_version, '6.0', '<' ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die( esc_html__( 'Plugin Buscador Cotizador requiere WordPress 6.0 o superior.', 'plugin-buscador-cotizador' ) );
	}

	Plugin_Buscador_Cotizador::create_destinations_table();
}
register_activation_hook( __FILE__, 'pbc_activate_plugin' );

/**
 * Inicializa el plugin.
 *
 * @return Plugin_Buscador_Cotizador
 */
function pbc_init_plugin() {
	return Plugin_Buscador_Cotizador::instance();
}

add_action( 'plugins_loaded', 'pbc_init_plugin' );
