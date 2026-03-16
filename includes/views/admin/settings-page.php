<?php
/**
 * Vista de ajustes del plugin.
 *
 * @package PluginBuscadorCotizador
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Buscador Cotizador · Ajustes', 'plugin-buscador-cotizador' ); ?></h1>
	<p><?php esc_html_e( 'Configurá los datos generales usados por el plugin.', 'plugin-buscador-cotizador' ); ?></p>

	<form method="post" action="options.php">
		<?php settings_fields( 'pbc_settings_group' ); ?>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="pbc_whatsapp_number"><?php esc_html_e( 'Número de WhatsApp', 'plugin-buscador-cotizador' ); ?></label>
					</th>
					<td>
						<input
							type="text"
							id="pbc_whatsapp_number"
							name="pbc_whatsapp_number"
							value="<?php echo esc_attr( (string) get_option( 'pbc_whatsapp_number', '' ) ); ?>"
							class="regular-text"
							placeholder="+5491122334455"
						/>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="pbc_contact_email"><?php esc_html_e( 'Email de consultas', 'plugin-buscador-cotizador' ); ?></label>
					</th>
					<td>
						<input
							type="email"
							id="pbc_contact_email"
							name="pbc_contact_email"
							value="<?php echo esc_attr( (string) get_option( 'pbc_contact_email', '' ) ); ?>"
							class="regular-text"
							placeholder="ventas@agencia.com"
						/>
					</td>
				</tr>
			</tbody>
		</table>
		<?php submit_button( __( 'Guardar ajustes', 'plugin-buscador-cotizador' ) ); ?>
	</form>
</div>
