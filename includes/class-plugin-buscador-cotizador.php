<?php
/**
 * Clase principal del plugin.
 *
 * @package PluginBuscadorCotizador
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once PBC_PLUGIN_PATH . 'includes/admin/class-pbc-admin.php';

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
		add_action( 'wp_ajax_pbc_send_email_inquiry', array( $this, 'handle_send_email_inquiry' ) );
		add_action( 'wp_ajax_nopriv_pbc_send_email_inquiry', array( $this, 'handle_send_email_inquiry' ) );
		add_action( 'wp_ajax_pbc_destination_suggestions', array( $this, 'handle_destination_suggestions' ) );
		add_action( 'wp_ajax_nopriv_pbc_destination_suggestions', array( $this, 'handle_destination_suggestions' ) );
		add_shortcode( 'buscador_cotizador', array( $this, 'render_buscador_cotizador_shortcode' ) );
		add_shortcode( 'buscador_cotizador_demo', array( $this, 'render_demo_shortcode' ) );

		if ( is_admin() ) {
			new PBC_Admin( $this );
		}
	}

	/**
	 * Obtiene el nombre de la tabla de destinos.
	 *
	 * @return string
	 */
	private static function get_destinations_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'buscador_destinos';
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
			record_type varchar(20) NOT NULL DEFAULT 'city',
			external_id varchar(120) NOT NULL,
			source_type varchar(40) NOT NULL DEFAULT 'cities1000',
			geoname_id bigint(20) unsigned DEFAULT NULL,
			name varchar(200) NOT NULL,
			ascii_name varchar(200) NOT NULL,
			alternate_names longtext NULL,
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
			UNIQUE KEY unique_record (record_type,external_id),
			KEY geoname_id (geoname_id),
			KEY search_name (search_name),
			KEY country_code (country_code),
			KEY record_type (record_type),
			KEY population (population)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Importa destinos desde archivo subido por administrador.
	 *
	 * @param string $file_path Ruta temporal del archivo.
	 *
	 * @return array<string, int|string>
	 */
	public function import_destinations_from_uploaded_file( $file_path ) {
		global $wpdb;

		if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
			return array(
				'imported' => 0,
				'updated'  => 0,
				'skipped'  => 0,
				'errors'   => 1,
				'message'  => __( 'No se encontró el archivo a importar.', 'plugin-buscador-cotizador' ),
			);
		}

		self::create_destinations_table();

		$table_name = self::get_destinations_table_name();
		$detected_type = $this->detect_import_file_type( $file_path );

		if ( '' === $detected_type ) {
			return array(
				'imported'      => 0,
				'updated'       => 0,
				'skipped'       => 0,
				'errors'        => 1,
				'detected_type' => '',
				'message'       => __( 'No se pudo detectar el tipo de archivo de GeoNames.', 'plugin-buscador-cotizador' ),
			);
		}

		$handle = fopen( $file_path, 'r' );

		if ( false === $handle ) {
			return array(
				'imported' => 0,
				'updated'  => 0,
				'skipped'  => 0,
				'errors'   => 1,
				'message'  => __( 'No se pudo abrir el archivo subido.', 'plugin-buscador-cotizador' ),
			);
		}

		$imported = 0;
		$updated  = 0;
		$skipped  = 0;
		$errors   = 0;

		while ( ( $line = fgets( $handle ) ) !== false ) {
			$record = $this->parse_destination_record( $line, $detected_type );

			if ( null === $record ) {
				continue;
			}

			if ( false === $record ) {
				++$skipped;
				continue;
			}

			$result = $wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$table_name}
						(record_type, external_id, source_type, geoname_id, name, ascii_name, alternate_names, country_code, admin1_code, admin2_code, latitude, longitude, population, search_name)
					VALUES
						(%s, %s, %s, %d, %s, %s, %s, %s, %s, %s, %f, %f, %d, %s)
					ON DUPLICATE KEY UPDATE
						source_type = VALUES(source_type),
						geoname_id = VALUES(geoname_id),
						name = VALUES(name),
						ascii_name = VALUES(ascii_name),
						alternate_names = VALUES(alternate_names),
						country_code = VALUES(country_code),
						admin1_code = VALUES(admin1_code),
						admin2_code = VALUES(admin2_code),
						latitude = VALUES(latitude),
						longitude = VALUES(longitude),
						population = VALUES(population),
						search_name = VALUES(search_name)",
					$record['record_type'],
					$record['external_id'],
					$record['source_type'],
					$record['geoname_id'],
					$record['name'],
					$record['ascii_name'],
					$record['alternate_names'],
					$record['country_code'],
					$record['admin1_code'],
					$record['admin2_code'],
					$record['latitude'],
					$record['longitude'],
					$record['population'],
					$record['search_name']
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
			'imported'      => $imported,
			'updated'       => $updated,
			'skipped'       => $skipped,
			'errors'        => $errors,
			'detected_type' => $detected_type,
			'message'       => __( 'Importación finalizada.', 'plugin-buscador-cotizador' ),
		);
	}

	/**
	 * Detecta el tipo de archivo de GeoNames subido.
	 *
	 * @param string $file_path Ruta del archivo temporal.
	 *
	 * @return string
	 */
	private function detect_import_file_type( $file_path ) {
		$file_name = isset( $_FILES['pbc_destinations_file']['name'] ) ? strtolower( sanitize_text_field( (string) $_FILES['pbc_destinations_file']['name'] ) ) : '';

		if ( false !== strpos( $file_name, 'countryinfo' ) ) {
			return 'countryInfo';
		}

		if ( false !== strpos( $file_name, 'admin1codesascii' ) ) {
			return 'admin1CodesASCII';
		}

		if ( false !== strpos( $file_name, 'cities1000' ) ) {
			return 'cities1000';
		}

		$handle = fopen( $file_path, 'r' );

		if ( false === $handle ) {
			return '';
		}

		$detected_type = '';

		while ( ( $line = fgets( $handle ) ) !== false ) {
			$columns = $this->parse_destinations_line( $line );

			if ( empty( $columns ) ) {
				continue;
			}

			if ( isset( $columns[0] ) && preg_match( '/^[A-Z]{2}\./', (string) $columns[0] ) && isset( $columns[3] ) && is_numeric( $columns[3] ) ) {
				$detected_type = 'admin1CodesASCII';
				break;
			}

			if ( isset( $columns[6] ) && isset( $columns[0] ) && is_numeric( $columns[0] ) && in_array( (string) $columns[6], array( 'P', 'A' ), true ) ) {
				$detected_type = 'cities1000';
				break;
			}

			if ( isset( $columns[0] ) && isset( $columns[8] ) && preg_match( '/^[A-Z]{2}$/', (string) $columns[0] ) && is_numeric( $columns[8] ) ) {
				$detected_type = 'countryInfo';
				break;
			}
		}

		fclose( $handle );

		return $detected_type;
	}

	/**
	 * Convierte una línea en columnas del importador.
	 *
	 * @param string $line Línea del archivo.
	 *
	 * @return array<int, string>
	 */
	private function parse_destinations_line( $line ) {
		$line = trim( (string) $line );

		if ( '' === $line || 0 === strpos( $line, '#' ) ) {
			return array();
		}

		$columns = explode( "\t", $line );

		if ( count( $columns ) < 15 ) {
			$columns = str_getcsv( $line );
		}

		return is_array( $columns ) ? $columns : array();
	}

	/**
	 * Parsea una línea según el tipo detectado.
	 *
	 * @param string $line        Línea actual.
	 * @param string $source_type Tipo de archivo detectado.
	 *
	 * @return array<string, mixed>|false|null
	 */
	private function parse_destination_record( $line, $source_type ) {
		$columns = $this->parse_destinations_line( $line );

		if ( empty( $columns ) ) {
			return null;
		}

		if ( 'cities1000' === $source_type ) {
			if ( count( $columns ) < 15 || 'P' !== (string) $columns[6] ) {
				return false;
			}

			$geoname_id = absint( $columns[0] );
			$name       = sanitize_text_field( (string) $columns[1] );
			$ascii_name = sanitize_text_field( (string) $columns[2] );
			$country    = sanitize_text_field( (string) $columns[8] );
			$admin1     = sanitize_text_field( (string) $columns[10] );
			$admin2     = sanitize_text_field( (string) $columns[11] );

			if ( 0 === $geoname_id || '' === $name || '' === $country ) {
				return false;
			}

			return array(
				'record_type'     => 'city',
				'external_id'     => 'city:' . $geoname_id,
				'source_type'     => $source_type,
				'geoname_id'      => $geoname_id,
				'name'            => $name,
				'ascii_name'      => $ascii_name,
				'alternate_names' => sanitize_text_field( (string) $columns[3] ),
				'country_code'    => $country,
				'admin1_code'     => $admin1,
				'admin2_code'     => $admin2,
				'latitude'        => (float) $columns[4],
				'longitude'       => (float) $columns[5],
				'population'      => isset( $columns[14] ) ? max( 0, (int) $columns[14] ) : 0,
				'search_name'     => strtolower( remove_accents( $ascii_name ? $ascii_name : $name ) ),
			);
		}

		if ( 'countryInfo' === $source_type ) {
			if ( count( $columns ) < 9 ) {
				return false;
			}

			$country_code = sanitize_text_field( (string) $columns[0] );
			$name         = sanitize_text_field( (string) $columns[4] );
			$ascii_name   = sanitize_text_field( (string) $columns[5] );

			if ( '' === $country_code || '' === $name ) {
				return false;
			}

			$geoname_id = isset( $columns[16] ) ? absint( $columns[16] ) : 0;

			return array(
				'record_type'     => 'country',
				'external_id'     => 'country:' . $country_code,
				'source_type'     => $source_type,
				'geoname_id'      => $geoname_id,
				'name'            => $name,
				'ascii_name'      => $ascii_name,
				'alternate_names' => '',
				'country_code'    => $country_code,
				'admin1_code'     => '',
				'admin2_code'     => '',
				'latitude'        => 0,
				'longitude'       => 0,
				'population'      => 0,
				'search_name'     => strtolower( remove_accents( $ascii_name ? $ascii_name : $name ) ),
			);
		}

		if ( count( $columns ) < 4 ) {
			return false;
		}

		$admin1_code = sanitize_text_field( (string) $columns[0] );
		$name        = sanitize_text_field( (string) $columns[1] );
		$ascii_name  = sanitize_text_field( (string) $columns[2] );

		if ( '' === $admin1_code || '' === $name ) {
			return false;
		}

		$country_code = '';
		$code_parts   = explode( '.', $admin1_code );

		if ( ! empty( $code_parts[0] ) ) {
			$country_code = sanitize_text_field( (string) $code_parts[0] );
		}

		$geoname_id = absint( $columns[3] );

		return array(
			'record_type'     => 'admin1',
			'external_id'     => 'admin1:' . $admin1_code,
			'source_type'     => $source_type,
			'geoname_id'      => $geoname_id,
			'name'            => $name,
			'ascii_name'      => $ascii_name,
			'alternate_names' => '',
			'country_code'    => $country_code,
			'admin1_code'     => $admin1_code,
			'admin2_code'     => '',
			'latitude'        => 0,
			'longitude'       => 0,
			'population'      => 0,
			'search_name'     => strtolower( remove_accents( $ascii_name ? $ascii_name : $name ) ),
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
			"SELECT geoname_id, name, ascii_name, country_code, admin1_code, admin2_code, latitude, longitude, population, record_type
			FROM {$table_name}
			WHERE search_name LIKE %s
			ORDER BY
				CASE record_type
					WHEN 'city' THEN 1
					WHEN 'admin1' THEN 2
					WHEN 'country' THEN 3
					ELSE 4
				END ASC,
				population DESC,
				name ASC
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
		wp_enqueue_script( 'pbc-script' );

		wp_localize_script(
			'pbc-script',
			'pbcFrontend',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'pbc_send_email_inquiry' ),
				'destinationNonce' => wp_create_nonce( 'pbc_destination_suggestions' ),
			)
		);

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
		$destino_input   = trim( (string) $form_data['destino'] );
		$resolved_result = $this->resolve_destination_from_imported( $destino_input );
		$destino_query   = ! empty( $resolved_result['resolved_destination'] ) ? (string) $resolved_result['resolved_destination'] : $destino_input;
		$fecha           = (string) $form_data['fecha'];
		$noches          = absint( (string) $form_data['noches'] );

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
			$posts = $this->run_packages_query( $destino_query, $fecha, $noches, $layer_config );

			if ( ! empty( $posts ) ) {
				$message = (string) $layer_config['message'];

				if ( ! empty( $resolved_result['note'] ) ) {
					$message .= ' ' . (string) $resolved_result['note'];
				}

				return array(
					'layer'         => $layer_key,
					'message'       => $message,
					'posts'         => $posts,
					'suggestions'   => array(),
				);
			}
		}

		$empty_message = __( 'No encontramos paquetes disponibles con los criterios ingresados.', 'plugin-buscador-cotizador' );

		if ( ! empty( $resolved_result['note'] ) ) {
			$empty_message .= ' ' . (string) $resolved_result['note'];
		}

		return array(
			'layer'       => 'sin_resultados',
			'message'     => $empty_message,
			'posts'       => array(),
			'suggestions' => $this->build_suggestions( $destino_input, $fecha, $noches, absint( (string) $form_data['pasajeros'] ) ),
		);
	}

	/**
	 * Resuelve el destino ingresado contra la base importada y aplica corrección sólo con confianza alta.
	 *
	 * @param string $destino_input Destino escrito por el usuario.
	 *
	 * @return array<string, string>
	 */
	private function resolve_destination_from_imported( $destino_input ) {
		$destino_input = trim( (string) $destino_input );

		if ( '' === $destino_input ) {
			return array(
				'resolved_destination' => '',
				'note'                 => '',
			);
		}

		$normalized_input = $this->normalize_destination_text( $destino_input );

		if ( '' === $normalized_input ) {
			return array(
				'resolved_destination' => $destino_input,
				'note'                 => '',
			);
		}

		$candidates = $this->get_imported_destinations( $destino_input, 15 );

		if ( empty( $candidates ) ) {
			return array(
				'resolved_destination' => $destino_input,
				'note'                 => '',
			);
		}

		$best_candidate = null;
		$best_score     = -1;

		foreach ( $candidates as $candidate ) {
			$candidate_name = isset( $candidate['name'] ) ? (string) $candidate['name'] : '';

			if ( '' === $candidate_name ) {
				continue;
			}

			$normalized_candidate = $this->normalize_destination_text( $candidate_name );

			if ( '' === $normalized_candidate ) {
				continue;
			}

			if ( $normalized_candidate === $normalized_input ) {
				return array(
					'resolved_destination' => $candidate_name,
					'note'                 => '',
				);
			}

			$distance = levenshtein( $normalized_input, $normalized_candidate );
			$max_len  = max( strlen( $normalized_input ), strlen( $normalized_candidate ) );
			$score    = $max_len > 0 ? 1 - ( $distance / $max_len ) : 0;

			if ( $score > $best_score ) {
				$best_score     = $score;
				$best_candidate = $candidate;
			}
		}

		if ( empty( $best_candidate ) || ! isset( $best_candidate['name'] ) ) {
			return array(
				'resolved_destination' => $destino_input,
				'note'                 => '',
			);
		}

		$input_length = strlen( $normalized_input );
		$threshold    = $input_length <= 5 ? 0.86 : 0.78;

		if ( $best_score < $threshold ) {
			return array(
				'resolved_destination' => $destino_input,
				'note'                 => '',
			);
		}

		$resolved_destination = (string) $best_candidate['name'];

		return array(
			'resolved_destination' => $resolved_destination,
			'note'                 => sprintf(
				/* translators: 1: entered destination, 2: corrected destination. */
				__( 'Interpretamos "%1$s" como "%2$s" para mejorar la búsqueda.', 'plugin-buscador-cotizador' ),
				$destino_input,
				$resolved_destination
			),
		);
	}

	/**
	 * Normaliza un texto para comparar destinos.
	 *
	 * @param string $value Valor de texto a normalizar.
	 *
	 * @return string
	 */
	private function normalize_destination_text( $value ) {
		$value = strtolower( remove_accents( sanitize_text_field( (string) $value ) ) );
		$value = preg_replace( '/\s+/', ' ', $value );

		return trim( (string) $value );
	}

	/**
	 * Endpoint AJAX para autocompletar destinos importados.
	 *
	 * @return void
	 */
	public function handle_destination_suggestions() {
		$nonce = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'pbc_destination_suggestions' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'No se pudo validar la búsqueda de destinos.', 'plugin-buscador-cotizador' ),
				),
				403
			);
		}

		$term    = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';
		$matches = $this->get_imported_destinations( $term, 8 );

		if ( empty( $matches ) ) {
			wp_send_json_success(
				array(
					'items' => array(),
				)
			);
		}

		$items = array();

		foreach ( $matches as $match ) {
			$name         = isset( $match['name'] ) ? (string) $match['name'] : '';
			$country_code = isset( $match['country_code'] ) ? (string) $match['country_code'] : '';
			$record_type  = isset( $match['record_type'] ) ? (string) $match['record_type'] : 'city';

			if ( '' === $name ) {
				continue;
			}

			$type_labels = array(
				'city'    => __( 'Ciudad', 'plugin-buscador-cotizador' ),
				'admin1'  => __( 'Región', 'plugin-buscador-cotizador' ),
				'country' => __( 'País', 'plugin-buscador-cotizador' ),
			);

			$type_label = isset( $type_labels[ $record_type ] ) ? $type_labels[ $record_type ] : __( 'Destino', 'plugin-buscador-cotizador' );

			$items[] = array(
				'value' => $name,
				'label' => '' !== $country_code ? sprintf( '%s (%s · %s)', $name, $country_code, $type_label ) : sprintf( '%s (%s)', $name, $type_label ),
			);
		}

		wp_send_json_success(
			array(
				'items' => $items,
			)
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
	 * @param string $destino   Destino ingresado por el usuario.
	 * @param string $fecha     Fecha ingresada por el usuario.
	 * @param int    $noches    Cantidad de noches solicitada.
	 * @param int    $pasajeros Cantidad de pasajeros solicitada.
	 *
	 * @return array<int, string>
	 */
	private function build_suggestions( $destino, $fecha, $noches, $pasajeros ) {
		$base_destination = ! empty( $destino ) ? $destino : __( 'Tu destino', 'plugin-buscador-cotizador' );
		$month_year       = $this->format_month_year_label( $fecha );
		$noches           = absint( $noches );
		$pasajeros        = absint( $pasajeros );

		$suggestions = array();

		if ( $noches > 0 && '' !== $month_year ) {
			$suggestions[] = sprintf( __( '%1$s · %2$d noches · %3$s', 'plugin-buscador-cotizador' ), $base_destination, $noches, $month_year );
		}

		if ( $pasajeros > 0 ) {
			$passenger_label = 1 === $pasajeros ? __( 'pasajero', 'plugin-buscador-cotizador' ) : __( 'pasajeros', 'plugin-buscador-cotizador' );
			$time_label      = '' !== $month_year ? $month_year : __( 'salida flexible', 'plugin-buscador-cotizador' );
			$suggestions[]   = sprintf( __( '%1$s · %2$d %3$s · %4$s', 'plugin-buscador-cotizador' ), $base_destination, $pasajeros, $passenger_label, $time_label );
		}

		if ( $noches > 0 ) {
			$min_noches    = max( 1, $noches - 1 );
			$max_noches    = $noches + 1;
			$suggestions[] = sprintf( __( '%1$s · %2$d a %3$d noches', 'plugin-buscador-cotizador' ), $base_destination, $min_noches, $max_noches );
		}

		if ( '' !== $month_year ) {
			$suggestions[] = sprintf( __( '%1$s · %2$s · salida flexible', 'plugin-buscador-cotizador' ), $base_destination, $month_year );
		}

		$suggestions[] = sprintf( __( '%s · consulta personalizada', 'plugin-buscador-cotizador' ), $base_destination );

		$suggestions = array_values( array_unique( $suggestions ) );

		if ( count( $suggestions ) > 4 ) {
			$suggestions = array_slice( $suggestions, 0, 4 );
		}

		if ( count( $suggestions ) < 3 ) {
			$suggestions[] = sprintf( __( '%s · salida flexible', 'plugin-buscador-cotizador' ), $base_destination );
			$suggestions[] = sprintf( __( '%s · consulta personalizada', 'plugin-buscador-cotizador' ), $base_destination );
			$suggestions   = array_values( array_unique( $suggestions ) );
			$suggestions   = array_slice( $suggestions, 0, 4 );
		}

		return $suggestions;
	}

	/**
	 * Formatea fecha en etiqueta mes + año para sugerencias.
	 *
	 * @param string $fecha Fecha ingresada por el usuario.
	 *
	 * @return string
	 */
	private function format_month_year_label( $fecha ) {
		$timestamp = strtotime( (string) $fecha );

		if ( false === $timestamp ) {
			return '';
		}

		return (string) wp_date( 'F Y', $timestamp );
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

	/**
	 * Procesa consultas por email enviadas desde el frontend.
	 *
	 * @return void
	 */
	public function handle_send_email_inquiry() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'pbc_send_email_inquiry' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'No se pudo validar la solicitud. Recargá la página e intentá nuevamente.', 'plugin-buscador-cotizador' ),
				),
				403
			);
		}

		$client_name   = isset( $_POST['client_name'] ) ? sanitize_text_field( wp_unslash( $_POST['client_name'] ) ) : '';
		$client_email  = isset( $_POST['client_email'] ) ? sanitize_email( wp_unslash( $_POST['client_email'] ) ) : '';
		$client_phone  = isset( $_POST['client_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['client_phone'] ) ) : '';
		$observations  = isset( $_POST['observations'] ) ? sanitize_textarea_field( wp_unslash( $_POST['observations'] ) ) : '';
		$item_name     = isset( $_POST['item_name'] ) ? sanitize_text_field( wp_unslash( $_POST['item_name'] ) ) : '';
		$destination   = isset( $_POST['destination'] ) ? sanitize_text_field( wp_unslash( $_POST['destination'] ) ) : '';
		$date          = isset( $_POST['travel_date'] ) ? sanitize_text_field( wp_unslash( $_POST['travel_date'] ) ) : '';
		$nights        = isset( $_POST['nights'] ) ? absint( $_POST['nights'] ) : 0;
		$passengers    = isset( $_POST['passengers'] ) ? absint( $_POST['passengers'] ) : 0;

		if ( '' === $client_name || '' === $client_email || ! is_email( $client_email ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Completá nombre y un email válido para enviar la consulta.', 'plugin-buscador-cotizador' ),
				),
				400
			);
		}

		$recipient = sanitize_email( (string) get_option( 'pbc_contact_email', '' ) );

		if ( '' === $recipient || ! is_email( $recipient ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'No hay un email de destino configurado en el administrador del plugin.', 'plugin-buscador-cotizador' ),
				),
				400
			);
		}

		$subject = sprintf(
			/* translators: %s: package or suggestion name */
			__( 'Nueva consulta de viaje: %s', 'plugin-buscador-cotizador' ),
			! empty( $item_name ) ? $item_name : __( 'Consulta general', 'plugin-buscador-cotizador' )
		);

		$lines = array(
			__( 'Se recibió una nueva consulta desde el Buscador Cotizador:', 'plugin-buscador-cotizador' ),
			'',
			sprintf( __( 'Cliente: %s', 'plugin-buscador-cotizador' ), $client_name ),
			sprintf( __( 'Email cliente: %s', 'plugin-buscador-cotizador' ), $client_email ),
			sprintf( __( 'Teléfono: %s', 'plugin-buscador-cotizador' ), ! empty( $client_phone ) ? $client_phone : __( 'No informado', 'plugin-buscador-cotizador' ) ),
			'',
			__( 'Detalle de la consulta:', 'plugin-buscador-cotizador' ),
			sprintf( __( 'Paquete/Sugerencia: %s', 'plugin-buscador-cotizador' ), ! empty( $item_name ) ? $item_name : __( 'No especificado', 'plugin-buscador-cotizador' ) ),
			sprintf( __( 'Destino: %s', 'plugin-buscador-cotizador' ), ! empty( $destination ) ? $destination : __( 'No especificado', 'plugin-buscador-cotizador' ) ),
			sprintf( __( 'Fecha: %s', 'plugin-buscador-cotizador' ), ! empty( $date ) ? $date : __( 'No especificada', 'plugin-buscador-cotizador' ) ),
			sprintf( __( 'Noches: %s', 'plugin-buscador-cotizador' ), $nights > 0 ? (string) $nights : __( 'No especificado', 'plugin-buscador-cotizador' ) ),
			sprintf( __( 'Pasajeros: %s', 'plugin-buscador-cotizador' ), $passengers > 0 ? (string) $passengers : __( 'No especificado', 'plugin-buscador-cotizador' ) ),
			'',
			sprintf( __( 'Observaciones: %s', 'plugin-buscador-cotizador' ), ! empty( $observations ) ? $observations : __( 'Sin observaciones', 'plugin-buscador-cotizador' ) ),
		);

		$body = implode( "\n", $lines );

		$headers = array(
			'Content-Type: text/plain; charset=UTF-8',
			sprintf( 'Reply-To: %s <%s>', $client_name, $client_email ),
		);

		$sent = wp_mail( $recipient, $subject, $body, $headers );

		if ( ! $sent ) {
			wp_send_json_error(
				array(
					'message' => __( 'No se pudo enviar la consulta. Intentá nuevamente en unos minutos.', 'plugin-buscador-cotizador' ),
				),
				500
			);
		}

		wp_send_json_success(
			array(
				'message' => __( 'Consulta enviada correctamente. Te responderemos a la brevedad.', 'plugin-buscador-cotizador' ),
			)
		);
	}

	/**
	 * Devuelve una URL de WhatsApp usando número configurado y contexto de consulta.
	 *
	 * @param array<string, mixed> $inquiry_context Contexto de consulta.
	 *
	 * @return string
	 */
	public function get_whatsapp_url( $inquiry_context ) {
		$raw_phone = (string) get_option( 'pbc_whatsapp_number', '' );
		$phone     = preg_replace( '/\D+/', '', $raw_phone );

		$message = $this->build_whatsapp_message( $inquiry_context );
		$query   = rawurlencode( $message );

		if ( ! empty( $phone ) ) {
			return 'https://wa.me/' . $phone . '?text=' . $query;
		}

		return 'https://wa.me/?text=' . $query;
	}

	/**
	 * Construye mensaje prearmado para consultas por WhatsApp.
	 *
	 * @param array<string, mixed> $inquiry_context Contexto de consulta.
	 *
	 * @return string
	 */
	private function build_whatsapp_message( $inquiry_context ) {
		$item_name  = isset( $inquiry_context['item_name'] ) ? sanitize_text_field( (string) $inquiry_context['item_name'] ) : '';
		$destination = isset( $inquiry_context['destination'] ) ? sanitize_text_field( (string) $inquiry_context['destination'] ) : '';
		$date       = isset( $inquiry_context['travel_date'] ) ? sanitize_text_field( (string) $inquiry_context['travel_date'] ) : '';
		$nights     = isset( $inquiry_context['nights'] ) ? absint( (string) $inquiry_context['nights'] ) : 0;
		$passengers = isset( $inquiry_context['passengers'] ) ? absint( (string) $inquiry_context['passengers'] ) : 0;

		$lines = array(
			__( 'Hola, quiero consultar la siguiente opción:', 'plugin-buscador-cotizador' ),
			sprintf( __( 'Paquete/Sugerencia: %s', 'plugin-buscador-cotizador' ), ! empty( $item_name ) ? $item_name : __( 'No especificado', 'plugin-buscador-cotizador' ) ),
			sprintf( __( 'Destino: %s', 'plugin-buscador-cotizador' ), ! empty( $destination ) ? $destination : __( 'No especificado', 'plugin-buscador-cotizador' ) ),
			sprintf( __( 'Fecha: %s', 'plugin-buscador-cotizador' ), ! empty( $date ) ? $date : __( 'No especificada', 'plugin-buscador-cotizador' ) ),
			sprintf( __( 'Noches: %s', 'plugin-buscador-cotizador' ), $nights > 0 ? (string) $nights : __( 'No especificado', 'plugin-buscador-cotizador' ) ),
			sprintf( __( 'Pasajeros: %s', 'plugin-buscador-cotizador' ), $passengers > 0 ? (string) $passengers : __( 'No especificado', 'plugin-buscador-cotizador' ) ),
		);

		return implode( "\n", $lines );
	}
}
