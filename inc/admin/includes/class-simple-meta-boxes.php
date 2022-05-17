<?php
/**
 * Meta Boxes Constructor Class
 * @author Misha Rudrastyh
 * @version 1.0
 * @param string $id
 * @param string $name
 * @param array|string $post_type
 * @param string $priority
 * @param string $capability
 * @param array $fields
 */

if( !class_exists( 'Simple_Meta_Boxes' ) ) {

	class Simple_Meta_Boxes{

		public $js; // jQuery code to be output in admin_footer

		/**
		 * Constructor
		 */
		function __construct( $metabox ) {

			$this->metabox = $metabox;
			$this->prefix = $this->metabox['id'] .'_';
			$this->js = ''; // empty string

			add_action( 'add_meta_boxes', array( $this, 'create' ) );
			add_action( 'save_post', array( $this, 'save' ), 1, 2 );
			add_action( 'admin_footer', array( $this, 'js' ) );

		}

		/**
		 * Create a metabox for each post type and with given capabilities
		 */
		function create() {

			if( !empty( $this->metabox['capability'] ) && !current_user_can( $this->metabox['capability'] ) ) {
				return;
			}

			add_meta_box(
				$this->metabox['id'],
				$this->metabox['name'],
				array( $this, 'render' ),
				$this->metabox['post_type'],
				'normal',
				( isset( $this->metabox['priority'] ) ? $this->metabox['priority'] : 'default' )
			);

		}

		/**
		 * Meta box content
		 */
		function render( $post ){

			wp_nonce_field( $this->metabox['id'], $this->metabox['id'].'_wpnonce' );


			if( isset( $this->metabox['fields'] ) && is_array( $this->metabox['fields'] ) ) {

				$metabox_html = '<table class="form-table"><tbody>';

				foreach ( $this->metabox['fields'] as $field ):

					// show if
					$show_if = simple_generate_show_if( $field, 'post', $this->metabox['fields'], $post->ID );
					$this->js .= $show_if[ 'event' ];

					// begin field wrap
					if( in_array( $field['type'], array( 'checkbox', 'radio' ) ) ) {
						$metabox_html .= '<tr class="' . $show_if[ 'class' ] . '"><th style="font-weight:normal">' . ( ! empty( $field['label'] ) ? $field['label'] : '' ) . '</th><td>';
					} else {
						$metabox_html .= '<tr class="' . $show_if[ 'class' ] . '"><th style="font-weight:normal"><label for="' . $this->prefix . $field['id'] . '">' . ( ! empty( $field['label'] ) ? $field['label'] : '' ) . '</label></th><td>';
					}

					$field['classes'] = ( isset( $field['class'] ) && ! is_array( $field['class'] ) ) ? $field['class'] : ( isset( $field['class'] ) ? join( ' ', $field['class'] ) : '' );
					$name = $field['id'];
					$value = get_post_meta( $post->ID, $name, true );

					switch ( $field['type'] ) :

						/* text */

						case 'text':
						default:{

							$metabox_html .= render_simple_text_field( $field, $value, $this->prefix );
							break;

						}

						case 'number':{

							$metabox_html .= render_simple_number_field( $field, $value, $this->prefix );
							break;

						}

						/* date and datetime  */
						case 'datetime':
						case 'date':{

							$metabox_html .= render_simple_date_field( $field, $value, $this->prefix );
							break;

						}

						// /* colorpicker */
						//
						// case 'color':{
						//
						// 	$field['class'][] = 'simple-color-field';
						// 	$metabox_html .= $this->text( $field, $value, $this->prefix );
						// 	break;
						//
						// }

						/* textarea */

						case 'textarea':{

							$metabox_html .= render_simple_textarea_field( $field, $value, $this->prefix );
							break;

						}

						/* checkbox */

						case 'checkbox':{

							$metabox_html .= render_simple_checkbox_field( $field, $value, $this->prefix );
							break;

						}

						/* select */

						case 'select':{

							$metabox_html .= render_simple_select_field( $field, $value, $this->prefix );
							break;

						}

						/* radio */

						case 'radio':{

							$metabox_html .= render_simple_radio_field( $field, $value, $this->prefix );
							break;

						}

						/* checklist */

						case 'checklist':{

							$metabox_html .= render_simple_checklist_field( $field, $value, $this->prefix );
							break;

						}

						/* image */

						case 'image':{

							$metabox_html .= render_simple_image_field( $field, $value, $this->prefix );
							break;

						}

						/* file */

						case 'file':{

							$metabox_html .= render_simple_file_field( $field, $value, $this->prefix );
							break;

						}

						/* gallery */

						case 'gallery':{

							$metabox_html .= render_simple_gallery_field( $field, $value, $this->prefix );
							break;

						}

						/* editor */

						case 'editor':{

							$metabox_html .= render_simple_editor_field( $field, $value, $this->prefix );
							break;

						}


					endswitch;

					if( isset( $field['description'] ) ) {
						$metabox_html .= '<p class="description">' . $field['description'] . '</p>';
					}

					$metabox_html .= '</td></tr>';

				endforeach;

				$metabox_html .= '</tbody></table>';

				echo $metabox_html;

			}

		}

		/**
		 * Save metabox content
		 */
		function save( $post_id, $post ){

			//echo '<pre>';print_r( $_POST ); exit;

    	if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) {
        return;
			}

    	if ( defined('DOING_AJAX') && DOING_AJAX ) {
        return;
			}

			if( !isset( $_POST[ $this->metabox['id'].'_wpnonce' ] ) || !wp_verify_nonce( $_POST[ $this->metabox['id'].'_wpnonce' ], $this->metabox['id'] ) ) {
				return;
			}

			if ( !current_user_can( 'edit_post', $post_id ) ) {
				return;
			}

			if ( is_array( $this->metabox['post_type'] ) && !in_array( $post->post_type, $this->metabox['post_type'] ) || !is_array( $this->metabox['post_type'] ) && $this->metabox['post_type'] !== $post->post_type ) {
				return; // this post type does not have a metabox
			}

			foreach ( $this->metabox['fields'] as $field ) :

				$name = $field['id'];
				$value = !empty( $_POST[ $name ] ) ? sanitize_simple_field( $_POST[ $name ], $field['type'] ) : '';

				update_post_meta( $post_id, $name, $value );

			endforeach;

		}



		// show if js
		function js() {

			if( !$this->js ) {
				return;
			}

			echo esc_html('<script>jQuery( function( $ ) { ' . $this->js . ' } );</script>');

		}


	}

}
