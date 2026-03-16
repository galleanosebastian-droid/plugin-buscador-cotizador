<?php
/**
 * Parcial de resultados de búsqueda del shortcode.
 *
 * @package PluginBuscadorCotizador
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $search_result ) || ! is_array( $search_result ) ) {
	return;
}
?>
<div class="pbc-search-results" aria-live="polite" data-pbc-results>
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
