<?php
/*
 * Option pages
 */
if( !class_exists( 'Simple_Option_Page' ) ) {

	class Simple_Option_Page {

		public $js; // jQuery code to be output in admin_footer

		function __construct( $options ) {

			$this->js = ''; // empty string
			$this->options = $options;
			$this->options['capability'] = ! empty( $this->options['capability'] ) ? $this->options['capability'] : 'manage_options';
			$this->options['position'] = ! empty( $this->options['position'] ) ? $this->options['position'] : null;
			$this->options[ 'parent_slug' ] = ! empty( $this->options['parent_slug'] ) ? $this->options[ 'parent_slug' ] : 'options-general.php';

			$this->prefix = $this->options['id'] . '_';

			if( !in_array( $this->options['id'], array('general','writing', 'reading','discussion','media','permalink' ) ) ) {
				add_action( 'admin_menu', array( $this, 'add_page' ) );
			}

			add_action( 'admin_init', array( $this, 'settings_fields') );
			add_action( 'admin_footer', array( $this, 'js' ) );

		}


		function add_page() {
			add_submenu_page( $this->options['parent_slug'], $this->options['title'], $this->options['menu_name'], $this->options['capability'], $this->options['id'], array( $this,'body'), $this->options['position'] );
		}

		function body() {
			?><div class="wrap">
			<h1><?php echo esc_html($this->options['title']); ?></h1>
			<form method="POST" action="options.php">
				<?php
					settings_fields( $this->options['id'] );
					do_settings_sections( $this->options['id'] );
					submit_button();
				?>
			</form>
			</div>
			<?php
		}

		function settings_fields(){

			// in case "section" parameter is empty
			if( empty( $this->options['sections'] ) || !is_array( $this->options['sections'] ) ) {
				$this->options['sections'] = array(
					array(
						'id'			=> 'default',
						'name'		=> '',
						'fields'	=> $this->options['fields'],
					)
				);
			}

			foreach ( $this->options['sections'] as $section ) :

				// Either NOT default section OR default section BUT not default page
				if( 'default' !== $section['id'] || !in_array( $this->options['id'], array('general','writing', 'reading','discussion','media','permalink' ) )) {
					$section_name = ! empty( $section['name'] ) ? $section['name'] : '';
					add_settings_section( $section['id'], $section_name, null, $this->options['id'] );
				}


				if( empty( $section['fields'] ) || !is_array( $section['fields'] ) ) return;


				foreach( $section['fields'] as $field ) :

					$field['classes'] = ( isset( $field['class'] ) && ! is_array( $field['class'] ) ) ? $field['class'] : ( isset( $field['class'] ) ? join( ' ', $field['class'] ) : '' );

					$name = $field['id'];
					$field['value'] = get_option( $name );

					if( ! in_array( $field['type'], array( 'checkbox', 'radio', 'checklist' ) ) ) {
						$field[ 'label_for' ] = $this->prefix . $name;
					}

					// show if
					$show_if = simple_generate_show_if( $field, 'option', $section['fields'] );
					$this->js .= $show_if[ 'event' ];
					$field['class'] = $show_if['class'];


					add_settings_field(
						$name,
						( ! empty( $field['label'] ) ? $field['label'] : '' ),
						array( $this, 'field'),
						$this->options['id'],
						$section['id'],
						$field
					);

					// define sanitization function
					switch( $field['type'] ) :
						case 'text':
						default:{
							$sanitize_callback = 'sanitize_text_field';
							break;
						}
						case 'textarea':{
							$sanitize_callback = 'sanitize_textarea_field';
							break;
						}
						case 'checkbox':{
							$sanitize_callback = array( $this, 'sanitize_checkbox' );
							break;
						}
						case 'checklist':{
							$sanitize_callback = array( $this, 'sanitize_checklist' );
							break;
						}
						case 'editor':{
							$sanitize_callback = array( $this, 'sanitize_editor' );
							break;
						}
						case 'gallery':{
							$sanitize_callback = array( $this, 'sanitize_gallery' );
							break;
						}
					endswitch;

					register_setting( $this->options['id'], $name, array( 'sanitize_callback'=> $sanitize_callback ) );

				endforeach;


			endforeach;

		}

		function field( $param = array() ) {
			switch ( $param['type'] ) :

				/* text */

				case 'text':
				default:{

					echo esc_html(render_simple_text_field( $param, $param['value'], $this->prefix ));
					break;

				}

				case 'number':{

					echo esc_html(render_simple_number_field( $param, $param['value'], $this->prefix ));
					break;

				}

				/* date and datetime  */
				case 'datetime':
				case 'date':{

					echo esc_html(render_simple_date_field( $param, $param['value'], $this->prefix ));
					break;

				}

				// /* colorpicker */
				//
				// case 'color':{
				//
				// 	$param['class'][] = 'simple-color-field';
				// 	echo $this->text( $param, $param['value'], $this->prefix );
				// 	break;
				//
				// }

				/* textarea */

				case 'textarea':{

					echo esc_html(render_simple_textarea_field( $param, $param['value'], $this->prefix ));
					break;

				}

				/* checkbox */

				case 'checkbox':{

					echo esc_html(render_simple_checkbox_field( $param, $param['value'], $this->prefix ));
					break;

				}

				/* select */

				case 'select':{

					echo esc_html(render_simple_select_field( $param, $param['value'], $this->prefix ));
					break;

				}

				/* radio */

				case 'radio':{

					echo esc_html(render_simple_radio_field( $param, $param['value'], $this->prefix ));
					break;

				}

				/* checklist */

				case 'checklist':{

					echo esc_html(render_simple_checklist_field( $param, $param['value'], $this->prefix ));
					break;

				}

				/* image */

				case 'image':{

					echo esc_html(render_simple_image_field( $param, $param['value'], $this->prefix ));
					break;

				}

				/* gallery */

				case 'gallery':{

					echo esc_html(render_simple_gallery_field( $param, $param['value'], $this->prefix ));
					break;

				}

				/* editor */

				case 'editor':{

					render_simple_editor_field( $param, $param['value'], $this->prefix, true );
					break;

				}


			endswitch;

			if( isset( $param['description'] ) ) {
				echo esc_html('<p class="description">' . $param['description'] . '</p>');
			}

		}


		function sanitize_checkbox( $input ) {
			return sanitize_simple_field( $input, 'checkbox' );
		}

		function sanitize_checklist( $input ) {
			return sanitize_simple_field( $input, 'checklist' );
		}

		function sanitize_editor( $input ) {
			return sanitize_simple_field( $input, 'editor' );
		}

		function sanitize_gallery( $input ) {
			return sanitize_simple_field( $input, 'gallery' );
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
