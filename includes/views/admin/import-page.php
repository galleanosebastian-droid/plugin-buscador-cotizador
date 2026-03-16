<?php
/**
 * Vista de importación de destinos.
 *
 * @package PluginBuscadorCotizador
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$status = isset( $_GET['pbc_status'] ) ? sanitize_key( wp_unslash( $_GET['pbc_status'] ) ) : '';
$notice = isset( $_GET['pbc_notice'] ) ? sanitize_text_field( rawurldecode( (string) wp_unslash( $_GET['pbc_notice'] ) ) ) : '';
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Buscador Cotizador · Importar destinos', 'plugin-buscador-cotizador' ); ?></h1>
	<p><?php esc_html_e( 'Subí un archivo .txt o .csv de GeoNames para actualizar países, regiones y ciudades. Soporta cities1000.txt, countryInfo.txt y admin1CodesASCII.txt.', 'plugin-buscador-cotizador' ); ?></p>

	<?php if ( ! empty( $notice ) ) : ?>
		<div class="notice notice-<?php echo ( 'success' === $status ) ? 'success' : 'error'; ?> is-dismissible">
			<p><?php echo esc_html( $notice ); ?></p>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
		<input type="hidden" name="action" value="pbc_import_destinations" />
		<?php wp_nonce_field( 'pbc_import_destinations', 'pbc_import_nonce' ); ?>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="pbc_destinations_file"><?php esc_html_e( 'Archivo de destinos', 'plugin-buscador-cotizador' ); ?></label>
					</th>
					<td>
						<input type="file" id="pbc_destinations_file" name="pbc_destinations_file" accept=".txt,.csv,text/plain,text/csv" required />
						<p class="description"><?php esc_html_e( 'El importador detecta automáticamente el tipo de archivo y usa el parser correcto para cada formato.', 'plugin-buscador-cotizador' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>

		<?php submit_button( __( 'Importar destinos', 'plugin-buscador-cotizador' ) ); ?>
	</form>
</div>
