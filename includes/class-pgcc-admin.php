<?php
/**
 * Settings → Pulsegrid screen.
 *
 * @package PulsegridContentConnector
 */

defined( 'ABSPATH' ) || exit;

class PGCC_Admin {

	const PAGE = 'pulsegrid';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register' ) );
		add_action( 'admin_post_pgcc_clear_cache', array( $this, 'handle_clear_cache' ) );
		add_action( 'admin_post_pgcc_test_connection', array( $this, 'handle_test_connection' ) );
		add_action( 'admin_post_pgcc_create_page', array( $this, 'handle_create_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( PGCC_FILE ), array( $this, 'action_links' ) );
	}

	public function menu() {
		add_options_page(
			__( 'Pulsegrid', 'pulsegrid-content-connector' ),
			__( 'Pulsegrid', 'pulsegrid-content-connector' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render' )
		);
	}

	/**
	 * @param array $links Plugin row links.
	 * @return array
	 */
	public function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=' . self::PAGE ) ) . '">' . esc_html__( 'Settings', 'pulsegrid-content-connector' ) . '</a>' );
		return $links;
	}

	const TOPICS_GROUP = 'pulsegrid-topics';

	public function register() {
		register_setting(
			self::PAGE,
			PGCC_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'PGCC_Settings', 'sanitize' ),
				'show_in_rest'      => false,
			)
		);
		register_setting(
			self::TOPICS_GROUP,
			PGCC_Topics::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'PGCC_Topics', 'sanitize_option' ),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Redirect back to the settings screen with a notice.
	 *
	 * @param string $code    Notice code.
	 * @param string $message Message (optional).
	 */
	private function back( $code, $message = '', $tab = '' ) {
		// The message travels in a short-lived per-user transient, not the URL,
		// so a crafted link can't put arbitrary text on this screen.
		set_transient(
			'pgcc_notice_' . get_current_user_id(),
			array(
				'code'    => $code,
				'message' => $message,
			),
			MINUTE_IN_SECONDS
		);
		$url = add_query_arg(
			array_filter(
				array(
					'page' => self::PAGE,
					'tab'  => $tab,
				)
			),
			admin_url( 'options-general.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}

	public function handle_clear_cache() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'pulsegrid-content-connector' ) );
		}
		check_admin_referer( 'pgcc_clear_cache' );
		$count = PGCC_Cache::clear();
		/* translators: %d: number of cached items */
		$this->back( 'cleared', sprintf( _n( 'Cleared %d cached item.', 'Cleared %d cached items.', $count, 'pulsegrid-content-connector' ), $count ) );
	}

	public function handle_test_connection() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'pulsegrid-content-connector' ) );
		}
		check_admin_referer( 'pgcc_test_connection' );

		if ( '' === PGCC_Settings::api_key() ) {
			$this->back( 'error', __( 'Add an API key first.', 'pulsegrid-content-connector' ) );
		}
		$client = new PGCC_API_Source( PGCC_Settings::base_url(), PGCC_Settings::api_key(), PGCC_Settings::trckr(), (string) PGCC_Settings::get( 'language' ) );
		$result = $client->test();
		if ( is_wp_error( $result ) ) {
			$this->back( 'error', $result->get_error_message() );
		}
		$this->back( 'ok', __( 'Connected to the Mayo Clinic API successfully.', 'pulsegrid-content-connector' ) );
	}

	public function handle_create_page() {
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'publish_pages' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'pulsegrid-content-connector' ) );
		}
		check_admin_referer( 'pgcc_create_page' );

		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => __( 'Health Library', 'pulsegrid-content-connector' ),
				'post_content' => '<!-- wp:shortcode -->[' . PGCC_Frontend::SHORTCODE . ']<!-- /wp:shortcode -->',
			),
			true
		);
		if ( is_wp_error( $page_id ) ) {
			$this->back( 'error', $page_id->get_error_message() );
		}

		$settings                    = PGCC_Settings::all();
		$settings['library_page_id'] = (int) $page_id;
		update_option( PGCC_Settings::OPTION, $settings );
		PGCC_Settings::flush();

		$this->back( 'ok', __( 'Created the Health Library page.', 'pulsegrid-content-connector' ) );
	}

	/**
	 * @param string $name Field name inside the option array.
	 * @return string
	 */
	private function name( $name ) {
		return PGCC_Settings::OPTION . '[' . $name . ']';
	}

	/**
	 * Settings tabs.
	 *
	 * @return array slug => label
	 */
	private function tabs() {
		$tabs = array(
			'general' => __( 'General', 'pulsegrid-content-connector' ),
			'topics'  => __( 'Topic buttons', 'pulsegrid-content-connector' ),
			'help'    => __( 'Shortcodes &amp; help', 'pulsegrid-content-connector' ),
		);
		/**
		 * Filter the settings tabs. Add-ons render their tab on the
		 * `pgcc_admin_tab_{slug}` action.
		 *
		 * @param array $tabs slug => label.
		 */
		$tabs = (array) apply_filters( 'pgcc_admin_tabs', $tabs );
		$tabs = array_map( 'wp_strip_all_tags', $tabs );
		return array( 'general' => $tabs['general'] ?? __( 'General', 'pulsegrid-content-connector' ) ) + $tabs;
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s          = PGCC_Settings::all();
		$key_const  = PGCC_Settings::api_key_from_constant();
		$trk_const  = PGCC_Settings::trckr_from_constant();
		$has_key    = '' !== PGCC_Settings::api_key();
		$page_id    = (int) $s['library_page_id'];
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only.
		$tab        = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
		$tabs       = $this->tabs();
		$tab        = isset( $tabs[ $tab ] ) ? $tab : 'general';
		// phpcs:enable
		$flash      = get_transient( 'pgcc_notice_' . get_current_user_id() );
		$notice     = is_array( $flash ) ? sanitize_key( (string) $flash['code'] ) : '';
		$notice_msg = is_array( $flash ) ? sanitize_textarea_field( (string) $flash['message'] ) : '';
		if ( $flash ) {
			delete_transient( 'pgcc_notice_' . get_current_user_id() );
		}
		$tab_url    = admin_url( 'options-general.php?page=' . self::PAGE );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Pulsegrid Content Connector', 'pulsegrid-content-connector' ); ?></h1>

			<?php if ( $notice && $notice_msg ) : ?>
				<div class="notice notice-<?php echo 'error' === $notice ? 'error' : ( 'warning' === $notice ? 'warning' : 'success' ); ?> is-dismissible"><p><?php echo nl2br( esc_html( $notice_msg ) ); ?></p></div>
			<?php endif; ?>

			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $pgcc_slug => $pgcc_label ) : ?>
					<a href="<?php echo esc_url( 'general' === $pgcc_slug ? $tab_url : $tab_url . '&tab=' . $pgcc_slug ); ?>" class="nav-tab <?php echo $pgcc_slug === $tab ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $pgcc_label ); ?></a>
				<?php endforeach; ?>
			</nav>

			<?php
			if ( 'general' !== $tab ) {
				if ( 'topics' === $tab ) {
					$this->render_topics_tab();
				} elseif ( 'help' === $tab ) {
					$this->render_help_tab();
				} else {
					/**
					 * Render an add-on's settings tab (registered with `pgcc_admin_tabs`).
					 *
					 * @param string $tab_url Base URL of the settings screen.
					 */
					do_action( 'pgcc_admin_tab_' . $tab, $tab_url );
				}
				echo '</div>';
				return;
			}
			?>

			<?php if ( 'live' === $s['mode'] && ! $has_key ) : ?>
				<div class="notice notice-warning"><p><?php esc_html_e( 'Live mode is selected but no API key is set, so demo content is being shown.', 'pulsegrid-content-connector' ); ?></p></div>
			<?php endif; ?>

			<p>
				<?php esc_html_e( 'Add [pulsegrid] to any page to show the library. Use [pulsegrid_search] for a search box and [pulsegrid_topics] for topic buttons anywhere else (home page, header, sidebar).', 'pulsegrid-content-connector' ); ?>
				<a href="<?php echo esc_url( $tab_url . '&tab=help' ); ?>"><?php esc_html_e( 'All shortcodes and options', 'pulsegrid-content-connector' ); ?></a> &middot;
				<?php if ( $page_id && get_post_status( $page_id ) ) : ?>
					<a href="<?php echo esc_url( get_permalink( $page_id ) ); ?>" target="_blank"><?php esc_html_e( 'View library page', 'pulsegrid-content-connector' ); ?> &rarr;</a>
				<?php endif; ?>
			</p>

			<form method="post" action="options.php">
				<?php settings_fields( self::PAGE ); ?>

				<h2><?php esc_html_e( 'Content source', 'pulsegrid-content-connector' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Mode', 'pulsegrid-content-connector' ); ?></th>
						<td>
							<fieldset>
								<label><input type="radio" name="<?php echo esc_attr( $this->name( 'mode' ) ); ?>" value="demo" <?php checked( $s['mode'], 'demo' ); ?>> <?php esc_html_e( 'Demo — bundled sample articles, no API key needed', 'pulsegrid-content-connector' ); ?></label><br>
								<label><input type="radio" name="<?php echo esc_attr( $this->name( 'mode' ) ); ?>" value="live" <?php checked( $s['mode'], 'live' ); ?>> <?php esc_html_e( 'Live — Mayo Clinic Realtime API (requires your licensed credentials)', 'pulsegrid-content-connector' ); ?></label>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="pgcc-api-key"><?php esc_html_e( 'API key', 'pulsegrid-content-connector' ); ?></label></th>
						<td>
							<?php if ( $key_const ) : ?>
								<p><code>PGCC_API_KEY</code> <?php esc_html_e( 'is defined in wp-config.php.', 'pulsegrid-content-connector' ); ?></p>
							<?php else : ?>
								<input type="password" id="pgcc-api-key" class="regular-text" autocomplete="new-password" name="<?php echo esc_attr( $this->name( 'api_key' ) ); ?>" value="" placeholder="<?php echo $has_key ? esc_attr__( '•••••••• (saved — leave blank to keep)', 'pulsegrid-content-connector' ) : ''; ?>">
								<?php if ( $has_key ) : ?>
									<label><input type="checkbox" name="<?php echo esc_attr( $this->name( 'api_key_clear' ) ); ?>" value="1"> <?php esc_html_e( 'Remove', 'pulsegrid-content-connector' ); ?></label>
								<?php endif; ?>
								<p class="description"><?php echo wp_kses( __( 'Tip: define <code>PGCC_API_KEY</code> in wp-config.php instead, so the key is never stored in the database.', 'pulsegrid-content-connector' ), array( 'code' => array() ) ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="pgcc-trckr"><?php esc_html_e( 'Tracking value (trckr)', 'pulsegrid-content-connector' ); ?></label></th>
						<td>
							<?php if ( $trk_const ) : ?>
								<p><code>PGCC_TRCKR</code> <?php esc_html_e( 'is defined in wp-config.php.', 'pulsegrid-content-connector' ); ?></p>
							<?php else : ?>
								<input type="password" id="pgcc-trckr" class="regular-text" autocomplete="off" name="<?php echo esc_attr( $this->name( 'trckr' ) ); ?>" value="" placeholder="<?php echo '' !== $s['trckr'] ? esc_attr__( '•••••••• (saved — leave blank to keep)', 'pulsegrid-content-connector' ) : ''; ?>">
								<?php if ( '' !== $s['trckr'] ) : ?>
									<label><input type="checkbox" name="<?php echo esc_attr( $this->name( 'trckr_clear' ) ); ?>" value="1"> <?php esc_html_e( 'Remove', 'pulsegrid-content-connector' ); ?></label>
								<?php endif; ?>
								<p class="description"><?php esc_html_e( 'Provided by your Mayo Clinic operations contact; sent as the trckr header.', 'pulsegrid-content-connector' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="pgcc-base"><?php esc_html_e( 'API base URL', 'pulsegrid-content-connector' ); ?></label></th>
						<td><input type="url" id="pgcc-base" class="regular-text code" name="<?php echo esc_attr( $this->name( 'base_url' ) ); ?>" value="<?php echo esc_attr( $s['base_url'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="pgcc-lang"><?php esc_html_e( 'Language', 'pulsegrid-content-connector' ); ?></label></th>
						<td><input type="text" id="pgcc-lang" class="small-text" name="<?php echo esc_attr( $this->name( 'language' ) ); ?>" value="<?php echo esc_attr( $s['language'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="pgcc-cache"><?php esc_html_e( 'Cache lifetime (hours)', 'pulsegrid-content-connector' ); ?></label></th>
						<td>
							<input type="number" id="pgcc-cache" class="small-text" min="1" max="48" name="<?php echo esc_attr( $this->name( 'cache_hours' ) ); ?>" value="<?php echo esc_attr( $s['cache_hours'] ); ?>">
							<p class="description"><?php esc_html_e( 'Maximum 48. Mayo Clinic’s syndication terms require content to be refreshed at least every 48 hours.', 'pulsegrid-content-connector' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Display', 'pulsegrid-content-connector' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="pgcc-page"><?php esc_html_e( 'Library page', 'pulsegrid-content-connector' ); ?></label></th>
						<td>
							<?php
							wp_dropdown_pages(
								array(
									'name'              => esc_attr( $this->name( 'library_page_id' ) ),
									'id'                => 'pgcc-page',
									'selected'          => absint( $page_id ),
									'show_option_none'  => esc_html__( '— Select —', 'pulsegrid-content-connector' ),
									'option_none_value' => '0',
								)
							);
							?>
							<p class="description"><?php esc_html_e( 'Where [pulsegrid_search] boxes send visitors.', 'pulsegrid-content-connector' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="pgcc-per"><?php esc_html_e( 'Results per page', 'pulsegrid-content-connector' ); ?></label></th>
						<td><input type="number" id="pgcc-per" class="small-text" min="1" max="50" name="<?php echo esc_attr( $this->name( 'per_page' ) ); ?>" value="<?php echo esc_attr( $s['per_page'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="pgcc-featured"><?php esc_html_e( 'Featured articles', 'pulsegrid-content-connector' ); ?></label></th>
						<td>
							<input type="number" id="pgcc-featured" class="small-text" min="0" max="50" name="<?php echo esc_attr( $this->name( 'featured_count' ) ); ?>" value="<?php echo esc_attr( $s['featured_count'] ); ?>">
							<p class="description"><?php esc_html_e( 'Shown on the landing view before a search. 0 hides them.', 'pulsegrid-content-connector' ); ?></p>
						</td>
					</tr>
					<?php
					$pgcc_palette = PGCC_Style::theme_palette();
					$pgcc_accent  = PGCC_Style::accent();
					$pgcc_source  = 'custom' === $s['color_source'] ? 'custom' : 'theme';
					?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Accent color', 'pulsegrid-content-connector' ); ?></th>
						<td>
							<p class="description pgcc-look-intro"><?php esc_html_e( 'Fonts, text and link colors always come from your theme. The accent color is used for buttons, badges and the sidebar box.', 'pulsegrid-content-connector' ); ?></p>
							<fieldset>
								<label><input type="radio" name="<?php echo esc_attr( $this->name( 'color_source' ) ); ?>" value="theme" <?php checked( $pgcc_source, 'theme' ); ?> <?php disabled( empty( $pgcc_palette ) ); ?>> <?php esc_html_e( 'Use a color from my theme', 'pulsegrid-content-connector' ); ?></label>
								<?php if ( $pgcc_palette ) : ?>
									<div class="pgcc-swatches">
										<?php foreach ( $pgcc_palette as $pgcc_c ) : ?>
											<label class="pgcc-swatch" title="<?php echo esc_attr( $pgcc_c['color'] ); ?>">
												<input type="radio" name="<?php echo esc_attr( $this->name( 'theme_color' ) ); ?>" value="<?php echo esc_attr( $pgcc_c['slug'] ); ?>" <?php checked( $pgcc_accent['from_theme'] && $pgcc_accent['slug'] === $pgcc_c['slug'] ); ?>>
												<span class="pgcc-swatch-chip" style="background:<?php echo esc_attr( $pgcc_c['color'] ); ?>"></span>
												<span><?php echo esc_html( $pgcc_c['name'] ); ?></span>
											</label>
										<?php endforeach; ?>
									</div>
									<p class="description"><?php esc_html_e( 'Linked to the theme: if the theme’s color changes, the library follows.', 'pulsegrid-content-connector' ); ?></p>
								<?php else : ?>
									<p class="description"><?php esc_html_e( 'Your theme doesn’t publish a color palette, so the custom color below is used.', 'pulsegrid-content-connector' ); ?></p>
								<?php endif; ?>
								<label><input type="radio" name="<?php echo esc_attr( $this->name( 'color_source' ) ); ?>" value="custom" <?php checked( $pgcc_source, 'custom' ); ?>> <?php esc_html_e( 'Use a custom color', 'pulsegrid-content-connector' ); ?></label>
								<input type="text" id="pgcc-accent" class="small-text" pattern="#[0-9A-Fa-f]{6}" aria-label="<?php esc_attr_e( 'Custom accent color', 'pulsegrid-content-connector' ); ?>" name="<?php echo esc_attr( $this->name( 'accent_color' ) ); ?>" value="<?php echo esc_attr( $s['accent_color'] ); ?>">
								<span class="pgcc-swatch-chip" style="background:<?php echo esc_attr( $s['accent_color'] ); ?>"></span>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Plugin styling', 'pulsegrid-content-connector' ); ?></th>
						<td>
							<fieldset>
								<label><input type="radio" name="<?php echo esc_attr( $this->name( 'style_level' ) ); ?>" value="full" <?php checked( 'layout' !== $s['style_level'] ); ?>> <?php esc_html_e( 'Cards, buttons and topic chips in the accent color (recommended)', 'pulsegrid-content-connector' ); ?></label><br>
								<label><input type="radio" name="<?php echo esc_attr( $this->name( 'style_level' ) ); ?>" value="layout" <?php checked( 'layout', $s['style_level'] ); ?>> <?php esc_html_e( 'Layout only: leave buttons, borders and colors entirely to my theme or custom CSS', 'pulsegrid-content-connector' ); ?></label>
							</fieldset>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Sidebar', 'pulsegrid-content-connector' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Show on search and article views', 'pulsegrid-content-connector' ); ?></th>
						<td>
							<?php $sidebar = PGCC_Settings::normalize_sidebar( $s['sidebar'] ); ?>
							<?php $modes = PGCC_Settings::sidebar_modes(); ?>
							<fieldset>
								<?php foreach ( $modes as $pgcc_mode => $pgcc_mode_label ) : ?>
									<label><input type="radio" name="<?php echo esc_attr( $this->name( 'sidebar' ) ); ?>" value="<?php echo esc_attr( $pgcc_mode ); ?>" <?php checked( $sidebar, $pgcc_mode ); ?>> <?php echo esc_html( $pgcc_mode_label ); ?></label><br>
								<?php endforeach; ?>
							</fieldset>
							<?php if ( ! isset( $modes['links'] ) ) : ?>
								<p class="description">
									<?php
									printf(
										/* translators: %s: link to Pulsegrid Pro */
										esc_html__( 'Want the sidebar to show your own provider and service pages matched to each article’s topic? %s adds that.', 'pulsegrid-content-connector' ),
										'<a href="' . esc_url( PGCC_PRO_URL ) . '" target="_blank" rel="noopener">Pulsegrid Pro</a>'
									);
									?>
								</p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="pgcc-cta-title"><?php esc_html_e( 'Call to action', 'pulsegrid-content-connector' ); ?></label></th>
						<td>
							<p><input type="text" id="pgcc-cta-title" class="regular-text" name="<?php echo esc_attr( $this->name( 'cta_title' ) ); ?>" value="<?php echo esc_attr( $s['cta_title'] ); ?>" placeholder="<?php esc_attr_e( 'Heading', 'pulsegrid-content-connector' ); ?>"></p>
							<p><textarea class="large-text" rows="2" name="<?php echo esc_attr( $this->name( 'cta_text' ) ); ?>" placeholder="<?php esc_attr_e( 'Text', 'pulsegrid-content-connector' ); ?>"><?php echo esc_textarea( $s['cta_text'] ); ?></textarea></p>
							<p><input type="text" class="regular-text" name="<?php echo esc_attr( $this->name( 'cta_button' ) ); ?>" value="<?php echo esc_attr( $s['cta_button'] ); ?>" placeholder="<?php esc_attr_e( 'Button label', 'pulsegrid-content-connector' ); ?>"></p>
							<p><input type="url" class="regular-text code" name="<?php echo esc_attr( $this->name( 'cta_url' ) ); ?>" value="<?php echo esc_attr( $s['cta_url'] ); ?>" placeholder="https://"></p>
							<p class="description"><?php esc_html_e( 'Shown beside search results and articles.', 'pulsegrid-content-connector' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<hr>
			<h2><?php esc_html_e( 'Tools', 'pulsegrid-content-connector' ); ?></h2>
			<p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
					<input type="hidden" name="action" value="pgcc_test_connection">
					<?php wp_nonce_field( 'pgcc_test_connection' ); ?>
					<?php submit_button( __( 'Test API connection', 'pulsegrid-content-connector' ), 'secondary', 'submit', false, $has_key ? array() : array( 'disabled' => 'disabled' ) ); ?>
				</form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
					<input type="hidden" name="action" value="pgcc_clear_cache">
					<?php wp_nonce_field( 'pgcc_clear_cache' ); ?>
					<?php submit_button( __( 'Clear cache', 'pulsegrid-content-connector' ), 'secondary', 'submit', false ); ?>
				</form>
				<?php if ( ! $page_id || ! get_post_status( $page_id ) ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
						<input type="hidden" name="action" value="pgcc_create_page">
						<?php wp_nonce_field( 'pgcc_create_page' ); ?>
						<?php submit_button( __( 'Create library page', 'pulsegrid-content-connector' ), 'secondary', 'submit', false ); ?>
					</form>
				<?php endif; ?>
			</p>

			<p class="description" style="margin-top:2em">
				<?php esc_html_e( 'This plugin is an independent project and is not affiliated with or endorsed by Mayo Clinic. You are responsible for complying with the terms of your Mayo Clinic content license, including attribution, canonical tags and caching limits.', 'pulsegrid-content-connector' ); ?>
			</p>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------
	 * Topic buttons tab
	 * ------------------------------------------------------------------ */

	private function render_topics_tab() {
		$topics   = PGCC_Topics::all();
		$is_saved = PGCC_Topics::has_saved();
		$base     = PGCC_Frontend::library_url();
		?>
		<p class="pgcc-intro">
			<?php echo wp_kses( __( 'Buttons for common topics, shown wherever you place <code>[pulsegrid_topics]</code> (for example on the home page next to <code>[pulsegrid_search]</code>). Each button opens a search or a specific article in the library.', 'pulsegrid-content-connector' ), array( 'code' => array() ) ); ?>
		</p>
		<ul class="pgcc-help">
			<li><?php echo wp_kses( __( 'One topic per line: <code>Button label | what it opens</code>.', 'pulsegrid-content-connector' ), array( 'code' => array() ) ); ?></li>
			<li><?php echo wp_kses( __( '<strong>Search:</strong> <code>Heart attack | heart attack</code> opens search results for “heart attack”. If the label and the search are the same, the label alone is enough: <code>Asthma</code>.', 'pulsegrid-content-connector' ), array( 'code' => array(), 'strong' => array() ) ); ?></li>
			<li><?php echo wp_kses( __( '<strong>Article:</strong> <code>Colonoscopy | CON-20373087</code> opens that article directly. The ID is the <code>mayo-id</code> value in an article’s address.', 'pulsegrid-content-connector' ), array( 'code' => array(), 'strong' => array() ) ); ?></li>
			<li><?php esc_html_e( 'Buttons appear in this order. Lines starting with # are ignored.', 'pulsegrid-content-connector' ); ?></li>
		</ul>

		<?php if ( ! $is_saved ) : ?>
			<div class="notice notice-info inline"><p><?php esc_html_e( 'This is the starter list. Edit and save it to use your own.', 'pulsegrid-content-connector' ); ?></p></div>
		<?php endif; ?>

		<form method="post" action="options.php">
			<?php settings_fields( self::TOPICS_GROUP ); ?>
			<p>
				<label for="pgcc-topics-text" class="screen-reader-text"><?php esc_html_e( 'Topics', 'pulsegrid-content-connector' ); ?></label>
				<textarea id="pgcc-topics-text" class="large-text code" rows="12" name="<?php echo esc_attr( PGCC_Topics::OPTION ); ?>"><?php echo esc_textarea( PGCC_Topics::to_text( $topics ) ); ?></textarea>
			</p>
			<?php submit_button( __( 'Save topics', 'pulsegrid-content-connector' ) ); ?>
		</form>

		<?php if ( $topics ) : ?>
			<h2><?php esc_html_e( 'Preview', 'pulsegrid-content-connector' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Your theme styles the real buttons; this shows the order and where each one goes.', 'pulsegrid-content-connector' ); ?></p>
			<ul class="pgcc-topics-preview">
				<?php foreach ( $topics as $topic ) : ?>
					<li>
						<a href="<?php echo esc_url( PGCC_Topics::url( $topic, $base ) ); ?>" target="_blank"><?php echo esc_html( $topic['label'] ); ?></a>
						<span class="description">
							<?php
							echo esc_html(
								PGCC_Topics::is_content_id( $topic['target'] )
									/* translators: %s: content ID */
									? sprintf( __( 'article %s', 'pulsegrid-content-connector' ), $topic['target'] )
									/* translators: %s: search terms */
									: sprintf( __( 'search “%s”', 'pulsegrid-content-connector' ), $topic['target'] )
							);
							?>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<?php
	}

	/* ------------------------------------------------------------------
	 * Shortcodes & help tab
	 * ------------------------------------------------------------------ */

	/**
	 * @param string $code Shortcode example.
	 */
	private function code_box( $code ) {
		printf(
			'<input type="text" class="large-text code pgcc-copy" readonly value="%s" aria-label="%s">',
			esc_attr( $code ),
			esc_attr__( 'Shortcode (select to copy)', 'pulsegrid-content-connector' )
		);
	}

	/**
	 * @param array $rows List of [ attribute, values, description ].
	 */
	private function attr_table( array $rows ) {
		?>
		<table class="widefat striped pgcc-attr-table">
			<thead><tr>
				<th><?php esc_html_e( 'Option', 'pulsegrid-content-connector' ); ?></th>
				<th><?php esc_html_e( 'Values', 'pulsegrid-content-connector' ); ?></th>
				<th><?php esc_html_e( 'What it does', 'pulsegrid-content-connector' ); ?></th>
			</tr></thead>
			<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<tr><td><code><?php echo esc_html( $row[0] ); ?></code></td><td><?php echo esc_html( $row[1] ); ?></td><td><?php echo esc_html( $row[2] ); ?></td></tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private function render_help_tab() {
		$tab_url = admin_url( 'options-general.php?page=' . self::PAGE );
		?>
		<div class="pgcc-doc">
			<p class="pgcc-intro"><?php esc_html_e( 'Shortcodes work in the block editor (Shortcode block), classic editor, most page builders (look for a “Shortcode” or “Text” element), and widget areas. Select a box below to copy it.', 'pulsegrid-content-connector' ); ?></p>

			<h2><code>[pulsegrid]</code> &mdash; <?php esc_html_e( 'the library', 'pulsegrid-content-connector' ); ?></h2>
			<p><?php esc_html_e( 'The full library: a landing view with search and featured articles, search results, and articles. Put it on one page and choose that page as the Library page on the General tab.', 'pulsegrid-content-connector' ); ?></p>
			<?php $this->code_box( '[pulsegrid]' ); ?>
			<?php $this->code_box( '[pulsegrid sidebar="cta" featured="6"]' ); ?>
			<?php
			$this->attr_table(
				array(
					array( 'sidebar', implode( ', ', array_keys( PGCC_Settings::sidebar_modes() ) ), __( 'Sidebar on search and article views. Defaults to the General tab setting.', 'pulsegrid-content-connector' ) ),
					array( 'featured', '0–50', __( 'How many featured articles the landing view shows. 0 hides them.', 'pulsegrid-content-connector' ) ),
				)
			);
			?>

			<h2><code>[pulsegrid_search]</code> &mdash; <?php esc_html_e( 'search box', 'pulsegrid-content-connector' ); ?></h2>
			<p><?php esc_html_e( 'Just the search box, for the home page, a header or a sidebar. Searches open on the Library page.', 'pulsegrid-content-connector' ); ?></p>
			<?php $this->code_box( '[pulsegrid_search placeholder="Search conditions, symptoms, tests…"]' ); ?>
			<?php
			$this->attr_table(
				array(
					array( 'placeholder', __( 'any text', 'pulsegrid-content-connector' ), __( 'Hint text shown in the empty box.', 'pulsegrid-content-connector' ) ),
				)
			);
			?>

			<h2><code>[pulsegrid_topics]</code> &mdash; <?php esc_html_e( 'topic buttons', 'pulsegrid-content-connector' ); ?></h2>
			<p>
				<?php esc_html_e( 'A row of buttons for common topics. Each opens a search or a specific article on the Library page.', 'pulsegrid-content-connector' ); ?>
				<a href="<?php echo esc_url( $tab_url . '&tab=topics' ); ?>"><?php esc_html_e( 'Edit the list on the Topic buttons tab.', 'pulsegrid-content-connector' ); ?></a>
			</p>
			<?php $this->code_box( '[pulsegrid_topics]' ); ?>
			<?php $this->code_box( '[pulsegrid_topics heading="Popular topics" limit="6"]' ); ?>
			<?php $this->code_box( '[pulsegrid_topics topics="Heart attack|heart attack; Colonoscopy|CON-20373087"]' ); ?>
			<?php
			$this->attr_table(
				array(
					array( 'heading', __( 'any text', 'pulsegrid-content-connector' ), __( 'Optional heading above the buttons.', 'pulsegrid-content-connector' ) ),
					array( 'limit', '0–50', __( 'Show only the first N topics. 0 shows all.', 'pulsegrid-content-connector' ) ),
					array( 'topics', 'Label|search; Label|ID', __( 'Use this list instead of the saved one, e.g. a different set on a service-line page. Separate topics with semicolons.', 'pulsegrid-content-connector' ) ),
				)
			);
			?>

			<h2><?php esc_html_e( 'Linking to the library yourself', 'pulsegrid-content-connector' ); ?></h2>
			<p><?php esc_html_e( 'Buttons and menu items can link straight into the library page with these address parameters:', 'pulsegrid-content-connector' ); ?></p>
			<?php
			$lib = PGCC_Frontend::library_url();
			$this->attr_table(
				array(
					array( 'mayo-q', __( 'search terms', 'pulsegrid-content-connector' ), add_query_arg( 'mayo-q', 'colonoscopy', $lib ) ),
					array( 'mayo-id', __( 'content ID', 'pulsegrid-content-connector' ), add_query_arg( 'mayo-id', 'CON-20373087', $lib ) ),
					array( 'mayo-page', __( 'page number', 'pulsegrid-content-connector' ), __( 'Page of search results, with mayo-q.', 'pulsegrid-content-connector' ) ),
				)
			);
			?>

			<h2><?php esc_html_e( 'Changing the layout', 'pulsegrid-content-connector' ); ?></h2>
			<p><?php echo wp_kses( __( 'Colors: fonts, text and links always follow your theme. On the General tab, pick one of your theme’s palette colors (or a custom color) for buttons and badges, or switch “Plugin styling” to layout only and style everything in your theme. You can also override the <code>--pgcc-*</code> CSS custom properties. Markup: copy any file from the plugin’s <code>templates/</code> folder into <code>your-theme/pulsegrid/</code> and edit it there; your copy is used instead and survives plugin updates.', 'pulsegrid-content-connector' ), array( 'code' => array() ) ); ?></p>

			<h2><?php esc_html_e( 'Command line (WP-CLI)', 'pulsegrid-content-connector' ); ?></h2>
			<?php
			$this->attr_table(
				array(
					array( 'wp pulsegrid status', '', __( 'Mode, whether an API key is set, library page, sidebar and topic settings.', 'pulsegrid-content-connector' ) ),
					array( 'wp pulsegrid test', '', __( 'Check the connection to the Mayo Clinic API.', 'pulsegrid-content-connector' ) ),
					array( 'wp pulsegrid cache clear', '', __( 'Empty the content and image cache.', 'pulsegrid-content-connector' ) ),
				)
			);
			?>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------
	 * Assets
	 * ------------------------------------------------------------------ */

	/**
	 * @param string $hook Admin page hook.
	 */
	public function assets( $hook ) {
		if ( 'settings_page_' . self::PAGE !== $hook ) {
			return;
		}
		wp_enqueue_style( 'pgcc-admin', PGCC_URL . 'assets/css/admin.css', array(), PGCC_VERSION );
	}
}
