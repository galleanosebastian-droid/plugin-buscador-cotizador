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
	 * Obtiene el nombre de la tabla de destinos.
	 *
	 * @return string
	 */
	private static function get_destinations_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'pbc_destinations';
	}

	/**
	 * Crea/actualiza la tabla de destinos importados desde GeoNames.
	 *
	 * @return void
	 */
	public static function create_destinations_table() {
		global $wpdb;

		$table_name      = self::get_destinations_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			geoname_id bigint(20) unsigned NOT NULL,
			name varchar(200) NOT NULL,
			ascii_name varchar(200) NOT NULL,
			country_code varchar(2) NOT NULL,
			admin1_code varchar(20) NOT NULL,
			admin2_code varchar(80) NOT NULL,
			latitude decimal(10,7) NOT NULL,
			longitude decimal(10,7) NOT NULL,
			population bigint(20) unsigned NOT NULL DEFAULT 0,
			search_name varchar(200) NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY geoname_id (geoname_id),
			KEY search_name (search_name),
			KEY country_code (country_code),
			KEY population (population)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Importa destinos desde /data/cities1000.txt de forma manual.
	 *
	 * @return array<string, int|string>
	 */
	public function import_destinations_from_geonames_file() {
		global $wpdb;

		$file_path = PBC_PLUGIN_PATH . 'data/cities1000.txt';

		if ( ! file_exists( $file_path ) ) {
			return array(
				'imported' => 0,
				'updated'  => 0,
				'skipped'  => 0,
				'errors'   => 1,
				'message'  => __( 'No se encontró el archivo /data/cities1000.txt.', 'plugin-buscador-cotizador' ),
			);
		}

		self::create_destinations_table();

		$table_name = self::get_destinations_table_name();
		$handle     = fopen( $file_path, 'r' );

		if ( false === $handle ) {
			return array(
				'imported' => 0,
				'updated'  => 0,
				'skipped'  => 0,
				'errors'   => 1,
				'message'  => __( 'No se pudo abrir el archivo cities1000.txt.', 'plugin-buscador-cotizador' ),
			);
		}

		$imported = 0;
		$updated  = 0;
		$skipped  = 0;
		$errors   = 0;

		while ( ( $line = fgets( $handle ) ) !== false ) {
			$line = trim( $line );

			if ( '' === $line ) {
				continue;
			}

			$columns = explode( "\t", $line );

			if ( count( $columns ) < 15 ) {
				++$skipped;
				continue;
			}

			if ( 'P' !== (string) $columns[6] ) {
				continue;
			}

			$geoname_id = absint( $columns[0] );
			$name       = sanitize_text_field( (string) $columns[1] );
			$ascii_name = sanitize_text_field( (string) $columns[2] );
			$latitude   = (float) $columns[4];
			$longitude  = (float) $columns[5];
			$country    = sanitize_text_field( (string) $columns[8] );
			$admin1     = sanitize_text_field( (string) $columns[10] );
			$admin2     = sanitize_text_field( (string) $columns[11] );
			$population = isset( $columns[14] ) ? max( 0, (int) $columns[14] ) : 0;

			if ( 0 === $geoname_id || '' === $name || '' === $country ) {
				++$skipped;
				continue;
			}

			$search_name = strtolower( remove_accents( $ascii_name ? $ascii_name : $name ) );

			$result = $wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$table_name}
						(geoname_id, name, ascii_name, country_code, admin1_code, admin2_code, latitude, longitude, population, search_name)
					VALUES
						(%d, %s, %s, %s, %s, %s, %f, %f, %d, %s)
					ON DUPLICATE KEY UPDATE
						name = VALUES(name),
						ascii_name = VALUES(ascii_name),
						country_code = VALUES(country_code),
						admin1_code = VALUES(admin1_code),
						admin2_code = VALUES(admin2_code),
						latitude = VALUES(latitude),
						longitude = VALUES(longitude),
						population = VALUES(population),
						search_name = VALUES(search_name)",
					$geoname_id,
					$name,
					$ascii_name,
					$country,
					$admin1,
					$admin2,
					$latitude,
					$longitude,
					$population,
					$search_name
				)
			);

			if ( false === $result ) {
				++$errors;
				continue;
			}

			if ( 1 === (int) $result ) {
				++$imported;
			} else {
				++$updated;
			}
		}

		fclose( $handle );

		return array(
			'imported' => $imported,
			'updated'  => $updated,
			'skipped'  => $skipped,
			'errors'   => $errors,
			'message'  => __( 'Importación finalizada.', 'plugin-buscador-cotizador' ),
		);
	}

	/**
	 * Consulta destinos desde la tabla importada.
	 *
	 * @param string $search_term Término de búsqueda.
	 * @param int    $limit       Cantidad máxima de resultados.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_imported_destinations( $search_term, $limit = 20 ) {
		global $wpdb;

		$search_term = strtolower( remove_accents( sanitize_text_field( $search_term ) ) );
		$limit       = max( 1, min( 50, absint( $limit ) ) );

		if ( '' === $search_term ) {
			return array();
		}

		$table_name = self::get_destinations_table_name();
		$like_term  = '%' . $wpdb->esc_like( $search_term ) . '%';

		$query = $wpdb->prepare(
			"SELECT geoname_id, name, ascii_name, country_code, admin1_code, admin2_code, latitude, longitude, population
			FROM {$table_name}
			WHERE search_name LIKE %s
			ORDER BY population DESC, name ASC
			LIMIT %d",
			$like_term,
			$limit
		);

		$results = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $results ) ? $results : array();
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
