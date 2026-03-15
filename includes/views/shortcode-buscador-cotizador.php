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
<div class="pbc-search-shell">
	<h3 class="pbc-search-heading"><?php esc_html_e( 'Encontrá tu próximo viaje', 'plugin-buscador-cotizador' ); ?></h3>
	<form class="pbc-search-form" method="post">
		<div class="pbc-search-column pbc-search-column-destino">
			<input id="pbc-destino" name="pbc_destino" type="text" value="<?php echo esc_attr( $form_data['destino'] ); ?>" placeholder="<?php esc_attr_e( 'Destino', 'plugin-buscador-cotizador' ); ?>" autocomplete="off" required />
		</div>

		<div class="pbc-search-column pbc-search-column-fecha">
			<input id="pbc-fecha" name="pbc_fecha" type="date" value="<?php echo esc_attr( $form_data['fecha'] ); ?>" required />
		</div>

		<div class="pbc-search-column pbc-search-column-noches">
			<input id="pbc-noches" name="pbc_noches" type="number" min="1" value="<?php echo esc_attr( (string) $form_data['noches'] ); ?>" placeholder="<?php esc_attr_e( 'Noches', 'plugin-buscador-cotizador' ); ?>" required />
		</div>

		<div class="pbc-search-column pbc-search-column-pasajeros">
			<input id="pbc-pasajeros" name="pbc_pasajeros" type="number" min="1" value="<?php echo esc_attr( (string) $form_data['pasajeros'] ); ?>" placeholder="<?php esc_attr_e( 'Pasajeros', 'plugin-buscador-cotizador' ); ?>" required />
		</div>

		<input name="pbc_form_submitted" type="hidden" value="1" />
		<div class="pbc-search-column pbc-search-column-submit">
			<button type="submit" class="pbc-submit-button"><?php esc_html_e( 'Buscar', 'plugin-buscador-cotizador' ); ?></button>
		</div>
	</form>

	<?php if ( $form_submitted ) : ?>
		<div class="pbc-result" aria-live="polite">
			<p><?php esc_html_e( 'Formulario funcionando correctamente', 'plugin-buscador-cotizador' ); ?></p>
		</div>
	<?php endif; ?>
</div>
