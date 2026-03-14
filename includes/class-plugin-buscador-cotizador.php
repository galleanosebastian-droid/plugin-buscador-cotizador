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

		$defaults = array(
			'destino'    => '',
			'fecha'      => '',
			'noches'     => '',
			'pasajeros'  => '',
			'submitted'  => false,
		);

		$form_data = $defaults;

		if ( isset( $_POST['pbc_form_submitted'] ) ) {
			$form_data['destino']   = isset( $_POST['pbc_destino'] ) ? sanitize_text_field( wp_unslash( $_POST['pbc_destino'] ) ) : '';
			$form_data['fecha']     = isset( $_POST['pbc_fecha'] ) ? sanitize_text_field( wp_unslash( $_POST['pbc_fecha'] ) ) : '';
			$form_data['noches']    = isset( $_POST['pbc_noches'] ) ? absint( $_POST['pbc_noches'] ) : '';
			$form_data['pasajeros'] = isset( $_POST['pbc_pasajeros'] ) ? absint( $_POST['pbc_pasajeros'] ) : '';
			$form_data['submitted'] = true;
		}

		$summary_lines = array();

		if ( $form_data['submitted'] ) {
			$summary_lines = array(
				sprintf( __( 'Destino: %s', 'plugin-buscador-cotizador' ), $form_data['destino'] ),
				sprintf( __( 'Fecha: %s', 'plugin-buscador-cotizador' ), $form_data['fecha'] ),
				sprintf( __( 'Cantidad de noches: %d', 'plugin-buscador-cotizador' ), (int) $form_data['noches'] ),
				sprintf( __( 'Cantidad de pasajeros: %d', 'plugin-buscador-cotizador' ), (int) $form_data['pasajeros'] ),
			);
		}

		ob_start();
		?>
		<div class="pbc-card">
			<h3 class="pbc-title"><?php esc_html_e( 'Buscador Cotizador', 'plugin-buscador-cotizador' ); ?></h3>
			<form class="pbc-form" method="post">
				<label for="pbc-destino"><?php esc_html_e( 'Destino', 'plugin-buscador-cotizador' ); ?></label>
				<input id="pbc-destino" name="pbc_destino" type="text" value="<?php echo esc_attr( $form_data['destino'] ); ?>" required />

				<label for="pbc-fecha"><?php esc_html_e( 'Fecha', 'plugin-buscador-cotizador' ); ?></label>
				<input id="pbc-fecha" name="pbc_fecha" type="date" value="<?php echo esc_attr( $form_data['fecha'] ); ?>" required />

				<label for="pbc-noches"><?php esc_html_e( 'Cantidad de noches', 'plugin-buscador-cotizador' ); ?></label>
				<input id="pbc-noches" name="pbc_noches" type="number" min="1" value="<?php echo esc_attr( (string) $form_data['noches'] ); ?>" required />

				<label for="pbc-pasajeros"><?php esc_html_e( 'Cantidad de pasajeros', 'plugin-buscador-cotizador' ); ?></label>
				<input id="pbc-pasajeros" name="pbc_pasajeros" type="number" min="1" value="<?php echo esc_attr( (string) $form_data['pasajeros'] ); ?>" required />

				<input name="pbc_form_submitted" type="hidden" value="1" />
				<button type="submit"><?php esc_html_e( 'Buscar / Cotizar', 'plugin-buscador-cotizador' ); ?></button>
			</form>

			<?php if ( $form_data['submitted'] ) : ?>
				<div class="pbc-result" aria-live="polite">
					<p><strong><?php esc_html_e( 'Resumen de búsqueda:', 'plugin-buscador-cotizador' ); ?></strong></p>
					<ul>
						<?php foreach ( $summary_lines as $summary_line ) : ?>
							<li><?php echo esc_html( $summary_line ); ?></li>
						<?php endforeach; ?>
					</ul>
					<div class="pbc-contact-actions">
						<a class="pbc-action-button" href="<?php echo esc_url( $this->build_whatsapp_url( $summary_lines ) ); ?>" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'Enviar por WhatsApp', 'plugin-buscador-cotizador' ); ?>
						</a>
						<a class="pbc-action-button" href="<?php echo esc_url( $this->build_mailto_url( $summary_lines ) ); ?>">
							<?php esc_html_e( 'Enviar por Email', 'plugin-buscador-cotizador' ); ?>
						</a>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Construye la URL de WhatsApp con mensaje prearmado.
	 *
	 * @param array<int, string> $summary_lines Líneas del resumen.
	 *
	 * @return string
	 */
	private function build_whatsapp_url( $summary_lines ) {
		$message = __( "Hola, quiero consultar esta búsqueda:\n", 'plugin-buscador-cotizador' ) . implode( "\n", $summary_lines );

		return 'https://wa.me/?text=' . rawurlencode( $message );
	}

	/**
	 * Construye la URL mailto con asunto y cuerpo prearmados.
	 *
	 * @param array<int, string> $summary_lines Líneas del resumen.
	 *
	 * @return string
	 */
	private function build_mailto_url( $summary_lines ) {
		$subject = __( 'Consulta de cotización turística', 'plugin-buscador-cotizador' );
		$body    = __( "Hola,\n\nQuiero consultar la siguiente búsqueda:\n", 'plugin-buscador-cotizador' ) . implode( "\n", $summary_lines );

		return 'mailto:?subject=' . rawurlencode( $subject ) . '&body=' . rawurlencode( $body );
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
