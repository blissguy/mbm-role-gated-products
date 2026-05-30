<?php
/**
 * Plugin settings screen and option sanitization.
 *
 * @package MBMRoleGatedProducts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MBM_RGP_Settings {
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_mbm_rgp_clear_cache', array( $this, 'handle_clear_cache' ) );
	}

	public static function defaults() {
		return array(
			'default_role'         => self::default_role_slug(),
			'blocked_behavior'     => '404',
			'redirect_url'         => '',
			'add_to_cart_message'  => __( 'This product is not available to your account.', 'mbm-role-gated-products' ),
			'cart_removed_message' => __( 'An item was removed from your cart because it is no longer available to your account.', 'mbm-role-gated-products' ),
			'admin_banner_enabled' => 1,
			'product_column_enabled' => 1,
		);
	}

	public function get() {
		$settings = get_option( MBM_RGP_Plugin::OPTION_SETTINGS, array() );
		$defaults = self::defaults();
		$settings = wp_parse_args( is_array( $settings ) ? $settings : array(), $defaults );

		return array_intersect_key( $settings, $defaults );
	}

	public function get_value( $key ) {
		$settings = $this->get();

		return isset( $settings[ $key ] ) ? $settings[ $key ] : null;
	}

	public function register_settings_page() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		add_submenu_page(
			'woocommerce',
			__( 'Role-Gated Products', 'mbm-role-gated-products' ),
			__( 'Role-Gated Products', 'mbm-role-gated-products' ),
			'manage_woocommerce',
			'mbm-role-gated-products',
			array( $this, 'render_settings_page' )
		);
	}

	public function register_settings() {
		register_setting(
			'mbm_role_gated_products_settings',
			MBM_RGP_Plugin::OPTION_SETTINGS,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	public function sanitize( $input ) {
		$input    = is_array( $input ) ? wp_unslash( $input ) : array();
		$defaults = self::defaults();

		$blocked_behavior = sanitize_key( $input['blocked_behavior'] ?? $defaults['blocked_behavior'] );
		if ( ! in_array( $blocked_behavior, array( '404', 'redirect' ), true ) ) {
			$blocked_behavior = '404';
		}

		return array(
			'default_role'           => $this->sanitize_role_slug( $input['default_role'] ?? $defaults['default_role'] ),
			'blocked_behavior'       => $blocked_behavior,
			'redirect_url'           => ! empty( $input['redirect_url'] ) ? esc_url_raw( $input['redirect_url'] ) : '',
			'add_to_cart_message'    => sanitize_text_field( $input['add_to_cart_message'] ?? $defaults['add_to_cart_message'] ),
			'cart_removed_message'   => sanitize_text_field( $input['cart_removed_message'] ?? $defaults['cart_removed_message'] ),
			'admin_banner_enabled'   => empty( $input['admin_banner_enabled'] ) ? 0 : 1,
			'product_column_enabled' => empty( $input['product_column_enabled'] ) ? 0 : 1,
		);
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$settings = $this->get();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Role-Gated Products', 'mbm-role-gated-products' ); ?></h1>
			<?php if ( isset( $_GET['mbm_rgp_cache_cleared'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Restricted product cache cleared.', 'mbm-role-gated-products' ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'mbm_role_gated_products_settings' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="mbm_rgp_default_role"><?php esc_html_e( 'Default approved role', 'mbm-role-gated-products' ); ?></label></th>
						<td>
							<?php $this->render_role_select( 'default_role', 'mbm_rgp_default_role', $settings['default_role'], true ); ?>
							<p class="description"><?php esc_html_e( 'Used as the initial approved role on products that have not been configured yet.', 'mbm-role-gated-products' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="mbm_rgp_blocked_behavior"><?php esc_html_e( 'Blocked product page behavior', 'mbm-role-gated-products' ); ?></label></th>
						<td>
							<select id="mbm_rgp_blocked_behavior" name="<?php echo esc_attr( MBM_RGP_Plugin::OPTION_SETTINGS ); ?>[blocked_behavior]">
								<option value="404" <?php selected( $settings['blocked_behavior'], '404' ); ?>><?php esc_html_e( 'Return a 404 page', 'mbm-role-gated-products' ); ?></option>
								<option value="redirect" <?php selected( $settings['blocked_behavior'], 'redirect' ); ?>><?php esc_html_e( 'Redirect to a page', 'mbm-role-gated-products' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="mbm_rgp_redirect_url"><?php esc_html_e( 'Redirect URL', 'mbm-role-gated-products' ); ?></label></th>
						<td>
							<input class="regular-text" type="url" id="mbm_rgp_redirect_url" name="<?php echo esc_attr( MBM_RGP_Plugin::OPTION_SETTINGS ); ?>[redirect_url]" value="<?php echo esc_attr( $settings['redirect_url'] ); ?>">
							<p class="description"><?php esc_html_e( 'Used only when redirect behavior is selected. Leave empty to fall back to a 404.', 'mbm-role-gated-products' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="mbm_rgp_add_to_cart_message"><?php esc_html_e( 'Blocked add-to-cart message', 'mbm-role-gated-products' ); ?></label></th>
						<td><input class="regular-text" type="text" id="mbm_rgp_add_to_cart_message" name="<?php echo esc_attr( MBM_RGP_Plugin::OPTION_SETTINGS ); ?>[add_to_cart_message]" value="<?php echo esc_attr( $settings['add_to_cart_message'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="mbm_rgp_cart_removed_message"><?php esc_html_e( 'Cart removal message', 'mbm-role-gated-products' ); ?></label></th>
						<td><input class="regular-text" type="text" id="mbm_rgp_cart_removed_message" name="<?php echo esc_attr( MBM_RGP_Plugin::OPTION_SETTINGS ); ?>[cart_removed_message]" value="<?php echo esc_attr( $settings['cart_removed_message'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Admin banner', 'mbm-role-gated-products' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( MBM_RGP_Plugin::OPTION_SETTINGS ); ?>[admin_banner_enabled]" value="1" <?php checked( $settings['admin_banner_enabled'], 1 ); ?>>
								<?php esc_html_e( 'Show a front-end banner to shop managers on restricted product pages.', 'mbm-role-gated-products' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Products table column', 'mbm-role-gated-products' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( MBM_RGP_Plugin::OPTION_SETTINGS ); ?>[product_column_enabled]" value="1" <?php checked( $settings['product_column_enabled'], 1 ); ?>>
								<?php esc_html_e( 'Show the Access column in the Products admin table.', 'mbm-role-gated-products' ); ?>
							</label>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<hr>
			<h2><?php esc_html_e( 'Tools', 'mbm-role-gated-products' ); ?></h2>
			<p><?php echo esc_html( $this->get_cache_status_text() ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="mbm_rgp_clear_cache">
				<?php wp_nonce_field( 'mbm_rgp_clear_cache' ); ?>
				<?php submit_button( __( 'Clear restricted product cache', 'mbm-role-gated-products' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	public function handle_clear_cache() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to clear this cache.', 'mbm-role-gated-products' ) );
		}

		check_admin_referer( 'mbm_rgp_clear_cache' );
		delete_transient( MBM_RGP_Plugin::TRANSIENT_RESTRICTED_PRODUCT_IDS );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                  => 'mbm-role-gated-products',
					'mbm_rgp_cache_cleared' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	private function render_role_select( $key, $id, $selected, $allow_empty ) {
		$roles = $this->get_role_choices();
		?>
		<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( MBM_RGP_Plugin::OPTION_SETTINGS . '[' . $key . ']' ); ?>">
			<?php if ( $allow_empty ) : ?>
				<option value="" <?php selected( $selected, '' ); ?>><?php esc_html_e( 'No default role', 'mbm-role-gated-products' ); ?></option>
			<?php endif; ?>
			<?php foreach ( $roles as $slug => $label ) : ?>
				<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $selected, $slug ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	private function get_cache_status_text() {
		$cached = get_transient( MBM_RGP_Plugin::TRANSIENT_RESTRICTED_PRODUCT_IDS );

		if ( false === $cached ) {
			return __( 'No restricted product cache is currently stored.', 'mbm-role-gated-products' );
		}

		return sprintf(
			/* translators: %d: number of product IDs. */
			_n( 'The cache currently contains %d restricted product ID.', 'The cache currently contains %d restricted product IDs.', count( (array) $cached ), 'mbm-role-gated-products' ),
			count( (array) $cached )
		);
	}

	private function sanitize_role_slug( $value ) {
		$value = sanitize_key( $value );
		if ( '' === $value ) {
			return '';
		}

		$roles = $this->get_role_choices();

		return isset( $roles[ $value ] ) ? $value : '';
	}

	private function get_role_choices() {
		$wp_roles = wp_roles();
		$choices  = array();

		foreach ( $wp_roles->roles as $role_slug => $role ) {
			$name                  = isset( $role['name'] ) ? $role['name'] : $role_slug;
			$choices[ $role_slug ] = translate_user_role( $name );
		}

		natcasesort( $choices );

		return $choices;
	}

	private static function default_role_slug() {
		$roles = wp_roles();

		if ( isset( $roles->roles['artist'] ) ) {
			return 'artist';
		}

		foreach ( $roles->roles as $role_slug => $role ) {
			$name = isset( $role['name'] ) ? $role['name'] : '';
			if ( 'artist' === strtolower( $role_slug ) || 'artist' === strtolower( (string) $name ) ) {
				return $role_slug;
			}
		}

		return '';
	}
}
