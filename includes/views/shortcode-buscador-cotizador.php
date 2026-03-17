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
	<form class="pbc-search-form" method="post" data-pbc-search-form>
		<div class="pbc-search-column pbc-search-column-destino">
			<input id="pbc-destino" name="pbc_destino" type="text" value="<?php echo esc_attr( $form_data['destino'] ); ?>" placeholder="<?php esc_attr_e( 'Destino', 'plugin-buscador-cotizador' ); ?>" autocomplete="off" list="pbc-destino-suggestions" required />
			<datalist id="pbc-destino-suggestions"></datalist>
		</div>

		<div class="pbc-search-column pbc-search-column-fecha">
			<label class="pbc-search-field-label" for="pbc-fecha"><?php esc_html_e( 'Fecha', 'plugin-buscador-cotizador' ); ?></label>
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

	<div class="pbc-search-results-wrapper" data-pbc-results-wrapper>
		<?php if ( $form_submitted && is_array( $search_result ) ) : ?>
			<?php require PBC_PLUGIN_PATH . 'includes/views/partials/search-results.php'; ?>
		<?php endif; ?>
	</div>

	<div class="pbc-email-modal" data-pbc-email-modal hidden>
		<div class="pbc-email-modal-backdrop" data-pbc-email-close></div>
		<div class="pbc-email-modal-dialog" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Consulta por email', 'plugin-buscador-cotizador' ); ?>">
			<button class="pbc-email-modal-close" type="button" data-pbc-email-close aria-label="<?php esc_attr_e( 'Cerrar', 'plugin-buscador-cotizador' ); ?>">&times;</button>
			<h4 class="pbc-email-modal-title"><?php esc_html_e( 'Consultar por Email', 'plugin-buscador-cotizador' ); ?></h4>
			<p class="pbc-email-selected" data-pbc-email-selected></p>
			<form class="pbc-email-form" data-pbc-email-form>
				<input type="hidden" name="item_name" value="" />
				<input type="hidden" name="destination" value="" />
				<input type="hidden" name="travel_date" value="" />
				<input type="hidden" name="nights" value="" />
				<input type="hidden" name="passengers" value="" />

				<label for="pbc-client-name"><?php esc_html_e( 'Nombre', 'plugin-buscador-cotizador' ); ?></label>
				<input id="pbc-client-name" name="client_name" type="text" required />

				<label for="pbc-client-email"><?php esc_html_e( 'Email', 'plugin-buscador-cotizador' ); ?></label>
				<input id="pbc-client-email" name="client_email" type="email" required />

				<label for="pbc-client-phone"><?php esc_html_e( 'Teléfono (opcional)', 'plugin-buscador-cotizador' ); ?></label>
				<input id="pbc-client-phone" name="client_phone" type="text" />

				<label for="pbc-observations"><?php esc_html_e( 'Observaciones', 'plugin-buscador-cotizador' ); ?></label>
				<textarea id="pbc-observations" name="observations" rows="4"></textarea>

				<button type="submit" class="pbc-submit-button"><?php esc_html_e( 'Enviar consulta', 'plugin-buscador-cotizador' ); ?></button>
			</form>
			<p class="pbc-email-feedback" data-pbc-email-feedback aria-live="polite"></p>
		</div>
	</div>
</div>
