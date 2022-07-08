<?php
/**
 * MightyShare Meta Box Functions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

add_action( 'admin_enqueue_scripts', function( $hook ) {
	global $pagenow;

	if ( ! ( ! empty( $pagenow ) && ( 'post.php' === $pagenow || 'post-new.php' === $pagenow ) ) && ! ( ! empty( $_GET['page'] ) && in_array( $_GET['page'], array( 'mightyshare' ), true ) ) ) {
		return;
	}

	if ( ! did_action( 'wp_enqueue_media' ) ) {
		wp_enqueue_media();
	}

	wp_register_script(
		'mightyshare_admin_js',
		plugin_dir_url( __FILE__ ) . 'assets/admin.js',
		array( 'jquery' ),
		MIGHTYSHARE_VERSION,
		true
	);

	wp_register_style(
		'mightyshare_admin_css',
		plugin_dir_url( __FILE__ ) . 'assets/admin.css',
		array(),
		MIGHTYSHARE_VERSION,
	);

	wp_localize_script( 'mightyshare_admin_js', 'mightyshareObject', array(
		'insertImage'  => __( 'Insert image', 'mightyshare' ),
		'useThisImage' => __( 'Use this image', 'mightyshare' ),
		'uploadImage'  => __( 'Upload Image', 'mightyshare' ),
	) );

	wp_enqueue_style( 'wp-color-picker' );
	wp_enqueue_script( 'wp-color-picker' );

	wp_enqueue_script( 'mightyshare_admin_js' );
	wp_enqueue_style( 'mightyshare_admin_css' );
});

function sanitize_mightyshare_field( $value, $type ) {

	switch ( $type ) {
		case 'textarea':{
			$value = sanitize_textarea_field( $value );
			break;
		}
		case 'checkbox':{
			$value = ( 'on' === $value ) ? 'yes' : 'no';
			break;
		}
		case 'checklist':{
			$value = is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : array();
			break;
		}
		case 'editor':{
			$value = wp_kses( $value, array(
				'h1'     => array(),
				'h2'     => array(),
				'h3'     => array(),
				'h4'     => array(),
				'h5'     => array(),
				'h6'     => array(),
				'p'      => array(),
				'br'     => array(),
				'hr'     => array(),
				'strong' => array(),
				'em'     => array(),
				'i'      => array(),
				's'      => array(),
				'del'    => array(),
				'ul'     => array(),
				'ol'     => array(),
				'li'     => array(),
				'code'   => array(),
				'a'      => array(
					'href'   => true,
					'target' => true,
					'rel'    => true,
				),
			) );
			break;
		}
		case 'gallery':{
			$value = is_array( $value ) ? array_map( 'intval', $value ) : array();
			break;
		}
		default: {
			$value = sanitize_text_field( $value );
			break;
		}
	}

	return $value;

}

function render_mightyshare_select_field( $param, $value = '', $prefix = '' ) {

	$value        = $value ? $value : ( isset( $param['default'] ) ? $param['default'] : '' );
	$printable_id = ( isset( $param['id'] ) ? str_replace( ']', '', str_replace( '[', '_', $param['id'] ) ) : '' );

	?>
	<select id="<?php echo esc_attr( $prefix . $printable_id ); ?>" name="<?php echo esc_attr( $param['id'] ); ?>" class="<?php echo esc_attr( ( ! empty( $param['classes'] ) ? $param['classes'] : '' ) ); ?>">
	<?php
	if ( isset( $param['placeholder'] ) ) {
		?>
		<option value=""><?php echo esc_html( $param['placeholder'] ); ?></option>
		<?php
	}

	if ( ! empty( $param['options'] ) && is_array( $param['options'] ) ) {
		foreach ( $param['options'] as $v => $l ) {
			$selected = $v === $value ? ' selected' : '';
			?>
			<option value="<?php echo esc_attr( $v ); ?>" <?php echo esc_attr( $selected ); ?>><?php echo esc_html( $l ); ?></option>
			<?php
		}
	}
	?>
	</select>
	<?php
	if ( isset( $param['classes'] ) && 'mightyshare_template_field' === $param['classes'] ) {
		?>
		<a href="#TB_inline?&width=753&height=750&inlineId=mightyshare-template-picker" class="mightyshare-template-picker-button thickbox button button-primary" data-pickerfor="<?php echo esc_attr( $prefix . $printable_id ); ?>">Browse Templates</a>
		<?php
		if ( isset( $param['modal'] ) && isset( $param['modal_id'] ) ) {
			?>
			<a href="#TB_inline?&width=630&height=550&inlineId=<?php echo esc_attr( $param['modal_id'] ); ?>" class="mightyshare-template-options-button thickbox button button-secondary" data-optionsfor="<?php echo esc_attr( $param['modal_id'] ); ?>"><?php echo esc_html( $param['modal'] ); ?></a>
			<?php
		}
		?>
		<div class="mightyshare-image-preview"></div>
		<?php
	}

	if ( isset( $param['description'] ) ) {
		?>
		<p class="mightyshare-description description"><?php echo wp_kses_post( $param['description'] ); ?></p>
		<?php
	}
}

function render_mightyshare_text_field( $param, $value = '', $prefix = '' ) {

	$value = $value ? $value : ( isset( $param['default'] ) ? $param['default'] : '' );

	?>
	<input type="text" value="<?php echo esc_attr( $value ); ?>" id="<?php echo esc_attr( $prefix . $param['id'] ); ?>" name="<?php echo esc_attr( $param['id'] ); ?>" class="<?php echo esc_attr( ( ! empty( $param['classes'] ) ? ' ' . $param['classes'] : '' ) ); ?>" value="<?php echo esc_attr( $value ); ?>">
	<?php
	if ( isset( $param['description'] ) ) {
		?>
		<p class="mightyshare-description description"><?php echo wp_kses_post( $param['description'] ); ?></p>
		<?php
	}
}

function render_mightyshare_color_field( $param, $value = '', $prefix = '' ) {

	$value = $value ? $value : ( isset( $param['default'] ) ? $param['default'] : '' );

	?>
	<input type="text" value="<?php echo esc_attr( $value ); ?>" id="<?php echo esc_attr( $prefix . $param['id'] ); ?>" name="<?php echo esc_attr( $param['id'] ); ?>" class="mightyshare_color_field<?php echo esc_attr( ( ! empty( $param['classes'] ) ? ' ' . $param['classes'] : '' ) ); ?>" value="<?php echo esc_attr( $value ); ?>">
	<?php
	if ( isset( $param['description'] ) ) {
		?>
		<p class="mightyshare-description description"><?php echo wp_kses_post( $param['description'] ); ?></p>
		<?php
	}
}

function render_mightyshare_checkbox_field( $param, $value = '', $prefix = '' ) {

	$value = ( 'yes' === $value || ( ! $value && isset( $param['default'] ) && 'yes' === $param['default'] ) ) ? 'yes' : 'no';
	$label = ! empty( $param['short_description'] ) ? $param['short_description'] : '';
	$id    = ! empty( $param['id'] ) ? $param['id'] : uniqid();

	if ( ! empty( $param['label'] ) ) {
		?>
		<label for="<?php echo esc_attr( $prefix . $id ); ?>" class="mightyshare-label checkbox <?php echo esc_attr( ( ! empty( $param['classes'] ) ? $param['classes'] : '' ) ); ?>">
		<?php
	}
	?>
	<input type="checkbox"
		id="<?php echo esc_attr( $prefix . $id ); ?>"
		name="<?php echo ! empty( $param['name'] ) ? esc_attr( $param['name'] ) : esc_attr( $param['id'] ); ?>"
		<?php if ( ! empty( $param['set_value'] ) ) { ?>
			value="<?php echo esc_attr( $param['set_value'] ); ?>"
		<?php }; ?>
		<?php echo checked( 'yes', $value, false ); ?> />
		<?php
		if ( ! empty( $param['classes'] ) && 'mightyshare-toggler-wrapper' === $param['classes'] ) {
			?>
			<div class="toggler-slider"><div class="toggler-knob"></div></div>
			<?php
		}
		if ( ! empty( $param['label'] ) ) {
			echo esc_attr( $label );
			?>
			</label>
			<?php
		}

		if ( isset( $param['description'] ) ) {
			?>
			<p class="mightyshare-description description"><?php echo wp_kses_post( $param['description'] ); ?></p>
			<?php
		}
}

function render_mightyshare_image_field( $param, $value = '', $prefix = '' ) {

	$name  = $param['id'];
	$value = $value ? intval( $value ) : ( ! empty( $param['default'] ) ? intval( $param['default'] ) : '' );

	/* if no image */
	$image_class       = ' button';
	$image_button_text = __( 'Upload image', 'mightyshare' );
	$display           = 'none';

	/* if image is set */
	$image_attributes = null;
	if ( $value ) {
		$image_attributes = wp_get_attachment_image_src( $value, 'full' );
	}
	if ( $value && $image_attributes ) {
		$image_class       = '';
		$image_button_text = '<img src="' . $image_attributes[0] . '" style="max-width:200px;max-height:200pxheight:auto;display:block;" />';
		$display           = 'inline-block';
	}

	?>
	<div>
		<a href="javascript:void(0)" class="mightyshare-upload-img-button<?php echo esc_attr( $image_class ); ?>"><?php echo wp_kses_post( $image_button_text ); ?></a><br />
		<input type="hidden" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $prefix . $param['id'] ); ?>" value="<?php echo esc_attr( $value ); ?>" />
		<a href="javascript:void(0)" class="mightyshare-remove-img-button" style="display:inline-block;display:<?php echo esc_attr( $display ); ?>"><?php echo esc_attr( __( 'Remove image', 'mightyshare' ) ); ?></a>
	</div>
	<?php
	if ( isset( $param['description'] ) ) {
		?>
		<p class="mightyshare-description description"><?php echo wp_kses_post( $param['description'] ); ?></p>
		<?php
	}
}
