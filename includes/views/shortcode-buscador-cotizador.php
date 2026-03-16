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
			<input id="pbc-destino" name="pbc_destino" type="text" value="<?php echo esc_attr( $form_data['destino'] ); ?>" placeholder="<?php esc_attr_e( 'Destino', 'plugin-buscador-cotizador' ); ?>" autocomplete="off" list="pbc-destino-suggestions" required />
			<datalist id="pbc-destino-suggestions"></datalist>
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

	<?php if ( $form_submitted && is_array( $search_result ) ) : ?>
		<div class="pbc-search-results" aria-live="polite">
			<p class="pbc-result-message"><?php echo esc_html( (string) $search_result['message'] ); ?></p>
			<p class="pbc-capacity-note"><?php esc_html_e( 'Capacidad sujeta a confirmación', 'plugin-buscador-cotizador' ); ?></p>

			<?php if ( ! empty( $search_result['posts'] ) ) : ?>
				<div class="pbc-package-list">
					<?php foreach ( $search_result['posts'] as $package_post ) : ?>
						<?php
						$package_id      = $package_post->ID;
						$package_title   = get_the_title( $package_id );
						$destination     = (string) get_post_meta( $package_id, 'destination', true );
						$departure       = (string) get_post_meta( $package_id, 'departure_date', true );
						$number_of_days  = absint( get_post_meta( $package_id, 'number_of_days', true ) );
						$price           = (string) get_post_meta( $package_id, 'price', true );
						$permalink       = get_permalink( $package_id );
						$image_html      = $this->get_package_result_image_html( $package_id, $package_title );
						$inquiry_context = array(
							'item_name'   => $package_title,
							'destination' => ! empty( $destination ) ? $destination : $form_data['destino'],
							'travel_date' => ! empty( $departure ) ? $departure : $form_data['fecha'],
							'nights'      => $number_of_days > 0 ? $number_of_days : absint( $form_data['noches'] ),
							'passengers'  => absint( $form_data['pasajeros'] ),
						);
						?>
						<article class="pbc-package-card">
							<div class="pbc-package-media"><?php echo wp_kses_post( $image_html ); ?></div>
							<div class="pbc-package-content">
								<h4 class="pbc-package-title"><?php echo esc_html( $package_title ); ?></h4>
								<ul class="pbc-package-meta">
									<li><strong><?php esc_html_e( 'Destino:', 'plugin-buscador-cotizador' ); ?></strong> <?php echo esc_html( $destination ); ?></li>
									<li><strong><?php esc_html_e( 'Fecha:', 'plugin-buscador-cotizador' ); ?></strong> <?php echo esc_html( $departure ); ?></li>
									<li><strong><?php esc_html_e( 'Duración:', 'plugin-buscador-cotizador' ); ?></strong> <?php echo esc_html( (string) $number_of_days ); ?></li>
									<li><strong><?php esc_html_e( 'Precio:', 'plugin-buscador-cotizador' ); ?></strong> <?php echo esc_html( $price ); ?></li>
								</ul>
								<div class="pbc-contact-actions">
									<a class="pbc-action-button pbc-action-secondary" href="<?php echo esc_url( $permalink ); ?>"><?php esc_html_e( 'Ver paquete', 'plugin-buscador-cotizador' ); ?></a>
									<a class="pbc-action-button" href="<?php echo esc_url( $this->get_whatsapp_url( $inquiry_context ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Consultar por WhatsApp', 'plugin-buscador-cotizador' ); ?></a>
									<button class="pbc-action-button pbc-action-secondary pbc-email-trigger" type="button" data-pbc-inquiry="<?php echo esc_attr( wp_json_encode( $inquiry_context ) ); ?>"><?php esc_html_e( 'Consultar por Email', 'plugin-buscador-cotizador' ); ?></button>
								</div>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			<?php elseif ( ! empty( $search_result['suggestions'] ) ) : ?>
				<div class="pbc-suggestion-list">
					<?php foreach ( $search_result['suggestions'] as $suggestion ) : ?>
						<?php
						$inquiry_context = array(
							'item_name'   => $suggestion,
							'destination' => ! empty( $form_data['destino'] ) ? $form_data['destino'] : $suggestion,
							'travel_date' => $form_data['fecha'],
							'nights'      => absint( $form_data['noches'] ),
							'passengers'  => absint( $form_data['pasajeros'] ),
						);
						?>
						<div class="pbc-suggestion-card">
							<p class="pbc-suggestion-title"><?php echo esc_html( $suggestion ); ?></p>
							<div class="pbc-contact-actions">
								<a class="pbc-action-button" href="<?php echo esc_url( $this->get_whatsapp_url( $inquiry_context ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Consultar por WhatsApp', 'plugin-buscador-cotizador' ); ?></a>
								<button class="pbc-action-button pbc-action-secondary pbc-email-trigger" type="button" data-pbc-inquiry="<?php echo esc_attr( wp_json_encode( $inquiry_context ) ); ?>"><?php esc_html_e( 'Consultar por Email', 'plugin-buscador-cotizador' ); ?></button>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>

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
