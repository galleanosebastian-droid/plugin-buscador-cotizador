<?php
/**
 * Clase principal del plugin.
 *
 * @package PluginBuscadorCotizador
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Plugin_Buscador_Cotizador {
	/**
	 * Instancia única.
	 *
	 * @var Plugin_Buscador_Cotizador|null
	 */
	private static $instance = null;

	/**
	 * Obtiene la instancia única.
	 *
	 * @return Plugin_Buscador_Cotizador
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_shortcode( 'buscador_cotizador_demo', array( $this, 'render_demo_shortcode' ) );
	}

	/**
	 * Registra assets públicos.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		wp_register_style(
			'pbc-styles',
			PBC_PLUGIN_URL . 'assets/css/plugin-buscador-cotizador.css',
			array(),
			PBC_PLUGIN_VERSION
		);

		wp_register_script(
			'pbc-script',
			PBC_PLUGIN_URL . 'assets/js/plugin-buscador-cotizador.js',
			array(),
			PBC_PLUGIN_VERSION,
			true
		);
	}

	/**
	 * Shortcode de prueba [buscador_cotizador_demo].
	 *
	 * @param array<string, string> $atts Atributos del shortcode.
	 *
	 * @return string
	 */
	public function render_demo_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'titulo' => 'Buscador Cotizador',
			),
			$atts,
			'buscador_cotizador_demo'
		);

		wp_enqueue_style( 'pbc-styles' );
		wp_enqueue_script( 'pbc-script' );

		ob_start();
		?>
		<div class="pbc-card" data-pbc-root>
			<h3 class="pbc-title"><?php echo esc_html( $atts['titulo'] ); ?></h3>
			<p class="pbc-description"><?php esc_html_e( 'Este shortcode confirma que el plugin está activo y funcionando.', 'plugin-buscador-cotizador' ); ?></p>
			<form class="pbc-form" data-pbc-form>
				<label for="pbc-dias"><?php esc_html_e( 'Cantidad de días', 'plugin-buscador-cotizador' ); ?></label>
				<input id="pbc-dias" name="dias" type="number" min="1" value="3" required />

				<label for="pbc-personas"><?php esc_html_e( 'Cantidad de personas', 'plugin-buscador-cotizador' ); ?></label>
				<input id="pbc-personas" name="personas" type="number" min="1" value="2" required />

				<button type="submit"><?php esc_html_e( 'Calcular cotización demo', 'plugin-buscador-cotizador' ); ?></button>
			</form>
			<div class="pbc-result" data-pbc-result aria-live="polite"></div>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
