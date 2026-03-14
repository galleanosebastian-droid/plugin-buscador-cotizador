<?php
/**
 * Vista del shortcode [buscador_cotizador].
 *
 * @package PluginBuscadorCotizador
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
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
		<button type="submit"><?php esc_html_e( 'Enviar', 'plugin-buscador-cotizador' ); ?></button>
	</form>

	<?php if ( $form_submitted ) : ?>
		<div class="pbc-result" aria-live="polite">
			<p><?php esc_html_e( 'Formulario funcionando correctamente', 'plugin-buscador-cotizador' ); ?></p>
		</div>
	<?php endif; ?>
</div>
