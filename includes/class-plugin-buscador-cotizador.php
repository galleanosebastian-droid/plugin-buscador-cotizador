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
		add_shortcode( 'buscador_cotizador', array( $this, 'render_buscador_cotizador_shortcode' ) );
		add_shortcode( 'buscador_cotizador_demo', array( $this, 'render_demo_shortcode' ) );
	}

	/**
	 * Shortcode principal [buscador_cotizador].
	 *
	 * @return string
	 */
	public function render_buscador_cotizador_shortcode() {
		wp_enqueue_style( 'pbc-styles' );

		$form_data = array(
			'destino'   => '',
			'fecha'     => '',
			'noches'    => '',
			'pasajeros' => '',
		);

		$form_submitted = false;
		$search_result  = null;

		if ( isset( $_POST['pbc_form_submitted'] ) ) {
			$form_data['destino']   = isset( $_POST['pbc_destino'] ) ? sanitize_text_field( wp_unslash( $_POST['pbc_destino'] ) ) : '';
			$form_data['fecha']     = isset( $_POST['pbc_fecha'] ) ? sanitize_text_field( wp_unslash( $_POST['pbc_fecha'] ) ) : '';
			$form_data['noches']    = isset( $_POST['pbc_noches'] ) ? absint( $_POST['pbc_noches'] ) : '';
			$form_data['pasajeros'] = isset( $_POST['pbc_pasajeros'] ) ? absint( $_POST['pbc_pasajeros'] ) : '';
			$form_submitted         = true;
			$search_result          = $this->search_wtp_packages( $form_data );
		}

		ob_start();
		require PBC_PLUGIN_PATH . 'includes/views/shortcode-buscador-cotizador.php';

		return (string) ob_get_clean();
	}

	/**
	 * Busca paquetes del plugin wordpress-tourism-plugin con estrategia de fallback por capas.
	 *
	 * @param array<string, int|string> $form_data Datos del formulario.
	 *
	 * @return array<string, mixed>
	 */
	private function search_wtp_packages( $form_data ) {
		$destino = trim( (string) $form_data['destino'] );
		$fecha   = (string) $form_data['fecha'];
		$noches  = absint( (string) $form_data['noches'] );

		$layers = array(
			'estricta'       => array(
				'include_date'       => true,
				'include_duration'   => true,
				'flex_destination'   => false,
				'message'            => __( 'Resultados exactos para tu búsqueda.', 'plugin-buscador-cotizador' ),
			),
			'relajar_fecha'  => array(
				'include_date'       => false,
				'include_duration'   => true,
				'flex_destination'   => false,
				'message'            => __( 'No hubo coincidencias exactas de fecha. Mostramos opciones con fechas alternativas.', 'plugin-buscador-cotizador' ),
			),
			'relajar_noches' => array(
				'include_date'       => false,
				'include_duration'   => false,
				'flex_destination'   => false,
				'message'            => __( 'No hubo coincidencias exactas de duración. Mostramos opciones del destino seleccionado.', 'plugin-buscador-cotizador' ),
			),
			'destino_flex'   => array(
				'include_date'       => false,
				'include_duration'   => false,
				'flex_destination'   => true,
				'message'            => __( 'Mostramos opciones por búsqueda flexible de destino.', 'plugin-buscador-cotizador' ),
			),
		);

		foreach ( $layers as $layer_key => $layer_config ) {
			$posts = $this->run_packages_query( $destino, $fecha, $noches, $layer_config );

			if ( ! empty( $posts ) ) {
				return array(
					'layer'         => $layer_key,
					'message'       => $layer_config['message'],
					'posts'         => $posts,
					'suggestions'   => array(),
				);
			}
		}

		return array(
			'layer'       => 'sin_resultados',
			'message'     => __( 'No encontramos paquetes disponibles con los criterios ingresados.', 'plugin-buscador-cotizador' ),
			'posts'       => array(),
			'suggestions' => $this->build_suggestions( $destino ),
		);
	}

	/**
	 * Ejecuta WP_Query para paquetes turísticos con filtros por metadatos.
	 *
	 * @param string              $destino      Destino ingresado.
	 * @param string              $fecha        Fecha ingresada.
	 * @param int                 $noches       Noches ingresadas.
	 * @param array<string,mixed> $layer_config Configuración de capa de fallback.
	 *
	 * @return array<int, WP_Post>
	 */
	private function run_packages_query( $destino, $fecha, $noches, $layer_config ) {
		$meta_query = array(
			'relation' => 'AND',
		);

		if ( ! empty( $destino ) ) {
			$meta_query[] = array(
				'key'     => 'destination',
				'value'   => $this->get_destination_value_for_query( $destino, ! empty( $layer_config['flex_destination'] ) ),
				'compare' => 'LIKE',
			);
		}

		if ( ! empty( $layer_config['include_date'] ) && ! empty( $fecha ) ) {
			$meta_query[] = array(
				'key'     => 'departure_date',
				'value'   => $fecha,
				'compare' => '>=',
				'type'    => 'DATE',
			);
		}

		if ( ! empty( $layer_config['include_duration'] ) && $noches > 0 ) {
			$meta_query[] = array(
				'key'     => 'number_of_days',
				'value'   => $noches,
				'compare' => '>=',
				'type'    => 'NUMERIC',
			);
		}

		$query_args = array(
			'post_type'      => 'wtp_package',
			'post_status'    => 'publish',
			'posts_per_page' => 6,
			'meta_query'     => $meta_query,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$query = new WP_Query( $query_args );

		return $query->posts;
	}

	/**
	 * Obtiene valor de destino para búsqueda base/flexible.
	 *
	 * @param string $destino Destino ingresado.
	 * @param bool   $flex    Si se aplica fallback flexible.
	 *
	 * @return string
	 */
	private function get_destination_value_for_query( $destino, $flex ) {
		if ( ! $flex ) {
			return $destino;
		}

		$parts = preg_split( '/\s+/', trim( $destino ) );

		if ( is_array( $parts ) && ! empty( $parts[0] ) ) {
			return (string) $parts[0];
		}

		return $destino;
	}

	/**
	 * Genera sugerencias automáticas cuando no hay resultados.
	 *
	 * @param string $destino Destino ingresado por el usuario.
	 *
	 * @return array<int, string>
	 */
	private function build_suggestions( $destino ) {
		$base_destination = ! empty( $destino ) ? $destino : __( 'Tu destino', 'plugin-buscador-cotizador' );

		return array(
			sprintf( __( '%s · 3 noches · abril', 'plugin-buscador-cotizador' ), $base_destination ),
			sprintf( __( '%s · otras fechas', 'plugin-buscador-cotizador' ), $base_destination ),
			sprintf( __( '%s · consulta personalizada', 'plugin-buscador-cotizador' ), $base_destination ),
		);
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
