<?php


abstract class iWorks_PWA {

	protected $configuration = array();

	protected $url;

	protected $debug = false;

	protected $version = '1.6.4';

	protected $root = '';

	private $media_dir_name = 'pwa';

	protected $icons_to_flush;

	protected $option_name_icons;

	/**
	 * iWorks Options object
	 *
	 * @since 1.0.1
	 */
	protected $options;

	/**
	 * End of line
	 *
	 * @since 1.1.1
	 */
	protected $eol = PHP_EOL;

	/**
	 * Check for OG plugin: https://wordpress.org/plugins/og/
	 *
	 * @since 1.2.2
	 */
	protected $is_og_installed = null;

	/**
	 * Settigs cache option name
	 *
	 * @since 1.3.0
	 */
	protected $settings_cache_option_name = 'ipwac';

	/**
	 * option to check meta viewport
	 *
	 * @since 1.5.1
	 */
	protected $option_name_check_meta_viewport = 'iworks_pwa_meta_viewport';

	protected function __construct() {
		$file        = dirname( dirname( __FILE__ ) );
		$this->url   = rtrim( plugin_dir_url( $file ), '/' );
		$this->root  = rtrim( plugin_dir_path( $file ), '/' );
		$this->debug = defined( 'WP_DEBUG' ) && WP_DEBUG;
		/**
		 * version filter
		 *
		 * @since 1.6.2
		 */
		$this->version = apply_filters( 'iworks/pwa/version/', $this->version );
		/**
		 * End of line
		 */
		$this->eol = apply_filters( 'iworks/pwa/eol', $this->eol );
		/**
		 * set options
		 */
		$this->options = get_iworks_pwa_options();
		$this->_set_configuration();
		/**
		 * integrations wiith external plugins
		 *
		 * @since 1.2.0
		 */
		add_action( 'plugins_loaded', array( $this, 'maybe_load_integrations' ) );
		/**
		 * clear cache
		 *
		 * @since 1.3.0
		 */
		add_action( 'load-settings_page_iworks_pwa_index', array( $this, 'action_cache_clear' ) );
		add_action( 'update_option_' . $this->options->get_option_name( 'icon_app' ), array( $this, 'action_cache_clear_icons_manifest' ) );
		add_action( 'update_option_' . $this->options->get_option_name( 'icon_splash' ), array( $this, 'action_cache_clear_icons_manifest' ) );
		add_action( 'update_option_' . $this->options->get_option_name( 'ms_square' ), array( $this, 'action_cache_clear_icons_ms' ) );
		add_action( 'wp_update_nav_menu', array( $this, 'action_cache_clear' ) );
	}

	/**
	 * show no SSL warning
	 *
	 * @since 1.0.0
	 */
	public function show_no_ssl() {
		$file            = $this->root . '/assets/templates/no-ssl.php';
		$args            = array(
			'title'       => __( 'iWorks PWA', 'iworks-pwa' ),
			'url'         => esc_url( _x( 'https://wordpress.org/plugins/iworks-pwa', 'plugins home', 'iworks-pwa' ) ),
			'logo'        => $this->get_logo_url(),
			'classes'     => array(),
			'support_url' => _x( 'https://wordpress.org/support/plugin/iworks-pwa', 'plugins support home', 'iworks-pwa' ),
			'slug'        => 'iworks-pwa',
		);
		$args['classes'] = array(
			'iworks-rate',
			'iworks-rate-' . $args['slug'],
			'iworks-rate-notice',
			'has-logo',
		);
		load_template( $file, true, $args );
	}

	/**
	 * Action handler for 'load-index.php'
	 * Set-up the Dashboard notification.
	 *
	 * @since  1.0.0
	 */
	public function admin_enqueue() {
		wp_enqueue_style(
			__CLASS__,
			plugin_dir_url( __FILE__ ) . 'rate/admin.css',
			array(),
			$this->version
		);
	}

	protected function get_configuration() {
		if ( empty( $this->configuration ) ) {
			$this->_set_configuration();
		}
		return $this->configuration;
	}

	private function _set_configuration() {
		$cache_key = $this->get_cache_name( 'cfg' );
		$value     = get_transient( $cache_key );
		if ( empty( $value ) ) {
			$value = apply_filters(
				'iworks_pwa_configuration',
				array(
					'plugin'           => 'PWA — easy way to Progressive Web App - 1.6.4',
					'id'               => $this->get_configuration_app_id(),
					'name'             => $this->get_configuration_name(),
					'short_name'       => $this->get_configuration_short_name(),
					'description'      => $this->get_configuration_description(),
					'theme_color'      => $this->get_configuration_color_theme(),
					'background_color' => $this->get_configuration_color_background(),
					'orientation'      => $this->get_configuration_orientation(),
					'display'          => $this->get_configuration_display(),
					'scope'            => $this->get_configuration_scope(),
					'start_url'        => apply_filters(
						'iworks_pwa_configuration_start_url',
						add_query_arg(
							array(
								'utm_source'   => 'manifest.json',
								'utm_medium'   => 'plugin',
								'utm_campaign' => 'iworks-pwa',
							),
							'/'
						)
					),
					'splash_pages'     => apply_filters( 'iworks_pwa_configuration_splash_pages', null ),
					'icons'            => $this->get_configuration_icons(),
					'categories'       => $this->get_configuration_categories(),
				)
			);
			if ( ! is_admin() ) {
				set_transient( $cache_key, $value, DAY_IN_SECONDS );
			}
		}
		$this->configuration = apply_filters( 'iworks_pwa_configuration_raw', $value );
	}

	protected function get_icons_base_url() {
		$dir = wp_get_upload_dir();
		return $dir['baseurl'] . '/' . $this->media_dir_name;
	}

	protected function get_icons_directory() {
		$dir = wp_get_upload_dir();
		$dir = $dir['basedir'] . '/' . $this->media_dir_name;
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		return $dir;
	}

	protected function image_resize_and_save( $image, $width, $destfilename ) {
		$image->resize( $width, $width );
		if ( is_file( $destfilename ) ) {
			unlink( $destfilename );
		}
		return $image->save( $destfilename );
	}

	protected function get_image_ext_from_attachement_id( $attachement_id ) {
		return pathinfo( wp_get_original_image_path( $attachement_id ), PATHINFO_EXTENSION );
	}

	protected function get_wp_image_object_from_attachement_id( $attachement_id ) {
		$path  = wp_get_original_image_path( $attachement_id );
		$args  = array(
			'path'      => $path,
			'mime_type' => get_post_mime_type( $attachement_id ),
		);
		$image = wp_get_image_editor( $path, $args );
		if ( ! is_wp_error( $image ) ) {
			$size = min( $image->get_size() );
			$image->resize( $size, $size, true );
		}
		return $image;
	}

	/**
	 * Defaults icon set
	 *
	 * @since 1.3.3
	 */
	private function get_defaults_icons() {
		$root  = sprintf( '%s/assets/images/icons/favicon', $this->url );
		$icons = apply_filters(
			'iworks_pwa_configuration_icons',
			array(
				array(
					'src'     => sprintf( '%s/android-icon-36x36.png', $root ),
					'sizes'   => '36x36',
					'type'    => 'image/png',
					'density' => '0.75',
				),
				array(
					'src'     => sprintf( '%s/android-icon-48x48.png', $root ),
					'sizes'   => '48x48',
					'type'    => 'image/png',
					'density' => '1.0',
				),
				array(
					'src'     => sprintf( '%s/android-icon-72x72.png', $root ),
					'sizes'   => '72x72',
					'type'    => 'image/png',
					'density' => '1.5',
				),
				array(
					'src'     => sprintf( '%s/android-icon-96x96.png', $root ),
					'sizes'   => '96x96',
					'type'    => 'image/png',
					'density' => '2.0',
				),
				array(
					'src'     => sprintf( '%s/android-icon-144x144.png', $root ),
					'sizes'   => '144x144',
					'type'    => 'image/png',
					'density' => '3.0',
				),
				array(
					'src'     => sprintf( '%s/android-icon-192x192.png', $root ),
					'sizes'   => '192x192',
					'type'    => 'image/png',
					'density' => '4.0',
				),
				array(
					'src'   => sprintf( '%s/android-icon-512x512.png', $root ),
					'sizes' => '512x512',
					'type'  => 'image/png',
				),
				array(
					'src'     => sprintf( '%s/maskable.png', $root ),
					'sizes'   => '1024x1024',
					'type'    => 'image/png',
					'purpose' => 'any maskable',
				),
			)
		);
		return $icons;
	}

	protected function get_configuration_icons( $group = 'manifest' ) {
		$value = intval( $this->options->get_option( 'icon_app' ) );
		if ( empty( $value ) ) {
			return $this->get_defaults_icons();
		}
		/**
		 * Handle cache
		 *
		 * @since 1.3.0
		 */
		$cache_key = $this->get_cache_name( $group );
		/**
		 * cache get
		 */
		$icons = get_transient( $cache_key );
		if ( ! empty( $icons ) ) {
			return apply_filters( 'iworks_pwa_configuration_icons', $icons );
		}
		$icons_option_name = 'icons_' . $group;
		$icons             = $this->options->get_option( $icons_option_name );
		$root              = $this->get_icons_directory();
		if ( ! empty( $icons ) ) {
			$icons = $this->maybe_add_purpose_maskable( $icons );
			/**
			 * Handle cache
			 *
			 * @since 1.3.0
			 */
			set_transient( $cache_key, $icons, DAY_IN_SECONDS );
			return apply_filters( 'iworks_pwa_configuration_icons', $icons );
		}
		if ( 0 < $value ) {
			$image = $this->get_wp_image_object_from_attachement_id( $value );
			if ( ! is_wp_error( $image ) ) {
				$size         = min( $image->get_size() );
				$ext          = $this->get_image_ext_from_attachement_id( $value );
				$config_icons = $this->options->get_group( 'icons' );
				krsort( $config_icons );
				foreach ( $config_icons as $width => $data ) {
					$width = intval( $width );
					if ( $width > $size ) {
						continue;
					}
					if ( ! in_array( $group, $data['group'] ) ) {
						continue;
					}
					$name         = sprintf( 'icon-pwa-%s.%s', $width, $ext );
					$destfilename = $this->get_icons_directory() . '/' . $name;
					$result       = $this->image_resize_and_save( $image, $width, $destfilename );
					if ( ! is_wp_error( $result ) ) {
						$one             = $data;
						$one['src']      = sprintf(
							'%s/%s?v=%s',
							$this->get_icons_base_url(),
							$name,
							$value
						);
						$icons[ $width ] = $one;
					}
				}
			}
		}
		if ( empty( $icons ) ) {
			delete_transient( $cache_key );
		} else {
			$icons = $this->maybe_add_purpose_maskable( $icons );
			$this->options->update_option( $icons_option_name, $icons );
			/**
			 * Handle cache
			 *
			 * @since 1.3.0
			 */
			set_transient( $cache_key, $icons, DAY_IN_SECONDS );
			return apply_filters( 'iworks_pwa_configuration_icons', $icons );
		}
		/**
		 * defaults for empty, only for basic
		 */
		if ( 'manifest' !== $group ) {
			return apply_filters( 'iworks_pwa_configuration_icons', array() );
		}
		$icons = $this->get_defaults_icons();
		/**
		 * Handle cache
		 *
		 * @since 1.3.0
		 */
		set_transient( $cache_key, $icons, MINUTE_IN_SECONDS );
		return apply_filters( 'iworks_pwa_configuration_icons', $icons );
	}

	protected function get_name( $name ) {
		return preg_replace( '/_/', '-', strtolower( $name ) );
	}

	/**
	 * get background color (meta: theme-color)
	 *
	 * @since 0.0.2
	 */
	protected function get_configuration_color_background() {
		$color = $this->options->get_option( 'color_bg' );
		if ( empty( $color ) ) {
			$color = get_theme_mod( 'background_color', 'f0f0f0' );
			if ( preg_match( '/^[0-9a-f]+$/', $color ) ) {
				$color = '#' . $color;
			}
		}
		return apply_filters( 'iworks_pwa_configuration_background_color', $color );
	}

	/**
	 * get color theme
	 */
	protected function get_configuration_color_theme() {
		$color = $this->options->get_option( 'color_theme' );
		if ( empty( $color ) ) {
			$color = $this->get_configuration_color_background();
		}
		return apply_filters( 'iworks_pwa_configuration_theme_color', $color );
	}

	/**
	 * get application name
	 */
	protected function get_configuration_name() {
		$value = $this->options->get_option( 'app_name' );
		if ( empty( $value ) ) {
			$value = substr( get_bloginfo( 'name' ), 0, 45 );
		}
		return apply_filters( 'iworks_pwa_configuration_name', $value );
	}

	/**
	 * get application short name
	 */
	protected function get_configuration_short_name() {
		$value = $this->options->get_option( 'app_short_name' );
		if ( empty( $value ) ) {
			$value = substr( get_bloginfo( 'name' ), 0, 15 );
		}
		return apply_filters( 'iworks_pwa_configuration_short_name', $value );
	}

	/**
	 * get application description
	 */
	protected function get_configuration_description() {
		$value = $this->options->get_option( 'app_description' );
		if ( empty( $value ) ) {
			$value = get_bloginfo( 'description' );
		}
		return apply_filters( 'iworks_pwa_configuration_description', $value );
	}

	protected function get_configuration_orientation() {
		$value = $this->options->get_option( 'app_orientation' );
		if ( empty( $value ) ) {
			$value = 'portrait';
		}
		$options = $this->options->get_values( 'app_orientation' );
		if ( ! array_key_exists( $value, $options ) ) {
			$value = 'portrait';
		}
		return apply_filters( 'iworks_pwa_configuration_orientation', $value );
	}

	protected function get_configuration_display() {
		$value = $this->options->get_option( 'app_display' );
		if ( empty( $value ) ) {
			$value = 'standalone';
		}
		$options = $this->options->get_values( 'app_display' );
		if ( ! array_key_exists( $value, $options ) ) {
			$value = 'standalone';
		}
		return apply_filters( 'iworks_pwa_configuration_display', $value );
	}

	/**
	 * Plugin logo for rate messages
	 *
	 * @since 1.0.0
	 *
	 * @param string $logo Logo, can be empty.
	 * @param object $plugin Plugin basic data.
	 */
	public function filter_plugin_logo( $logo, $plugin ) {
		if ( is_object( $plugin ) ) {
			$plugin = (array) $plugin;
		}
		if ( 'iworks-pwa' === $plugin['slug'] ) {
			return $this->get_logo_url();
		}
		return $logo;
	}

	/**
	 * get logo url
	 *
	 * @since 1.0.0
	 */
	private function get_logo_url() {
		return plugin_dir_url( dirname( dirname( __FILE__ ) ) ) . 'assets/images/icon.svg';
	}

	/**
	 * maybe load integrations
	 *
	 * @since 1.2.0
	 */
	public function maybe_load_integrations() {
		$plugins = get_option( 'active_plugins' );
		if ( empty( $plugins ) ) {
			return;
		}
		$root = dirname( __file__ ) . '/pwa';
		include_once $root . '/class-iworks-pwa-integrations.php';
		$root .= '/integrations';
		foreach ( $plugins as $plugin ) {
			/**
			 * WPML
			 * https://wpml.org
			 *
			 * @since 1.2.0
			 */
			if ( preg_match( '/sitepress\.php$/', $plugin ) ) {
				include_once $root . '/class-iworks-pwa-integrations-wpml.php';
				new iWorks_PWA_Integrations_WPML( $this->options );
			}
			/**
			 * OG — Better Share on Social Media
			 * https://wordpress.org/plugins/og/
			 *
			 * @since 1.2.2
			 */
			if ( preg_match( '/og\.php$/', $plugin ) ) {
				include_once $root . '/class-iworks-pwa-integrations-og.php';
				new iWorks_PWA_Integrations_OG( $this->options );
			}
			/**
			 * Menu Icons by ThemeIsle
			 * https://wordpress.org/plugins/menu-icons/
			 *
			 * @since 1.4.0
			 */
			if ( preg_match( '/menu-icons\.php$/', $plugin ) ) {
				include_once $root . '/class-iworks-pwa-integrations-menu-icons.php';
				new iWorks_PWA_Integrations_Menu_Icons( $this->options );
			}
		}
	}

	protected function get_configuration_offline_page_content() {
		$content = apply_filters(
			'iworks_pwa_configuration_offline_page_content',
			$this->options->get_option( 'offline_content' )
		);
		if ( empty( $content ) ) {
			$content  = '';
			$content .= __( 'We were unable to load the page you requested.', 'iworks-pwa' );
			$content .= PHP_EOL;
			$content .= PHP_EOL;
			$content .= __( 'Please check your network connection and try again.', 'iworks-pwa' );
		}
		$content = wpautop( $content );
		return apply_filters( 'iworks_pwa_offline_content', $content );
	}

	/**
	 * check for OG plugin
	 *
	 * @since 1.2.2
	 */
	public function check_og_plugin() {
		if ( null !== $this->is_og_installed ) {
			return;
		}
		$this->is_og_installed = false;
		$plugins               = get_option( 'active_plugins' );
		if ( empty( $plugins ) ) {
			return;
		}
		foreach ( $plugins as $plugin ) {
			if ( preg_match( '/og\.php$/', $plugin ) ) {
				$this->is_og_installed = true;
				return;
			}
		}
	}

	public function action_flush_icons( $old_value, $value, $option ) {
		delete_option( $this->options->get_option_name( $this->option_name_icons ) );
	}

	/**
	 * Try to add purpose "any maskable" if it is not present.
	 *
	 * @since 1.2.2
	 */
	private function maybe_add_purpose_maskable( $icons ) {
		$attachement_id = intval( $this->options->get_option( 'icon_splash' ) );
		if ( ! empty( $attachement_id ) ) {
			$value = wp_get_attachment_image_src( $attachement_id, 'full' );
			if ( is_array( $value ) ) {
				$icons[ $attachement_id ] = array(
					'sizes'   => sprintf( '%dx%d', $value[1], $value[2] ),
					'type'    => get_post_mime_type( $attachement_id ),
					'group'   => array( 'manifest' ),
					'src'     => $value[0],
					'purpose' => 'any maskable',
				);
				return $icons;
			}
		}
		$max = 0;
		foreach ( $icons as $size => $icon ) {
			if ( isset( $icon['purpose'] ) && preg_match( '/maskable/', $icon['purpose'] ) ) {
				return $icons;
			}
			if ( $size > $max ) {
				$max = $size;
			}
		}
		if ( 0 < $max ) {
			$icons[ $max ]['purpose'] = 'any maskable';
		}
		return $icons;
	}

	/**
	 * clear cache
	 *
	 * @since 1.3.0
	 */
	public function action_cache_clear() {
		$keys = array(
			'cfg',
			'head_microsoft',
		);
		foreach ( $keys as $key ) {
			delete_transient( $this->get_cache_name( $key ) );
		}
	}

	private function clear_icon_cache( $key ) {
		/**
		 * general configuration cache
		 */
		delete_transient( $this->get_cache_name( 'cfg' ) );
		/**
		 * cache for key
		 */
		delete_transient( $this->get_cache_name( $key ) );
		/**
		 * option
		 */
		$cache_key = $this->options->get_option_name( 'icons_' . $key );
		delete_transient( $cache_key );
		delete_option( $cache_key );
	}

	public function action_cache_clear_icons_manifest() {
		$this->clear_icon_cache( 'manifest' );
	}

	public function action_cache_clear_icons_ms() {
		$this->clear_icon_cache( 'windows8' );
		$this->clear_icon_cache( 'ie11' );
		/**
		 * Clear cache for html head microsoft
		 *
		 * @since 1.4.3
		 */
		delete_transient( $this->get_cache_name( 'head_microsoft' ) );
	}

	/**
	 * get configuration application ID
	 *
	 * @since 1.5.3
	 */
	protected function get_configuration_app_id() {
		$app_id = '';
		if ( empty( $this->configuration ) ) {
			$app_id = md5( __CLASS__ );
		} else {
			$app_id = md5( serialize( $this->configuration ) );
		}
		return add_query_arg( 'app_id', $app_id, home_url() );
	}

	/**
	 * get cache key, depent of version
	 *
	 * @since 1.6.2
	 */
	private function get_cache_name( $name ) {
		return sprintf(
			'%s/%s/%s',
			$this->settings_cache_option_name,
			$name,
			'1.6.4' === $this->version ? crc32( time() ) : $this->version
		);
	}

	/**
	 * get configuration scope
	 *
	 * @since 1.6.2
	 */
	protected function get_configuration_scope() {
		$value = $this->options->get_option( 'app_scope' );
		if ( 'current-site' === $value ) {
			return apply_filters( 'iworks_pwa_configuration_scope', get_home_url() . '/' );
		}
		return apply_filters( 'iworks_pwa_configuration_scope', '/' );
	}

	/**
	 * get configuration categories
	 *
	 * @since 1.6.3
	 */
	protected function get_configuration_categories() {
		$value = $this->options->get_option( 'categories' );
		if ( is_array( $value ) ) {
			$value = array_values( $value );
		} else {
			$value = null;
		}
		return apply_filters(
			'iworks/pwa/configuration/categories',
			$value
		);
	}

}
