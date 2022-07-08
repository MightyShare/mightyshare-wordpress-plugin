<?php
/**
 * MightyShare Meta Boxes Constructor Class.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'Mightyshare_Meta_Boxes' ) ) {
	class Mightyshare_Meta_Boxes {

		public function __construct( $metabox ) {
			$this->metabox = $metabox;
			$this->prefix  = $this->metabox['id'] . '_';

			add_action( 'add_meta_boxes', array( $this, 'create' ) );
			add_action( 'save_post', array( $this, 'save' ), 1, 2 );
		}

		public function create() {
			if ( ! empty( $this->metabox['capability'] ) && ! current_user_can( $this->metabox['capability'] ) ) {
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
		public function render( $post ) {
			wp_nonce_field( $this->metabox['id'], $this->metabox['id'] . '_wpnonce' );

			if ( isset( $this->metabox['fields'] ) && is_array( $this->metabox['fields'] ) ) {

				$table_opened = false;
				$table_closed = false;

				foreach ( $this->metabox['fields'] as $key => $field ) :

					// Begin field wrap.
					if ( isset( $field['modal_before_id'] ) && isset( $field['modal_title'] ) ) {
						if ( true === $table_opened && false === $table_closed ) {
							?>
							</tbody></table>
							<?php
							$table_opened = false;
							$table_closed = false;
						}
						?>
						<div id="<?php echo esc_attr( $field['modal_before_id'] ); ?>" style="display:none;">
						<h2><?php echo esc_html( $field['modal_title'] ); ?></h2>
						<?php
					}
					if ( false === $table_opened ) {
						$table_opened = true;
						?>
						<table class="form-table"><tbody>
						<?php
					}

					if ( in_array( $field['type'], array( 'checkbox', 'radio' ), true ) ) {
						?>
						<tr><th><?php echo esc_attr( ( ! empty( $field['label'] ) ? $field['label'] : '' ) ); ?></th><td>
						<?php
					} else {
						?>
						<tr><th><label for="<?php echo esc_attr( $this->prefix . $field['id'] ); ?>"><?php echo esc_attr( ( ! empty( $field['label'] ) ? $field['label'] : '' ) ); ?></label></th><td>
						<?php
					}

					$field['classes'] = ( isset( $field['class'] ) && ! is_array( $field['class'] ) ) ? $field['class'] : ( isset( $field['class'] ) ? join( ' ', $field['class'] ) : '' );
					$name             = $field['id'];
					$value            = get_post_meta( $post->ID, $name, true );

					switch ( $field['type'] ) :
						/* text */
						case 'text':
						default:
							render_mightyshare_text_field( $field, $value, $this->prefix );
							break;

						case 'textarea':
							render_mightyshare_textarea_field( $field, $value, $this->prefix );
							break;

						/* checkbox */
						case 'checkbox':
							render_mightyshare_checkbox_field( $field, $value, $this->prefix );
							break;

						/* select */
						case 'select':
							render_mightyshare_select_field( $field, $value, $this->prefix );
							break;

						/* radio */
						case 'radio':
							render_mightyshare_radio_field( $field, $value, $this->prefix );
							break;

						/* color */
						case 'color':
							render_mightyshare_color_field( $field, $value, $this->prefix );
							break;

						/* image */
						case 'image':
							render_mightyshare_image_field( $field, $value, $this->prefix );
							break;

				endswitch;
					?>
					</td></tr>
					<?php
					if ( isset( $field['modal_after_end'] ) ) {
						if ( true === $table_opened && false === $table_closed ) {
							?>
							</tbody></table>
							<?php
							$table_opened = false;
							$table_closed = false;
						}
						?>
						</div>
						<?php
					}

					if ( array_key_last( $this->metabox['fields'] ) === $key && false === $table_closed ) {
						?>
						</tbody></table>
						<?php
						$table_opened = false;
						$table_closed = false;
					}

				endforeach;
				?>
				<?php
			}
		}

		/**
		* Save metabox content
		*/
		public function save( $post_id, $post ) {

			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
				return;
			}

			if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
				return;
			}

			if ( ! isset( $_POST[ $this->metabox['id'] . '_wpnonce' ] ) || ! wp_verify_nonce( $_POST[ $this->metabox['id'] . '_wpnonce' ], $this->metabox['id'] ) ) {
				return;
			}

			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return;
			}

			if ( is_array( $this->metabox['post_type'] ) && ! in_array( $post->post_type, $this->metabox['post_type'], true ) || ! is_array( $this->metabox['post_type'] ) && $this->metabox['post_type'] !== $post->post_type ) {
				return;
			}

			foreach ( $this->metabox['fields'] as $field ) :

				$name  = $field['id'];
				$value = ! empty( $_POST[ $name ] ) ? sanitize_mightyshare_field( $_POST[ $name ], $field['type'] ) : '';

				update_post_meta( $post_id, $name, $value );

			endforeach;
		}
	}
}
