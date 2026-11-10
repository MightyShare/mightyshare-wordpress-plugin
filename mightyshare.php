<?php
/**
 * Plugin Name: MightyShare - Auto-Generated Social Media Images
 * Plugin URI: https://mightyshare.io/wordpress/
 * Description: Automatically generate social share preview images with MightyShare!
 * Version: 1.5.1
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: mightyshare
 * Author: MightyShare
 * Author URI: https://mightyshare.io
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'MIGHTYSHARE_VERSION', '1.5.1' );
define( 'MIGHTYSHARE_DIR_URL', plugin_dir_url( __FILE__ ) );
define( 'MIGHTYSHARE_DIR_URI', plugin_dir_path( __FILE__ ) );
define( 'MIGHTYSHARE_TEMPLATES_API', 'https://mightyshare.io/api/templates' );

// Translations load automatically from translate.wordpress.org (WP 4.6+).
add_action( 'init', function() {
	// Meta boxes (and their nonce-gated save handlers) only do work on admin
	// requests — skip building the definitions on the frontend.
	if ( ! is_admin() ) {
		return;
	}

	$mightyshare_metaboxes = apply_filters( 'mightyshare_register_metaboxes', array() );

	if ( $mightyshare_metaboxes && is_array( $mightyshare_metaboxes ) ) {
		require_once( MIGHTYSHARE_DIR_URI . '/inc/admin/includes/class-mightyshare-meta-boxes.php' );

		foreach ( $mightyshare_metaboxes as $metabox ) {
			new Mightyshare_Meta_Boxes( $metabox );
		}
	}
});

/**
 * Per-request cached read of the 'mightyshare' option.
 *
 * Avoids repeated get_option() calls scattered across the plugin.
 */
function mightyshare_get_options() {
	static $options = null;

	if ( null === $options ) {
		$options = get_option( 'mightyshare' );
	}

	return $options;
}

/**
 * Recursively sanitize submitted settings. Every stored value is a plain
 * string (keys, template slugs, hex colors, attachment IDs, font names).
 */
function mightyshare_sanitize_option_values( $value ) {
	if ( is_array( $value ) ) {
		return array_map( 'mightyshare_sanitize_option_values', $value );
	}

	return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
}

/**
 * Template IDs are slugs. Keeps case and dots (for templates added through the
 * mightyshare_templates filter) but drops anything that could break out of HTML.
 */
function mightyshare_sanitize_template( $template ) {
	return preg_replace( '/[^A-Za-z0-9_.\-]/', '', (string) $template );
}

/**
 * Look up the plan for an API key. Memoized per request: the settings
 * sanitize callback can run twice on one save (add_option + update_option).
 *
 * @return array|WP_Error Plan fields to merge into the options.
 */
function mightyshare_validate_api_key( $api_key ) {
	static $results = array();

	if ( isset( $results[ $api_key ] ) ) {
		return $results[ $api_key ];
	}

	$response = wp_remote_post(
		'https://api.mightyshare.io/validate-key/',
		array(
			'timeout' => 5,
			'body'    => wp_json_encode( array( 'apikey' => $api_key ) ),
			'headers' => array( 'Content-Type' => 'application/json' ),
		)
	);

	if ( is_wp_error( $response ) ) {
		$results[ $api_key ] = $response;
		return $response;
	}

	$body = json_decode( wp_remote_retrieve_body( $response ) );
	if ( ! is_object( $body ) ) {
		$results[ $api_key ] = new WP_Error( 'mightyshare_invalid_response', __( 'Unexpected response from MightyShare.', 'mightyshare' ) );
		return $results[ $api_key ];
	}

	$plan = array();
	if ( isset( $body->plan_type ) ) {
		$plan['plan_type'] = sanitize_text_field( $body->plan_type );
	}
	if ( isset( $body->message ) ) {
		$plan['plan_message'] = sanitize_text_field( $body->message );
	}
	if ( isset( $body->type ) ) {
		$plan['plan_response_type'] = sanitize_key( $body->type );
	}

	set_transient( 'mightyshare_plan_checked', 1, 12 * HOUR_IN_SECONDS );
	$results[ $api_key ] = $plan;

	return $plan;
}

/**
 * Re-check the plan when the settings page is opened (at most every 12 hours)
 * so an upgrade unlocks paid features without re-saving the key, and a key
 * saved during a timeout eventually gets its plan.
 */
function mightyshare_refresh_plan() {
	if ( ! isset( $_GET['page'] ) || 'mightyshare' !== sanitize_key( wp_unslash( $_GET['page'] ) ) || ! current_user_can( 'manage_options' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen detection.
		return;
	}

	$options = get_option( 'mightyshare' );
	if ( empty( $options['mightyshare_api_key'] ) || get_transient( 'mightyshare_plan_checked' ) ) {
		return;
	}

	$plan = mightyshare_validate_api_key( trim( $options['mightyshare_api_key'] ) );
	if ( is_wp_error( $plan ) ) {
		// Don't retry on every page view while the API is unreachable.
		set_transient( 'mightyshare_plan_checked', 1, HOUR_IN_SECONDS );
		return;
	}

	if ( array_diff_assoc( $plan, $options ) ) {
		update_option( 'mightyshare', array_merge( $options, $plan ) );
	}
}
add_action( 'admin_init', 'mightyshare_refresh_plan', 20 );

/**
 * Add a "Settings" link on the plugins screen row.
 */
function mightyshare_add_plugin_page_settings_link( $links ) {
	$links[] = '<a href="' . esc_url( menu_page_url( 'mightyshare', false ) ) . '">' . esc_html__( 'Settings', 'mightyshare' ) . '</a>';

	return $links;
}

/**
 * Register the per-post MightyShare metabox via the
 * `mightyshare_register_metaboxes` filter.
 */
function mightyshare_metaboxes( $metaboxes ) {
	$options          = mightyshare_get_options();
	$default_enabled  = '';
	$default_template = '';

	if ( ! empty( $options ) ) {
		$default_enabled  = ' (' . __( 'Disabled', 'mightyshare' ) . ')';
		$default_template = isset( $options['default_template'] ) ? $options['default_template'] : '';

		global $pagenow;
		$post_type = '';
		if ( isset( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen detection.
			$post_type = get_post_type( absint( wp_unslash( $_GET['post'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen detection.
		} elseif ( 'post-new.php' === $pagenow ) {
			$post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : 'post'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen detection.
		}

		if ( $post_type && ! empty( $options['enabled_on']['post_types'] ) && in_array( $post_type, $options['enabled_on']['post_types'], true ) ) {
			$default_enabled = ' (' . __( 'Enabled', 'mightyshare' ) . ')';
		}

		if ( $post_type && ! empty( $options['post_type_overwrites']['post_types'][ $post_type ]['template'] ) ) {
			$default_template = $options['post_type_overwrites']['post_types'][ $post_type ]['template'];
		}
	}

	$mightyshare_globals   = new Mightyshare_Globals();
	$template_options      = $mightyshare_globals->mightyshare_templates();
	$default_label         = isset( $template_options[ $default_template ] ) ? $template_options[ $default_template ] : $default_template;
	$post_template_options = array( '' => __( 'Default', 'mightyshare' ) . ( $default_label ? ' (' . $default_label . ')' : '' ) );

	foreach ( $template_options as $key => $value ) {
		$post_template_options[ $key ] = $value;
	}

	$post_types = get_post_types( array( 'public' => true ), 'names' );

	$metaboxes[] = array(
		'id'        => 'mightyshare',
		'name'      => 'MightyShare Options',
		'post_type' => $post_types,
		'fields'    => array(
			array(
				'id'    => 'mightyshare_preview',
				'label' => __( 'Preview', 'mightyshare' ),
				'type'  => 'preview',
			),
			array(
				'id'          => 'mightyshare_enabled',
				'label'       => __( 'Enable MightyShare?', 'mightyshare' ),
				'type'        => 'select',
				'options'     => array(
					''      => __( 'Default', 'mightyshare' ) . $default_enabled,
					'false' => __( 'Disabled', 'mightyshare' ),
					'true'  => __( 'Enabled', 'mightyshare' ),
				),
				'description' => '<a href="https://socialmediasharepreview.com/" rel="nofollow noopener" target="_blank">' . __( 'Open Graph Tester', 'mightyshare' ) . '</a>',
			),
			array(
				'id'       => 'mightyshare_template',
				'label'    => __( 'Template', 'mightyshare' ),
				'class'    => 'mightyshare_template_field',
				'type'     => 'select',
				'options'  => $post_template_options,
				'default_template' => $default_template,
				'modal'    => __( 'Template Options', 'mightyshare' ),
				'modal_id' => 'mightyshare-template-options-post',
			),
			array(
				'id'          => 'mightyshare_title',
				'label'       => __( 'Title Override', 'mightyshare' ),
				'class'       => 'large-text',
				'type'        => 'text',
				'options'     => $post_template_options,
				'description' => __( 'Optional. Use a different title in the share image than the post title.', 'mightyshare' ),
			),
		),
	);

	$setting_prefix = array(
		'name'  => 'mightyshare',
		'value' => $options,
	);

	$mightyshare_template_display_options = $mightyshare_globals->mightyshare_template_options( 'post', $setting_prefix );

	foreach ( $mightyshare_template_display_options as $key => $template_options ) {
		if ( $key === array_key_first( $mightyshare_template_display_options ) ) {
			$template_options['field_options']['modal_title']     = __( 'Template Options', 'mightyshare' );
			$template_options['field_options']['modal_before_id'] = 'mightyshare-template-options-post';
		}
		if ( $key === array_key_last( $mightyshare_template_display_options ) ) {
			$template_options['field_options']['modal_after_end'] = true;
		}
		$template_options['field_options']['label'] = $template_options['label'];
		$template_options['field_options']['type']  = str_replace( array( 'render_mightyshare_', '_field' ), '', $template_options['field_type'] );
		$template_options['field_options']['id']    = str_replace( array( '[', ']' ), array( '_', '' ), $template_options['field_options']['id'] );

		if ( 'background' === $key ) {
			$template_options['field_options']['label']       = __( 'Image', 'mightyshare' );
			$template_options['field_options']['description'] = __( 'Optional. Uses the featured image when empty.', 'mightyshare' );
		}

		$metaboxes[0]['fields'][] = $template_options['field_options'];
	}

	return $metaboxes;
}

//MightyShare plugin option pages.
class Mightyshare_Plugin_Options {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'in_admin_header', array( $this, 'admin_header' ) );
		add_action( 'admin_init', array( $this, 'init_settings' ) );

		$mightyshare_options = mightyshare_get_options();
		if ( ! is_array( $mightyshare_options ) || empty( $mightyshare_options['mightyshare_api_key'] ) ) {
			add_action( 'admin_notices', array( $this, 'setup_mightyshare_message' ) );
		}

		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'mightyshare_add_plugin_page_settings_link' );

		// Post meta boxes.
		require_once MIGHTYSHARE_DIR_URI . '/inc/admin/mightyshare-metaboxes.php';

		add_filter( 'mightyshare_register_metaboxes', 'mightyshare_metaboxes' );
	}

	public function setup_mightyshare_message() {
		global $pagenow;
		if ( ! current_user_can( 'manage_options' ) || ! in_array( $pagenow, array( 'index.php', 'plugins.php', 'options-general.php' ), true ) ) {
			return;
		}
		?>
		<?php /* translators: %s: URL of the MightyShare settings page */ ?>
		<div class='mightyshare notice notice-success'><p><?php echo wp_kses_post( sprintf( __( 'Thanks for installing <strong>MightyShare</strong>! Add your API key in <a href="%s">Settings &gt; MightyShare</a> to start generating share images.', 'mightyshare' ), menu_page_url( 'mightyshare', false ) ) ); ?></p></div>
		<?php
	}

	public function add_admin_menu() {
		add_options_page(
			esc_html__( 'MightyShare', 'mightyshare' ),
			esc_html__( 'MightyShare', 'mightyshare' ),
			'manage_options',
			'mightyshare',
			array( $this, 'page_layout' )
		);
	}

	function mightyshare_merge_options( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		$data = mightyshare_sanitize_option_values( $data );

		// Template values are slugs; the admin UI puts them in image URLs.
		if ( isset( $data['default_template'] ) ) {
			$data['default_template'] = mightyshare_sanitize_template( $data['default_template'] );
		}
		if ( ! empty( $data['post_type_overwrites'] ) && is_array( $data['post_type_overwrites'] ) ) {
			foreach ( $data['post_type_overwrites'] as $group => $types ) {
				foreach ( (array) $types as $type => $type_options ) {
					if ( isset( $type_options['template'] ) ) {
						$data['post_type_overwrites'][ $group ][ $type ]['template'] = mightyshare_sanitize_template( $type_options['template'] );
					}
				}
			}
		}

		if ( ! empty( $data['mightyshare_api_key'] ) ) {
			$data['mightyshare_api_key'] = trim( $data['mightyshare_api_key'] );
			$plan                        = mightyshare_validate_api_key( $data['mightyshare_api_key'] );

			if ( is_wp_error( $plan ) ) {
				add_settings_error(
					'mightyshare',
					'mightyshare_api_key_error',
					sprintf(
						/* translators: %s: error message */
						esc_html__( 'Unable to fetch API Key details: %s', 'mightyshare' ),
						esc_html( $plan->get_error_message() )
					),
					'error'
				);
			} else {
				$data = array_merge( $data, array_fill_keys( array( 'plan_type', 'plan_message', 'plan_response_type' ), '' ), $plan );
			}
		}

		$existing = get_option( 'mightyshare' );

		if ( ! is_array( $existing ) ) {
			return $data;
		}

		if ( array_key_exists( 'mightyshare_api_key', $data ) && '' === $data['mightyshare_api_key'] ) {
			unset( $existing['plan_type'], $existing['plan_message'], $existing['plan_response_type'] );
		}

		$page = isset( $data['current_settings_page_name'] ) ? $data['current_settings_page_name'] : '';

		if ( 'options' === $page ) {
			$checkboxes = array(
				'enable_mightyshare',
				'enable_description',
				'output_opengraph',
				'enable_archives',
			);

			foreach ( $checkboxes as $checkbox ) {
				if ( empty( $data[ $checkbox ] ) ) {
					unset( $existing[ $checkbox ] );
				}
			}
		}

		// Both settings tabs render every "enabled on" checkbox, and unchecked
		// boxes aren't submitted, so a missing key means "none checked" rather
		// than "keep the old value".
		if ( in_array( $page, array( 'options', 'post-types' ), true ) && ! isset( $data['enabled_on'] ) ) {
			$data['enabled_on'] = array();
		}

		return array_merge( $existing, $data );
	}

	public function init_settings() {

		require_once( MIGHTYSHARE_DIR_URI . '/inc/admin/mightyshare-metaboxes.php' );

		register_setting(
			'mightyshare',
			'mightyshare',
			array( &$this, 'mightyshare_merge_options' )
		);

		add_settings_section(
			'general',
			__( 'General', 'mightyshare' ),
			false,
			'mightyshare'
		);

		add_settings_field(
			'enable_mightyshare',
			__( 'Enable MightyShare', 'mightyshare' ),
			array( $this, 'render_enable_mightyshare_field' ),
			'mightyshare',
			'general'
		);
		add_settings_field(
			'mightyshare_api_key',
			__( 'MightyShare API Key', 'mightyshare' ),
			array( $this, 'render_mightyshare_api_key_field' ),
			'mightyshare',
			'general',
			array( 'label_for' => 'mightyshare_api_key_field' )
		);
		add_settings_field(
			'prewarm',
			__( 'Pre-generate on Publish', 'mightyshare' ),
			array( $this, 'render_prewarm_field' ),
			'mightyshare',
			'general'
		);

		add_settings_section(
			'display',
			__( 'Default Display', 'mightyshare' ),
			false,
			'mightyshare'
		);

		add_settings_field(
			'enabled_post_types',
			__( 'Enabled by Default On', 'mightyshare' ),
			array( $this, 'render_post_types_field' ),
			'mightyshare',
			'display'
		);

		add_settings_field(
			'enable_archives',
			__( 'Homepage & Archives', 'mightyshare' ),
			array( $this, 'render_enable_archives_field' ),
			'mightyshare',
			'display'
		);

		add_settings_field(
			'default_template',
			__( 'Default Template', 'mightyshare' ),
			array( $this, 'render_default_template_field' ),
			'mightyshare',
			'display',
			array( 'label_for' => 'mightyshare_default_template' )
		);

		$options = get_option( 'mightyshare' );
		$setting_prefix = array(
			'name'  => 'mightyshare',
			'value' => $options,
		);

		$mightyshare_globals                  = new Mightyshare_Globals();
		$mightyshare_template_display_options = $mightyshare_globals->mightyshare_template_options( 'default', $setting_prefix );

		if ( ! empty( $mightyshare_template_display_options ) ) {
			add_settings_section(
				'mightyshare_template_default_modal',
				__( 'Default', 'mightyshare' ),
				false,
				'mightyshare'
			);
		}

		$this->mightyshare_register_settings_fields( $mightyshare_template_display_options, 'mightyshare_template_default_modal' );

		add_settings_section(
			'opengraph',
			__( 'Open Graph Settings', 'mightyshare' ),
			false,
			'mightyshare'
		);

		add_settings_field(
			'detected_seo_plugin',
			__( 'Detected SEO Plugin', 'mightyshare' ),
			array( $this, 'render_detected_seo_plugin_field' ),
			'mightyshare',
			'opengraph'
		);

		add_settings_field(
			'opengraph',
			__( 'Enable Open Graph', 'mightyshare' ),
			array( $this, 'render_opengraph_field' ),
			'mightyshare',
			'opengraph'
		);
	}

	public function admin_header() {
		global $pagenow;
		$current_page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen detection.
		if ( ! ( ! empty( $pagenow ) && ( 'post.php' === $pagenow || 'post-new.php' === $pagenow ) ) && 'mightyshare' !== $current_page ) {
			return;
		}
		?>
		<?php add_thickbox(); ?>
		<script>document.addEventListener('DOMContentLoaded', function () {tb_init('a.thickbox')});</script>
		<div id="mightyshare-template-picker" style="display:none;">
			<div>
				<h2><?php esc_html_e( 'Select Template', 'mightyshare' ); ?></h2>
				<div class="mightyshare-template-picker" id="mightyshare-template-picker-modal" data-pickerfor="">
				<?php
				$mightyshare_globals = new Mightyshare_Globals();
				$template_options    = $mightyshare_globals->mightyshare_templates();
				foreach ( $template_options as $key => $template ) {
					?>
					<div class="template-block" role="button" tabindex="0" data-mightysharetemplate="<?php echo esc_attr( $key ); ?>">
						<img src="<?php echo esc_url( 'https://api.mightyshare.io/template/preview/' . $key . '.jpeg' ); ?>" loading="lazy" alt="">
						<div class="title"><?php echo esc_html( $template ); ?></div>
					</div>
					<?php
				}
				?>
				</div>
			</div>
		</div>
		<?php
		if ( 'mightyshare' !== $current_page ) {
			return;
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen detection.
		$tab = in_array( $tab, array( 'options', 'post-types' ), true ) ? $tab : 'options';

		?>
		<div id="mightyshare-admin-header"><div id="mightyshare-page-title"><svg style="width:185px;height:60px;" xmlns="http://www.w3.org/2000/svg" width="1268" height="234" viewBox="0 0 1268 234"><g><g><path fill="#fff" d="M145.893 92.496c1.512 29.807.59 81.268.295 89.055-.331 9.113-1.665 34.785-2.157 39.86-.59 8.844-2.703 11.878-10.616 12.084-8.155.331-18.923-.823-27.033-5.138-2.265-1.208-3.59-2.882-3.93-5.496-.545-4.189-.527-8.396-.08-12.55 2.104-19.423 2.399-38.928 2.614-58.415.233-20.785 1.092-40.28-.94-61.11-1.056-7.77-5.962.869-11.681 25.198-2.274 7.617-4.45 16.228-6.311 19.603-4.19 7.86-14.465 8.325-18.511 1.056-2.14-3.849-3.59-9.005-4.628-13.265-2.56-10.464-6.275-20.069-9.273-30.399-1.63-5.138-5.443-5.773-6.096-2.434-1.262 7.778-.555 21.482-.671 38.212-.269 7.42 2.515 68.96 3.526 78.645 2.39 25.252 2.39 25.252-13.525 25.887-7.465.305-21.322-.841-23.29-1.128-7.055-1.011-9.955-3.508-10.304-15.288-.027-3.464-1.557-28.017-2.166-51.138C.033 141.79.516 91.753.982 79.732c.644-16.891 2.426-35.34 4.198-54.209C7.346 9.151 7.346 9.151 17.658 6.162c9.658-2.543 18.224-1.415 24.123-1.083C53.731 6.85 55.871 7.2 58.977 13.94c2.032 4.888 4.968 15.011 6.803 20.006 6.83 20.865 9.604 27.069 11.833 21 .716-2.363 3.16-10.616 4.207-14.385 3.867-12.567 3.482-15.888 9.247-29.091 1.315-3.008 3.204-6.758 9.425-7.296 8.692-1.181 17.42-1.817 26.129-.447 8.987.895 10.32 2.596 12.308 12.755 4.914 24.42 5.934 60.484 6.964 76.014zm55.196-23.255c.314 5.353.672 15.539.68 19.021.054 17.723.457 26.898-.116 44.604-.546 16.801-.644 35.33-1.495 52.105-.644 12.657-.465 23.56-1.396 36.198-.385 5.192-1.343 7.108-7.984 9.14-7.269 1.575-13.186 2.067-21.806 1.727-7.286.108-7.653-4.762-8.817-9.488-1.36-5.514-2.953-15.888-3.383-21.555-2.005-26.603-3.643-46.394-3.536-73.113.099-4.306.358-12.899.233-18.368-.313-14.044 1.316-27.937 2.864-41.847 1.155-10.294 1.773-12.236 8.719-13.444 3.723-.645 10.992-.61 14.751-.887 4.252-.313 10.473-.152 15.495.359 4.628.205 5.487 3.473 5.791 15.548zm-42.035-34.695c-5.03-9.667-4.072-16.076 3.733-23.363 11.09-10.365 30.031-8.056 38.437 4.753 2.032 3.097 3.535 6.499 3.106 10.151.08 8.056-4.216 13.185-10.187 16.408-7.868 4.251-16.237 5.513-24.714 1.181-4.216-2.157-8.02-4.628-10.375-9.13zm167.718 69.614c.877 13.105.725 26.191.957 39.278.063 26.164 0 57.807-18.985 74.993-15.217 14.975-43.199 16.667-60.886 9.56-8.226-3.697-15.262-7.958-21.68-16.873-5.2-8.065-7.617-12.756-9.837-23.685-1.907-7.609-1.37-25.833.734-34.578.6-2.346 2.04-9.086 5.2-14.466 1.71-2.918 3.366-5.594 8.397-5.594 8.253 0 19.907 1.996 29.413 7.295 9.515 5.3 7.743 8.566 6.526 13.615-1.835 7.608-3.447 13.409-3.447 13.409-2.273 8.835-2.846 17.23-1.172 24.517.68 2.882 1.781 6.114 3.885 8.37 2.264 2.434 5.934 2.255 8.029.85 1.781-1.19 2.121-1.808 2.82-3.858 2.014-5.908 4.34-17.455 4.985-22.54 1.764-13.856 2.4-19.584 2.659-37.156 0-5.988-.34-10.374-.627-15.566-.152-2.757-1.897-2.927-4.019-2.067-5.54 2.255-11.287 3.482-17.276 4.036-10.097.94-18.68-1.1-27.247-5.558-4.073-1.817-11.717-7.537-15.181-13.346-10.67-18.422-11.986-37.48-3.07-56.787 5.11-11.064 15.539-16.9 27.363-20.507 7.806-2.766 18.619-3.607 25.61-1.97 2.354.574 3.562 1.12 3.679-2.882.009-1.87.196-3.75.098-5.612.224-6.516.868-7.797 7.304-10.652 3.24-1.405 18.726-5.004 27.328-5.37 10.598-.538 12.111-.27 13.848 5.782 1.172 4.073 2.327 15.288 2.497 21.017.215 12.532.609 18.556.573 26.46.555 19.961.52 34.954 1.522 49.885zm-47.979-25.887c.117-3.965.134-5.38-.197-8.566-.438-6.472-4.44-7.895-9.184-7.77-12.128.188-21.527 3.348-21.518 13.114-.206 7.322 4.144 11.815 9.434 13.158 7.537 1.683 11.717 1.19 16.3-.958 2.802-1.378 5.317-4.028 5.165-8.978zm177.784-9.299c2.49.943 4.588 1.858 4.633 5.326.64 20.725.462 41.46 1.022 62.184.009 8.26-.24 23.09-.418 29.056-.338 14.137-2.747 47.746-6.064 54.69-3.734 8.09-6.641 8.402-28.602 9.833-16.955-.116-15.248-6.001-15-15.053.418-10.135.596-21 .81-30.816a1.505 1.505 0 0 0-1.539-1.556c-4.97.071-9.886.053-13.185-.142-4.028-.25-9.3-.027-14.608.178a1.657 1.657 0 0 0-1.61 1.671c-.026 10.385.303 22.681.427 32.782.08 6.268.436 7.397-5.503 9.593-7.673 3.147-19.8 4.846-29.954 4.454-5.584.063-6.66-2.907-7.673-8.473-2.694-14.857-2.774-29.99-3.601-45.06-1.414-25.66-.907-51.364-1.6-77.041-.01-8.269.24-23.09.417-29.056.338-14.137 2.739-47.745 6.064-54.69 3.734-8.09 6.642-8.401 28.603-9.833 16.955.116 15.248 5.993 14.999 15.053-.614 15.061-.72 31.794-1.174 44.17-.222 6.251-.435 47.924-1.075 69.431a1.607 1.607 0 0 0 1.555 1.672c5.104.178 9.985.729 17.178.782 1.04.045 5.921.311 11.407.738.88.071 1.645-.631 1.654-1.538.178-13.986.4-29.11.738-38.739.116-7.192.098-12.732.027-21.231.115-6.953 1.644-6.811 7.246-8.385 4.472-1.253 28.896-2.223 34.826 0zm193.106 75.059c-.009 25.989 0 57.419-18.876 74.49-15.141 14.874-42.953 16.555-60.54 9.495-8.188-3.672-15.185-7.904-21.56-16.76-5.175-8.01-7.567-12.67-9.78-23.525-1.894-7.558-1.37-25.66.729-34.347 2.258-8.855 3.12-11.38 15.061-9.362 7.033 1.04 15.506 3.254 21.472 5.3 12.092 4.276 12.741 4.907 9.496 17.203-2.499 9.167-3.12 17.863-1.396 25.384.676 2.863 1.778 6.073 3.868 8.314 2.258 2.418 5.903 2.24 7.984.844 1.778-1.182 2.116-1.796 2.81-3.832 2-5.868 4.312-17.337 4.952-22.388 1.458-11.425 1.965-15.292 2.302-26.93-.169-7.176-.32-13.613-.39-15.462-.125-3.299-1.726-3.726-3.895-2.659-1.04.507-2.08.996-3.12 1.512-10.501 5.228-21.313 5.557-32.347 3.458-12.412-2.347-19.702-12.385-24.335-25.642-2.951-8.455-5.539-20.351-5.823-29.687-.116-8.206-.009-10.99 9.104-12.545 7.691-1.307 23.882-1.272 31.101 1.502 2.534.978 4.5 3.023 4.9 11.38.302 6.367 1.564 12.262 3.947 17.872 3.2 7.522 9.709 8.909 14.617 2.703 3.574-4.526 5.486-10.314 5.788-25.873.169-8.953-.018-15.124-.071-20.005-.151-13.746.311-32.248.507-35.476.293-4.863 1.733-6.81 6.632-7.726 10.225-2.276 23.188-1.138 26.06-.4 8.491 2.17 8.767 2.427 8.696 12.376-.178 11.772-.045 23.562-.045 35.342-.187 13.328 2.16 59.953 2.152 85.444zm-75.565-86.35c-14.342-2.854-30.052-5.14-45.407-3.441-3.041.346-4.517 1.342-5.317 3.094-.498 1.787-.65 4.348-.951 8.153-.24 6.535-.472 51.79-1.2 72.258-.205 12.536.035 26.735.177 43.664-.089 10.82.293 24.184.427 35.013.062 4.81.258 6.588-2.374 8.153-.827.534-1.956 1.032-3.53 1.6-7.85 3.148-20.245 4.846-30.63 4.455-5.717.053-6.82-2.916-7.85-8.482-2.766-14.857-2.846-29.999-3.69-45.078-1.45-25.668-.925-51.39-1.636-77.077-.01-8.26.249-23.099.426-29.065.356-14.137.56-14.928-4.454-16.386-.267-.071-.569-.062-.845-.107-13.452.4-27.953 1.405-39.707 3.912-13.79 2.943-15.782-1.529-18.174-22.21-2.774-23.961-.987-24.939 13.47-26.468 15.72-1.663 37.708-2.525 56.414-2.979 1.965-.124 6.233-.187 11.71-.169 4.881-.08 10.35-.106 17.035-.142 24.77-.124 56.37 1.138 70.16 2.57 14.475 1.502 16.484 2.302 13.826 26.282-2.828 25.455-4.055 25.206-17.88 22.45zm196.624 103.99c2.685 14.393 0 34.059-6.293 43.529-2.73 4.798-8.02 11.27-13.498 15.351-12.827 9.56-21.77 12.344-38.24 12.997-18.529-.546-30.496-7.519-34.193-10.32-9.57-7.25-15.352-15.137-18.887-29.065-2.345-12.353-2.113-20.847-.877-30.747 1.888-11.485 1.262-13.454 10.723-15.87 7.152-1.898 24.911-.636 30.747 2.515 4.046 1.691 3.993 4.681 3.205 10.517-.949 10.097-.43 17.947 1.101 27.122.546 3.276 1.862 7.573 4.26 9.936 3.277 3.232 7.86 2.336 10.25-1.522 6.391-10.302 5.98-21.151 2.059-31.991-2.542-7.018-6.14-13.552-11.646-18.905-5.263-6.024-14.984-15.62-19.513-19.612-7.269-6.185-11.897-11.404-16.945-17.965-7.993-10.366-14.107-21.617-16.085-34.758-2.686-17.848 1.763-31.66 11.985-46.322 6.937-9.3 18.44-18.646 46.914-19.872 16.765.036 31.15 5.496 41.828 19.004 3.662 4.627 6.248 11.233 8.164 21.67.904 6.347 1.244 15.772-.188 26.594-1.808 8.101-3.222 8.567-12.37 9.99-6.759.967-15.325.09-22.074-.949-7.716-1.181-7.886-4.252-8.2-14.662-.232-6.857.43-12.97-1.1-19.71-1.37-7.448-6.857-10.455-10.617-10.814-5.469-.205-8.235 2.444-9.882 7.358-2.202 7.573-2.676 16.229 3.312 27.105 3.321 6.847 9.873 13.203 15.754 17.92 11.028 8.844 18.771 16.945 27.92 27.802 11.59 13.758 19.03 25.619 22.386 43.673zm136.813-24.796c.01 8.316-.242 23.247-.42 29.253-.34 14.232-2.766 48.068-6.105 55.059-3.76 8.145-6.687 8.459-28.796 9.9-17.08-.117-15.36-6.033-15.11-15.155.42-10.204.6-21.142.815-31.024a1.533 1.533 0 0 0-1.549-1.576c-5.004.072-9.954.054-13.275-.143-4.054-.25-9.362-.027-14.706.179-.904.027-1.62.76-1.62 1.683-.027 10.455.304 22.834.43 33.003.08 6.31.438 7.447-5.542 9.658-7.725 3.169-19.934 4.879-30.156 4.485-5.622.062-6.705-2.927-7.725-8.53-2.712-14.958-2.793-30.193-3.625-45.365-1.424-25.834-.913-51.712-1.612-77.563-.009-8.315.242-23.246.421-29.252.34-14.233 2.757-48.068 6.105-55.059 3.76-8.146 6.686-8.459 28.796-9.9 17.07.116 15.351 6.042 15.1 15.154-.617 15.164-.724 32.001-1.172 44.479-.215 6.293-.439 48.247-1.083 69.9a1.623 1.623 0 0 0 1.566 1.683c5.13.179 10.052.734 17.294.787 1.047.045 5.962.314 11.484.743a1.56 1.56 0 0 0 1.665-1.548c.18-14.08.403-29.306.743-39 .206-12.613-.035-26.908-.17-43.942.09-10.894-.286-24.338-.42-35.232-.081-6.31-.44-7.438 5.54-9.658 7.725-3.169 19.935-4.879 30.157-4.485 5.621-.062 6.704 2.927 7.725 8.53 2.72 14.958 2.802 30.193 3.634 45.365 1.423 25.833.904 51.72 1.611 77.571zm126.5 87.803c-.877 4.028-3.876 4.475-7.895 5.308-3.5.725-15.28 2.443-24.49.94-7.287-1.19-9.757-.609-10.268-8.083-.223-7.976-.295-15.951-.635-23.918-.35-8.208-.52-9.408-8.584-9.631-5.308-.108-8.155.197-14.313 1.665-3.223 1.047-3.984 5.075-4.225 6.507-1.253 7.483-1.647 14.492-1.79 22.074-.17 9.085 1.19 10.052-7.52 11.788-2.192.44-24.875-.725-33.933-1.924-5.756-.76-7.206-2.462-7.421-8.262-.295-7.958-.063-40.066 3.867-83.148 4.458-37.622 10.679-71.323 13.66-86.083 2.953-11.968 3.258-18.601 7.17-32.01 3.16-10.822 5.782-10.822 15.655-12.478 16.336-2.515 39.869-.841 43.422.063 6.821 1.727 10.634 3.571 13.615 13.507 5.881 22.969 10.276 45.034 13.445 67.653 3.858 21.868 10.267 99.797 11.09 113.188.43 7.045.663 15.96-.85 22.844zm-46.967-77.24c-.027-1.29-.134-2.57-.26-3.85-1.468-14.912-2.694-29.843-4.511-44.72-1.692-13.793-1.862-19.388-4.816-34.39-1.172-4.923-3.706-7.215-5.925 8.548-2.364 19.165-4.44 31.276-6.4 48.955-1.236 11.081-1.925 17.007-2.39 28.133-.01 5.98 1.414 6.785 8.763 5.792 3.535-.475 6.99-1.406 10.508-1.996 4.118-.699 5.13-1.406 5.031-6.472zm187.389 23.347c-1.12 11.807-3.053 24.866-8.405 35.107-6.007 11.475-12.514 20.077-22.182 23.649-4.242 1.575-14.715 2.497-21.545-1.468-12.004-6.973-18.18-17.974-23.228-32.905-5.541-16.39-6.177-31.419-7.466-48.775-.349-4.655.206-7.671-2.748-7.788-2.318-.214-2.802 1.46-3.509 8.262-1.029 9.927-1.2 15.638-1.1 24.437.116 10.41.089 27.436.313 39.26.349 18.037-1.719 18.86-6.929 20.176-5.057 1.28-12.88 1.36-16.882 1.271-12.997-.286-17.5-1.656-18.761-3.786-1.558-2.632-1.844-10.401-1.844-14.662-.01-17.5.447-31.965.268-46.94-.107-8.987.287-17.983 0-26.961-.653-20.069.08-40.102.108-60.143.027-18.234.671-25.467 1.557-43.629.143-2.971 1.441-16.962 1.79-20.981.645-7.269.645-8.79 7.672-8.754 8.817.044 22.655-.162 32.77.805 11.986 1.146 24.616 2.82 35.51 6.776 10.687 3.885 14.769 6.284 24.024 13.454 9.677 7.501 15.916 16.076 20.391 27.749 3.733 9.712 6.096 29.431 1.683 41.417-5.165 14.008-10.93 19.137-25.018 27.963-8.755 5.487-16.48 5.317-15.558 12.613 1.504 11.94 2.659 24.544 5.147 36.476.278 1.324 1.164 6.937 3.053 10.088 2.703 4.52 5.522-4.915 6.409-9.354.734-3.661 1.289-8.87 1.79-11.771 1.459-8.522 1.316-9.98 10.124-8.933 10.58 1.262 16.971 4.314 18.466 5.147 4.52 2.515 4.941 3.365 4.1 12.2zm-50.95-101.05c-2.928-6.176-12.335-12.04-15.29-13.49-7.214-3.544-12.934-5.62-16.881-6.149-5.989-.797-5.998.815-6.356 4.01-.904 13.57-.814 24.902.036 34.274.367 4.091-.036 6.597 5.3 6.597 10.302 0 17.213.51 26.674-4.529 5.514-2.936 11.243-10.75 6.516-20.713zm142.05-52.797c3.15 1.495 2.049 3.993 2.013 7.457-.018 1.978-1.307 9.55-1.97 12.871-1.01 4.897-1.61 10.017-3.875 19.407-2.372 7.017-2.372 7.017-10.724 6.73-6.301-.34-11.287-.84-18.886-.84-4.995-.421-4.995-.421-5.855 5.845-1.074 7.84-1.826 15.736-2.73 23.604-.385 3.312.645 5.219 4.225 6.105 6.561 1.62 12.962 3.867 19.46 5.746 10.992 3.178 12.263 2.426 11.547 15.495-.152 6.293-1.772 14.67-4.055 20.355-1.566 3.885-4.314 3.92-12.836 3.509-5.308-.108-9.497-1.083-15.78-1.585-5.541-.438-5.747-.376-6.007 5.443-.42 9.434-.51 18.878-1.924 28.268-.663 4.395.322 4.968 4.672 4.654 7.394-.528 14.779-.886 22.11-2.282 6.176-1.182 8.888-2.077 10.177 7.16 1.576 11.315 2.99 22.71 2.014 34.194-.304 3.563.188 5.944-3.58 7.304-6.955 2.507-16.193 2.381-23.336 2.802-4.35.26-27.328.958-36.432 1.02-3.992.027-10.258 1.469-11.072-5.397-.779-6.58-1.746-14.823-2.113-23.336-.188-6.928-.895-18.072-.895-23.962.331-12.398.099-15.897.08-30.64-.026-18.735.26-40.46 1.701-59.123 1.155-14.921 2.167-29.852 4.593-44.657 1.423-8.683 2.407-17.446 3.303-26.2.886-5.72.474-8.03 4.52-9.23 3.473-.751 9.408-.321 15.71.457 13.551 1.674 26.03 2.543 38.92 4.816 4.958.877 12.432 1.826 17.024 4.01z"/></g></g></svg></div><div id="mightyshare-admin-header-buttons"><a href="?page=mightyshare" class="mightyshare-<?php echo esc_attr( ( 'options' === $tab ? 'active' : 'inactive') ); ?>" ><?php esc_html_e( 'Options', 'mightyshare' ); ?></a><a href="?page=mightyshare&tab=post-types" class="mightyshare-<?php echo esc_attr( ( 'post-types' === $tab ? 'active' : 'inactive') ); ?>" ><?php esc_html_e( 'Post Type Options', 'mightyshare' ); ?></a><a href="https://mightyshare.io/account/" rel="nofollow noopener" target="_blank" class="mightyshare-inactive"><?php esc_html_e( 'Account', 'mightyshare' ); ?></a><span style="color: rgba(255,255,255,0.5); margin: 0px 10px;">v<?php echo esc_attr( MIGHTYSHARE_VERSION ); ?></span></div></div>
		<?php
	}

	public function page_layout() {

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen detection.
		$tab = in_array( $tab, array( 'options', 'post-types' ), true ) ? $tab : 'options';
		?>
		<div id="mightyshare-settings-page" class="wrap">
			<h2 style="display:none;"></h2>
			<?php
			// Check required user capability.
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'mightyshare' ) );
			}

			if ( 'options' === $tab ) {
			// General Options Page Layout.
			?>
			<form action="options.php" method="post">
				<?php

				settings_fields( 'mightyshare' );

				$this->mightyshare_settings_section( 'mightyshare', 'general' );
				$this->mightyshare_settings_section( 'mightyshare', 'display' );
				$this->mightyshare_settings_section( 'mightyshare', 'mightyshare_template_default_modal', 'mightyshare-template-options-default' );
				$this->mightyshare_settings_section( 'mightyshare', 'opengraph' );

				submit_button();
				?>
			<input type="hidden" id="mightyshare[current_settings_page_name]" name="mightyshare[current_settings_page_name]" value="options">
			</form>
			<?php
			$this->render_bulk_prewarm_box();
			} elseif ( 'post-types' === $tab ) {
			?>
				<form action="options.php" method="post">
				<?php

				$mightyshare_globals = new Mightyshare_Globals();
				$template_options    = $mightyshare_globals->mightyshare_templates();
				$template_options    = array( '' => __( 'Default', 'mightyshare' ) ) + $template_options;

				settings_fields( 'mightyshare' );
				$options = get_option( 'mightyshare' );

				$enabled_on['post_types'] = get_post_types( array( 'public' => true ), 'objects', 'and' );
				$enabled_on['taxonomies'] = get_taxonomies( array( 'public' => true ), 'objects', 'and' );
				$enabled_on['users'] = (object) array( 'user' => (object) array( 'name' => 'users', 'label' => 'Authors' ) );

				if ( ! empty( $enabled_on ) ) {

					$i = 0;
					foreach ( $enabled_on as $enabled_key => $enabled_value ) {

						foreach ( $enabled_value as $key => $value ) {

							$setting_prefix = array(
								'name'  => 'mightyshare[post_type_overwrites][' . $enabled_key . '][' . $key . ']',
								'value' => ( ! empty( $options['post_type_overwrites'][ $enabled_key ][ $key ] ) ) ? $options['post_type_overwrites'][ $enabled_key ][ $key ] : '',
							);

							add_settings_section(
								$key,
								$value->label,
								false,
								'mightyshare'
							);

							$mightyshare_display_options = array(
								'enabled_on' => array(
									/* translators: %s: post type, taxonomy or "Authors" label */
								'label'         => sprintf( __( 'Enabled for %s?', 'mightyshare' ), $value->label ),
									'weight'        => 1,
									'field_type'    => 'render_mightyshare_checkbox_field',
									'prefix'        => $key . '-',
									'value'         => ( ! empty( $options['enabled_on'][ $enabled_key ] ) && in_array( $key, $options['enabled_on'][ $enabled_key ], true ) ) ? 'yes' : '',
									'field_options' => array(
										'id'        => 'mightyshare[enabled_on][' . $enabled_key . '][]',
										/* translators: %s: post type, taxonomy or "Authors" label */
										'aria_label' => sprintf( __( 'Enabled for %s?', 'mightyshare' ), $value->label ),
										'classes'   => 'mightyshare-toggler-wrapper',
										'label'     => true,
										'set_value' => $key,
									),
								),
								'template' => array(
									'label'         => __( 'Template', 'mightyshare' ),
									'weight'        => 4,
									'field_type'    => 'render_mightyshare_select_field',
									'value'         => ( ! empty( $setting_prefix['value']['template'] ) ) ? $setting_prefix['value']['template'] : '',
									'field_options' => array(
										'id'       => $setting_prefix['name'] . '[template]',
										'classes'  => 'mightyshare_template_field',
										'options'  => $template_options,
										'modal'    => __( 'Template Options', 'mightyshare' ),
										'modal_id' => 'mightyshare-template-options-'.$enabled_key.'-' . $key,
									),
								),
							);

							$this->mightyshare_register_settings_fields( $mightyshare_display_options, $key );
							$this->mightyshare_settings_section( 'mightyshare', $key );

							$mightyshare_globals                  = new Mightyshare_Globals();
							$mightyshare_template_display_options = $mightyshare_globals->mightyshare_template_options( $value, $setting_prefix );

							if ( ! empty( $mightyshare_template_display_options ) ) {
								add_settings_section(
									$key.'_modal',
									$value->label,
									false,
									'mightyshare'
								);
							}

							$this->mightyshare_register_settings_fields( $mightyshare_template_display_options, $key.'_modal' );
							$this->mightyshare_settings_section( 'mightyshare', $key.'_modal', 'mightyshare-template-options-' . $enabled_key . '-' . $key );
						}
					}
				}

				submit_button();

				?>
				<input type="hidden" id="mightyshare[current_settings_page_name]" name="mightyshare[current_settings_page_name]" value="post-types">
				</form>
				<?php
			}
			?>
		</div>
		<?php
	}

	public function render_bulk_prewarm_box() {
		$options = get_option( 'mightyshare' );
		if ( empty( $options['mightyshare_api_key'] ) ) {
			return;
		}

		$state      = get_option( 'mightyshare_bulk_prewarm' );
		$running    = is_array( $state ) && ! empty( $state['running'] );
		$post_types = ! empty( $options['enabled_on']['post_types'] ) ? (array) $options['enabled_on']['post_types'] : array();
		$available  = 0;
		foreach ( $post_types as $post_type ) {
			$counts     = wp_count_posts( $post_type );
			$available += isset( $counts->publish ) ? (int) $counts->publish : 0;
		}
		?>
		<div class="postbox">
			<div class="mightyshare-settings-section inside">
				<h2><?php esc_html_e( 'Pre-generate Existing Content', 'mightyshare' ); ?></h2>
				<p>
				<?php
				/* translators: %d: number of published items */
				echo esc_html( sprintf( _n( 'Render images for your %d published item on enabled post types, a few at a time in the background. Useful after changing templates or installing on an existing site.', 'Render images for your %d published items on enabled post types, a few at a time in the background. Useful after changing templates or installing on an existing site.', $available, 'mightyshare' ), $available ) );
				?>
				<br><small><?php esc_html_e( 'Images that are already rendered and unchanged are served from cache; each new image counts as one render.', 'mightyshare' ); ?></small></p>
				<?php if ( $running ) { ?>
					<p><strong>
					<?php
					/* translators: 1: processed count, 2: total count */
					echo esc_html( sprintf( __( 'Running: %1$d of %2$d processed.', 'mightyshare' ), $state['done'], $state['total'] ) );
					?>
					</strong> <?php esc_html_e( 'It continues as your site gets visits; reload this page to see progress.', 'mightyshare' ); ?></p>
				<?php } elseif ( is_array( $state ) && ! empty( $state['error'] ) ) { ?>
					<p class="mightyshare-error"><?php echo esc_html( $state['error'] ); ?></p>
				<?php } elseif ( is_array( $state ) && ! empty( $state['finished'] ) ) { ?>
					<p>
					<?php
					/* translators: 1: processed count, 2: date */
					echo esc_html( sprintf( __( 'Last run finished: %1$d items on %2$s.', 'mightyshare' ), $state['done'], wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $state['finished'] ) ) );
					?>
					</p>
				<?php } ?>
				<?php if ( is_array( $state ) && ! empty( $state['failed'] ) ) { ?>
					<p class="mightyshare-error">
					<?php
					/* translators: %d: number of failed renders */
					echo esc_html( sprintf( _n( '%d image failed to render.', '%d images failed to render.', (int) $state['failed'], 'mightyshare' ), (int) $state['failed'] ) );
					?>
					</p>
				<?php } ?>
				<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
					<input type="hidden" name="action" value="mightyshare_bulk_prewarm">
					<?php wp_nonce_field( 'mightyshare_bulk_prewarm' ); ?>
					<?php if ( $running ) { ?>
						<button type="submit" name="mightyshare_bulk" value="cancel" class="button"><?php esc_html_e( 'Cancel', 'mightyshare' ); ?></button>
					<?php } else { ?>
						<button type="submit" name="mightyshare_bulk" value="start" class="button button-secondary" <?php disabled( 0, $available ); ?>><?php esc_html_e( 'Pre-generate All Images', 'mightyshare' ); ?></button>
					<?php } ?>
					<p class="mightyshare-description description"><?php echo wp_kses_post( __( 'Prefer the command line? Run <code>wp mightyshare prewarm</code>.', 'mightyshare' ) ); ?></p>
				</form>
			</div>
		</div>
		<?php
	}

	// Loop through array and configure settings page.
	public function mightyshare_register_settings_fields( $fields, $key ) {
		uasort( $fields, function( $a, $b ) {
				return $a['weight'] <=> $b['weight'];
			}
		);
		foreach ( $fields as $display_key => $display_value ) {
			add_settings_field(
				$display_key,
				$display_value['label'],
				function( $display_value ) {
					if ( ! empty( $display_value['field_type'] ) ){
						$display_value['field_type']( $display_value['field_options'], $display_value['value'], ( ! empty( $display_value['prefix'] ) ) ? $display_value['prefix'] : '' );
					}
				},
				'mightyshare',
				$key,
				$display_value
			);
		}
	}

	// Fields for admin page.
	public function render_enable_mightyshare_field() {

		// Retrieve data from the database.
		$options = get_option( 'mightyshare' );

		// Set default value.
		if ( empty( $options ) ) {
			$value = isset( $options['enable_mightyshare'] ) ? $options['enable_mightyshare'] : 'checked';
		} else {
			$value = isset( $options['enable_mightyshare'] ) ? $options['enable_mightyshare'] : '';
		}

		// Field output.
		?>
		<label class="mightyshare-toggler-wrapper"><input type="checkbox" name="mightyshare[enable_mightyshare]" class="enable_mightyshare_field" aria-label="<?php esc_attr_e( 'Enable MightyShare', 'mightyshare' ); ?>" value="checked" <?php echo esc_attr( checked( $value, 'checked', false ) ); ?>><div class="toggler-slider">
		<div class="toggler-knob"></div></div></label>
		<p class="mightyshare-description description"><?php echo wp_kses_post( __( 'Turn off to stop all MightyShare images on your site. <br /><small>When on, choose which content gets images below, and override it per post.</small>', 'mightyshare' ) ); ?></p>
		<?php
	}

	public function render_mightyshare_api_key_field() {

		// Retrieve data from the database.
		$options = get_option( 'mightyshare' );

		// Set default values.
		$value_api_key = isset( $options['mightyshare_api_key'] ) ? $options['mightyshare_api_key'] : '';
		$field_type    = isset( $options['mightyshare_api_key'] ) ? 'password' : 'text';
		$checked       = isset( $options['mightyshare_api_key'] ) ? '' : 'checked';

		// Field output.
		?>
		<input type="<?php echo esc_attr( $field_type ); ?>" name="mightyshare[mightyshare_api_key]" class="regular-text	mightyshare_api_key_field" placeholder="<?php echo esc_attr( __( 'API KEY', 'mightyshare' ) ); ?>" id="mightyshare_api_key_field" value="<?php echo esc_attr( $value_api_key ); ?>"> <label><input type="checkbox" onclick="toggleApiKeyFieldMask('.mightyshare_api_key_field')" <?php echo esc_attr( $checked ); ?>> <?php echo wp_kses_post( __( 'Display API Key', 'mightyshare' ) ); ?></label>
		<p class="mightyshare-description description">
			<?php if( ! empty( $options ) && ! empty( $options['plan_response_type'] ) && ! empty( $options['plan_message'] ) ) { ?>
				<span id="mightyshare-api-key-status" class="loaded <?php echo esc_attr( $options['plan_response_type'] ); ?>"><?php echo esc_html( $options['plan_message'] ); ?>
				</span>
			<?php }; ?>
			<?php echo wp_kses_post( __( 'Your MightyShare.io API Key. <br /><small>Don\'t have an API Key? <a href="https://mightyshare.io/register" rel="nofollow noopener" target="_blank">Get a free MightyShare API Key</a></small>', 'mightyshare' ) ); ?></p>
		<?php
	}

	public function render_prewarm_field() {
		$options = get_option( 'mightyshare' );
		$value   = ( empty( $options['prewarm'] ) || 'no' !== $options['prewarm'] ) ? 'yes' : 'no';
		?>
		<input type="hidden" name="mightyshare[prewarm]" value="no">
		<?php
		render_mightyshare_checkbox_field(
			array(
				'id'        => 'mightyshare[prewarm]',
				'aria_label' => __( 'Pre-generate on Publish', 'mightyshare' ),
				'set_value' => 'yes',
				'classes'   => 'mightyshare-toggler-wrapper',
				'label'     => true,
			),
			$value
		);
		?>
		<p class="mightyshare-description description"><?php echo wp_kses_post( __( 'Render each image in the background when you publish or update, so social crawlers get it instantly. <br /><small>Each new or changed image counts as one render.</small>', 'mightyshare' ) ); ?></p>
		<?php
	}

	public function render_enable_archives_field() {
		$options = get_option( 'mightyshare' );
		render_mightyshare_checkbox_field(
			array(
				'id'        => 'mightyshare[enable_archives]',
				'aria_label' => __( 'Homepage & Archives', 'mightyshare' ),
				'set_value' => 'yes',
				'classes'   => 'mightyshare-toggler-wrapper',
				'label'     => true,
			),
			! empty( $options['enable_archives'] ) ? 'yes' : 'no'
		);
		?>
		<p class="mightyshare-description description"><?php echo wp_kses_post( __( 'Also generate images for the homepage (when it shows latest posts), the blog page, post type archives and the WooCommerce shop. <br /><small>The homepage uses your Posts settings, the blog and shop pages use Pages, and archives use their post type. That type must be enabled above.</small>', 'mightyshare' ) ); ?></p>
		<?php
	}

	public function render_post_types_field() {

		// Retrieve data from the database.
		$options = get_option( 'mightyshare' );

		// Field output.
		$enabled_on['post_types'] = get_post_types( array( 'public' => true ), 'objects', 'and' );
		$enabled_on['taxonomies'] = get_taxonomies( array( 'public' => true ), 'objects', 'and' );
		$enabled_on['users'] = (object) array( 'user' => (object) array( 'name' => 'users', 'label' => 'Authors' ) );

		if ( ! empty( $enabled_on ) ) {
			$used_labels = array();
			foreach ( $enabled_on as $enabled_key => $enabled_value ) {

				foreach ( $enabled_value as $key => $value ) {

					// Check if enabled for item.
					$is_checked = '';
					if ( ! empty( $options['enabled_on'][ $enabled_key ] ) && in_array( $key, $options['enabled_on'][ $enabled_key ], true ) ) {
						$is_checked = 'yes';
					} elseif ( is_array( $options ) ) {
						$is_checked = 'no';
					}

					// Set default values.
					$default_value = 'yes';
					if ( 'taxonomies' === $enabled_key || 'users' === $enabled_key ) {
						$default_value = 'no';
					}

					// Check if label needs extra context.
					if ( in_array( $value->label, $used_labels, true ) ) {
						$value->label = $value->label . ' (' . $value->name . ')';
					}

					// Field output.
					render_mightyshare_checkbox_field(
						array(
							'name'              => 'mightyshare[enabled_on][' . $enabled_key . '][]',
							'set_value'         => $key,
							'label'             => true,
							'default'           => $default_value,
							'short_description' => $value->label,
						),
						$is_checked
					);

					array_push( $used_labels, $value->label );

				}
			}
		}

	}

	public function render_default_template_field() {

		// Retrieve data from the database.
		$options             = get_option( 'mightyshare' );
		$mightyshare_globals = new Mightyshare_Globals();
		$template_options    = $mightyshare_globals->mightyshare_templates();

		// Set default value.
		$value = isset( $options['default_template'] ) ? $options['default_template'] : '';

		$field_options = array(
			'id'       => 'mightyshare[default_template]',
			'classes'  => 'mightyshare_template_field',
			'options'  => $template_options,
			'modal'    => __( 'Template Options', 'mightyshare' ),
			'modal_id' => 'mightyshare-template-options-default',
		);

		// Field output.
		render_mightyshare_select_field( $field_options, $value );
	}

	public function render_detected_seo_plugin_field() {
		$mightyshare_globals = new Mightyshare_Globals();
		if ( $mightyshare_globals->isYoastSEOActive() ) {
			?>
			Yoast SEO
			<?php
		} elseif ( $mightyshare_globals->isRankMathActive() ) {
			?>
			Rank Math
			<?php
		} elseif ( $mightyshare_globals->isAllInOneSEOActive() ) {
			?>
			All in One SEO
			<?php
		} elseif ( $mightyshare_globals->isTheSEOFrameworkActive() ) {
			?>
			The SEO Framework
			<?php
		} elseif ( $mightyshare_globals->isSlimSEOActive() ) {
			?>
			Slim SEO
			<?php
		} elseif ( $mightyshare_globals->isSEOPressActive() ) {
			?>
			SEOPress
			<?php
		} elseif ( $mightyshare_globals->isSquirrlyActive() ) {
			?>
			Squirrly SEO
			<?php
		} else {
			?>
			<span class="mightyshare-error"><?php esc_html_e( 'None detected', 'mightyshare' ); ?></span>
			<p class="mightyshare-description description"><?php esc_html_e( 'Turn on "Enable Open Graph" below so MightyShare can add the image tags itself.', 'mightyshare' ); ?></p>
			<?php
		}
	}

	protected function no_seo_plugin() {
		$globals = new Mightyshare_Globals();
		return ! ( $globals->isYoastSEOActive() || $globals->isRankMathActive() || $globals->isAllInOneSEOActive() || $globals->isTheSEOFrameworkActive() || $globals->isSlimSEOActive() || $globals->isSEOPressActive() || $globals->isSquirrlyActive() );
	}

	public function render_opengraph_field() {
		// Retrieve data from the database.
		$options = get_option( 'mightyshare' );

		// Set default value: on for a fresh install with no SEO plugin, so it works out of the box.
		if ( ! is_array( $options ) ) {
			$value = $this->no_seo_plugin() ? 'yes' : 'no';
		} else {
			$value = ( ! empty( $options['output_opengraph'] ) ) ? 'yes' : 'no';
		}

		// Field output.
		render_mightyshare_checkbox_field(
			array(
				'id'        => 'mightyshare[output_opengraph]',
				'aria_label' => __( 'Enable Open Graph', 'mightyshare' ),
				'default'   => 'no',
				'set_value' => 'yes',
				'classes'   => 'mightyshare-toggler-wrapper',
				'label'     => true,
			),
			$value
		);
		?>
		<p class="mightyshare-description description"><?php echo wp_kses_post( __( 'Output Open Graph and X (Twitter) image tags for your MightyShare images. <br /><small>Turn this on if you don\'t use an SEO plugin (or use Jetpack\'s tags).</small>', 'mightyshare' ) ); ?></p>
		<?php
	}

	// Render Template Section.
	public function mightyshare_settings_section( $page, $section, $model_id = null ) {
		global $wp_settings_sections;

		if ( ! empty( $wp_settings_sections[ $page ][ $section ] ) ) {
			if ( isset( $model_id ) ) {
				?>
				<div id="<?php echo esc_attr( $model_id ); ?>" style="display:none;">
					<div class="mightyshare-options-modal mightyshare-settings-section">
						<h2>
						<?php
						/* translators: %s: settings section name, e.g. "Default" or "Posts" */
						echo esc_html( sprintf( __( '%s Template Options', 'mightyshare' ), $wp_settings_sections[ $page ][ $section ]['title'] ) );
						?>
						</h2>
				<?php
			}else{
				?>
				<div class="postbox">
					<div class="mightyshare-settings-section inside">
					<h2><?php echo esc_attr( $wp_settings_sections[ $page ][ $section ]['title'] ); ?></h2>
				<?php
			}
			?>
				<table class="form-table">
					<tbody>
						<?php do_settings_fields( $page, $section ); ?>
					</tbody>
				</table>
				</div>
			</div>
			<?php
		}
	}
}

new Mightyshare_Plugin_Options();

class Mightyshare_Generate_Engine {

	// Core Screenshot Engine by MightyShare.io.
	public function get_image_url( $url, $options, $key ) {
		$api_key         = substr( $key['api_key'], 0, 16 );
		$api_secret      = substr( $key['api_key'], 16, 32 );
		$options['page'] = $url;
		$format          = 'jpeg';
		unset( $options['format'] );
		$option_parts = array();

		foreach ( $options as $key => $values ) {
			$values = is_array( $values ) ? $values : array( $values );

			foreach ( $values as $value ) {
				if ( ! empty( $value ) ) {
					$encoded_value  = rawurlencode( $value );
					$option_parts[] = "$key=$encoded_value";
				}
			}
		}
		$query_string     = implode( '&', $option_parts );
		$generated_secret = hash_hmac( 'sha256', $query_string, $api_secret );

		return "https://api.mightyshare.io/v1/$api_key/$generated_secret/$format?$query_string";
	}
}

class Mightyshare_Public_Functions {
	public static function get_mightyshare_url( $post_id = 0, $options = array() ) {
		$template_parts = Mightyshare_Frontend::instance()->get_mightyshare_post_details( $post_id );

		if ( ! empty( $options ) ) {
			$template_parts = array_merge( $template_parts, $options );
		}

		return Mightyshare_Frontend::mightyshare_generate_og_image( $template_parts );
	}
}

/**
 * Plain text for a template field: no tags, no HTML entities, bounded length.
 */
function mightyshare_clean_text( $value ) {
	// Only real tags ("<em>", "</a>") — titles like "I <3 you" or "<$50" keep their "<".
	$value = preg_replace( '#<(script|style)[^>]*>.*?</\1>#is', '', (string) $value );
	$value = html_entity_decode( preg_replace( '#</?[a-zA-Z][^<>]*>#', '', $value ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );

	// ponytail: a hard cap keeps very long excerpts (and CJK text, which
	// percent-encodes to ~18 bytes per character) under URL length limits.
	return mb_strlen( $value ) > 300 ? rtrim( mb_substr( $value, 0, 299 ) ) . '…' : $value;
}

class Mightyshare_Frontend {

	// Post meta keys the metabox stores per-post template options under.
	const POST_TEMPLATE_OPTION_KEYS = array( 'primary_font', 'primary_color', 'logo', 'background' );

	protected static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	// Replace OG image if using an SEO plugin.
	public function mightyshare_opengraph_meta_tags() {
		$mightyshare_globals = new Mightyshare_Globals();
		if ( $mightyshare_globals->isYoastSEOActive() ) {
			// Using Yoast SEO.
			add_filter( 'wpseo_frontend_presentation', array( $this, 'mightyshare_overwrite_yoast_url' ) );
		} elseif ( $mightyshare_globals->isRankMathActive() ) {
			// Using Rank Math.
			add_filter( 'rank_math/opengraph/facebook/image', array( $this, 'mightyshare_overwrite_rankmath_opengraph_url' ) );
			add_filter( 'rank_math/opengraph/twitter/image', array( $this, 'mightyshare_overwrite_rankmath_opengraph_url' ) );
		} elseif ( $mightyshare_globals->isAllInOneSEOActive() ) {
			// Using All in One SEO.
			add_filter( 'aioseo_facebook_tags', array( $this, 'mightyshare_overwrite_all_in_one_seo_facebook_opengraph_url' ) );
			add_filter( 'aioseo_twitter_tags', array( $this, 'mightyshare_overwrite_all_in_one_seo_twitter_opengraph_url' ) );
		} elseif ( $mightyshare_globals->isTheSEOFrameworkActive() ) {
			// Using The SEO Framework.
			add_filter( 'the_seo_framework_image_details', array( $this, 'mightyshare_overwrite_the_seo_framework_opengraph_url' ) );
		} elseif ( $mightyshare_globals->isSlimSEOActive() ) {
			// Using Slim SEO.
			add_filter( 'slim_seo_open_graph_image', array( $this, 'mightyshare_overwrite_slim_seo_opengraph_url' ) );
			add_filter( 'slim_seo_twitter_card_image', array( $this, 'mightyshare_overwrite_slim_seo_opengraph_url' ) );
		} elseif ( $mightyshare_globals->isSEOPressActive() ) {
			// Using SEOPress.
			add_filter( 'seopress_social_og_thumb', array( $this, 'mightyshare_overwrite_seopress_opengraph_url' ) );
			add_filter( 'seopress_social_twitter_card_thumb', array( $this, 'mightyshare_overwrite_seopress_twitter_url' ) );
		} elseif ( $mightyshare_globals->isSquirrlyActive() ) {
			// Using Squirrly SEO.
			add_filter( 'sq_post', array( $this, 'mightyshare_overwrite_squirrly_post' ), 100 );
		} else {
			// No plugin manually add og:image meta.
			$options = mightyshare_get_options();

			if ( ! empty( $options['output_opengraph'] ) && 'yes' === $options['output_opengraph'] ) {
				// Jetpack prints its own Open Graph tags when no SEO plugin is active.
				add_filter( 'jetpack_open_graph_tags', array( $this, 'mightyshare_overwrite_jetpack_tags' ) );
				add_action( 'wp_head', array( $this, 'mightyshare_render_opengraph' ), 1 );
			}
		}
	}

	// Using Yoast SEO.
	public function mightyshare_overwrite_yoast_url( $presentation ) {
		$template_parts = $this->get_mightyshare_post_details();

		if ( $template_parts['is_enabled'] ) {
			$image_url = $this->mightyshare_generate_og_image();

			$presentation->open_graph_images = array(
				array(
					'url'    => $image_url,
					'width'  => '1200',
					'height' => '630',
					'type'   => 'image/jpeg',
				),
			);

			$presentation->twitter_image = $image_url;
		}

		return $presentation;
	}

	// Using Rank Math.
	public function mightyshare_overwrite_rankmath_opengraph_url( $attachment_url ) {
		$template_parts = $this->get_mightyshare_post_details();

		if ( $template_parts['is_enabled'] ) {
			return $this->mightyshare_generate_og_image();
		}

		return $attachment_url;
	}

	// Using All in One SEO.
	public function mightyshare_overwrite_all_in_one_seo_facebook_opengraph_url( $facebookMeta ) {
		$template_parts = $this->get_mightyshare_post_details();

		if ( $template_parts['is_enabled'] ) {
			$image_url                           = $this->mightyshare_generate_og_image();
			$facebookMeta['og:image']            = $image_url;
			$facebookMeta['og:image:secure_url'] = $image_url;
			$facebookMeta['og:image:width']      = '1200';
			$facebookMeta['og:image:height']     = '630';
		}

		return $facebookMeta;
	}

	public function mightyshare_overwrite_all_in_one_seo_twitter_opengraph_url( $twitterMeta ) {
		$template_parts = $this->get_mightyshare_post_details();

		if ( $template_parts['is_enabled'] ) {
			$twitterMeta['twitter:image'] = $this->mightyshare_generate_og_image();
		}

		return $twitterMeta;
	}

	// Using The SEO Framework.
	public function mightyshare_overwrite_the_seo_framework_opengraph_url( $image ) {
		$template_parts = $this->get_mightyshare_post_details();

		if ( $template_parts['is_enabled'] ) {
			$image[0]['height'] = '';
			$image[0]['width']  = '';
			$image[0]['alt']    = '';
			$image[0]['url']    = $this->mightyshare_generate_og_image();
		}

		return $image;
	}

	// Using Slim SEO.
	public function mightyshare_overwrite_slim_seo_opengraph_url( $value ) {
		$template_parts = $this->get_mightyshare_post_details();

		if ( $template_parts['is_enabled'] ) {
			return $this->mightyshare_generate_og_image();
		}

		return $value;
	}

	// Using SEOPress.
	public function mightyshare_overwrite_seopress_opengraph_url( $value ) {
		$template_parts = $this->get_mightyshare_post_details();

		if ( $template_parts['is_enabled'] ) {
			return '<meta property="og:image" content="' . esc_url( $this->mightyshare_generate_og_image() ) . '" />';
		}

		return $value;
	}
	public function mightyshare_overwrite_seopress_twitter_url( $value ) {
		$template_parts = $this->get_mightyshare_post_details();

		if ( $template_parts['is_enabled'] ) {
			return '<meta property="twitter:image:src" content="' . esc_url( $this->mightyshare_generate_og_image() ) . '" />';
		}

		return $value;
	}

	// Using Squirrly SEO.
	public function mightyshare_overwrite_squirrly_post( $post ) {
		$template_parts = $this->get_mightyshare_post_details();

		if ( $template_parts['is_enabled'] && null !== $post && isset( $post->sq ) ) {
			$post->sq->og_media        = $this->mightyshare_generate_og_image();
			$post->sq->og_media_width  = '1200';
			$post->sq->og_media_height = '630';
			$post->sq->og_media_type   = 'image/jpeg';
		}

		return $post;
	}

	// Using Jetpack's Open Graph tags.
	public function mightyshare_overwrite_jetpack_tags( $tags ) {
		$template_parts = $this->get_mightyshare_post_details();

		if ( $template_parts['is_enabled'] ) {
			$image_url               = $this->mightyshare_generate_og_image();
			$tags['og:image']        = $image_url;
			$tags['og:image:width']  = 1200;
			$tags['og:image:height'] = 630;
			$tags['twitter:image']   = $image_url;
			$tags['twitter:card']    = 'summary_large_image';
			unset( $tags['og:image:alt'], $tags['twitter:image:alt'] );
		}

		return $tags;
	}

	// No SEO plugin so render tags.
	public function mightyshare_render_opengraph( $attachment_url ) {
		// Jetpack is already printing (filtered) tags; don't duplicate them.
		if ( has_action( 'wp_head', 'jetpack_og_tags' ) ) {
			return $attachment_url;
		}

		$template_parts = $this->get_mightyshare_post_details();

		if ( $template_parts['is_enabled'] ) {
			$image_url = $this->mightyshare_generate_og_image();
			?>
			<meta property="og:image" content="<?php echo esc_url( $image_url ); ?>" />
			<meta property="og:image:width" content="1200" />
			<meta property="og:image:height" content="630" />
			<meta property="og:image:type" content="image/jpeg" />
			<meta name="twitter:card" content="summary_large_image" />
			<meta name="twitter:image" content="<?php echo esc_url( $image_url ); ?>" />
			<?php
		}

		return $attachment_url;
	}

	// Generate Social Image for Post using MightyShare.
	public static function mightyshare_generate_og_image( $template_parts = null ) {
		global $wp;

		if ( ! $template_parts ) {
			$template_parts = self::instance()->get_mightyshare_post_details();
		}

		// Get API Key.
		$options = mightyshare_get_options();

		if ( empty( $options['mightyshare_api_key'] ) ) {
			return '';
		}

		$key['api_key'] = trim( $options['mightyshare_api_key'] );

		// Setup defaults for render.
		$render_options['cache']  = 'true';
		$render_options['height'] = ! empty( $template_parts['height'] ) ? $template_parts['height'] : '630';
		$render_options['width']  = ! empty( $template_parts['width'] ) ? $template_parts['width'] : '1200';

		// Grab the template.
		if ( ! empty( $template_parts['template'] ) ) {
			$render_options['template'] = $template_parts['template'];
		}

		// Configure the template. Keep this output stable: any change to it
		// changes every signed URL and re-renders every existing image.
		$template_json = array();

		foreach ( $template_parts as $value_key => $value ) {
			if ( 'template' === $value_key && 'screenshot-self' === $value ) {
				break;
			}
			$type = 'text';
			if ( in_array( $value_key, array( 'is_enabled', 'template', 'ID', 'type', 'object_type', 'overwrite_page_url', 'render_version' ), true ) ) {
				continue;
			} elseif ( 'primary_color' === $value_key ) {
				$type = 'color';
			} elseif ( 'primary_font' === $value_key ) {
				$type      = 'google_font';
				$value_key = 'google_font';
				$value     = str_replace( ' ', '+', (string) $value );
			} elseif ( 'background' === $value_key || 'logo' === $value_key ) {
				$type      = 'image_url';
				$image_url = is_numeric( $value ) ? wp_get_attachment_image_src( $value, 'full' ) : $value;
				if ( ! empty( $image_url ) && is_array( $image_url ) ) {
					$image_url = $image_url[0];
				}
				$value = rawurlencode( htmlspecialchars_decode( (string) $image_url ) );
			} elseif ( 'title' === $value_key || 'description' === $value_key ) {
				$value = rawurlencode( mightyshare_clean_text( $value ) );
			} elseif ( 'rating' === $value_key ) {
				$type = 'rating_number';
			} elseif ( 'reviews' === $value_key ) {
				$type = 'number';
			}

			array_push(
				$template_json,
				array(
					'name' => $value_key,
					$type  => $value,
				)
			);
		}

		if ( ! empty( $template_json ) ) {
			$render_options['template_values'] = wp_json_encode( $template_json );
		}

		// Bumped by "Regenerate image" to force a fresh render of this post.
		if ( ! empty( $template_parts['render_version'] ) ) {
			$render_options['v'] = $template_parts['render_version'];
		}

		$mightyshare = new Mightyshare_Generate_Engine();

		return $mightyshare->get_image_url( ! empty( $template_parts['overwrite_page_url'] ) ? $template_parts['overwrite_page_url'] : home_url( $wp->request ), $render_options, $key );
	}

	// Get the current post's details no matter type. Pass a post ID to build
	// details outside the frontend loop (e.g. on save, WP-Cron, or the
	// get_mightyshare_url() developer function).
	public function get_mightyshare_post_details( $post_id = 0 ) {
		// SEO plugins ask several times per page; the queried object doesn't change.
		static $queried_cache = null;

		if ( ! $post_id && null !== $queried_cache && did_action( 'wp' ) ) {
			return $queried_cache;
		}

		$details = $this->build_post_details( $post_id );

		if ( ! $post_id && did_action( 'wp' ) ) {
			$queried_cache = $details;
		}

		return $details;
	}

	protected function build_post_details( $post_id ) {
		$options = mightyshare_get_options();

		// Don't use if MightyShare is disabled globally.
		if ( empty( $options['enable_mightyshare'] ) || empty( $options['mightyshare_api_key'] ) ) {
			$returned_template_parts['is_enabled'] = false;
			return $returned_template_parts;
		}

		global $wp_query, $post;

		$forced_post     = $post_id ? get_post( $post_id ) : null;
		$queried_object  = $forced_post ? $forced_post : get_queried_object();
		$template_parts  = $queried_object ? $queried_object : $post;
		$archives_on     = ! empty( $options['enable_archives'] );
		$is_post_object  = false;
		$returned_template_parts = array();

		// The blog page and WooCommerce shop are real pages: treat them as such.
		if ( ! $forced_post && $archives_on ) {
			if ( $wp_query->is_home && ! $wp_query->is_front_page() && $queried_object instanceof WP_Post ) {
				$forced_post = $queried_object;
			} elseif ( function_exists( 'is_shop' ) && function_exists( 'wc_get_page_id' ) && is_shop() && wc_get_page_id( 'shop' ) > 0 ) {
				$forced_post = get_post( wc_get_page_id( 'shop' ) );
			}
			if ( $forced_post ) {
				$template_parts = $forced_post;
			}
		}

		// Defaults.
		$returned_template_parts['is_enabled']    = false;
		$returned_template_parts['template']      = isset( $options['default_template'] ) ? $options['default_template'] : '';
		$returned_template_parts['primary_font']  = isset( $options['primary_font'] ) ? $options['primary_font'] : '';
		$returned_template_parts['logo_width']    = isset( $options['logo_width'] ) ? $options['logo_width'] : '';
		$returned_template_parts['primary_color'] = isset( $options['default_primary_color'] ) ? $options['default_primary_color'] : '';
		$returned_template_parts['logo']          = isset( $options['logo'] ) ? $options['logo'] : '';
		$returned_template_parts['background']    = isset( $options['fallback_image'] ) ? $options['fallback_image'] : '';

		if ( ! empty( $options['enable_description'] ) ) {
			$returned_template_parts['enable_description'] = true;
		}

		if ( ( $forced_post || $wp_query->is_singular || $wp_query->is_feed ) && $template_parts instanceof WP_Post ) {
			$is_post_object                         = true;
			$returned_template_parts['ID']          = $template_parts->ID;
			$returned_template_parts['title']       = $template_parts->post_title;
			$returned_template_parts['description'] = '' === $template_parts->post_password ? $template_parts->post_excerpt : '';
			$returned_template_parts['type']        = $template_parts->post_type;
			$returned_template_parts['object_type'] = 'post_types';
			// Page views keep 1.4.0's home_url( $wp->request ) so existing images keep
			// their URLs. Pre-warm/feeds use the permalink, untrailingslashed so it
			// matches; plain permalinks have an empty $wp->request, so they need it too.
			if ( $forced_post || $wp_query->is_feed || ! get_option( 'permalink_structure' ) ) {
				$page_url = untrailingslashit( get_permalink( $template_parts ) );
				// Drafts link to ?p=ID; preview with the post's future URL so the
				// render is reused after publishing.
				if ( 'publish' !== $template_parts->post_status && get_option( 'permalink_structure' ) && function_exists( 'get_sample_permalink' ) ) {
					list( $permalink_template, $sample_slug ) = get_sample_permalink( $template_parts->ID );
					$page_url = untrailingslashit( str_replace( array( '%postname%', '%pagename%' ), $sample_slug, $permalink_template ) );
				}
				// PATHINFO permalinks (/index.php/slug): $wp->request omits index.php.
				if ( 0 === strpos( (string) get_option( 'permalink_structure' ), '/index.php' ) ) {
					$page_url = str_replace( home_url( '/index.php' ), home_url(), $page_url );
				}
				$returned_template_parts['overwrite_page_url'] = $page_url;
			}
		} elseif ( ! $forced_post && ( $wp_query->is_category || $wp_query->is_tag || $wp_query->is_tax ) && $template_parts instanceof WP_Term ) {
			$returned_template_parts['ID']          = $template_parts->term_id;
			$returned_template_parts['title']       = $template_parts->name;
			$returned_template_parts['description'] = $template_parts->description;
			$returned_template_parts['type']        = $template_parts->taxonomy;
			$returned_template_parts['object_type'] = 'taxonomies';
		} elseif ( ! $forced_post && $wp_query->is_author && $template_parts instanceof WP_User ) {
			$returned_template_parts['ID']          = $template_parts->ID;
			$returned_template_parts['title']       = $template_parts->display_name;
			$returned_template_parts['description'] = get_the_author_meta( 'description', $template_parts->ID );
			$returned_template_parts['type']        = 'user';
			$returned_template_parts['object_type'] = 'users';
		} elseif ( ! $forced_post && $archives_on && $wp_query->is_home && $wp_query->is_front_page() ) {
			// Front page showing the latest posts.
			$returned_template_parts['title']       = get_bloginfo( 'name' );
			$returned_template_parts['description'] = get_bloginfo( 'description' );
			$returned_template_parts['type']        = 'post';
			$returned_template_parts['object_type'] = 'post_types';
			$returned_template_parts['overwrite_page_url'] = home_url();
		} elseif ( ! $forced_post && $archives_on && $wp_query->is_post_type_archive && $template_parts instanceof WP_Post_Type ) {
			$returned_template_parts['title']       = post_type_archive_title( '', false );
			$returned_template_parts['description'] = $template_parts->description;
			$returned_template_parts['type']        = $template_parts->name;
			$returned_template_parts['object_type'] = 'post_types';
			$returned_template_parts['overwrite_page_url'] = untrailingslashit( (string) get_post_type_archive_link( $template_parts->name ) );
		}

		// Get post type overwrites.
		if ( ! empty( $returned_template_parts['object_type'] ) && ! empty( $returned_template_parts['type'] ) && ! empty( $options['post_type_overwrites'][ $returned_template_parts['object_type'] ][ $returned_template_parts['type'] ] ) ) {
			$overwrites = array_filter( $options['post_type_overwrites'][ $returned_template_parts['object_type'] ][ $returned_template_parts['type'] ] );
			if ( ! empty( $overwrites ) ) {
				$returned_template_parts = array_replace( $returned_template_parts, $overwrites );
			}
		}

		if ( ! empty( $returned_template_parts['object_type'] ) ) {
			$returned_template_parts['is_enabled'] = ! empty( $options['enabled_on'][ $returned_template_parts['object_type'] ] )
				&& in_array( $returned_template_parts['type'], $options['enabled_on'][ $returned_template_parts['object_type'] ], true );
		}

		// Featured image and per-post overwrites only exist for posts — term and
		// user IDs would otherwise read an unrelated post's data.
		if ( $is_post_object ) {
			$post_id   = $returned_template_parts['ID'];
			$thumbnail = get_post_thumbnail_id( $post_id );
			if ( ! empty( $thumbnail ) ) {
				$returned_template_parts['background'] = $thumbnail;
			}

			$post_display_overwrite = get_post_meta( $post_id, 'mightyshare_enabled', true );
			if ( ! empty( $post_display_overwrite ) ) {
				$returned_template_parts['is_enabled'] = filter_var( $post_display_overwrite, FILTER_VALIDATE_BOOLEAN );
			}

			$post_template_overwrite = get_post_meta( $post_id, 'mightyshare_template', true );
			if ( ! empty( $post_template_overwrite ) ) {
				$returned_template_parts['template'] = $post_template_overwrite;
			}

			$post_title_overwrite = get_post_meta( $post_id, 'mightyshare_title', true );
			if ( ! empty( $post_title_overwrite ) ) {
				$returned_template_parts['title'] = $post_title_overwrite;
			}
		}

		// Check is subheadings are enabled.
		if ( empty( $returned_template_parts['enable_description'] ) ) {
			$returned_template_parts['description'] = null;
		}

		if ( $is_post_object ) {
			foreach ( self::POST_TEMPLATE_OPTION_KEYS as $option_key ) {
				$post_template_option_overwrite = get_post_meta( $post_id, 'mightyshare_' . $option_key, true );
				if ( ! empty( $post_template_option_overwrite ) ) {
					$returned_template_parts[ $option_key ] = $post_template_option_overwrite;
				}
			}

			// Real WooCommerce rating instead of the template's sample values.
			if ( 'product-1' === $returned_template_parts['template'] && 'product' === $returned_template_parts['type'] && function_exists( 'wc_get_product' ) ) {
				$product = wc_get_product( $post_id );
				if ( $product ) {
					$returned_template_parts['rating']  = number_format( (float) $product->get_average_rating() / 5, 1, '.', '' );
					$returned_template_parts['reviews'] = (string) (int) $product->get_review_count();
				}
			}

			$render_version = get_post_meta( $post_id, 'mightyshare_render_version', true );
			if ( ! empty( $render_version ) ) {
				$returned_template_parts['render_version'] = $render_version;
			}
		}

		// "Template Default" means: send no font (checked after every overwrite
		// so a per-post or per-type choice of it works too).
		if ( ! empty( $returned_template_parts['primary_font'] ) && 'template-default' === $returned_template_parts['primary_font'] ) {
			$returned_template_parts['primary_font'] = null;
		}

		// Apply filters of the render for devs.
		return apply_filters( 'mightyshare_filter_post', $returned_template_parts );
	}
}

add_action( 'template_redirect', array( Mightyshare_Frontend::instance(), 'mightyshare_opengraph_meta_tags' ), 1 );

/**
 * Whether background pre-warming is on (settings toggle + developer filter).
 */
function mightyshare_prewarm_enabled( $post = null ) {
	$options = mightyshare_get_options();
	$enabled = ! empty( $options['enable_mightyshare'] ) && ! empty( $options['mightyshare_api_key'] ) && ( empty( $options['prewarm'] ) || 'no' !== $options['prewarm'] );

	return (bool) apply_filters( 'mightyshare_prewarm_enabled', $enabled, $post );
}

// Queue a render pre-warm when a post is published or updated. First renders
// can be slow enough that social crawlers (X in particular) time out and cache
// a blank card; requesting the image at save time means it's already cached by
// the time the post is shared. Deferred to WP-Cron because a fire-and-forget
// request (timeout 0.01, blocking false) is aborted before TLS to the API even
// completes, and a blocking request would hang the editor's save.
function mightyshare_schedule_og_prewarm( $post_id, $post ) {
	if ( 'publish' !== $post->post_status || ! is_post_type_viewable( $post->post_type ) ) {
		return;
	}

	// Imports would queue (and bill) one render per imported post.
	if ( defined( 'WP_IMPORTING' ) && WP_IMPORTING ) {
		return;
	}

	if ( ! mightyshare_prewarm_enabled( $post ) ) {
		return;
	}

	$template_parts = Mightyshare_Frontend::instance()->get_mightyshare_post_details( $post_id );
	if ( empty( $template_parts['is_enabled'] ) ) {
		return;
	}

	$args = array( (int) $post_id );

	// The block editor saves over REST, then posts the meta boxes in a second
	// request. Delay the REST-time event so that second request (which
	// replaces it) renders with the final per-post options. A REST client
	// with no meta box follow-up still gets its pre-warm on the next cron run.
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		wp_schedule_single_event( time() + 30, 'mightyshare_prewarm_og_image', $args );
		return;
	}

	wp_clear_scheduled_hook( 'mightyshare_prewarm_og_image', $args );
	wp_schedule_single_event( time(), 'mightyshare_prewarm_og_image', $args );

	// Kick cron now instead of waiting for the next page view — a CLI publish
	// may never get a follow-up request. Core checks DISABLE_WP_CRON in
	// wp_cron(), not spawn_cron(), so honor it here.
	if ( ! ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) ) {
		spawn_cron();
	}
}
add_action( 'wp_after_insert_post', 'mightyshare_schedule_og_prewarm', 10, 2 );

// Runs from WP-Cron: request the signed image URL so MightyShare renders and
// caches it before any crawler asks for it. The URL is built from post state
// at run time, so it always reflects the latest saved title/meta and unchanged
// posts resolve to the same signed URL, served from cache without a new render.
//
// Returns the image URL that was requested, or '' when the post was skipped.
function mightyshare_prewarm_og_image( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post || 'publish' !== $post->post_status ) {
		return '';
	}

	$template_parts = Mightyshare_Frontend::instance()->get_mightyshare_post_details( $post_id );

	if ( empty( $template_parts['is_enabled'] ) ) {
		return '';
	}

	$image_url = Mightyshare_Frontend::mightyshare_generate_og_image( $template_parts );

	// Nobody is waiting on cron, so give a cold render time to finish.
	$response = wp_remote_get( $image_url, array( 'timeout' => 20 ) );
	$status   = (int) wp_remote_retrieve_response_code( $response );

	if ( is_wp_error( $response ) || 200 !== $status ) {
		return new WP_Error(
			'mightyshare_render_failed',
			is_wp_error( $response ) ? $response->get_error_message() : sprintf( 'MightyShare returned HTTP %d.', $status ),
			array( 'status' => $status )
		);
	}

	return $image_url;
}

/**
 * Message for a failed pre-generate that should stop a bulk run (bad key,
 * plan limit, rate limit — retrying every remaining post wouldn't help).
 */
function mightyshare_prewarm_stop_reason( $result ) {
	if ( ! is_wp_error( $result ) ) {
		return '';
	}
	$data   = $result->get_error_data();
	$status = isset( $data['status'] ) ? (int) $data['status'] : 0;
	// Only errors that would repeat for every remaining post (bad key, plan or rate limit).
	if ( ! in_array( $status, array( 401, 402, 403, 429 ), true ) ) {
		return '';
	}

	/* translators: %d: HTTP status code */
	return sprintf( __( 'Stopped: MightyShare returned HTTP %d. Check your API key and your plan\'s remaining renders.', 'mightyshare' ), $status );
}
add_action( 'mightyshare_prewarm_og_image', 'mightyshare_prewarm_og_image' );

/**
 * Published post IDs of enabled post types after $after_id, oldest first.
 * Paging by ID (not offset) stays correct while posts are added or deleted.
 */
function mightyshare_bulk_prewarm_ids( $after_id, $limit, $post_types = null ) {
	global $wpdb;

	if ( null === $post_types ) {
		$options    = mightyshare_get_options();
		$post_types = ! empty( $options['enabled_on']['post_types'] ) ? (array) $options['enabled_on']['post_types'] : array();
	}

	if ( ! $post_types ) {
		return array();
	}

	$placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );

	// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery,WordPress.DB.NoCaching
	return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ($placeholders) AND ID > %d ORDER BY ID ASC LIMIT %d", array_merge( $post_types, array( (int) $after_id, (int) $limit ) ) ) ) );
}

// Start/cancel from the settings page.
function mightyshare_bulk_prewarm_handle() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'mightyshare' ) );
	}
	check_admin_referer( 'mightyshare_bulk_prewarm' );

	wp_clear_scheduled_hook( 'mightyshare_bulk_prewarm_batch' );
	$action = isset( $_POST['mightyshare_bulk'] ) ? sanitize_key( wp_unslash( $_POST['mightyshare_bulk'] ) ) : '';

	if ( 'start' === $action ) {
		$options    = mightyshare_get_options();
		$post_types = ! empty( $options['enabled_on']['post_types'] ) ? (array) $options['enabled_on']['post_types'] : array();
		$total      = 0;
		foreach ( $post_types as $post_type ) {
			$counts = wp_count_posts( $post_type );
			$total += isset( $counts->publish ) ? (int) $counts->publish : 0;
		}

		update_option(
			'mightyshare_bulk_prewarm',
			array(
				'running' => true,
				'last_id' => 0,
				'done'    => 0,
				'total'   => $total,
			),
			false
		);
		wp_schedule_single_event( time(), 'mightyshare_bulk_prewarm_batch' );
		if ( ! ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) ) {
			spawn_cron();
		}
	} else {
		$state            = (array) get_option( 'mightyshare_bulk_prewarm', array() );
		$state['running'] = false;
		update_option( 'mightyshare_bulk_prewarm', $state, false );
	}

	wp_safe_redirect( admin_url( 'options-general.php?page=mightyshare' ) );
	exit;
}
add_action( 'admin_post_mightyshare_bulk_prewarm', 'mightyshare_bulk_prewarm_handle' );

// One WP-Cron batch: renders sequentially for up to ~20 seconds, then queues
// the next batch, which runs on the following cron trigger (next site visit).
function mightyshare_bulk_prewarm_batch() {
	$state = get_option( 'mightyshare_bulk_prewarm' );
	if ( ! is_array( $state ) || empty( $state['running'] ) ) {
		return;
	}

	$started = time();

	while ( time() - $started < 20 ) {
		$ids = mightyshare_bulk_prewarm_ids( $state['last_id'], 10 );

		if ( ! $ids ) {
			$state['running']  = false;
			$state['finished'] = time();
			update_option( 'mightyshare_bulk_prewarm', $state, false );
			return;
		}

		foreach ( $ids as $id ) {
			$result = mightyshare_prewarm_og_image( $id );

			// Re-read after the (slow) request so a Cancel clicked meanwhile wins.
			wp_cache_delete( 'mightyshare_bulk_prewarm', 'options' );
			$state = get_option( 'mightyshare_bulk_prewarm' );
			if ( ! is_array( $state ) || empty( $state['running'] ) ) {
				return;
			}

			$state['last_id'] = $id;
			++$state['done'];
			if ( is_wp_error( $result ) ) {
				$state['failed'] = ( isset( $state['failed'] ) ? (int) $state['failed'] : 0 ) + 1;
				$stop            = mightyshare_prewarm_stop_reason( $result );
				if ( $stop ) {
					$state['running'] = false;
					$state['error']   = $stop;
					update_option( 'mightyshare_bulk_prewarm', $state, false );
					return;
				}
			}
			update_option( 'mightyshare_bulk_prewarm', $state, false );

			if ( time() - $started >= 20 ) {
				break 2;
			}
		}
	}

	wp_schedule_single_event( time(), 'mightyshare_bulk_prewarm_batch' );
}
add_action( 'mightyshare_bulk_prewarm_batch', 'mightyshare_bulk_prewarm_batch' );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Pre-generate MightyShare images for published content.
	 *
	 * ## OPTIONS
	 *
	 * [--post_type=<type>]
	 * : Only this post type (default: every post type enabled in settings).
	 *
	 * [--limit=<number>]
	 * : Stop after this many posts.
	 *
	 * ## EXAMPLES
	 *
	 *     wp mightyshare prewarm
	 *     wp mightyshare prewarm --post_type=product --limit=50
	 *
	 * @when after_wp_load
	 */
	WP_CLI::add_command(
		'mightyshare prewarm',
		function ( $args, $assoc_args ) {
			$options = mightyshare_get_options();
			if ( empty( $options['mightyshare_api_key'] ) || empty( $options['enable_mightyshare'] ) ) {
				WP_CLI::error( 'MightyShare is disabled or has no API key.' );
			}

			$post_types = ! empty( $assoc_args['post_type'] ) ? array( sanitize_key( $assoc_args['post_type'] ) ) : null;
			$limit      = ! empty( $assoc_args['limit'] ) ? absint( $assoc_args['limit'] ) : PHP_INT_MAX;
			$last_id    = 0;
			$rendered   = 0;
			$skipped    = 0;
			$failed     = 0;

			while ( $rendered + $skipped + $failed < $limit ) {
				$ids = mightyshare_bulk_prewarm_ids( $last_id, min( 50, $limit - $rendered - $skipped - $failed ), $post_types );
				if ( ! $ids ) {
					break;
				}
				foreach ( $ids as $id ) {
					$last_id = $id;
					$result = mightyshare_prewarm_og_image( $id );
					if ( is_wp_error( $result ) ) {
						$stop = mightyshare_prewarm_stop_reason( $result );
						if ( $stop ) {
							WP_CLI::error( "#$id: $stop ($rendered images requested before stopping.)" );
						}
						++$failed;
						WP_CLI::warning( "#$id " . get_the_title( $id ) . ': ' . $result->get_error_message() );
					} elseif ( $result ) {
						++$rendered;
						WP_CLI::log( "Pre-generated #$id " . get_the_title( $id ) );
					} else {
						++$skipped;
					}
				}
			}

			$summary = "$rendered images pre-generated, $skipped posts skipped (MightyShare disabled for them), $failed failed.";
			if ( $failed ) {
				WP_CLI::warning( $summary );
			} else {
				WP_CLI::success( $summary );
			}
		}
	);
}

// Metabox "Load preview" / "Regenerate image".
function mightyshare_ajax_preview() {
	check_ajax_referer( 'mightyshare_preview' );

	$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_send_json_error( array( 'message' => __( 'You are not allowed to edit this post.', 'mightyshare' ) ), 403 );
	}

	$regenerate = ! empty( $_POST['regenerate'] );

	$options = mightyshare_get_options();
	if ( empty( $options['mightyshare_api_key'] ) ) {
		wp_send_json_error( array( 'message' => __( 'Add your API key in Settings > MightyShare first.', 'mightyshare' ) ) );
	}
	if ( empty( $options['enable_mightyshare'] ) ) {
		wp_send_json_error( array( 'message' => __( 'MightyShare is turned off in Settings > MightyShare.', 'mightyshare' ) ) );
	}

	$template_parts = Mightyshare_Frontend::instance()->get_mightyshare_post_details( $post_id );
	if ( empty( $template_parts['is_enabled'] ) ) {
		wp_send_json_error( array( 'message' => __( 'MightyShare is disabled for this post. Enable it above (or for this post type in settings), then save.', 'mightyshare' ) ) );
	}

	if ( $regenerate ) {
		// A new version changes the signed URL, so the renderer makes a fresh
		// image instead of serving the cached one.
		$previous_version = get_post_meta( $post_id, 'mightyshare_render_version', true );
		update_post_meta( $post_id, 'mightyshare_render_version', (string) time() );
		$template_parts = Mightyshare_Frontend::instance()->get_mightyshare_post_details( $post_id );
	}

	$image_url = Mightyshare_Frontend::mightyshare_generate_og_image( $template_parts );

	if ( $regenerate ) {
		$response = wp_remote_get( $image_url, array( 'timeout' => 25 ) );
		$status   = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status ) {
			// Keep the post on its previous (working) image.
			if ( '' === $previous_version ) {
				delete_post_meta( $post_id, 'mightyshare_render_version' );
			} else {
				update_post_meta( $post_id, 'mightyshare_render_version', $previous_version );
			}
			wp_send_json_error(
				array(
					/* translators: %s: HTTP status code or error message */
					'message' => sprintf( __( 'The new image could not be rendered (%s). Check your API key and plan usage.', 'mightyshare' ), is_wp_error( $response ) ? $response->get_error_message() : 'HTTP ' . $status ),
				)
			);
		}
	}

	wp_send_json_success( array( 'url' => $image_url ) );
}
add_action( 'wp_ajax_mightyshare_preview', 'mightyshare_ajax_preview' );

class Mightyshare_Globals {

	// Built-in copy of the template catalog, used until (or whenever) the live
	// list at MIGHTYSHARE_TEMPLATES_API can't be fetched. Order = picker order.
	const FALLBACK_TEMPLATES = array(
		'standard-1' => 'The Original 1',
		'standard-2' => 'The Original 2',
		'standard-3' => 'The Original 3',
		'standard-4' => 'The Original 4',
		'standard-5' => 'The Original 5',
		'standard-6' => 'The Original 6',
		'mesh-1' => 'Color Story 1',
		'mesh-2' => 'Color Story 2',
		'mesh-3' => 'Color Story 3',
		'mighty-1' => 'Mighty Good 1',
		'mighty-2' => 'Mighty Good 2',
		'mighty-3' => 'Mighty Good 3',
		'basic-1' => 'Everyday 1',
		'basic-2' => 'Everyday 2',
		'basic-3' => 'Everyday 3',
		'basic-4' => 'Everyday 4',
		'basic-5' => 'Everyday 5',
		'basic-6' => 'Everyday 6',
		'clean-1' => 'Clean Slate 1',
		'clean-2' => 'Clean Slate 2',
		'clean-3' => 'Clean Slate 3',
		'bold-1' => 'Big Energy 1',
		'bold-2' => 'Big Energy 2',
		'bold-3' => 'Big Energy 3',
		'float-1' => 'Floating Ideas 1',
		'float-2' => 'Floating Ideas 2',
		'float-3' => 'Floating Ideas 3',
		'business-1' => 'Good Business 1',
		'business-2' => 'Good Business 2',
		'travel-1' => 'Out There 1',
		'8bit-1' => 'Pixel Party 1',
		'8bit-2' => 'Pixel Party 2',
		'bar-1' => 'The Headliner 1',
		'bar-2' => 'The Headliner 2',
		'bar-3' => 'The Headliner 3',
		'news-1' => 'Front Page 1',
		'news-2' => 'Front Page 2',
		'editorial-1' => 'The Editorial 1',
		'go-1' => 'On the Go 1',
		'product-1' => 'Shelf Appeal 1',
		'tweet-1' => 'Social Proof 1',
	);

	// Grab templates for MightyShare: id => display name.
	public function mightyshare_templates() {
		$theme_options                    = self::template_catalog();
		$theme_options['screenshot-self'] = 'Use a screenshot of the current page';

		return apply_filters( 'mightyshare_templates', $theme_options );
	}

	/**
	 * Live template list from mightyshare.io, cached for 12 hours, so new
	 * templates appear without a plugin release. Only fetched in admin/cron/CLI
	 * (never during a front-end page view); falls back to the built-in list.
	 */
	protected static function template_catalog() {
		static $catalog = null;

		if ( null !== $catalog ) {
			return $catalog;
		}

		$cached = get_transient( 'mightyshare_template_catalog' );
		if ( is_array( $cached ) && $cached ) {
			$catalog = $cached;
			return $catalog;
		}

		$catalog = self::FALLBACK_TEMPLATES;

		$can_fetch = ( is_admin() && ! wp_doing_ajax() ) || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI );
		if ( ! $can_fetch ) {
			return $catalog;
		}

		$response = wp_remote_get( MIGHTYSHARE_TEMPLATES_API, array( 'timeout' => 3 ) );
		$body     = is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ? null : json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['templates'] ) || ! is_array( $body['templates'] ) ) {
			// Retry in an hour rather than on every admin page view.
			set_transient( 'mightyshare_template_catalog', $catalog, HOUR_IN_SECONDS );
			return $catalog;
		}

		$remote = array();
		foreach ( $body['templates'] as $template ) {
			if ( ! empty( $template['id'] ) && is_string( $template['id'] ) ) {
				$id            = sanitize_key( $template['id'] );
				$remote[ $id ] = ! empty( $template['name'] ) && is_string( $template['name'] ) ? sanitize_text_field( $template['name'] ) : $id;
			}
		}

		if ( $remote ) {
			// Known templates keep their familiar order; new ones go at the end.
			$catalog = array_merge( array_intersect_key( self::FALLBACK_TEMPLATES, $remote ), $remote );
		}

		set_transient( 'mightyshare_template_catalog', $catalog, 12 * HOUR_IN_SECONDS );

		return $catalog;
	}
	
	/**
	 * Per-request cache of the active plugins list, filtered through
	 * 'active_plugins' (so MU plugins / multisite can override).
	 *
	 * Avoids dozens of get_option() + apply_filters() calls per page load.
	 */
	protected static function active_plugins() {
		static $active = null;

		if ( null === $active ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core's own filter.
			$active = (array) apply_filters( 'active_plugins', (array) get_option( 'active_plugins', array() ) );
		}

		return $active;
	}

	public function isYoastSEOActive() {
		$active = self::active_plugins();
		return in_array( 'wordpress-seo/wp-seo.php', $active, true ) || in_array( 'wordpress-seo-premium/wp-seo-premium.php', $active, true ) || defined( 'WPSEO_VERSION' );
	}

	public function isRankMathActive() {
		return in_array( 'seo-by-rank-math/rank-math.php', self::active_plugins(), true ) || defined( 'RANK_MATH_VERSION' );
	}

	public function isAllInOneSEOActive() {
		$active = self::active_plugins();
		return in_array( 'all-in-one-seo-pack/all_in_one_seo_pack.php', $active, true ) || in_array( 'all-in-one-seo-pack-pro/all_in_one_seo_pack.php', $active, true ) || defined( 'AIOSEO_PHP_VERSION_DIR' );
	}

	public function isTheSEOFrameworkActive() {
		return in_array( 'autodescription/autodescription.php', self::active_plugins(), true ) || defined( 'THE_SEO_FRAMEWORK_VERSION' );
	}

	public function isSlimSEOActive() {
		return in_array( 'slim-seo/slim-seo.php', self::active_plugins(), true ) || defined( 'SLIM_SEO_VER' );
	}

	public function isSEOPressActive() {
		return in_array( 'wp-seopress/seopress.php', self::active_plugins(), true ) || defined( 'SEOPRESS_VERSION' );
	}

	public function isSquirrlyActive() {
		return in_array( 'squirrly-seo/squirrly.php', self::active_plugins(), true ) || defined( 'SQUIRRLY_VERSION' ) || defined( 'SQ_VERSION' );
	}

	public function mightyshare_template_options( $value, $setting_prefix ) {
		$options             = get_option( 'mightyshare' );
		$mightyshare_globals = new Mightyshare_Globals();
		$template_options    = $mightyshare_globals->mightyshare_templates();

		$google_fonts_list = array(
			'' => 'Default',
			'template-default' => 'Template Default',
			'ABeeZee' => 'ABeeZee',
			'Abel' => 'Abel',
			'Abhaya Libre' => 'Abhaya Libre',
			'Abril Fatface' => 'Abril Fatface',
			'Aclonica' => 'Aclonica',
			'Acme' => 'Acme',
			'Actor' => 'Actor',
			'Adamina' => 'Adamina',
			'Advent Pro' => 'Advent Pro',
			'Aguafina Script' => 'Aguafina Script',
			'Akaya Kanadaka' => 'Akaya Kanadaka',
			'Akaya Telivigala' => 'Akaya Telivigala',
			'Akronim' => 'Akronim',
			'Akshar' => 'Akshar',
			'Aladin' => 'Aladin',
			'Alata' => 'Alata',
			'Alatsi' => 'Alatsi',
			'Aldrich' => 'Aldrich',
			'Alef' => 'Alef',
			'Alegreya' => 'Alegreya',
			'Alegreya SC' => 'Alegreya SC',
			'Alegreya Sans' => 'Alegreya Sans',
			'Alegreya Sans SC' => 'Alegreya Sans SC',
			'Aleo' => 'Aleo',
			'Alex Brush' => 'Alex Brush',
			'Alfa Slab One' => 'Alfa Slab One',
			'Alice' => 'Alice',
			'Alike' => 'Alike',
			'Alike Angular' => 'Alike Angular',
			'Allan' => 'Allan',
			'Allerta' => 'Allerta',
			'Allerta Stencil' => 'Allerta Stencil',
			'Allison' => 'Allison',
			'Allura' => 'Allura',
			'Almarai' => 'Almarai',
			'Almendra' => 'Almendra',
			'Almendra Display' => 'Almendra Display',
			'Almendra SC' => 'Almendra SC',
			'Alumni Sans' => 'Alumni Sans',
			'Alumni Sans Inline One' => 'Alumni Sans Inline One',
			'Amarante' => 'Amarante',
			'Amaranth' => 'Amaranth',
			'Amatic SC' => 'Amatic SC',
			'Amethysta' => 'Amethysta',
			'Amiko' => 'Amiko',
			'Amiri' => 'Amiri',
			'Amita' => 'Amita',
			'Anaheim' => 'Anaheim',
			'Andada Pro' => 'Andada Pro',
			'Andika' => 'Andika',
			'Andika New Basic' => 'Andika New Basic',
			'Anek Bangla' => 'Anek Bangla',
			'Anek Devanagari' => 'Anek Devanagari',
			'Anek Gujarati' => 'Anek Gujarati',
			'Anek Gurmukhi' => 'Anek Gurmukhi',
			'Anek Kannada' => 'Anek Kannada',
			'Anek Latin' => 'Anek Latin',
			'Anek Malayalam' => 'Anek Malayalam',
			'Anek Odia' => 'Anek Odia',
			'Anek Tamil' => 'Anek Tamil',
			'Anek Telugu' => 'Anek Telugu',
			'Angkor' => 'Angkor',
			'Annie Use Your Telescope' => 'Annie Use Your Telescope',
			'Anonymous Pro' => 'Anonymous Pro',
			'Antic' => 'Antic',
			'Antic Didone' => 'Antic Didone',
			'Antic Slab' => 'Antic Slab',
			'Anton' => 'Anton',
			'Antonio' => 'Antonio',
			'Anybody' => 'Anybody',
			'Arapey' => 'Arapey',
			'Arbutus' => 'Arbutus',
			'Arbutus Slab' => 'Arbutus Slab',
			'Architects Daughter' => 'Architects Daughter',
			'Archivo' => 'Archivo',
			'Archivo Black' => 'Archivo Black',
			'Archivo Narrow' => 'Archivo Narrow',
			'Are You Serious' => 'Are You Serious',
			'Aref Ruqaa' => 'Aref Ruqaa',
			'Arima Madurai' => 'Arima Madurai',
			'Arimo' => 'Arimo',
			'Arizonia' => 'Arizonia',
			'Armata' => 'Armata',
			'Arsenal' => 'Arsenal',
			'Artifika' => 'Artifika',
			'Arvo' => 'Arvo',
			'Arya' => 'Arya',
			'Asap' => 'Asap',
			'Asap Condensed' => 'Asap Condensed',
			'Asar' => 'Asar',
			'Asset' => 'Asset',
			'Assistant' => 'Assistant',
			'Astloch' => 'Astloch',
			'Asul' => 'Asul',
			'Athiti' => 'Athiti',
			'Atkinson Hyperlegible' => 'Atkinson Hyperlegible',
			'Atma' => 'Atma',
			'Atomic Age' => 'Atomic Age',
			'Aubrey' => 'Aubrey',
			'Audiowide' => 'Audiowide',
			'Autour One' => 'Autour One',
			'Average' => 'Average',
			'Average Sans' => 'Average Sans',
			'Averia Gruesa Libre' => 'Averia Gruesa Libre',
			'Averia Libre' => 'Averia Libre',
			'Averia Sans Libre' => 'Averia Sans Libre',
			'Averia Serif Libre' => 'Averia Serif Libre',
			'Azeret Mono' => 'Azeret Mono',
			'B612' => 'B612',
			'B612 Mono' => 'B612 Mono',
			'BIZ UDGothic' => 'BIZ UDGothic',
			'BIZ UDMincho' => 'BIZ UDMincho',
			'BIZ UDPGothic' => 'BIZ UDPGothic',
			'BIZ UDPMincho' => 'BIZ UDPMincho',
			'Babylonica' => 'Babylonica',
			'Bad Script' => 'Bad Script',
			'Bahiana' => 'Bahiana',
			'Bahianita' => 'Bahianita',
			'Bai Jamjuree' => 'Bai Jamjuree',
			'Bakbak One' => 'Bakbak One',
			'Ballet' => 'Ballet',
			'Baloo 2' => 'Baloo 2',
			'Baloo Bhai 2' => 'Baloo Bhai 2',
			'Baloo Bhaijaan 2' => 'Baloo Bhaijaan 2',
			'Baloo Bhaina 2' => 'Baloo Bhaina 2',
			'Baloo Chettan 2' => 'Baloo Chettan 2',
			'Baloo Da 2' => 'Baloo Da 2',
			'Baloo Paaji 2' => 'Baloo Paaji 2',
			'Baloo Tamma 2' => 'Baloo Tamma 2',
			'Baloo Tammudu 2' => 'Baloo Tammudu 2',
			'Baloo Thambi 2' => 'Baloo Thambi 2',
			'Balsamiq Sans' => 'Balsamiq Sans',
			'Balthazar' => 'Balthazar',
			'Bangers' => 'Bangers',
			'Barlow' => 'Barlow',
			'Barlow Condensed' => 'Barlow Condensed',
			'Barlow Semi Condensed' => 'Barlow Semi Condensed',
			'Barriecito' => 'Barriecito',
			'Barrio' => 'Barrio',
			'Basic' => 'Basic',
			'Baskervville' => 'Baskervville',
			'Battambang' => 'Battambang',
			'Baumans' => 'Baumans',
			'Bayon' => 'Bayon',
			'Be Vietnam Pro' => 'Be Vietnam Pro',
			'Beau Rivage' => 'Beau Rivage',
			'Bebas Neue' => 'Bebas Neue',
			'Belgrano' => 'Belgrano',
			'Bellefair' => 'Bellefair',
			'Belleza' => 'Belleza',
			'Bellota' => 'Bellota',
			'Bellota Text' => 'Bellota Text',
			'BenchNine' => 'BenchNine',
			'Benne' => 'Benne',
			'Bentham' => 'Bentham',
			'Berkshire Swash' => 'Berkshire Swash',
			'Besley' => 'Besley',
			'Beth Ellen' => 'Beth Ellen',
			'Bevan' => 'Bevan',
			'BhuTuka Expanded One' => 'BhuTuka Expanded One',
			'Big Shoulders Display' => 'Big Shoulders Display',
			'Big Shoulders Inline Display' => 'Big Shoulders Inline Display',
			'Big Shoulders Inline Text' => 'Big Shoulders Inline Text',
			'Big Shoulders Stencil Display' => 'Big Shoulders Stencil Display',
			'Big Shoulders Stencil Text' => 'Big Shoulders Stencil Text',
			'Big Shoulders Text' => 'Big Shoulders Text',
			'Bigelow Rules' => 'Bigelow Rules',
			'Bigshot One' => 'Bigshot One',
			'Bilbo' => 'Bilbo',
			'Bilbo Swash Caps' => 'Bilbo Swash Caps',
			'BioRhyme' => 'BioRhyme',
			'BioRhyme Expanded' => 'BioRhyme Expanded',
			'Birthstone' => 'Birthstone',
			'Birthstone Bounce' => 'Birthstone Bounce',
			'Biryani' => 'Biryani',
			'Bitter' => 'Bitter',
			'Black And White Picture' => 'Black And White Picture',
			'Black Han Sans' => 'Black Han Sans',
			'Black Ops One' => 'Black Ops One',
			'Blaka' => 'Blaka',
			'Blaka Hollow' => 'Blaka Hollow',
			'Blinker' => 'Blinker',
			'Bodoni Moda' => 'Bodoni Moda',
			'Bokor' => 'Bokor',
			'Bona Nova' => 'Bona Nova',
			'Bonbon' => 'Bonbon',
			'Bonheur Royale' => 'Bonheur Royale',
			'Boogaloo' => 'Boogaloo',
			'Bowlby One' => 'Bowlby One',
			'Bowlby One SC' => 'Bowlby One SC',
			'Brawler' => 'Brawler',
			'Bree Serif' => 'Bree Serif',
			'Brygada 1918' => 'Brygada 1918',
			'Bubblegum Sans' => 'Bubblegum Sans',
			'Bubbler One' => 'Bubbler One',
			'Buda' => 'Buda',
			'Buenard' => 'Buenard',
			'Bungee' => 'Bungee',
			'Bungee Hairline' => 'Bungee Hairline',
			'Bungee Inline' => 'Bungee Inline',
			'Bungee Outline' => 'Bungee Outline',
			'Bungee Shade' => 'Bungee Shade',
			'Butcherman' => 'Butcherman',
			'Butterfly Kids' => 'Butterfly Kids',
			'Cabin' => 'Cabin',
			'Cabin Condensed' => 'Cabin Condensed',
			'Cabin Sketch' => 'Cabin Sketch',
			'Caesar Dressing' => 'Caesar Dressing',
			'Cagliostro' => 'Cagliostro',
			'Cairo' => 'Cairo',
			'Caladea' => 'Caladea',
			'Calistoga' => 'Calistoga',
			'Calligraffitti' => 'Calligraffitti',
			'Cambay' => 'Cambay',
			'Cambo' => 'Cambo',
			'Candal' => 'Candal',
			'Cantarell' => 'Cantarell',
			'Cantata One' => 'Cantata One',
			'Cantora One' => 'Cantora One',
			'Capriola' => 'Capriola',
			'Caramel' => 'Caramel',
			'Carattere' => 'Carattere',
			'Cardo' => 'Cardo',
			'Carme' => 'Carme',
			'Carrois Gothic' => 'Carrois Gothic',
			'Carrois Gothic SC' => 'Carrois Gothic SC',
			'Carter One' => 'Carter One',
			'Castoro' => 'Castoro',
			'Catamaran' => 'Catamaran',
			'Caudex' => 'Caudex',
			'Caveat' => 'Caveat',
			'Caveat Brush' => 'Caveat Brush',
			'Cedarville Cursive' => 'Cedarville Cursive',
			'Ceviche One' => 'Ceviche One',
			'Chakra Petch' => 'Chakra Petch',
			'Changa' => 'Changa',
			'Changa One' => 'Changa One',
			'Chango' => 'Chango',
			'Charis SIL' => 'Charis SIL',
			'Charm' => 'Charm',
			'Charmonman' => 'Charmonman',
			'Chathura' => 'Chathura',
			'Chau Philomene One' => 'Chau Philomene One',
			'Chela One' => 'Chela One',
			'Chelsea Market' => 'Chelsea Market',
			'Chenla' => 'Chenla',
			'Cherish' => 'Cherish',
			'Cherry Cream Soda' => 'Cherry Cream Soda',
			'Cherry Swash' => 'Cherry Swash',
			'Chewy' => 'Chewy',
			'Chicle' => 'Chicle',
			'Chilanka' => 'Chilanka',
			'Chivo' => 'Chivo',
			'Chonburi' => 'Chonburi',
			'Cinzel' => 'Cinzel',
			'Cinzel Decorative' => 'Cinzel Decorative',
			'Clicker Script' => 'Clicker Script',
			'Coda' => 'Coda',
			'Coda Caption' => 'Coda Caption',
			'Codystar' => 'Codystar',
			'Coiny' => 'Coiny',
			'Combo' => 'Combo',
			'Comfortaa' => 'Comfortaa',
			'Comforter' => 'Comforter',
			'Comforter Brush' => 'Comforter Brush',
			'Comic Neue' => 'Comic Neue',
			'Coming Soon' => 'Coming Soon',
			'Commissioner' => 'Commissioner',
			'Concert One' => 'Concert One',
			'Condiment' => 'Condiment',
			'Content' => 'Content',
			'Contrail One' => 'Contrail One',
			'Convergence' => 'Convergence',
			'Cookie' => 'Cookie',
			'Copse' => 'Copse',
			'Corben' => 'Corben',
			'Corinthia' => 'Corinthia',
			'Cormorant' => 'Cormorant',
			'Cormorant Garamond' => 'Cormorant Garamond',
			'Cormorant Infant' => 'Cormorant Infant',
			'Cormorant SC' => 'Cormorant SC',
			'Cormorant Unicase' => 'Cormorant Unicase',
			'Cormorant Upright' => 'Cormorant Upright',
			'Courgette' => 'Courgette',
			'Courier Prime' => 'Courier Prime',
			'Cousine' => 'Cousine',
			'Coustard' => 'Coustard',
			'Covered By Your Grace' => 'Covered By Your Grace',
			'Crafty Girls' => 'Crafty Girls',
			'Creepster' => 'Creepster',
			'Crete Round' => 'Crete Round',
			'Crimson Pro' => 'Crimson Pro',
			'Crimson Text' => 'Crimson Text',
			'Croissant One' => 'Croissant One',
			'Crushed' => 'Crushed',
			'Cuprum' => 'Cuprum',
			'Cute Font' => 'Cute Font',
			'Cutive' => 'Cutive',
			'Cutive Mono' => 'Cutive Mono',
			'DM Mono' => 'DM Mono',
			'DM Sans' => 'DM Sans',
			'DM Serif Display' => 'DM Serif Display',
			'DM Serif Text' => 'DM Serif Text',
			'Damion' => 'Damion',
			'Dancing Script' => 'Dancing Script',
			'Dangrek' => 'Dangrek',
			'Darker Grotesque' => 'Darker Grotesque',
			'David Libre' => 'David Libre',
			'Dawning of a New Day' => 'Dawning of a New Day',
			'Days One' => 'Days One',
			'Dekko' => 'Dekko',
			'Dela Gothic One' => 'Dela Gothic One',
			'Delius' => 'Delius',
			'Delius Swash Caps' => 'Delius Swash Caps',
			'Delius Unicase' => 'Delius Unicase',
			'Della Respira' => 'Della Respira',
			'Denk One' => 'Denk One',
			'Devonshire' => 'Devonshire',
			'Dhurjati' => 'Dhurjati',
			'Didact Gothic' => 'Didact Gothic',
			'Diplomata' => 'Diplomata',
			'Diplomata SC' => 'Diplomata SC',
			'Do Hyeon' => 'Do Hyeon',
			'Dokdo' => 'Dokdo',
			'Domine' => 'Domine',
			'Donegal One' => 'Donegal One',
			'Dongle' => 'Dongle',
			'Doppio One' => 'Doppio One',
			'Dorsa' => 'Dorsa',
			'Dosis' => 'Dosis',
			'DotGothic16' => 'DotGothic16',
			'Dr Sugiyama' => 'Dr Sugiyama',
			'Duru Sans' => 'Duru Sans',
			'Dynalight' => 'Dynalight',
			'EB Garamond' => 'EB Garamond',
			'Eagle Lake' => 'Eagle Lake',
			'East Sea Dokdo' => 'East Sea Dokdo',
			'Eater' => 'Eater',
			'Economica' => 'Economica',
			'Eczar' => 'Eczar',
			'El Messiri' => 'El Messiri',
			'Electrolize' => 'Electrolize',
			'Elsie' => 'Elsie',
			'Elsie Swash Caps' => 'Elsie Swash Caps',
			'Emblema One' => 'Emblema One',
			'Emilys Candy' => 'Emilys Candy',
			'Encode Sans' => 'Encode Sans',
			'Encode Sans Condensed' => 'Encode Sans Condensed',
			'Encode Sans Expanded' => 'Encode Sans Expanded',
			'Encode Sans SC' => 'Encode Sans SC',
			'Encode Sans Semi Condensed' => 'Encode Sans Semi Condensed',
			'Encode Sans Semi Expanded' => 'Encode Sans Semi Expanded',
			'Engagement' => 'Engagement',
			'Englebert' => 'Englebert',
			'Enriqueta' => 'Enriqueta',
			'Ephesis' => 'Ephesis',
			'Epilogue' => 'Epilogue',
			'Erica One' => 'Erica One',
			'Esteban' => 'Esteban',
			'Estonia' => 'Estonia',
			'Euphoria Script' => 'Euphoria Script',
			'Ewert' => 'Ewert',
			'Exo' => 'Exo',
			'Exo 2' => 'Exo 2',
			'Expletus Sans' => 'Expletus Sans',
			'Explora' => 'Explora',
			'Fahkwang' => 'Fahkwang',
			'Familjen Grotesk' => 'Familjen Grotesk',
			'Fanwood Text' => 'Fanwood Text',
			'Farro' => 'Farro',
			'Farsan' => 'Farsan',
			'Fascinate' => 'Fascinate',
			'Fascinate Inline' => 'Fascinate Inline',
			'Faster One' => 'Faster One',
			'Fasthand' => 'Fasthand',
			'Fauna One' => 'Fauna One',
			'Faustina' => 'Faustina',
			'Federant' => 'Federant',
			'Federo' => 'Federo',
			'Felipa' => 'Felipa',
			'Fenix' => 'Fenix',
			'Festive' => 'Festive',
			'Finger Paint' => 'Finger Paint',
			'Fira Code' => 'Fira Code',
			'Fira Mono' => 'Fira Mono',
			'Fira Sans' => 'Fira Sans',
			'Fira Sans Condensed' => 'Fira Sans Condensed',
			'Fira Sans Extra Condensed' => 'Fira Sans Extra Condensed',
			'Fjalla One' => 'Fjalla One',
			'Fjord One' => 'Fjord One',
			'Flamenco' => 'Flamenco',
			'Flavors' => 'Flavors',
			'Fleur De Leah' => 'Fleur De Leah',
			'Flow Block' => 'Flow Block',
			'Flow Circular' => 'Flow Circular',
			'Flow Rounded' => 'Flow Rounded',
			'Fondamento' => 'Fondamento',
			'Fontdiner Swanky' => 'Fontdiner Swanky',
			'Forum' => 'Forum',
			'Francois One' => 'Francois One',
			'Frank Ruhl Libre' => 'Frank Ruhl Libre',
			'Fraunces' => 'Fraunces',
			'Freckle Face' => 'Freckle Face',
			'Fredericka the Great' => 'Fredericka the Great',
			'Fredoka' => 'Fredoka',
			'Fredoka One' => 'Fredoka One',
			'Freehand' => 'Freehand',
			'Fresca' => 'Fresca',
			'Frijole' => 'Frijole',
			'Fruktur' => 'Fruktur',
			'Fugaz One' => 'Fugaz One',
			'Fuggles' => 'Fuggles',
			'Fuzzy Bubbles' => 'Fuzzy Bubbles',
			'GFS Didot' => 'GFS Didot',
			'GFS Neohellenic' => 'GFS Neohellenic',
			'Gabriela' => 'Gabriela',
			'Gaegu' => 'Gaegu',
			'Gafata' => 'Gafata',
			'Galada' => 'Galada',
			'Galdeano' => 'Galdeano',
			'Galindo' => 'Galindo',
			'Gamja Flower' => 'Gamja Flower',
			'Gayathri' => 'Gayathri',
			'Gelasio' => 'Gelasio',
			'Gemunu Libre' => 'Gemunu Libre',
			'Genos' => 'Genos',
			'Gentium Basic' => 'Gentium Basic',
			'Gentium Book Basic' => 'Gentium Book Basic',
			'Gentium Plus' => 'Gentium Plus',
			'Geo' => 'Geo',
			'Georama' => 'Georama',
			'Geostar' => 'Geostar',
			'Geostar Fill' => 'Geostar Fill',
			'Germania One' => 'Germania One',
			'Gideon Roman' => 'Gideon Roman',
			'Gidugu' => 'Gidugu',
			'Gilda Display' => 'Gilda Display',
			'Girassol' => 'Girassol',
			'Give You Glory' => 'Give You Glory',
			'Glass Antiqua' => 'Glass Antiqua',
			'Glegoo' => 'Glegoo',
			'Gloria Hallelujah' => 'Gloria Hallelujah',
			'Glory' => 'Glory',
			'Gluten' => 'Gluten',
			'Goblin One' => 'Goblin One',
			'Gochi Hand' => 'Gochi Hand',
			'Goldman' => 'Goldman',
			'Gorditas' => 'Gorditas',
			'Gothic A1' => 'Gothic A1',
			'Gotu' => 'Gotu',
			'Goudy Bookletter 1911' => 'Goudy Bookletter 1911',
			'Gowun Batang' => 'Gowun Batang',
			'Gowun Dodum' => 'Gowun Dodum',
			'Graduate' => 'Graduate',
			'Grand Hotel' => 'Grand Hotel',
			'Grandstander' => 'Grandstander',
			'Grape Nuts' => 'Grape Nuts',
			'Gravitas One' => 'Gravitas One',
			'Great Vibes' => 'Great Vibes',
			'Grechen Fuemen' => 'Grechen Fuemen',
			'Grenze' => 'Grenze',
			'Grenze Gotisch' => 'Grenze Gotisch',
			'Grey Qo' => 'Grey Qo',
			'Griffy' => 'Griffy',
			'Gruppo' => 'Gruppo',
			'Gudea' => 'Gudea',
			'Gugi' => 'Gugi',
			'Gupter' => 'Gupter',
			'Gurajada' => 'Gurajada',
			'Gwendolyn' => 'Gwendolyn',
			'Habibi' => 'Habibi',
			'Hachi Maru Pop' => 'Hachi Maru Pop',
			'Hahmlet' => 'Hahmlet',
			'Halant' => 'Halant',
			'Hammersmith One' => 'Hammersmith One',
			'Hanalei' => 'Hanalei',
			'Hanalei Fill' => 'Hanalei Fill',
			'Handlee' => 'Handlee',
			'Hanuman' => 'Hanuman',
			'Happy Monkey' => 'Happy Monkey',
			'Harmattan' => 'Harmattan',
			'Headland One' => 'Headland One',
			'Heebo' => 'Heebo',
			'Henny Penny' => 'Henny Penny',
			'Hepta Slab' => 'Hepta Slab',
			'Herr Von Muellerhoff' => 'Herr Von Muellerhoff',
			'Hi Melody' => 'Hi Melody',
			'Hina Mincho' => 'Hina Mincho',
			'Hind' => 'Hind',
			'Hind Guntur' => 'Hind Guntur',
			'Hind Madurai' => 'Hind Madurai',
			'Hind Siliguri' => 'Hind Siliguri',
			'Hind Vadodara' => 'Hind Vadodara',
			'Holtwood One SC' => 'Holtwood One SC',
			'Homemade Apple' => 'Homemade Apple',
			'Homenaje' => 'Homenaje',
			'Hubballi' => 'Hubballi',
			'Hurricane' => 'Hurricane',
			'IBM Plex Mono' => 'IBM Plex Mono',
			'IBM Plex Sans' => 'IBM Plex Sans',
			'IBM Plex Sans Arabic' => 'IBM Plex Sans Arabic',
			'IBM Plex Sans Condensed' => 'IBM Plex Sans Condensed',
			'IBM Plex Sans Devanagari' => 'IBM Plex Sans Devanagari',
			'IBM Plex Sans Hebrew' => 'IBM Plex Sans Hebrew',
			'IBM Plex Sans KR' => 'IBM Plex Sans KR',
			'IBM Plex Sans Thai' => 'IBM Plex Sans Thai',
			'IBM Plex Sans Thai Looped' => 'IBM Plex Sans Thai Looped',
			'IBM Plex Serif' => 'IBM Plex Serif',
			'IM Fell DW Pica' => 'IM Fell DW Pica',
			'IM Fell DW Pica SC' => 'IM Fell DW Pica SC',
			'IM Fell Double Pica' => 'IM Fell Double Pica',
			'IM Fell Double Pica SC' => 'IM Fell Double Pica SC',
			'IM Fell English' => 'IM Fell English',
			'IM Fell English SC' => 'IM Fell English SC',
			'IM Fell French Canon' => 'IM Fell French Canon',
			'IM Fell French Canon SC' => 'IM Fell French Canon SC',
			'IM Fell Great Primer' => 'IM Fell Great Primer',
			'IM Fell Great Primer SC' => 'IM Fell Great Primer SC',
			'Ibarra Real Nova' => 'Ibarra Real Nova',
			'Iceberg' => 'Iceberg',
			'Iceland' => 'Iceland',
			'Imbue' => 'Imbue',
			'Imperial Script' => 'Imperial Script',
			'Imprima' => 'Imprima',
			'Inconsolata' => 'Inconsolata',
			'Inder' => 'Inder',
			'Indie Flower' => 'Indie Flower',
			'Ingrid Darling' => 'Ingrid Darling',
			'Inika' => 'Inika',
			'Inknut Antiqua' => 'Inknut Antiqua',
			'Inria Sans' => 'Inria Sans',
			'Inria Serif' => 'Inria Serif',
			'Inspiration' => 'Inspiration',
			'Inter' => 'Inter',
			'Irish Grover' => 'Irish Grover',
			'Island Moments' => 'Island Moments',
			'Istok Web' => 'Istok Web',
			'Italiana' => 'Italiana',
			'Italianno' => 'Italianno',
			'Itim' => 'Itim',
			'Jacques Francois' => 'Jacques Francois',
			'Jacques Francois Shadow' => 'Jacques Francois Shadow',
			'Jaldi' => 'Jaldi',
			'JetBrains Mono' => 'JetBrains Mono',
			'Jim Nightshade' => 'Jim Nightshade',
			'Joan' => 'Joan',
			'Jockey One' => 'Jockey One',
			'Jolly Lodger' => 'Jolly Lodger',
			'Jomhuria' => 'Jomhuria',
			'Jomolhari' => 'Jomolhari',
			'Josefin Sans' => 'Josefin Sans',
			'Josefin Slab' => 'Josefin Slab',
			'Jost' => 'Jost',
			'Joti One' => 'Joti One',
			'Jua' => 'Jua',
			'Judson' => 'Judson',
			'Julee' => 'Julee',
			'Julius Sans One' => 'Julius Sans One',
			'Junge' => 'Junge',
			'Jura' => 'Jura',
			'Just Another Hand' => 'Just Another Hand',
			'Just Me Again Down Here' => 'Just Me Again Down Here',
			'K2D' => 'K2D',
			'Kadwa' => 'Kadwa',
			'Kaisei Decol' => 'Kaisei Decol',
			'Kaisei HarunoUmi' => 'Kaisei HarunoUmi',
			'Kaisei Opti' => 'Kaisei Opti',
			'Kaisei Tokumin' => 'Kaisei Tokumin',
			'Kalam' => 'Kalam',
			'Kameron' => 'Kameron',
			'Kanit' => 'Kanit',
			'Kantumruy' => 'Kantumruy',
			'Karantina' => 'Karantina',
			'Karla' => 'Karla',
			'Karma' => 'Karma',
			'Katibeh' => 'Katibeh',
			'Kaushan Script' => 'Kaushan Script',
			'Kavivanar' => 'Kavivanar',
			'Kavoon' => 'Kavoon',
			'Kdam Thmor' => 'Kdam Thmor',
			'Kdam Thmor Pro' => 'Kdam Thmor Pro',
			'Keania One' => 'Keania One',
			'Kelly Slab' => 'Kelly Slab',
			'Kenia' => 'Kenia',
			'Khand' => 'Khand',
			'Khmer' => 'Khmer',
			'Khula' => 'Khula',
			'Kings' => 'Kings',
			'Kirang Haerang' => 'Kirang Haerang',
			'Kite One' => 'Kite One',
			'Kiwi Maru' => 'Kiwi Maru',
			'Klee One' => 'Klee One',
			'Knewave' => 'Knewave',
			'KoHo' => 'KoHo',
			'Kodchasan' => 'Kodchasan',
			'Koh Santepheap' => 'Koh Santepheap',
			'Kolker Brush' => 'Kolker Brush',
			'Kosugi' => 'Kosugi',
			'Kosugi Maru' => 'Kosugi Maru',
			'Kotta One' => 'Kotta One',
			'Koulen' => 'Koulen',
			'Kranky' => 'Kranky',
			'Kreon' => 'Kreon',
			'Kristi' => 'Kristi',
			'Krona One' => 'Krona One',
			'Krub' => 'Krub',
			'Kufam' => 'Kufam',
			'Kulim Park' => 'Kulim Park',
			'Kumar One' => 'Kumar One',
			'Kumar One Outline' => 'Kumar One Outline',
			'Kumbh Sans' => 'Kumbh Sans',
			'Kurale' => 'Kurale',
			'La Belle Aurore' => 'La Belle Aurore',
			'Lacquer' => 'Lacquer',
			'Laila' => 'Laila',
			'Lakki Reddy' => 'Lakki Reddy',
			'Lalezar' => 'Lalezar',
			'Lancelot' => 'Lancelot',
			'Langar' => 'Langar',
			'Lateef' => 'Lateef',
			'Lato' => 'Lato',
			'Lavishly Yours' => 'Lavishly Yours',
			'League Gothic' => 'League Gothic',
			'League Script' => 'League Script',
			'League Spartan' => 'League Spartan',
			'Leckerli One' => 'Leckerli One',
			'Ledger' => 'Ledger',
			'Lekton' => 'Lekton',
			'Lemon' => 'Lemon',
			'Lemonada' => 'Lemonada',
			'Lexend' => 'Lexend',
			'Lexend Deca' => 'Lexend Deca',
			'Lexend Exa' => 'Lexend Exa',
			'Lexend Giga' => 'Lexend Giga',
			'Lexend Mega' => 'Lexend Mega',
			'Lexend Peta' => 'Lexend Peta',
			'Lexend Tera' => 'Lexend Tera',
			'Lexend Zetta' => 'Lexend Zetta',
			'Libre Barcode 128' => 'Libre Barcode 128',
			'Libre Barcode 128 Text' => 'Libre Barcode 128 Text',
			'Libre Barcode 39' => 'Libre Barcode 39',
			'Libre Barcode 39 Extended' => 'Libre Barcode 39 Extended',
			'Libre Barcode 39 Extended Text' => 'Libre Barcode 39 Extended Text',
			'Libre Barcode 39 Text' => 'Libre Barcode 39 Text',
			'Libre Barcode EAN13 Text' => 'Libre Barcode EAN13 Text',
			'Libre Baskerville' => 'Libre Baskerville',
			'Libre Bodoni' => 'Libre Bodoni',
			'Libre Caslon Display' => 'Libre Caslon Display',
			'Libre Caslon Text' => 'Libre Caslon Text',
			'Libre Franklin' => 'Libre Franklin',
			'Licorice' => 'Licorice',
			'Life Savers' => 'Life Savers',
			'Lilita One' => 'Lilita One',
			'Lily Script One' => 'Lily Script One',
			'Limelight' => 'Limelight',
			'Linden Hill' => 'Linden Hill',
			'Literata' => 'Literata',
			'Liu Jian Mao Cao' => 'Liu Jian Mao Cao',
			'Livvic' => 'Livvic',
			'Lobster' => 'Lobster',
			'Lobster Two' => 'Lobster Two',
			'Londrina Outline' => 'Londrina Outline',
			'Londrina Shadow' => 'Londrina Shadow',
			'Londrina Sketch' => 'Londrina Sketch',
			'Londrina Solid' => 'Londrina Solid',
			'Long Cang' => 'Long Cang',
			'Lora' => 'Lora',
			'Love Light' => 'Love Light',
			'Love Ya Like A Sister' => 'Love Ya Like A Sister',
			'Loved by the King' => 'Loved by the King',
			'Lovers Quarrel' => 'Lovers Quarrel',
			'Luckiest Guy' => 'Luckiest Guy',
			'Lusitana' => 'Lusitana',
			'Lustria' => 'Lustria',
			'Luxurious Roman' => 'Luxurious Roman',
			'Luxurious Script' => 'Luxurious Script',
			'M PLUS 1' => 'M PLUS 1',
			'M PLUS 1 Code' => 'M PLUS 1 Code',
			'M PLUS 1p' => 'M PLUS 1p',
			'M PLUS 2' => 'M PLUS 2',
			'M PLUS Code Latin' => 'M PLUS Code Latin',
			'M PLUS Rounded 1c' => 'M PLUS Rounded 1c',
			'Ma Shan Zheng' => 'Ma Shan Zheng',
			'Macondo' => 'Macondo',
			'Macondo Swash Caps' => 'Macondo Swash Caps',
			'Mada' => 'Mada',
			'Magra' => 'Magra',
			'Maiden Orange' => 'Maiden Orange',
			'Maitree' => 'Maitree',
			'Major Mono Display' => 'Major Mono Display',
			'Mako' => 'Mako',
			'Mali' => 'Mali',
			'Mallanna' => 'Mallanna',
			'Mandali' => 'Mandali',
			'Manjari' => 'Manjari',
			'Manrope' => 'Manrope',
			'Mansalva' => 'Mansalva',
			'Manuale' => 'Manuale',
			'Marcellus' => 'Marcellus',
			'Marcellus SC' => 'Marcellus SC',
			'Marck Script' => 'Marck Script',
			'Margarine' => 'Margarine',
			'Markazi Text' => 'Markazi Text',
			'Marko One' => 'Marko One',
			'Marmelad' => 'Marmelad',
			'Martel' => 'Martel',
			'Martel Sans' => 'Martel Sans',
			'Marvel' => 'Marvel',
			'Mate' => 'Mate',
			'Mate SC' => 'Mate SC',
			'Maven Pro' => 'Maven Pro',
			'McLaren' => 'McLaren',
			'Mea Culpa' => 'Mea Culpa',
			'Meddon' => 'Meddon',
			'MedievalSharp' => 'MedievalSharp',
			'Medula One' => 'Medula One',
			'Meera Inimai' => 'Meera Inimai',
			'Megrim' => 'Megrim',
			'Meie Script' => 'Meie Script',
			'Meow Script' => 'Meow Script',
			'Merienda' => 'Merienda',
			'Merienda One' => 'Merienda One',
			'Merriweather' => 'Merriweather',
			'Merriweather Sans' => 'Merriweather Sans',
			'Metal' => 'Metal',
			'Metal Mania' => 'Metal Mania',
			'Metamorphous' => 'Metamorphous',
			'Metrophobic' => 'Metrophobic',
			'Michroma' => 'Michroma',
			'Milonga' => 'Milonga',
			'Miltonian' => 'Miltonian',
			'Miltonian Tattoo' => 'Miltonian Tattoo',
			'Mina' => 'Mina',
			'Miniver' => 'Miniver',
			'Miriam Libre' => 'Miriam Libre',
			'Mirza' => 'Mirza',
			'Miss Fajardose' => 'Miss Fajardose',
			'Mitr' => 'Mitr',
			'Mochiy Pop One' => 'Mochiy Pop One',
			'Mochiy Pop P One' => 'Mochiy Pop P One',
			'Modak' => 'Modak',
			'Modern Antiqua' => 'Modern Antiqua',
			'Mogra' => 'Mogra',
			'Mohave' => 'Mohave',
			'Molengo' => 'Molengo',
			'Molle' => 'Molle',
			'Monda' => 'Monda',
			'Monofett' => 'Monofett',
			'Monoton' => 'Monoton',
			'Monsieur La Doulaise' => 'Monsieur La Doulaise',
			'Montaga' => 'Montaga',
			'Montagu Slab' => 'Montagu Slab',
			'MonteCarlo' => 'MonteCarlo',
			'Montez' => 'Montez',
			'Montserrat' => 'Montserrat',
			'Montserrat Alternates' => 'Montserrat Alternates',
			'Montserrat Subrayada' => 'Montserrat Subrayada',
			'Moo Lah Lah' => 'Moo Lah Lah',
			'Moon Dance' => 'Moon Dance',
			'Moul' => 'Moul',
			'Moulpali' => 'Moulpali',
			'Mountains of Christmas' => 'Mountains of Christmas',
			'Mouse Memoirs' => 'Mouse Memoirs',
			'Mr Bedfort' => 'Mr Bedfort',
			'Mr Dafoe' => 'Mr Dafoe',
			'Mr De Haviland' => 'Mr De Haviland',
			'Mrs Saint Delafield' => 'Mrs Saint Delafield',
			'Mrs Sheppards' => 'Mrs Sheppards',
			'Ms Madi' => 'Ms Madi',
			'Mukta' => 'Mukta',
			'Mukta Mahee' => 'Mukta Mahee',
			'Mukta Malar' => 'Mukta Malar',
			'Mukta Vaani' => 'Mukta Vaani',
			'Mulish' => 'Mulish',
			'Murecho' => 'Murecho',
			'MuseoModerno' => 'MuseoModerno',
			'My Soul' => 'My Soul',
			'Mystery Quest' => 'Mystery Quest',
			'NTR' => 'NTR',
			'Nanum Brush Script' => 'Nanum Brush Script',
			'Nanum Gothic' => 'Nanum Gothic',
			'Nanum Gothic Coding' => 'Nanum Gothic Coding',
			'Nanum Myeongjo' => 'Nanum Myeongjo',
			'Nanum Pen Script' => 'Nanum Pen Script',
			'Neonderthaw' => 'Neonderthaw',
			'Nerko One' => 'Nerko One',
			'Neucha' => 'Neucha',
			'Neuton' => 'Neuton',
			'New Rocker' => 'New Rocker',
			'New Tegomin' => 'New Tegomin',
			'News Cycle' => 'News Cycle',
			'Newsreader' => 'Newsreader',
			'Niconne' => 'Niconne',
			'Niramit' => 'Niramit',
			'Nixie One' => 'Nixie One',
			'Nobile' => 'Nobile',
			'Nokora' => 'Nokora',
			'Norican' => 'Norican',
			'Nosifer' => 'Nosifer',
			'Notable' => 'Notable',
			'Nothing You Could Do' => 'Nothing You Could Do',
			'Noticia Text' => 'Noticia Text',
			'Noto Emoji' => 'Noto Emoji',
			'Noto Kufi Arabic' => 'Noto Kufi Arabic',
			'Noto Music' => 'Noto Music',
			'Noto Naskh Arabic' => 'Noto Naskh Arabic',
			'Noto Nastaliq Urdu' => 'Noto Nastaliq Urdu',
			'Noto Rashi Hebrew' => 'Noto Rashi Hebrew',
			'Noto Sans' => 'Noto Sans',
			'Noto Sans Adlam' => 'Noto Sans Adlam',
			'Noto Sans Adlam Unjoined' => 'Noto Sans Adlam Unjoined',
			'Noto Sans Anatolian Hieroglyphs' => 'Noto Sans Anatolian Hieroglyphs',
			'Noto Sans Arabic' => 'Noto Sans Arabic',
			'Noto Sans Armenian' => 'Noto Sans Armenian',
			'Noto Sans Avestan' => 'Noto Sans Avestan',
			'Noto Sans Balinese' => 'Noto Sans Balinese',
			'Noto Sans Bamum' => 'Noto Sans Bamum',
			'Noto Sans Bassa Vah' => 'Noto Sans Bassa Vah',
			'Noto Sans Batak' => 'Noto Sans Batak',
			'Noto Sans Bengali' => 'Noto Sans Bengali',
			'Noto Sans Bhaiksuki' => 'Noto Sans Bhaiksuki',
			'Noto Sans Brahmi' => 'Noto Sans Brahmi',
			'Noto Sans Buginese' => 'Noto Sans Buginese',
			'Noto Sans Buhid' => 'Noto Sans Buhid',
			'Noto Sans Canadian Aboriginal' => 'Noto Sans Canadian Aboriginal',
			'Noto Sans Carian' => 'Noto Sans Carian',
			'Noto Sans Caucasian Albanian' => 'Noto Sans Caucasian Albanian',
			'Noto Sans Chakma' => 'Noto Sans Chakma',
			'Noto Sans Cham' => 'Noto Sans Cham',
			'Noto Sans Cherokee' => 'Noto Sans Cherokee',
			'Noto Sans Coptic' => 'Noto Sans Coptic',
			'Noto Sans Cuneiform' => 'Noto Sans Cuneiform',
			'Noto Sans Cypriot' => 'Noto Sans Cypriot',
			'Noto Sans Deseret' => 'Noto Sans Deseret',
			'Noto Sans Devanagari' => 'Noto Sans Devanagari',
			'Noto Sans Display' => 'Noto Sans Display',
			'Noto Sans Duployan' => 'Noto Sans Duployan',
			'Noto Sans Egyptian Hieroglyphs' => 'Noto Sans Egyptian Hieroglyphs',
			'Noto Sans Elbasan' => 'Noto Sans Elbasan',
			'Noto Sans Elymaic' => 'Noto Sans Elymaic',
			'Noto Sans Georgian' => 'Noto Sans Georgian',
			'Noto Sans Glagolitic' => 'Noto Sans Glagolitic',
			'Noto Sans Gothic' => 'Noto Sans Gothic',
			'Noto Sans Grantha' => 'Noto Sans Grantha',
			'Noto Sans Gujarati' => 'Noto Sans Gujarati',
			'Noto Sans Gunjala Gondi' => 'Noto Sans Gunjala Gondi',
			'Noto Sans Gurmukhi' => 'Noto Sans Gurmukhi',
			'Noto Sans HK' => 'Noto Sans HK',
			'Noto Sans Hanifi Rohingya' => 'Noto Sans Hanifi Rohingya',
			'Noto Sans Hanunoo' => 'Noto Sans Hanunoo',
			'Noto Sans Hatran' => 'Noto Sans Hatran',
			'Noto Sans Hebrew' => 'Noto Sans Hebrew',
			'Noto Sans Imperial Aramaic' => 'Noto Sans Imperial Aramaic',
			'Noto Sans Indic Siyaq Numbers' => 'Noto Sans Indic Siyaq Numbers',
			'Noto Sans Inscriptional Pahlavi' => 'Noto Sans Inscriptional Pahlavi',
			'Noto Sans Inscriptional Parthian' => 'Noto Sans Inscriptional Parthian',
			'Noto Sans JP' => 'Noto Sans JP',
			'Noto Sans Javanese' => 'Noto Sans Javanese',
			'Noto Sans KR' => 'Noto Sans KR',
			'Noto Sans Kaithi' => 'Noto Sans Kaithi',
			'Noto Sans Kannada' => 'Noto Sans Kannada',
			'Noto Sans Kayah Li' => 'Noto Sans Kayah Li',
			'Noto Sans Kharoshthi' => 'Noto Sans Kharoshthi',
			'Noto Sans Khmer' => 'Noto Sans Khmer',
			'Noto Sans Khojki' => 'Noto Sans Khojki',
			'Noto Sans Khudawadi' => 'Noto Sans Khudawadi',
			'Noto Sans Lao' => 'Noto Sans Lao',
			'Noto Sans Lepcha' => 'Noto Sans Lepcha',
			'Noto Sans Limbu' => 'Noto Sans Limbu',
			'Noto Sans Linear A' => 'Noto Sans Linear A',
			'Noto Sans Linear B' => 'Noto Sans Linear B',
			'Noto Sans Lisu' => 'Noto Sans Lisu',
			'Noto Sans Lycian' => 'Noto Sans Lycian',
			'Noto Sans Lydian' => 'Noto Sans Lydian',
			'Noto Sans Mahajani' => 'Noto Sans Mahajani',
			'Noto Sans Malayalam' => 'Noto Sans Malayalam',
			'Noto Sans Mandaic' => 'Noto Sans Mandaic',
			'Noto Sans Manichaean' => 'Noto Sans Manichaean',
			'Noto Sans Marchen' => 'Noto Sans Marchen',
			'Noto Sans Masaram Gondi' => 'Noto Sans Masaram Gondi',
			'Noto Sans Math' => 'Noto Sans Math',
			'Noto Sans Mayan Numerals' => 'Noto Sans Mayan Numerals',
			'Noto Sans Medefaidrin' => 'Noto Sans Medefaidrin',
			'Noto Sans Meetei Mayek' => 'Noto Sans Meetei Mayek',
			'Noto Sans Meroitic' => 'Noto Sans Meroitic',
			'Noto Sans Miao' => 'Noto Sans Miao',
			'Noto Sans Modi' => 'Noto Sans Modi',
			'Noto Sans Mongolian' => 'Noto Sans Mongolian',
			'Noto Sans Mono' => 'Noto Sans Mono',
			'Noto Sans Mro' => 'Noto Sans Mro',
			'Noto Sans Multani' => 'Noto Sans Multani',
			'Noto Sans Myanmar' => 'Noto Sans Myanmar',
			'Noto Sans N Ko' => 'Noto Sans N Ko',
			'Noto Sans Nabataean' => 'Noto Sans Nabataean',
			'Noto Sans New Tai Lue' => 'Noto Sans New Tai Lue',
			'Noto Sans Newa' => 'Noto Sans Newa',
			'Noto Sans Nushu' => 'Noto Sans Nushu',
			'Noto Sans Ogham' => 'Noto Sans Ogham',
			'Noto Sans Ol Chiki' => 'Noto Sans Ol Chiki',
			'Noto Sans Old Hungarian' => 'Noto Sans Old Hungarian',
			'Noto Sans Old Italic' => 'Noto Sans Old Italic',
			'Noto Sans Old North Arabian' => 'Noto Sans Old North Arabian',
			'Noto Sans Old Permic' => 'Noto Sans Old Permic',
			'Noto Sans Old Persian' => 'Noto Sans Old Persian',
			'Noto Sans Old Sogdian' => 'Noto Sans Old Sogdian',
			'Noto Sans Old South Arabian' => 'Noto Sans Old South Arabian',
			'Noto Sans Old Turkic' => 'Noto Sans Old Turkic',
			'Noto Sans Oriya' => 'Noto Sans Oriya',
			'Noto Sans Osage' => 'Noto Sans Osage',
			'Noto Sans Osmanya' => 'Noto Sans Osmanya',
			'Noto Sans Pahawh Hmong' => 'Noto Sans Pahawh Hmong',
			'Noto Sans Palmyrene' => 'Noto Sans Palmyrene',
			'Noto Sans Pau Cin Hau' => 'Noto Sans Pau Cin Hau',
			'Noto Sans Phags Pa' => 'Noto Sans Phags Pa',
			'Noto Sans Phoenician' => 'Noto Sans Phoenician',
			'Noto Sans Psalter Pahlavi' => 'Noto Sans Psalter Pahlavi',
			'Noto Sans Rejang' => 'Noto Sans Rejang',
			'Noto Sans Runic' => 'Noto Sans Runic',
			'Noto Sans SC' => 'Noto Sans SC',
			'Noto Sans Samaritan' => 'Noto Sans Samaritan',
			'Noto Sans Saurashtra' => 'Noto Sans Saurashtra',
			'Noto Sans Sharada' => 'Noto Sans Sharada',
			'Noto Sans Shavian' => 'Noto Sans Shavian',
			'Noto Sans Siddham' => 'Noto Sans Siddham',
			'Noto Sans Sinhala' => 'Noto Sans Sinhala',
			'Noto Sans Sogdian' => 'Noto Sans Sogdian',
			'Noto Sans Sora Sompeng' => 'Noto Sans Sora Sompeng',
			'Noto Sans Soyombo' => 'Noto Sans Soyombo',
			'Noto Sans Sundanese' => 'Noto Sans Sundanese',
			'Noto Sans Syloti Nagri' => 'Noto Sans Syloti Nagri',
			'Noto Sans Symbols' => 'Noto Sans Symbols',
			'Noto Sans Symbols 2' => 'Noto Sans Symbols 2',
			'Noto Sans Syriac' => 'Noto Sans Syriac',
			'Noto Sans TC' => 'Noto Sans TC',
			'Noto Sans Tagalog' => 'Noto Sans Tagalog',
			'Noto Sans Tagbanwa' => 'Noto Sans Tagbanwa',
			'Noto Sans Tai Le' => 'Noto Sans Tai Le',
			'Noto Sans Tai Tham' => 'Noto Sans Tai Tham',
			'Noto Sans Tai Viet' => 'Noto Sans Tai Viet',
			'Noto Sans Takri' => 'Noto Sans Takri',
			'Noto Sans Tamil' => 'Noto Sans Tamil',
			'Noto Sans Tamil Supplement' => 'Noto Sans Tamil Supplement',
			'Noto Sans Telugu' => 'Noto Sans Telugu',
			'Noto Sans Thaana' => 'Noto Sans Thaana',
			'Noto Sans Thai' => 'Noto Sans Thai',
			'Noto Sans Thai Looped' => 'Noto Sans Thai Looped',
			'Noto Sans Tifinagh' => 'Noto Sans Tifinagh',
			'Noto Sans Tirhuta' => 'Noto Sans Tirhuta',
			'Noto Sans Ugaritic' => 'Noto Sans Ugaritic',
			'Noto Sans Vai' => 'Noto Sans Vai',
			'Noto Sans Wancho' => 'Noto Sans Wancho',
			'Noto Sans Warang Citi' => 'Noto Sans Warang Citi',
			'Noto Sans Yi' => 'Noto Sans Yi',
			'Noto Sans Zanabazar Square' => 'Noto Sans Zanabazar Square',
			'Noto Serif' => 'Noto Serif',
			'Noto Serif Ahom' => 'Noto Serif Ahom',
			'Noto Serif Armenian' => 'Noto Serif Armenian',
			'Noto Serif Balinese' => 'Noto Serif Balinese',
			'Noto Serif Bengali' => 'Noto Serif Bengali',
			'Noto Serif Devanagari' => 'Noto Serif Devanagari',
			'Noto Serif Display' => 'Noto Serif Display',
			'Noto Serif Dogra' => 'Noto Serif Dogra',
			'Noto Serif Ethiopic' => 'Noto Serif Ethiopic',
			'Noto Serif Georgian' => 'Noto Serif Georgian',
			'Noto Serif Grantha' => 'Noto Serif Grantha',
			'Noto Serif Gujarati' => 'Noto Serif Gujarati',
			'Noto Serif Gurmukhi' => 'Noto Serif Gurmukhi',
			'Noto Serif Hebrew' => 'Noto Serif Hebrew',
			'Noto Serif JP' => 'Noto Serif JP',
			'Noto Serif KR' => 'Noto Serif KR',
			'Noto Serif Kannada' => 'Noto Serif Kannada',
			'Noto Serif Khmer' => 'Noto Serif Khmer',
			'Noto Serif Lao' => 'Noto Serif Lao',
			'Noto Serif Malayalam' => 'Noto Serif Malayalam',
			'Noto Serif Myanmar' => 'Noto Serif Myanmar',
			'Noto Serif Nyiakeng Puachue Hmong' => 'Noto Serif Nyiakeng Puachue Hmong',
			'Noto Serif SC' => 'Noto Serif SC',
			'Noto Serif Sinhala' => 'Noto Serif Sinhala',
			'Noto Serif TC' => 'Noto Serif TC',
			'Noto Serif Tamil' => 'Noto Serif Tamil',
			'Noto Serif Tangut' => 'Noto Serif Tangut',
			'Noto Serif Telugu' => 'Noto Serif Telugu',
			'Noto Serif Thai' => 'Noto Serif Thai',
			'Noto Serif Tibetan' => 'Noto Serif Tibetan',
			'Noto Serif Yezidi' => 'Noto Serif Yezidi',
			'Noto Traditional Nushu' => 'Noto Traditional Nushu',
			'Nova Cut' => 'Nova Cut',
			'Nova Flat' => 'Nova Flat',
			'Nova Mono' => 'Nova Mono',
			'Nova Oval' => 'Nova Oval',
			'Nova Round' => 'Nova Round',
			'Nova Script' => 'Nova Script',
			'Nova Slim' => 'Nova Slim',
			'Nova Square' => 'Nova Square',
			'Numans' => 'Numans',
			'Nunito' => 'Nunito',
			'Nunito Sans' => 'Nunito Sans',
			'Nuosu SIL' => 'Nuosu SIL',
			'Odibee Sans' => 'Odibee Sans',
			'Odor Mean Chey' => 'Odor Mean Chey',
			'Offside' => 'Offside',
			'Oi' => 'Oi',
			'Old Standard TT' => 'Old Standard TT',
			'Oldenburg' => 'Oldenburg',
			'Ole' => 'Ole',
			'Oleo Script' => 'Oleo Script',
			'Oleo Script Swash Caps' => 'Oleo Script Swash Caps',
			'Oooh Baby' => 'Oooh Baby',
			'Open Sans' => 'Open Sans',
			'Oranienbaum' => 'Oranienbaum',
			'Orbitron' => 'Orbitron',
			'Oregano' => 'Oregano',
			'Orelega One' => 'Orelega One',
			'Orienta' => 'Orienta',
			'Original Surfer' => 'Original Surfer',
			'Oswald' => 'Oswald',
			'Otomanopee One' => 'Otomanopee One',
			'Outfit' => 'Outfit',
			'Over the Rainbow' => 'Over the Rainbow',
			'Overlock' => 'Overlock',
			'Overlock SC' => 'Overlock SC',
			'Overpass' => 'Overpass',
			'Overpass Mono' => 'Overpass Mono',
			'Ovo' => 'Ovo',
			'Oxanium' => 'Oxanium',
			'Oxygen' => 'Oxygen',
			'Oxygen Mono' => 'Oxygen Mono',
			'PT Mono' => 'PT Mono',
			'PT Sans' => 'PT Sans',
			'PT Sans Caption' => 'PT Sans Caption',
			'PT Sans Narrow' => 'PT Sans Narrow',
			'PT Serif' => 'PT Serif',
			'PT Serif Caption' => 'PT Serif Caption',
			'Pacifico' => 'Pacifico',
			'Padauk' => 'Padauk',
			'Palanquin' => 'Palanquin',
			'Palanquin Dark' => 'Palanquin Dark',
			'Palette Mosaic' => 'Palette Mosaic',
			'Pangolin' => 'Pangolin',
			'Paprika' => 'Paprika',
			'Parisienne' => 'Parisienne',
			'Passero One' => 'Passero One',
			'Passion One' => 'Passion One',
			'Passions Conflict' => 'Passions Conflict',
			'Pathway Gothic One' => 'Pathway Gothic One',
			'Patrick Hand' => 'Patrick Hand',
			'Patrick Hand SC' => 'Patrick Hand SC',
			'Pattaya' => 'Pattaya',
			'Patua One' => 'Patua One',
			'Pavanam' => 'Pavanam',
			'Paytone One' => 'Paytone One',
			'Peddana' => 'Peddana',
			'Peralta' => 'Peralta',
			'Permanent Marker' => 'Permanent Marker',
			'Petemoss' => 'Petemoss',
			'Petit Formal Script' => 'Petit Formal Script',
			'Petrona' => 'Petrona',
			'Philosopher' => 'Philosopher',
			'Piazzolla' => 'Piazzolla',
			'Piedra' => 'Piedra',
			'Pinyon Script' => 'Pinyon Script',
			'Pirata One' => 'Pirata One',
			'Plaster' => 'Plaster',
			'Play' => 'Play',
			'Playball' => 'Playball',
			'Playfair Display' => 'Playfair Display',
			'Playfair Display SC' => 'Playfair Display SC',
			'Plus Jakarta Sans' => 'Plus Jakarta Sans',
			'Podkova' => 'Podkova',
			'Poiret One' => 'Poiret One',
			'Poller One' => 'Poller One',
			'Poly' => 'Poly',
			'Pompiere' => 'Pompiere',
			'Pontano Sans' => 'Pontano Sans',
			'Poor Story' => 'Poor Story',
			'Poppins' => 'Poppins',
			'Port Lligat Sans' => 'Port Lligat Sans',
			'Port Lligat Slab' => 'Port Lligat Slab',
			'Potta One' => 'Potta One',
			'Pragati Narrow' => 'Pragati Narrow',
			'Praise' => 'Praise',
			'Prata' => 'Prata',
			'Preahvihear' => 'Preahvihear',
			'Press Start 2P' => 'Press Start 2P',
			'Pridi' => 'Pridi',
			'Princess Sofia' => 'Princess Sofia',
			'Prociono' => 'Prociono',
			'Prompt' => 'Prompt',
			'Prosto One' => 'Prosto One',
			'Proza Libre' => 'Proza Libre',
			'Public Sans' => 'Public Sans',
			'Puppies Play' => 'Puppies Play',
			'Puritan' => 'Puritan',
			'Purple Purse' => 'Purple Purse',
			'Qahiri' => 'Qahiri',
			'Quando' => 'Quando',
			'Quantico' => 'Quantico',
			'Quattrocento' => 'Quattrocento',
			'Quattrocento Sans' => 'Quattrocento Sans',
			'Questrial' => 'Questrial',
			'Quicksand' => 'Quicksand',
			'Quintessential' => 'Quintessential',
			'Qwigley' => 'Qwigley',
			'Qwitcher Grypen' => 'Qwitcher Grypen',
			'Racing Sans One' => 'Racing Sans One',
			'Radio Canada' => 'Radio Canada',
			'Radley' => 'Radley',
			'Rajdhani' => 'Rajdhani',
			'Rakkas' => 'Rakkas',
			'Raleway' => 'Raleway',
			'Raleway Dots' => 'Raleway Dots',
			'Ramabhadra' => 'Ramabhadra',
			'Ramaraja' => 'Ramaraja',
			'Rambla' => 'Rambla',
			'Rammetto One' => 'Rammetto One',
			'Rampart One' => 'Rampart One',
			'Ranchers' => 'Ranchers',
			'Rancho' => 'Rancho',
			'Ranga' => 'Ranga',
			'Rasa' => 'Rasa',
			'Rationale' => 'Rationale',
			'Ravi Prakash' => 'Ravi Prakash',
			'Readex Pro' => 'Readex Pro',
			'Recursive' => 'Recursive',
			'Red Hat Display' => 'Red Hat Display',
			'Red Hat Mono' => 'Red Hat Mono',
			'Red Hat Text' => 'Red Hat Text',
			'Red Rose' => 'Red Rose',
			'Redacted' => 'Redacted',
			'Redacted Script' => 'Redacted Script',
			'Redressed' => 'Redressed',
			'Reem Kufi' => 'Reem Kufi',
			'Reenie Beanie' => 'Reenie Beanie',
			'Reggae One' => 'Reggae One',
			'Revalia' => 'Revalia',
			'Rhodium Libre' => 'Rhodium Libre',
			'Ribeye' => 'Ribeye',
			'Ribeye Marrow' => 'Ribeye Marrow',
			'Righteous' => 'Righteous',
			'Risque' => 'Risque',
			'Road Rage' => 'Road Rage',
			'Roboto' => 'Roboto',
			'Roboto Condensed' => 'Roboto Condensed',
			'Roboto Flex' => 'Roboto Flex',
			'Roboto Mono' => 'Roboto Mono',
			'Roboto Serif' => 'Roboto Serif',
			'Roboto Slab' => 'Roboto Slab',
			'Rochester' => 'Rochester',
			'Rock 3D' => 'Rock 3D',
			'Rock Salt' => 'Rock Salt',
			'RocknRoll One' => 'RocknRoll One',
			'Rokkitt' => 'Rokkitt',
			'Romanesco' => 'Romanesco',
			'Ropa Sans' => 'Ropa Sans',
			'Rosario' => 'Rosario',
			'Rosarivo' => 'Rosarivo',
			'Rouge Script' => 'Rouge Script',
			'Rowdies' => 'Rowdies',
			'Rozha One' => 'Rozha One',
			'Rubik' => 'Rubik',
			'Rubik Beastly' => 'Rubik Beastly',
			'Rubik Bubbles' => 'Rubik Bubbles',
			'Rubik Glitch' => 'Rubik Glitch',
			'Rubik Microbe' => 'Rubik Microbe',
			'Rubik Mono One' => 'Rubik Mono One',
			'Rubik Moonrocks' => 'Rubik Moonrocks',
			'Rubik Puddles' => 'Rubik Puddles',
			'Rubik Wet Paint' => 'Rubik Wet Paint',
			'Ruda' => 'Ruda',
			'Rufina' => 'Rufina',
			'Ruge Boogie' => 'Ruge Boogie',
			'Ruluko' => 'Ruluko',
			'Rum Raisin' => 'Rum Raisin',
			'Ruslan Display' => 'Ruslan Display',
			'Russo One' => 'Russo One',
			'Ruthie' => 'Ruthie',
			'Rye' => 'Rye',
			'STIX Two Text' => 'STIX Two Text',
			'Sacramento' => 'Sacramento',
			'Sahitya' => 'Sahitya',
			'Sail' => 'Sail',
			'Saira' => 'Saira',
			'Saira Condensed' => 'Saira Condensed',
			'Saira Extra Condensed' => 'Saira Extra Condensed',
			'Saira Semi Condensed' => 'Saira Semi Condensed',
			'Saira Stencil One' => 'Saira Stencil One',
			'Salsa' => 'Salsa',
			'Sanchez' => 'Sanchez',
			'Sancreek' => 'Sancreek',
			'Sansita' => 'Sansita',
			'Sansita Swashed' => 'Sansita Swashed',
			'Sarabun' => 'Sarabun',
			'Sarala' => 'Sarala',
			'Sarina' => 'Sarina',
			'Sarpanch' => 'Sarpanch',
			'Sassy Frass' => 'Sassy Frass',
			'Satisfy' => 'Satisfy',
			'Sawarabi Gothic' => 'Sawarabi Gothic',
			'Sawarabi Mincho' => 'Sawarabi Mincho',
			'Scada' => 'Scada',
			'Scheherazade New' => 'Scheherazade New',
			'Schoolbell' => 'Schoolbell',
			'Scope One' => 'Scope One',
			'Seaweed Script' => 'Seaweed Script',
			'Secular One' => 'Secular One',
			'Sedgwick Ave' => 'Sedgwick Ave',
			'Sedgwick Ave Display' => 'Sedgwick Ave Display',
			'Sen' => 'Sen',
			'Send Flowers' => 'Send Flowers',
			'Sevillana' => 'Sevillana',
			'Seymour One' => 'Seymour One',
			'Shadows Into Light' => 'Shadows Into Light',
			'Shadows Into Light Two' => 'Shadows Into Light Two',
			'Shalimar' => 'Shalimar',
			'Shanti' => 'Shanti',
			'Share' => 'Share',
			'Share Tech' => 'Share Tech',
			'Share Tech Mono' => 'Share Tech Mono',
			'Shippori Antique' => 'Shippori Antique',
			'Shippori Antique B1' => 'Shippori Antique B1',
			'Shippori Mincho' => 'Shippori Mincho',
			'Shippori Mincho B1' => 'Shippori Mincho B1',
			'Shizuru' => 'Shizuru',
			'Shojumaru' => 'Shojumaru',
			'Short Stack' => 'Short Stack',
			'Shrikhand' => 'Shrikhand',
			'Siemreap' => 'Siemreap',
			'Sigmar One' => 'Sigmar One',
			'Signika' => 'Signika',
			'Signika Negative' => 'Signika Negative',
			'Simonetta' => 'Simonetta',
			'Single Day' => 'Single Day',
			'Sintony' => 'Sintony',
			'Sirin Stencil' => 'Sirin Stencil',
			'Six Caps' => 'Six Caps',
			'Skranji' => 'Skranji',
			'Slabo 13px' => 'Slabo 13px',
			'Slabo 27px' => 'Slabo 27px',
			'Slackey' => 'Slackey',
			'Smokum' => 'Smokum',
			'Smooch' => 'Smooch',
			'Smooch Sans' => 'Smooch Sans',
			'Smythe' => 'Smythe',
			'Sniglet' => 'Sniglet',
			'Snippet' => 'Snippet',
			'Snowburst One' => 'Snowburst One',
			'Sofadi One' => 'Sofadi One',
			'Sofia' => 'Sofia',
			'Solway' => 'Solway',
			'Song Myung' => 'Song Myung',
			'Sonsie One' => 'Sonsie One',
			'Sora' => 'Sora',
			'Sorts Mill Goudy' => 'Sorts Mill Goudy',
			'Source Code Pro' => 'Source Code Pro',
			'Source Sans 3' => 'Source Sans 3',
			'Source Sans Pro' => 'Source Sans Pro',
			'Source Serif 4' => 'Source Serif 4',
			'Source Serif Pro' => 'Source Serif Pro',
			'Space Grotesk' => 'Space Grotesk',
			'Space Mono' => 'Space Mono',
			'Special Elite' => 'Special Elite',
			'Spectral' => 'Spectral',
			'Spectral SC' => 'Spectral SC',
			'Spicy Rice' => 'Spicy Rice',
			'Spinnaker' => 'Spinnaker',
			'Spirax' => 'Spirax',
			'Spline Sans' => 'Spline Sans',
			'Spline Sans Mono' => 'Spline Sans Mono',
			'Squada One' => 'Squada One',
			'Square Peg' => 'Square Peg',
			'Sree Krushnadevaraya' => 'Sree Krushnadevaraya',
			'Sriracha' => 'Sriracha',
			'Srisakdi' => 'Srisakdi',
			'Staatliches' => 'Staatliches',
			'Stalemate' => 'Stalemate',
			'Stalinist One' => 'Stalinist One',
			'Stardos Stencil' => 'Stardos Stencil',
			'Stick' => 'Stick',
			'Stick No Bills' => 'Stick No Bills',
			'Stint Ultra Condensed' => 'Stint Ultra Condensed',
			'Stint Ultra Expanded' => 'Stint Ultra Expanded',
			'Stoke' => 'Stoke',
			'Strait' => 'Strait',
			'Style Script' => 'Style Script',
			'Stylish' => 'Stylish',
			'Sue Ellen Francisco' => 'Sue Ellen Francisco',
			'Suez One' => 'Suez One',
			'Sulphur Point' => 'Sulphur Point',
			'Sumana' => 'Sumana',
			'Sunflower' => 'Sunflower',
			'Sunshiney' => 'Sunshiney',
			'Supermercado One' => 'Supermercado One',
			'Sura' => 'Sura',
			'Suranna' => 'Suranna',
			'Suravaram' => 'Suravaram',
			'Suwannaphum' => 'Suwannaphum',
			'Swanky and Moo Moo' => 'Swanky and Moo Moo',
			'Syncopate' => 'Syncopate',
			'Syne' => 'Syne',
			'Syne Mono' => 'Syne Mono',
			'Syne Tactile' => 'Syne Tactile',
			'Tai Heritage Pro' => 'Tai Heritage Pro',
			'Tajawal' => 'Tajawal',
			'Tangerine' => 'Tangerine',
			'Tapestry' => 'Tapestry',
			'Taprom' => 'Taprom',
			'Tauri' => 'Tauri',
			'Taviraj' => 'Taviraj',
			'Teko' => 'Teko',
			'Telex' => 'Telex',
			'Tenali Ramakrishna' => 'Tenali Ramakrishna',
			'Tenor Sans' => 'Tenor Sans',
			'Text Me One' => 'Text Me One',
			'Texturina' => 'Texturina',
			'Thasadith' => 'Thasadith',
			'The Girl Next Door' => 'The Girl Next Door',
			'The Nautigal' => 'The Nautigal',
			'Tienne' => 'Tienne',
			'Tillana' => 'Tillana',
			'Timmana' => 'Timmana',
			'Tinos' => 'Tinos',
			'Tiro Bangla' => 'Tiro Bangla',
			'Tiro Devanagari Hindi' => 'Tiro Devanagari Hindi',
			'Tiro Devanagari Marathi' => 'Tiro Devanagari Marathi',
			'Tiro Devanagari Sanskrit' => 'Tiro Devanagari Sanskrit',
			'Tiro Gurmukhi' => 'Tiro Gurmukhi',
			'Tiro Kannada' => 'Tiro Kannada',
			'Tiro Tamil' => 'Tiro Tamil',
			'Tiro Telugu' => 'Tiro Telugu',
			'Titan One' => 'Titan One',
			'Titillium Web' => 'Titillium Web',
			'Tomorrow' => 'Tomorrow',
			'Tourney' => 'Tourney',
			'Trade Winds' => 'Trade Winds',
			'Train One' => 'Train One',
			'Trirong' => 'Trirong',
			'Trispace' => 'Trispace',
			'Trocchi' => 'Trocchi',
			'Trochut' => 'Trochut',
			'Truculenta' => 'Truculenta',
			'Trykker' => 'Trykker',
			'Tulpen One' => 'Tulpen One',
			'Turret Road' => 'Turret Road',
			'Twinkle Star' => 'Twinkle Star',
			'Ubuntu' => 'Ubuntu',
			'Ubuntu Condensed' => 'Ubuntu Condensed',
			'Ubuntu Mono' => 'Ubuntu Mono',
			'Uchen' => 'Uchen',
			'Ultra' => 'Ultra',
			'Uncial Antiqua' => 'Uncial Antiqua',
			'Underdog' => 'Underdog',
			'Unica One' => 'Unica One',
			'UnifrakturCook' => 'UnifrakturCook',
			'UnifrakturMaguntia' => 'UnifrakturMaguntia',
			'Unkempt' => 'Unkempt',
			'Unlock' => 'Unlock',
			'Unna' => 'Unna',
			'Updock' => 'Updock',
			'Urbanist' => 'Urbanist',
			'VT323' => 'VT323',
			'Vampiro One' => 'Vampiro One',
			'Varela' => 'Varela',
			'Varela Round' => 'Varela Round',
			'Varta' => 'Varta',
			'Vast Shadow' => 'Vast Shadow',
			'Vazirmatn' => 'Vazirmatn',
			'Vesper Libre' => 'Vesper Libre',
			'Viaoda Libre' => 'Viaoda Libre',
			'Vibes' => 'Vibes',
			'Vibur' => 'Vibur',
			'Vidaloka' => 'Vidaloka',
			'Viga' => 'Viga',
			'Voces' => 'Voces',
			'Volkhov' => 'Volkhov',
			'Vollkorn' => 'Vollkorn',
			'Vollkorn SC' => 'Vollkorn SC',
			'Voltaire' => 'Voltaire',
			'Vujahday Script' => 'Vujahday Script',
			'Waiting for the Sunrise' => 'Waiting for the Sunrise',
			'Wallpoet' => 'Wallpoet',
			'Walter Turncoat' => 'Walter Turncoat',
			'Warnes' => 'Warnes',
			'Water Brush' => 'Water Brush',
			'Waterfall' => 'Waterfall',
			'Wellfleet' => 'Wellfleet',
			'Wendy One' => 'Wendy One',
			'Whisper' => 'Whisper',
			'WindSong' => 'WindSong',
			'Wire One' => 'Wire One',
			'Work Sans' => 'Work Sans',
			'Xanh Mono' => 'Xanh Mono',
			'Yaldevi' => 'Yaldevi',
			'Yanone Kaffeesatz' => 'Yanone Kaffeesatz',
			'Yantramanav' => 'Yantramanav',
			'Yatra One' => 'Yatra One',
			'Yellowtail' => 'Yellowtail',
			'Yeon Sung' => 'Yeon Sung',
			'Yeseva One' => 'Yeseva One',
			'Yesteryear' => 'Yesteryear',
			'Yomogi' => 'Yomogi',
			'Yrsa' => 'Yrsa',
			'Yuji Boku' => 'Yuji Boku',
			'Yuji Hentaigana Akari' => 'Yuji Hentaigana Akari',
			'Yuji Hentaigana Akebono' => 'Yuji Hentaigana Akebono',
			'Yuji Mai' => 'Yuji Mai',
			'Yuji Syuku' => 'Yuji Syuku',
			'Yusei Magic' => 'Yusei Magic',
			'ZCOOL KuaiLe' => 'ZCOOL KuaiLe',
			'ZCOOL QingKe HuangYou' => 'ZCOOL QingKe HuangYou',
			'ZCOOL XiaoWei' => 'ZCOOL XiaoWei',
			'Zen Antique' => 'Zen Antique',
			'Zen Antique Soft' => 'Zen Antique Soft',
			'Zen Dots' => 'Zen Dots',
			'Zen Kaku Gothic Antique' => 'Zen Kaku Gothic Antique',
			'Zen Kaku Gothic New' => 'Zen Kaku Gothic New',
			'Zen Kurenaido' => 'Zen Kurenaido',
			'Zen Loop' => 'Zen Loop',
			'Zen Maru Gothic' => 'Zen Maru Gothic',
			'Zen Old Mincho' => 'Zen Old Mincho',
			'Zen Tokyo Zoo' => 'Zen Tokyo Zoo',
			'Zeyada' => 'Zeyada',
			'Zhi Mang Xing' => 'Zhi Mang Xing',
			'Zilla Slab' => 'Zilla Slab',
			'Zilla Slab Highlight' => 'Zilla Slab Highlight',
		);

		if ( 'default' === $value || empty( $options['primary_font'] ) ) {
			$google_fonts_list[''] = $google_fonts_list['template-default'];
			unset( $google_fonts_list['template-default'] );
		}

		$primary_color_value = ( 'default' === $value ) ? 'default_primary_color' : 'primary_color';
		$fallback_image_value = ( 'default' === $value ) ? 'fallback_image' : 'background';

		$mightyshare_template_display_options = array(
			'primary_font' => array(
				'label'         => 'Google Font',
				'weight'        => 4,
				'field_type'    => 'render_mightyshare_select_field',
				'value'         => ( ! empty( $setting_prefix['value']['primary_font'] ) ) ? $setting_prefix['value']['primary_font'] : '',
				'field_options' => array(
					'id'          => $setting_prefix['name'] . '[primary_font]',
					'classes'     => 'paid-only',
					'options'     => $google_fonts_list,
					'description' => 'Google Fonts are available for <a href="https://mightyshare.io/pricing/" rel="noopener nofollow" target="_blank">paid plans</a>.',
				),
			),
			/*
			'logo_width' => array(
				'label'         => 'Logo Width',
				'weight'        => 5,
				'field_type'    => 'render_mightyshare_text_field',
				'value'         => ( ! empty( $setting_prefix['value']['logo_width'] ) ) ? $setting_prefix['value']['logo_width'] : '',
				'field_options' => array(
					'id'          => $setting_prefix['name'] . '[logo_width]',
					'classes'     => '',
					'description' => 'Max-width of the logo added to the template in pixels (keep empty for template default).',
				),
			),
			*/
			'primary_color' => array(
				'label'         => 'Primary Color',
				'weight'        => 6,
				'field_type'    => 'render_mightyshare_color_field',
				'value'         => ( ! empty( $setting_prefix['value'][ $primary_color_value ] ) ) ? $setting_prefix['value'][ $primary_color_value ] : '',
				'field_options' => array(
					'id'          => $setting_prefix['name'] . '[' . $primary_color_value . ']',
					'classes'     => '',
					'description' => 'Primary color used in many image templates.',
				),
			),
			'logo' => array(
				'label'         => 'Logo',
				'weight'        => 8,
				'field_type'    => 'render_mightyshare_image_field',
				'value'         => ( ! empty( $setting_prefix['value']['logo'] ) ) ? $setting_prefix['value']['logo'] : '',
				'field_options' => array(
					'id'          => $setting_prefix['name'] . '[logo]',
					'classes'     => '',
					'options'     => $template_options,
					'description' => 'Your logo to be used in templates.',
				),
			),
			'background' => array(
				'label'         => 'Fallback Image',
				'weight'        => 10,
				'field_type'    => 'render_mightyshare_image_field',
				'value'         => ( ! empty( $setting_prefix['value'][ $fallback_image_value ] ) ) ? $setting_prefix['value'][ $fallback_image_value ] : '',
				'field_options' => array(
					'id'          => $setting_prefix['name'] . '[' . $fallback_image_value . ']',
					'classes'     => '',
					'options'     => $template_options,
					'description' => __( 'Used when there is no featured image (and on category, tag and author pages).', 'mightyshare' ),
				),
			),
		);

		if ( 'default' === $value || ( is_object( $value ) && ( 'WP_Post_Type' === get_class( $value ) || 'WP_Taxonomy' === get_class( $value ) ) ) || ( ! empty( $value->name ) && 'users' === $value->name ) ) {
			$mightyshare_template_display_options['description'] =
				array(
					'label'         => 'Enable Subheadings',
					'weight'        => 7,
					'field_type'    => 'render_mightyshare_checkbox_field',
					'value'         => ( ! empty( $setting_prefix['value']['enable_description'] ) ) ? 'yes' : '',
					'field_options' => array(
						'id'          => $setting_prefix['name'] . '[enable_description]',
						'aria_label'  => __( 'Enable Subheadings', 'mightyshare' ),
						'classes'     => 'mightyshare-toggler-wrapper',
						'label'       => true,
						'default'     => 'no',
						'set_value'   => 'yes',
						'description' => 'Display a subheading in compatible templates (uses the post excerpt).',
					),
				);

				if ( is_object( $value ) && 'WP_Taxonomy' === get_class( $value ) ) {
					$mightyshare_template_display_options['description']['field_options']['description'] = 'Display a subheading in compatible templates (uses the taxonomy description).';
				}

				if ( ! empty( $value->name ) && 'users' === $value->name ) {
					$mightyshare_template_display_options['description']['field_options']['description'] = 'Display a subheading in compatible templates (uses the author biography).';
				}

				// Per-type values can only add subheadings; off means "use the default".
				if ( 'default' !== $value ) {
					$mightyshare_template_display_options['description']['field_options']['description'] .= ' ' . __( 'When off, the default setting applies.', 'mightyshare' );
				}
		}

		if ( ( ! empty( $options['plan_type'] ) && 'paid' === $options['plan_type'] ) && ! empty( $mightyshare_template_display_options['primary_font'] ) ) {
			$mightyshare_template_display_options['primary_font']['field_options']['classes']     = '';
			$mightyshare_template_display_options['primary_font']['field_options']['description'] = 'Font used in image renders (paid plan feature).';
		}

		return $mightyshare_template_display_options;
	}
}
