<?php
/**
 * Administración del plugin.
 *
 * @package PluginBuscadorCotizador
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PBC_Admin {
	/**
	 * Instancia del núcleo del plugin.
	 *
	 * @var Plugin_Buscador_Cotizador
	 */
	private $plugin;

	/**
	 * Constructor.
	 *
	 * @param Plugin_Buscador_Cotizador $plugin Instancia principal.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;

		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_pbc_import_destinations', array( $this, 'handle_destinations_import' ) );
	}

	/**
	 * Registra menú y submenús del plugin.
	 *
	 * @return void
	 */
	public function register_admin_menu() {
		add_menu_page(
			__( 'Buscador Cotizador', 'plugin-buscador-cotizador' ),
			__( 'Buscador Cotizador', 'plugin-buscador-cotizador' ),
			'manage_options',
			'pbc-settings',
			array( $this, 'render_settings_page' ),
			'dashicons-location-alt',
			58
		);

		add_submenu_page(
			'pbc-settings',
			__( 'Ajustes', 'plugin-buscador-cotizador' ),
			__( 'Ajustes', 'plugin-buscador-cotizador' ),
			'manage_options',
			'pbc-settings',
			array( $this, 'render_settings_page' )
		);

		add_submenu_page(
			'pbc-settings',
			__( 'Importar destinos', 'plugin-buscador-cotizador' ),
			__( 'Importar destinos', 'plugin-buscador-cotizador' ),
			'manage_options',
			'pbc-import-destinations',
			array( $this, 'render_import_page' )
		);
	}

	/**
	 * Registra ajustes generales del plugin.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'pbc_settings_group',
			'pbc_whatsapp_number',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		register_setting(
			'pbc_settings_group',
			'pbc_contact_email',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_email',
				'default'           => '',
			)
		);
	}

	/**
	 * Muestra la pantalla de ajustes.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tenés permisos para acceder a esta pantalla.', 'plugin-buscador-cotizador' ) );
		}

		require PBC_PLUGIN_PATH . 'includes/views/admin/settings-page.php';
	}

	/**
	 * Muestra la pantalla de importación.
	 *
	 * @return void
	 */
	public function render_import_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tenés permisos para acceder a esta pantalla.', 'plugin-buscador-cotizador' ) );
		}

		require PBC_PLUGIN_PATH . 'includes/views/admin/import-page.php';
	}

	/**
	 * Procesa la importación de destinos desde archivo subido.
	 *
	 * @return void
	 */
	public function handle_destinations_import() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tenés permisos para importar destinos.', 'plugin-buscador-cotizador' ) );
		}

		check_admin_referer( 'pbc_import_destinations', 'pbc_import_nonce' );

		if ( empty( $_FILES['pbc_destinations_file'] ) || ! is_array( $_FILES['pbc_destinations_file'] ) ) {
			$this->redirect_import_page( 'error', __( 'No se recibió ningún archivo.', 'plugin-buscador-cotizador' ) );
		}

		$file = $_FILES['pbc_destinations_file'];

		if ( ! isset( $file['error'] ) || UPLOAD_ERR_OK !== (int) $file['error'] ) {
			$this->redirect_import_page( 'error', __( 'Hubo un problema al subir el archivo.', 'plugin-buscador-cotizador' ) );
		}

		if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			$this->redirect_import_page( 'error', __( 'El archivo subido no es válido.', 'plugin-buscador-cotizador' ) );
		}

		$filename  = isset( $file['name'] ) ? (string) $file['name'] : '';
		$extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

		if ( ! in_array( $extension, array( 'txt', 'csv' ), true ) ) {
			$this->redirect_import_page( 'error', __( 'Formato inválido. Solo se aceptan .txt o .csv.', 'plugin-buscador-cotizador' ) );
		}

		$import_result = $this->plugin->import_destinations_from_uploaded_file( $file['tmp_name'] );
		$detected_type = isset( $import_result['detected_type'] ) ? sanitize_text_field( (string) $import_result['detected_type'] ) : __( 'desconocido', 'plugin-buscador-cotizador' );

		if ( ! empty( $import_result['errors'] ) ) {
			$this->redirect_import_page(
				'error',
				sprintf(
					/* translators: 1: detected type, 2: imported count, 3: updated count, 4: skipped count, 5: errors count */
					__( 'Importación con errores (%1$s). Importados: %2$d, Actualizados: %3$d, Omitidos: %4$d, Errores: %5$d.', 'plugin-buscador-cotizador' ),
					$detected_type,
					(int) $import_result['imported'],
					(int) $import_result['updated'],
					(int) $import_result['skipped'],
					(int) $import_result['errors']
				)
			);
		}

		$this->redirect_import_page(
			'success',
			sprintf(
				/* translators: 1: detected type, 2: imported count, 3: updated count, 4: skipped count */
				__( 'Importación completada (%1$s). Importados: %2$d, Actualizados: %3$d, Omitidos: %4$d.', 'plugin-buscador-cotizador' ),
				$detected_type,
				(int) $import_result['imported'],
				(int) $import_result['updated'],
				(int) $import_result['skipped']
			)
		);
	}

	/**
	 * Redirige a la pantalla de importación con mensaje.
	 *
	 * @param string $status  Estado del mensaje.
	 * @param string $message Mensaje a mostrar.
	 *
	 * @return void
	 */
	private function redirect_import_page( $status, $message ) {
		$url = add_query_arg(
			array(
				'page'       => 'pbc-import-destinations',
				'pbc_status' => sanitize_key( $status ),
				'pbc_notice' => rawurlencode( $message ),
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}
}
