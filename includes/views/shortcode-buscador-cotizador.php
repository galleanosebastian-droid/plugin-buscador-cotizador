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

	<?php if ( $form_submitted && is_array( $search_result ) ) : ?>
		<div class="pbc-search-results" aria-live="polite">
			<p class="pbc-result-message"><?php echo esc_html( (string) $search_result['message'] ); ?></p>
			<p class="pbc-capacity-note"><?php esc_html_e( 'Capacidad sujeta a confirmación', 'plugin-buscador-cotizador' ); ?></p>

			<?php if ( ! empty( $search_result['posts'] ) ) : ?>
				<div class="pbc-package-list">
					<?php foreach ( $search_result['posts'] as $package_post ) : ?>
						<?php
						$package_id    = $package_post->ID;
						$destination   = (string) get_post_meta( $package_id, 'destination', true );
						$departure     = (string) get_post_meta( $package_id, 'departure_date', true );
						$number_of_days = (string) get_post_meta( $package_id, 'number_of_days', true );
						$price         = (string) get_post_meta( $package_id, 'price', true );
						$permalink     = get_permalink( $package_id );
						$image_html    = get_the_post_thumbnail( $package_id, 'medium', array( 'class' => 'pbc-package-image' ) );

						if ( empty( $image_html ) ) {
							$image_html = sprintf(
								'<img class="pbc-package-image" src="%1$s" alt="%2$s" />',
								esc_url( PBC_PLUGIN_URL . 'assets/img/package-fallback.svg' ),
								esc_attr( get_the_title( $package_id ) )
							);
						}

						$whatsapp_text = rawurlencode( sprintf( __( 'Hola, quiero consultar el paquete: %s', 'plugin-buscador-cotizador' ), get_the_title( $package_id ) ) );
						$whatsapp_url  = 'https://wa.me/?text=' . $whatsapp_text;
						?>
						<article class="pbc-package-card">
							<div class="pbc-package-media"><?php echo wp_kses_post( $image_html ); ?></div>
							<div class="pbc-package-content">
								<h4 class="pbc-package-title"><?php echo esc_html( get_the_title( $package_id ) ); ?></h4>
								<ul class="pbc-package-meta">
									<li><strong><?php esc_html_e( 'Destino:', 'plugin-buscador-cotizador' ); ?></strong> <?php echo esc_html( $destination ); ?></li>
									<li><strong><?php esc_html_e( 'Fecha:', 'plugin-buscador-cotizador' ); ?></strong> <?php echo esc_html( $departure ); ?></li>
									<li><strong><?php esc_html_e( 'Duración:', 'plugin-buscador-cotizador' ); ?></strong> <?php echo esc_html( $number_of_days ); ?></li>
									<li><strong><?php esc_html_e( 'Precio:', 'plugin-buscador-cotizador' ); ?></strong> <?php echo esc_html( $price ); ?></li>
								</ul>
								<div class="pbc-contact-actions">
									<a class="pbc-action-button pbc-action-secondary" href="<?php echo esc_url( $permalink ); ?>"><?php esc_html_e( 'Ver paquete', 'plugin-buscador-cotizador' ); ?></a>
									<a class="pbc-action-button" href="<?php echo esc_url( $whatsapp_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Consultar', 'plugin-buscador-cotizador' ); ?></a>
								</div>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			<?php elseif ( ! empty( $search_result['suggestions'] ) ) : ?>
				<div class="pbc-suggestion-list">
					<?php foreach ( $search_result['suggestions'] as $suggestion ) : ?>
						<?php
						$wa_url      = 'https://wa.me/?text=' . rawurlencode( sprintf( __( 'Hola, quiero información sobre: %s', 'plugin-buscador-cotizador' ), $suggestion ) );
						$email_url   = 'mailto:?subject=' . rawurlencode( __( 'Consulta de paquete', 'plugin-buscador-cotizador' ) ) . '&body=' . rawurlencode( sprintf( __( 'Quiero información sobre: %s', 'plugin-buscador-cotizador' ), $suggestion ) );
						?>
						<div class="pbc-suggestion-card">
							<p class="pbc-suggestion-title"><?php echo esc_html( $suggestion ); ?></p>
							<div class="pbc-contact-actions">
								<a class="pbc-action-button" href="<?php echo esc_url( $wa_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Consultar por WhatsApp', 'plugin-buscador-cotizador' ); ?></a>
								<a class="pbc-action-button pbc-action-secondary" href="<?php echo esc_url( $email_url ); ?>"><?php esc_html_e( 'Consultar por Email', 'plugin-buscador-cotizador' ); ?></a>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
