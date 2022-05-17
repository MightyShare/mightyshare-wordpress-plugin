<?php
/**
 * All in one sanitization
 */
function sanitize_simple_field( $value, $type ) {

	switch( $type ) {
		case 'textarea':{
			$value = sanitize_textarea_field( $value );
			break;
		}
		case 'checkbox':{
			$value = ( $value == 'on' ) ? 'yes' : 'no';
			break;
		}
		case 'checklist':{
			$value = is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : array();
			break;
		}
		case 'editor':{
			$value = wp_kses( $value, array(
				'h1' => array(),
				'h2' => array(),
				'h3' => array(),
				'h4' => array(),
				'h5' => array(),
				'h6' => array(),
				'p' => array(),
				'br' => array(),
				'hr' => array(),
				'strong' => array(),
				'em' => array(),
				'i' => array(),
				's' => array(),
				'del' => array(),
				'ul' => array(),
				'ol' => array(),
				'li' => array(),
				'code' => array(),
				'a' => array(
					'href' => true,
					'target' => true,
					'rel' => true,
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


/**
 * Text field constructor
 * @author Misha Rudrastyh
 * @version 1.0
 * @param string $id
 * @param string $name
 * @param array|string $class
 * @param string $placeholder
 * @param int $size
 * @param int $maxlength
 * @param string $default
 */
function render_simple_text_field( $param, $value = '', $prefix = '' ) {

	$field = array();

	$field[] = '<input type="text"';
	$field[] = 'id="' . $prefix . $param['id'] . '"';
	$field[] = 'name="' . $param['id'] . '"';
	$field[] = 'value="' . ( $value ? esc_textarea( $value ) : ( isset( $param['default'] ) ? $param['default'] : '' ) ) . '"';
	$field[] = 'class="' . ( ! empty( $param['classes'] ) ? $param['classes'] : 'regular-text' ) . ( ( !empty( $param['counter'] ) && $param['counter'] == true ) ? ' with-simple-counter' : '' ) . '"';

	if( isset( $param['placeholder'] ) ) $field[] = 'placeholder="' . $param['placeholder'] . '"';
	if( !empty( $param['size'] ) ) $field[] = 'size="' . intval( $param['size'] ) . '"';
	if( !empty( $param['maxlength'] ) ) $field[] = 'maxlength="' . intval( $param['maxlength'] ) . '"';

	$field[] = '/>';

	if( !empty( $param['counter'] ) && $param['counter'] == true ) {
		$field[] = '<div class="simple-counter"><span>' . ( !empty( $value ) ? mb_strlen( $value ) : 0 ) . '</span>' . ( !empty( $param['maxlength'] ) ? ' / ' . intval( $param['maxlength'] ) : '' ) . '</div>';
	}

	return join( ' ', $field );

}

/**
 * Number field constructor
 * @author Misha Rudrastyh
 * @version 1.0
 * @param string $id
 * @param string $name
 * @param array|string $class
 * @param string $placeholder
 * @param string $default
 * @param float $min
 * @param float $max
 * @param float $step
 */
function render_simple_number_field( $param, $value = '', $prefix = '' ) {

	$field = array();

	$field[] = '<input type="number"';
	$field[] = 'id="' . $prefix . $param['id'] . '"';
	$field[] = 'name="' . $param['id'] . '"';
	$field[] = 'value="' . ( $value ? floatval( $value ) : ( isset( $param['default'] ) ? floatval( $param['default'] ) : '' ) ) . '"';
	$field[] = 'class="' . ( ! empty( $param['classes'] ) ? $param['classes'] : 'small-text' ) . '"';

	if( isset( $param['placeholder'] ) ) $field[] = 'placeholder="' . $param['placeholder'] . '"';
	if( !empty( $param['min'] ) ) $field[] = 'min="' . floatval( $param['min'] ) . '"';
	if( !empty( $param['max'] ) ) $field[] = 'max="' . floatval( $param['max'] ) . '"';
	if( !empty( $param['step'] ) ) $field[] = 'step="' . floatval( $param['step'] ) . '"';

	$field[] = '/>';

	if( !empty( $param['short_description'] ) ) $field[] = ' ' . $param['short_description'];

	return join( ' ', $field );


}


/**
 * Date field constructor
 * @author Misha Rudrastyh
 * @version 1.0
 * @param string $id
 * @param string $name
 * @param array|string $class
 * @param string $default
 * @param string $min_date
 * @param string $max_date
 * @param string $date_format
 */
function render_simple_date_field( $param, $value = '', $prefix = '' ) {

	$field = array();

	$field[] = '<input type="text"';
	$field[] = 'id="' . $prefix . $param['id'] . '"';
	$field[] = 'name="' . $param['id'] . '"';
	$field[] = 'value="' . ( $value ? esc_attr( $value ) : ( isset( $param['default'] ) ? $param['default'] : '' ) ) . '"';
	$field[] = 'class="' . ( !empty( $param['classes'] ) ? join( ' ', array( $param['classes'], 'simple-datepicker' ) ) : 'simple-datepicker' ) . '"';

	if( !empty( $param[ 'min_date' ] ) ) $field[] = 'data-mindate="' . esc_attr( $param['min_date'] ) . '"';
	if( !empty( $param[ 'max_date' ] ) ) $field[] = 'data-maxdate="' . esc_attr( $param['max_date'] ) . '"';
	$field[] = 'data-dateformat="' . ( ! empty( $param[ 'date_format' ] ) ? esc_attr( $param[ 'date_format' ] ) : 'yy-mm-dd' ) . '"';

	$field[] = '/>';

	return join( ' ', $field );


}


/**
 * Textarea field constructor
 * @author Misha Rudrastyh
 * @version 1.0
 * @param string $id
 * @param string $name
 * @param array|string $class
 * @param string $placeholder
 * @param int $maxlength
 * @param string $default
 * @param int $cols
 * @param int $rows
 */
function render_simple_textarea_field( $param, $value = '', $prefix = '' ){

	$attributes = array();
	$value = $value ? esc_textarea( $value ) : ( isset( $param['default'] ) ? $param['default'] : '' );

	$attributes[] = 'id="' . $prefix . $param['id'] . '"';
	$attributes[] = 'name="' . $param['id'] . '"';
	$attributes[] = 'class="' . ( ! empty( $param['classes'] ) ? $param['classes'] : 'large-text' ) . ( ( !empty( $param['counter'] ) && $param['counter'] == true ) ? ' with-simple-counter' : '' ) . '"';
	$attributes[] = 'rows="' . ( ! empty( $param['rows'] ) ? intval( $param['rows'] ) : 5 ) . '"';

	if( isset( $param['placeholder'] ) ) $attributes[] = 'placeholder="' . $param['placeholder'] . '"';
	if( !empty( $param['maxlength'] ) ) $attributes[] = 'maxlength="' . intval( $param['maxlength'] ) . '"';
	if( !empty( $param['cols'] ) ) $attributes[] = 'cols="' . intval( $param['cols'] ) . '" style="width:auto"';

	$return = '<textarea ' . join( ' ', $attributes ) . '>' . $value . '</textarea>';

	if( !empty( $param['counter'] ) && $param['counter'] == true ) {
		$return .= '<div class="simple-counter"><span>' . ( !empty( $value ) ? mb_strlen( $value ) : 0 ) . '</span>' . ( !empty( $param['maxlength'] ) ? ' / ' . intval( $param['maxlength'] ) : '' ) . '</div>';
	}

	return $return;

}



/**
 * Checkbox field constructor
 * @author Misha Rudrastyh
 * @version 1.0
 * @param string $id
 * @param string $name
 * @param string $default
 * @param string $short_description
 */
function render_simple_checkbox_field( $param, $value = '', $prefix = '' ){

	$field = array();
	$value = ( $value == 'yes' || ( ! $value && isset( $param['default'] ) && $param[ 'default' ] == 'yes' ) ) ? 'yes' : 'no';
	$label = ! empty( $param[ 'short_description' ] ) ? $param[ 'short_description' ] : '';

	$field[] = '<input type="checkbox"';
	$field[] = 'id="' . $prefix . $param['id'] . '"';
	$field[] = 'name="' . $param['id'] . '"';
	$field[] = checked( 'yes', $value, false );
	$field[] = '/>';

	$return = join( ' ', $field );

	if( !empty( $param['label'] ) )	$return .= '<label for="' . $prefix .$param['id'] . '"> ' . $label . '</label>';

	return $return;

}


/**
 * Select field constructor
 * @author Misha Rudrastyh
 * @version 1.0
 * @param string $id
 * @param string $name
 * @param array|string $class
 * @param array $options
 * @param string $default
 */
function render_simple_select_field( $param, $value = '', $prefix = '' ) {

	$value = $value ? $value : ( isset( $param['default'] ) ? $param['default'] : '' );

	$attributes = array();

	$attributes[] = 'id="' . $prefix . $param['id'] . '"';
	$attributes[] = 'name="' . $param['id'] . '"';
	$attributes[] = 'class="' . ( ! empty( $param['classes'] ) ? $param['classes'] : '' ) . '"';

	$field = '<select ' . join( ' ', $attributes ) . '>';

	if( isset( $param['placeholder'] ) ) {
		$field .= '<option value="">' . $param['placeholder'] . '</option>';
	}

	if( !empty( $param['options'] ) && is_array( $param['options'] ) ) {
		foreach( $param['options'] as $v => $l ) {
			$selected = $v == $value ? ' selected="selected"' : '';
			$field .= '<option value="' . $v . '" ' . $selected . ' >' . $l . '</option>';
		}
	}

	$field .= '</select>';

	return $field;

}


/**
 * Radio buttons constructor
 * @author Misha Rudrastyh
 * @version 1.0
 * @param string $id
 * @param string $name
 * @param bool $inline
 * @param array $options
 * @param string $default
 */
function render_simple_radio_field( $param, $value = '', $prefix = '' ) {

	$field = '';
	$name = $param['id'];
	$tag = ( isset( $param['inline'] ) && $param['inline'] == true ) ? 'span' : 'p';
	$style = ( isset( $param['inline'] ) && $param['inline'] == true ) ? ' class="simple-radio-inline"' : '';
	$value = $value ? $value : ( isset( $param['default'] ) ? $param['default'] : '' );


	if( !empty( $param['options'] ) && is_array( $param['options'] ) ) {
		foreach( $param['options'] as $option_value => $option_name ) {

			$field .= '<'.$tag.$style.'><label><input type="radio" name="'.$name.'" '.checked( $value, $option_value, false ).' value="' . $option_value . '"> '. $option_name .'</label></'.$tag.'>';

		}
	}

	return $field;

}


/**
 * Checkboxlist constructor (similar to radio buttons)
 * @author Misha Rudrastyh
 * @version 1.0
 * @param string $id
 * @param string $name
 * @param array $options
 * @param array $default
 */
function render_simple_checklist_field( $param, $value = '', $prefix = '' ) {

	$field = '';
	$name = $param['id'];

	$value = $value ? $value : ( isset( $param['default'] ) ? $param['default'] : '' );

	if( !empty( $param['options'] ) && is_array( $param['options'] ) ) {
		foreach( $param['options'] as $option_value => $option_name ) {

			$checked = ( is_array( $value ) && in_array( $option_value, $value ) || $value == $option_value ) ? ' checked="checked"' : '';
			$field .= '<p><label><input type="checkbox" name="'.$name.'[]"' . $checked . ' value="' . $option_value . '"> '. $option_name .'</label></p>';

		}
	}

	return $field;

}


/**
 * Image field constructor
 * @author Misha Rudrastyh
 * @version 1.0
 * @param string $id
 * @param string $name
 * @param string $default
 */
function render_simple_image_field( $param, $value = '', $prefix = '' ) {

	$name = $param['id'];
	$value = $value ? intval( $value ) : ( !empty( $param['default'] ) ? intval( $param['default'] ) : '' );

	/* if no image */
	$image = ' button">' . __('Upload image', 'simple_meta_boxes_text_domain' );
	$display = 'none';

	/* if image is set */
	if( $value && $image_attributes = wp_get_attachment_image_src( $value, 'full' ) ) {
		$image = '"><img src="' . $image_attributes[0] . '" style="max-width:300px;height:auto;display:block;" />';
		$display = 'inline-block';
	}

	return '
	<div>
		<a href="javascript:void(0)" class="simple-upload-img-button' . $image . '</a><br />
		<input type="hidden" name="' . $name . '" id="' . $prefix.$param['id'] . '" value="' . $value . '" />
		<a href="javascript:void(0)" class="simple-remove-img-button" style="display:inline-block;display:' . $display . '">' . __( 'Remove image', 'simple_meta_boxes_text_domain' ) . '</a>
	</div>
	';

}


/**
 * File field constructor
 * @author Misha Rudrastyh
 * @version 1.0
 * @param string $id
 * @param string $name
 * @param string $default
 */
function render_simple_file_field( $param, $value = '', $prefix = '' ) {

	$name = $param['id'];
	// value == attachment id
	$value = $value ? intval( $value ) : ( !empty( $param['default'] ) ? intval( $param['default'] ) : '' );

	/* if no file */
	$file = ' button">' . __( 'Upload file', 'simple_meta_boxes_text_domain' );
	$display = 'none';

	/* if file exists */
	if( $value && $url = wp_get_attachment_url( $value ) ) {
		$parsed = parse_url( $url );
		$file = '"><a href="' . $url . '">' . rawurlencode( basename( $parsed[ 'path' ] ) ) . '</a>';
		$display = 'inline-block';
	}

	return '
	<div>
		<a href="javascript:void(0)" class="simple-upload-file-button' . $file . '</a><br />
		<input type="hidden" name="' . $name . '" id="' . $prefix.$param['id'] . '" value="' . $value . '" />
		<a href="javascript:void(0)" class="simple-remove-file-button" style="display:inline-block;display:' . $display . '">' . __( 'Remove file', 'simple_meta_boxes_text_domain' ) . '</a>
	</div>
	';

}


/**
 * Editor constructor
 * @author Misha Rudrastyh
 * @version 1.0
 * @param string $id
 * @param string $name
 * @param int $rows
 * @param array $default
 */
function render_simple_editor_field( $param, $value = '', $prefix = '', $echo = false ) {

	$editor_args = array(
 		'textarea_rows'=> ( !empty( $param['rows'] ) ? intval( $param['rows'] ) : 5 ),
 		'teeny'=>true,
		'tinymce' => false,
		'media_buttons' => false,
		'drag_drop_upload' => false,
		'quicktags' => array(
			'buttons' => 'strong,em,link,ul,ol,li,code'
			)
 	);

	$name = $param['id'];
	$value = $value ? $value : ( isset( $param['default'] ) ? $param['default'] : '' );

	if( $echo == true ) {

		wp_editor( $value, $name, $editor_args );

	} else {

	 	ob_start();

	 	// Echo the editor to the buffer
	 	wp_editor( $value, $name, $editor_args );

	 	// Store the contents of the buffer in a variable
	 	$editor_contents = ob_get_clean();

	 	return $editor_contents;

	}

}



/**
 * Gallery field constructor
 * @author Misha Rudrastyh
 * @version 1.0
 * @param string $id
 * @param string $name
 * @param array $default
 */
function render_simple_gallery_field( $param, $value, $prefix = '' ) {

	$name = $param['id'];
	$images = !empty( $value ) ? array_map( 'intval', $value ) : ( ! empty( $param['default'] ) ? array_map( 'intval', $param['default'] ) : array() );

	$return = '<div><ul class="simple-gallery-field">';

	foreach( $images as $image_id ) {

		if( $image = wp_get_attachment_image_src( $image_id, array( 100, 100 ) ) ) {

			$return .= '<li data-id="' . $image_id .  '">';
			$return .= '<span style="background-image:url(' . $image[0] . ')"></span><a href="#" class="simple-gallery-remove">&times;</a>';
			$return .= '<input type="hidden" name="' . $name . '[]" value="' . $image_id . '" />';
			$return .= '</li>';

		}

	}

	$return .= '</ul><div style="clear:both"></div></div><a href="#" data-name="' . $name . '" class="button simple-upload-images-button">' . __( 'Add images', 'simple_meta_boxes_text_domain' ) . '</a>';

	return $return;

}


/**
 * Generate show_if conditionals
 * @author Misha Rudrastyh
 * @version 1.2
 * @param array $field
 * @param string $type
 */
function simple_generate_show_if( $field, $type = 'post', $fields = array(), $object_id = null ) {

	$return = array(
		'class' => '', // css class to hide/show field and to connect so we can add an event there
		'event' => '' // jQuery event
	);

	// exit the function if not enough params
	if( empty( $field[ 'show_if' ][ 'id' ] ) || ! $field[ 'show_if' ][ 'id' ] || ! isset( $field[ 'show_if' ][ 'value' ] ) ) {
		return $return;
	}

	// define the field type
	$fields = wp_list_pluck( $fields, 'type', 'id'  );
	$field[ 'show_if' ][ 'type' ] = $fields[ $field[ 'show_if' ][ 'id' ] ];

	// only conditioned to checkboxes, radios and selects
	if( ! in_array( $field[ 'show_if' ][ 'type' ], array( 'checkbox', 'radio', 'select' ) ) ) {
		return $return;
	}

	// get the value to compare
	switch( $type ) {
		case 'term' : {
			$value = get_term_meta( $object_id, $field[ 'show_if' ][ 'id' ], true );
			break;
		}
		case 'option' : {
			$value = get_option( $field[ 'show_if' ][ 'id' ] );
			break;
		}
		default: {
			$value = get_post_meta( $object_id, $field[ 'show_if' ][ 'id' ], true );
			break;
		}
	}

	// check compare to default value
	$value = $value ? $value : ( !empty( $field['default'] ) ? $field['default'] : '' );
	$return[ 'class' ] = 'simple-metabox__' . $field[ 'id' ] . ' ';

	if(
		$value != $field[ 'show_if' ][ 'value' ] && 'checkbox' != $field[ 'show_if' ][ 'type' ] // NOT checkbox AND value mismatch
	) {
		$return[ 'class' ] .= 'simple-metabox__hide'; // display:none
	} elseif(
		'checkbox' == $field[ 'show_if' ][ 'type' ]
		&& ( ( 'no' == $field[ 'show_if' ][ 'value' ] && $value && 'no' != $value ) || 'yes' == $field[ 'show_if' ][ 'value' ] && 'yes' != $value )
	){
		$return[ 'class' ] .= 'simple-metabox__hide'; // display:none
	}

	$return[ 'event' ] = "$('[name=\"{$field[ 'show_if' ][ 'id' ]}\"]').change(function(){
		if( $(this).val() == '{$field[ 'show_if' ][ 'value' ]}' || ( 'yes' == '{$field[ 'show_if' ][ 'value' ]}' && $(this).is(':checked') ) || ( 'no' == '{$field[ 'show_if' ][ 'value' ]}' && ! $(this).is(':checked') ) ) {
			$( '.simple-metabox__{$field[ 'id' ]}').removeClass( 'simple-metabox__hide' );
		} else {
			$( '.simple-metabox__{$field[ 'id' ]}').addClass( 'simple-metabox__hide' );
		}
	});";

	return $return;

}
