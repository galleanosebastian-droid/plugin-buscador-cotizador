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
	<h3 class="pbc-title"><?php esc_html_e( 'Encontrá tu próximo viaje', 'plugin-buscador-cotizador' ); ?></h3>
	<form class="pbc-form" method="post">
		<div class="pbc-field pbc-field-destino">
			<label for="pbc-destino"><?php esc_html_e( 'Destino', 'plugin-buscador-cotizador' ); ?></label>
			<div class="pbc-destino-input-wrapper">
				<input id="pbc-destino" name="pbc_destino" type="text" value="<?php echo esc_attr( $form_data['destino'] ); ?>" placeholder="<?php esc_attr_e( '¿A dónde querés viajar?', 'plugin-buscador-cotizador' ); ?>" autocomplete="off" required />
			</div>
		</div>

		<div class="pbc-field">
			<label for="pbc-fecha"><?php esc_html_e( 'Fecha', 'plugin-buscador-cotizador' ); ?></label>
			<input id="pbc-fecha" name="pbc_fecha" type="date" value="<?php echo esc_attr( $form_data['fecha'] ); ?>" required />
		</div>

		<div class="pbc-field">
			<label for="pbc-noches"><?php esc_html_e( 'Noches', 'plugin-buscador-cotizador' ); ?></label>
			<input id="pbc-noches" name="pbc_noches" type="number" min="1" value="<?php echo esc_attr( (string) $form_data['noches'] ); ?>" required />
		</div>

		<div class="pbc-field">
			<label for="pbc-pasajeros"><?php esc_html_e( 'Pasajeros', 'plugin-buscador-cotizador' ); ?></label>
			<input id="pbc-pasajeros" name="pbc_pasajeros" type="number" min="1" value="<?php echo esc_attr( (string) $form_data['pasajeros'] ); ?>" required />
		</div>

		<input name="pbc_form_submitted" type="hidden" value="1" />
		<div class="pbc-field pbc-field-submit">
			<button type="submit" class="pbc-submit-button"><?php esc_html_e( 'Buscar paquetes', 'plugin-buscador-cotizador' ); ?></button>
		</div>
	</form>

	<?php if ( $form_submitted ) : ?>
		<div class="pbc-result" aria-live="polite">
			<p><?php esc_html_e( 'Formulario funcionando correctamente', 'plugin-buscador-cotizador' ); ?></p>
		</div>
	<?php endif; ?>
</div>
