<?php
/**
  * Plugin Name: MightyShare
  * Plugin URI: https://mightyshare.io/wordpress/
  * Description: Automatically generate social share preview images with MightyShare!
  * Version: 1.0.0
  * Text Domain: mightyshare
  * Author: MightyShare
  * Author URI: https://mightyshare.io
*/

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly.
}

define( 'MIGHTYSHARE_VERSION', '1.0.0' );
define( 'MIGHTYSHARE_DIR_URL', plugin_dir_url( __FILE__ ) );
define( 'MIGHTYSHARE_DIR_URI', plugin_dir_path( __FILE__ ) );

class mightyshare_plugin_options {

  public function __construct() {

    add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
    add_action('in_admin_header', array( $this, 'admin_header' ));
    add_action( 'admin_init', array( $this, 'init_settings'	) );
    
    if( !isset(get_option('mightyshare')['mightyshare_api_key']) ) {
      add_action('admin_notices', array( $this, 'setup_mightyshare_message'));
    }
    
    add_filter('plugin_action_links_'.plugin_basename(__FILE__), 'mightyshare_add_plugin_page_settings_link');
    function mightyshare_add_plugin_page_settings_link( $links ) {
      $links[] = '<a href="' .
        menu_page_url('mightyshare', false) .
        '">' . __('Settings', 'mightyshare') . '</a>';
      return $links;
    }
  
  //Post meta boxes
  require_once MIGHTYSHARE_DIR_URI . '/inc/admin/simple-meta-boxes.php';
  add_filter( 'simple_register_metaboxes', 'mightshare_metaboxes' );
  function mightshare_metaboxes( $metaboxes ) {
    
    $options = get_option( 'mightyshare' );
    if(!empty($options)){
      $defaultEnabled = ' (Disabled)';
      if(!empty($_GET['post']) && $options['enabled_on']['post_types'] && get_post_type( $_GET['post'] ) && in_array(get_post_type( $_GET['post'] ), $options['enabled_on']['post_types'])){
      $defaultEnabled = ' (Enabled)';
      }
      $defaultTemplate = isset( $options['default_template'] ) ? ' ('.$options['default_template'].')' : ' (Disabled)';
    }
    
    $mightyshareGlobals = new mightyshare_globals();
    $templateOptions = $mightyshareGlobals->mightyshare_templates();
    $postTemplateOptions = ['' => 'Default'.$defaultTemplate];
    foreach($templateOptions as $themeOption){
      $postTemplateOptions[$themeOption['value']] = $themeOption['name'];
    }
    
    $post_types = get_post_types(array('public' => true), 'objects', 'and');
    $metaboxPostTypes = [];
    foreach($post_types as $key => $value) {
      $metaboxPostTypes[] = $key;
    }
    
    $metaboxes[] = array(
      'id' => 'mightyshare',
      'name' => 'MightyShare Options',
      'post_type' => $metaboxPostTypes,
      'fields' => array(
        array(
          'id' => 'mightyshare_enabled',
          'label' => 'Enable MightyShare?',
          'type' => 'select',
          'options' => array(
          '' => 'Default'.$defaultEnabled,
          'false' => 'Disabled',
          'true' => 'Enabled',
          ),
          'description' => '<a href="https://developers.facebook.com/tools/debug/" rel="nofollow noopener" target="_blank">Open Facebook\'s Open Graph Tester</a>'
        ),
        array(
          'id' => 'mightyshare_template',
          'label' => 'Overwrite Template?',
          'type' => 'select',
          'options' => $postTemplateOptions
        )
      )
    );
   
    return $metaboxes;
   
  }

  }
  
  public function setup_mightyshare_message() {
    echo "<div class='mightyshare notice notice-success'><p>" . sprintf(__('Thank you for installing <strong>MightyShare</strong> - Remember to head to the <a href="%s" title="MightyShare Settings">settings</a> to finish setting up.', 'mightyshare'), menu_page_url('mightyshare', false)) . "</p></div>";
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

  public function init_settings() {

    register_setting(
      'mightyshare',
      'mightyshare'
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
      array('label_for'=>'mightyshare_api_key_field')
    );
    
    //DISPLAY
    add_settings_section(
      'display',
      __( 'Display', 'mightyshare' ),
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
      'default_template',
      __( 'Default Template', 'mightyshare' ),
      array( $this, 'render_default_template_field' ),
      'mightyshare',
      'display',
      array('label_for'=>'default_template_field')
    );
    
    add_settings_field(
      'default_primary_color',
      __( 'Primary Color', 'mightyshare' ),
      array( $this, 'render_default_primary_color_field' ),
      'mightyshare',
      'display'
    );
    
    add_settings_field(
      'logo',
      __( 'Logo', 'mightyshare' ),
      array( $this, 'render_logo_field' ),
      'mightyshare',
      'display'
    );
    
    add_settings_field(
      'opengraph',
      __( 'Enable Open Graph', 'mightyshare' ),
      array( $this, 'render_opengraph_field' ),
      'mightyshare',
      'display'
    );

  }

  public function admin_header() {
    if(empty($_GET['page']) || !in_array($_GET['page'], array('mightyshare'))) {
      return;
    }
    
    wp_enqueue_style('wp-color-picker');
    wp_enqueue_script( 'wp-color-picker');
    
    echo '<div id="mightyshare-admin-header"><div id="mightyshare-page-title"><svg style="width:185px;height:60px;" xmlns="http://www.w3.org/2000/svg" width="1268" height="234" viewBox="0 0 1268 234"><g><g><path fill="#fff" d="M145.893 92.496c1.512 29.807.59 81.268.295 89.055-.331 9.113-1.665 34.785-2.157 39.86-.59 8.844-2.703 11.878-10.616 12.084-8.155.331-18.923-.823-27.033-5.138-2.265-1.208-3.59-2.882-3.93-5.496-.545-4.189-.527-8.396-.08-12.55 2.104-19.423 2.399-38.928 2.614-58.415.233-20.785 1.092-40.28-.94-61.11-1.056-7.77-5.962.869-11.681 25.198-2.274 7.617-4.45 16.228-6.311 19.603-4.19 7.86-14.465 8.325-18.511 1.056-2.14-3.849-3.59-9.005-4.628-13.265-2.56-10.464-6.275-20.069-9.273-30.399-1.63-5.138-5.443-5.773-6.096-2.434-1.262 7.778-.555 21.482-.671 38.212-.269 7.42 2.515 68.96 3.526 78.645 2.39 25.252 2.39 25.252-13.525 25.887-7.465.305-21.322-.841-23.29-1.128-7.055-1.011-9.955-3.508-10.304-15.288-.027-3.464-1.557-28.017-2.166-51.138C.033 141.79.516 91.753.982 79.732c.644-16.891 2.426-35.34 4.198-54.209C7.346 9.151 7.346 9.151 17.658 6.162c9.658-2.543 18.224-1.415 24.123-1.083C53.731 6.85 55.871 7.2 58.977 13.94c2.032 4.888 4.968 15.011 6.803 20.006 6.83 20.865 9.604 27.069 11.833 21 .716-2.363 3.16-10.616 4.207-14.385 3.867-12.567 3.482-15.888 9.247-29.091 1.315-3.008 3.204-6.758 9.425-7.296 8.692-1.181 17.42-1.817 26.129-.447 8.987.895 10.32 2.596 12.308 12.755 4.914 24.42 5.934 60.484 6.964 76.014zm55.196-23.255c.314 5.353.672 15.539.68 19.021.054 17.723.457 26.898-.116 44.604-.546 16.801-.644 35.33-1.495 52.105-.644 12.657-.465 23.56-1.396 36.198-.385 5.192-1.343 7.108-7.984 9.14-7.269 1.575-13.186 2.067-21.806 1.727-7.286.108-7.653-4.762-8.817-9.488-1.36-5.514-2.953-15.888-3.383-21.555-2.005-26.603-3.643-46.394-3.536-73.113.099-4.306.358-12.899.233-18.368-.313-14.044 1.316-27.937 2.864-41.847 1.155-10.294 1.773-12.236 8.719-13.444 3.723-.645 10.992-.61 14.751-.887 4.252-.313 10.473-.152 15.495.359 4.628.205 5.487 3.473 5.791 15.548zm-42.035-34.695c-5.03-9.667-4.072-16.076 3.733-23.363 11.09-10.365 30.031-8.056 38.437 4.753 2.032 3.097 3.535 6.499 3.106 10.151.08 8.056-4.216 13.185-10.187 16.408-7.868 4.251-16.237 5.513-24.714 1.181-4.216-2.157-8.02-4.628-10.375-9.13zm167.718 69.614c.877 13.105.725 26.191.957 39.278.063 26.164 0 57.807-18.985 74.993-15.217 14.975-43.199 16.667-60.886 9.56-8.226-3.697-15.262-7.958-21.68-16.873-5.2-8.065-7.617-12.756-9.837-23.685-1.907-7.609-1.37-25.833.734-34.578.6-2.346 2.04-9.086 5.2-14.466 1.71-2.918 3.366-5.594 8.397-5.594 8.253 0 19.907 1.996 29.413 7.295 9.515 5.3 7.743 8.566 6.526 13.615-1.835 7.608-3.447 13.409-3.447 13.409-2.273 8.835-2.846 17.23-1.172 24.517.68 2.882 1.781 6.114 3.885 8.37 2.264 2.434 5.934 2.255 8.029.85 1.781-1.19 2.121-1.808 2.82-3.858 2.014-5.908 4.34-17.455 4.985-22.54 1.764-13.856 2.4-19.584 2.659-37.156 0-5.988-.34-10.374-.627-15.566-.152-2.757-1.897-2.927-4.019-2.067-5.54 2.255-11.287 3.482-17.276 4.036-10.097.94-18.68-1.1-27.247-5.558-4.073-1.817-11.717-7.537-15.181-13.346-10.67-18.422-11.986-37.48-3.07-56.787 5.11-11.064 15.539-16.9 27.363-20.507 7.806-2.766 18.619-3.607 25.61-1.97 2.354.574 3.562 1.12 3.679-2.882.009-1.87.196-3.75.098-5.612.224-6.516.868-7.797 7.304-10.652 3.24-1.405 18.726-5.004 27.328-5.37 10.598-.538 12.111-.27 13.848 5.782 1.172 4.073 2.327 15.288 2.497 21.017.215 12.532.609 18.556.573 26.46.555 19.961.52 34.954 1.522 49.885zm-47.979-25.887c.117-3.965.134-5.38-.197-8.566-.438-6.472-4.44-7.895-9.184-7.77-12.128.188-21.527 3.348-21.518 13.114-.206 7.322 4.144 11.815 9.434 13.158 7.537 1.683 11.717 1.19 16.3-.958 2.802-1.378 5.317-4.028 5.165-8.978zm177.784-9.299c2.49.943 4.588 1.858 4.633 5.326.64 20.725.462 41.46 1.022 62.184.009 8.26-.24 23.09-.418 29.056-.338 14.137-2.747 47.746-6.064 54.69-3.734 8.09-6.641 8.402-28.602 9.833-16.955-.116-15.248-6.001-15-15.053.418-10.135.596-21 .81-30.816a1.505 1.505 0 0 0-1.539-1.556c-4.97.071-9.886.053-13.185-.142-4.028-.25-9.3-.027-14.608.178a1.657 1.657 0 0 0-1.61 1.671c-.026 10.385.303 22.681.427 32.782.08 6.268.436 7.397-5.503 9.593-7.673 3.147-19.8 4.846-29.954 4.454-5.584.063-6.66-2.907-7.673-8.473-2.694-14.857-2.774-29.99-3.601-45.06-1.414-25.66-.907-51.364-1.6-77.041-.01-8.269.24-23.09.417-29.056.338-14.137 2.739-47.745 6.064-54.69 3.734-8.09 6.642-8.401 28.603-9.833 16.955.116 15.248 5.993 14.999 15.053-.614 15.061-.72 31.794-1.174 44.17-.222 6.251-.435 47.924-1.075 69.431a1.607 1.607 0 0 0 1.555 1.672c5.104.178 9.985.729 17.178.782 1.04.045 5.921.311 11.407.738.88.071 1.645-.631 1.654-1.538.178-13.986.4-29.11.738-38.739.116-7.192.098-12.732.027-21.231.115-6.953 1.644-6.811 7.246-8.385 4.472-1.253 28.896-2.223 34.826 0zm193.106 75.059c-.009 25.989 0 57.419-18.876 74.49-15.141 14.874-42.953 16.555-60.54 9.495-8.188-3.672-15.185-7.904-21.56-16.76-5.175-8.01-7.567-12.67-9.78-23.525-1.894-7.558-1.37-25.66.729-34.347 2.258-8.855 3.12-11.38 15.061-9.362 7.033 1.04 15.506 3.254 21.472 5.3 12.092 4.276 12.741 4.907 9.496 17.203-2.499 9.167-3.12 17.863-1.396 25.384.676 2.863 1.778 6.073 3.868 8.314 2.258 2.418 5.903 2.24 7.984.844 1.778-1.182 2.116-1.796 2.81-3.832 2-5.868 4.312-17.337 4.952-22.388 1.458-11.425 1.965-15.292 2.302-26.93-.169-7.176-.32-13.613-.39-15.462-.125-3.299-1.726-3.726-3.895-2.659-1.04.507-2.08.996-3.12 1.512-10.501 5.228-21.313 5.557-32.347 3.458-12.412-2.347-19.702-12.385-24.335-25.642-2.951-8.455-5.539-20.351-5.823-29.687-.116-8.206-.009-10.99 9.104-12.545 7.691-1.307 23.882-1.272 31.101 1.502 2.534.978 4.5 3.023 4.9 11.38.302 6.367 1.564 12.262 3.947 17.872 3.2 7.522 9.709 8.909 14.617 2.703 3.574-4.526 5.486-10.314 5.788-25.873.169-8.953-.018-15.124-.071-20.005-.151-13.746.311-32.248.507-35.476.293-4.863 1.733-6.81 6.632-7.726 10.225-2.276 23.188-1.138 26.06-.4 8.491 2.17 8.767 2.427 8.696 12.376-.178 11.772-.045 23.562-.045 35.342-.187 13.328 2.16 59.953 2.152 85.444zm-75.565-86.35c-14.342-2.854-30.052-5.14-45.407-3.441-3.041.346-4.517 1.342-5.317 3.094-.498 1.787-.65 4.348-.951 8.153-.24 6.535-.472 51.79-1.2 72.258-.205 12.536.035 26.735.177 43.664-.089 10.82.293 24.184.427 35.013.062 4.81.258 6.588-2.374 8.153-.827.534-1.956 1.032-3.53 1.6-7.85 3.148-20.245 4.846-30.63 4.455-5.717.053-6.82-2.916-7.85-8.482-2.766-14.857-2.846-29.999-3.69-45.078-1.45-25.668-.925-51.39-1.636-77.077-.01-8.26.249-23.099.426-29.065.356-14.137.56-14.928-4.454-16.386-.267-.071-.569-.062-.845-.107-13.452.4-27.953 1.405-39.707 3.912-13.79 2.943-15.782-1.529-18.174-22.21-2.774-23.961-.987-24.939 13.47-26.468 15.72-1.663 37.708-2.525 56.414-2.979 1.965-.124 6.233-.187 11.71-.169 4.881-.08 10.35-.106 17.035-.142 24.77-.124 56.37 1.138 70.16 2.57 14.475 1.502 16.484 2.302 13.826 26.282-2.828 25.455-4.055 25.206-17.88 22.45zm196.624 103.99c2.685 14.393 0 34.059-6.293 43.529-2.73 4.798-8.02 11.27-13.498 15.351-12.827 9.56-21.77 12.344-38.24 12.997-18.529-.546-30.496-7.519-34.193-10.32-9.57-7.25-15.352-15.137-18.887-29.065-2.345-12.353-2.113-20.847-.877-30.747 1.888-11.485 1.262-13.454 10.723-15.87 7.152-1.898 24.911-.636 30.747 2.515 4.046 1.691 3.993 4.681 3.205 10.517-.949 10.097-.43 17.947 1.101 27.122.546 3.276 1.862 7.573 4.26 9.936 3.277 3.232 7.86 2.336 10.25-1.522 6.391-10.302 5.98-21.151 2.059-31.991-2.542-7.018-6.14-13.552-11.646-18.905-5.263-6.024-14.984-15.62-19.513-19.612-7.269-6.185-11.897-11.404-16.945-17.965-7.993-10.366-14.107-21.617-16.085-34.758-2.686-17.848 1.763-31.66 11.985-46.322 6.937-9.3 18.44-18.646 46.914-19.872 16.765.036 31.15 5.496 41.828 19.004 3.662 4.627 6.248 11.233 8.164 21.67.904 6.347 1.244 15.772-.188 26.594-1.808 8.101-3.222 8.567-12.37 9.99-6.759.967-15.325.09-22.074-.949-7.716-1.181-7.886-4.252-8.2-14.662-.232-6.857.43-12.97-1.1-19.71-1.37-7.448-6.857-10.455-10.617-10.814-5.469-.205-8.235 2.444-9.882 7.358-2.202 7.573-2.676 16.229 3.312 27.105 3.321 6.847 9.873 13.203 15.754 17.92 11.028 8.844 18.771 16.945 27.92 27.802 11.59 13.758 19.03 25.619 22.386 43.673zm136.813-24.796c.01 8.316-.242 23.247-.42 29.253-.34 14.232-2.766 48.068-6.105 55.059-3.76 8.145-6.687 8.459-28.796 9.9-17.08-.117-15.36-6.033-15.11-15.155.42-10.204.6-21.142.815-31.024a1.533 1.533 0 0 0-1.549-1.576c-5.004.072-9.954.054-13.275-.143-4.054-.25-9.362-.027-14.706.179-.904.027-1.62.76-1.62 1.683-.027 10.455.304 22.834.43 33.003.08 6.31.438 7.447-5.542 9.658-7.725 3.169-19.934 4.879-30.156 4.485-5.622.062-6.705-2.927-7.725-8.53-2.712-14.958-2.793-30.193-3.625-45.365-1.424-25.834-.913-51.712-1.612-77.563-.009-8.315.242-23.246.421-29.252.34-14.233 2.757-48.068 6.105-55.059 3.76-8.146 6.686-8.459 28.796-9.9 17.07.116 15.351 6.042 15.1 15.154-.617 15.164-.724 32.001-1.172 44.479-.215 6.293-.439 48.247-1.083 69.9a1.623 1.623 0 0 0 1.566 1.683c5.13.179 10.052.734 17.294.787 1.047.045 5.962.314 11.484.743a1.56 1.56 0 0 0 1.665-1.548c.18-14.08.403-29.306.743-39 .206-12.613-.035-26.908-.17-43.942.09-10.894-.286-24.338-.42-35.232-.081-6.31-.44-7.438 5.54-9.658 7.725-3.169 19.935-4.879 30.157-4.485 5.621-.062 6.704 2.927 7.725 8.53 2.72 14.958 2.802 30.193 3.634 45.365 1.423 25.833.904 51.72 1.611 77.571zm126.5 87.803c-.877 4.028-3.876 4.475-7.895 5.308-3.5.725-15.28 2.443-24.49.94-7.287-1.19-9.757-.609-10.268-8.083-.223-7.976-.295-15.951-.635-23.918-.35-8.208-.52-9.408-8.584-9.631-5.308-.108-8.155.197-14.313 1.665-3.223 1.047-3.984 5.075-4.225 6.507-1.253 7.483-1.647 14.492-1.79 22.074-.17 9.085 1.19 10.052-7.52 11.788-2.192.44-24.875-.725-33.933-1.924-5.756-.76-7.206-2.462-7.421-8.262-.295-7.958-.063-40.066 3.867-83.148 4.458-37.622 10.679-71.323 13.66-86.083 2.953-11.968 3.258-18.601 7.17-32.01 3.16-10.822 5.782-10.822 15.655-12.478 16.336-2.515 39.869-.841 43.422.063 6.821 1.727 10.634 3.571 13.615 13.507 5.881 22.969 10.276 45.034 13.445 67.653 3.858 21.868 10.267 99.797 11.09 113.188.43 7.045.663 15.96-.85 22.844zm-46.967-77.24c-.027-1.29-.134-2.57-.26-3.85-1.468-14.912-2.694-29.843-4.511-44.72-1.692-13.793-1.862-19.388-4.816-34.39-1.172-4.923-3.706-7.215-5.925 8.548-2.364 19.165-4.44 31.276-6.4 48.955-1.236 11.081-1.925 17.007-2.39 28.133-.01 5.98 1.414 6.785 8.763 5.792 3.535-.475 6.99-1.406 10.508-1.996 4.118-.699 5.13-1.406 5.031-6.472zm187.389 23.347c-1.12 11.807-3.053 24.866-8.405 35.107-6.007 11.475-12.514 20.077-22.182 23.649-4.242 1.575-14.715 2.497-21.545-1.468-12.004-6.973-18.18-17.974-23.228-32.905-5.541-16.39-6.177-31.419-7.466-48.775-.349-4.655.206-7.671-2.748-7.788-2.318-.214-2.802 1.46-3.509 8.262-1.029 9.927-1.2 15.638-1.1 24.437.116 10.41.089 27.436.313 39.26.349 18.037-1.719 18.86-6.929 20.176-5.057 1.28-12.88 1.36-16.882 1.271-12.997-.286-17.5-1.656-18.761-3.786-1.558-2.632-1.844-10.401-1.844-14.662-.01-17.5.447-31.965.268-46.94-.107-8.987.287-17.983 0-26.961-.653-20.069.08-40.102.108-60.143.027-18.234.671-25.467 1.557-43.629.143-2.971 1.441-16.962 1.79-20.981.645-7.269.645-8.79 7.672-8.754 8.817.044 22.655-.162 32.77.805 11.986 1.146 24.616 2.82 35.51 6.776 10.687 3.885 14.769 6.284 24.024 13.454 9.677 7.501 15.916 16.076 20.391 27.749 3.733 9.712 6.096 29.431 1.683 41.417-5.165 14.008-10.93 19.137-25.018 27.963-8.755 5.487-16.48 5.317-15.558 12.613 1.504 11.94 2.659 24.544 5.147 36.476.278 1.324 1.164 6.937 3.053 10.088 2.703 4.52 5.522-4.915 6.409-9.354.734-3.661 1.289-8.87 1.79-11.771 1.459-8.522 1.316-9.98 10.124-8.933 10.58 1.262 16.971 4.314 18.466 5.147 4.52 2.515 4.941 3.365 4.1 12.2zm-50.95-101.05c-2.928-6.176-12.335-12.04-15.29-13.49-7.214-3.544-12.934-5.62-16.881-6.149-5.989-.797-5.998.815-6.356 4.01-.904 13.57-.814 24.902.036 34.274.367 4.091-.036 6.597 5.3 6.597 10.302 0 17.213.51 26.674-4.529 5.514-2.936 11.243-10.75 6.516-20.713zm142.05-52.797c3.15 1.495 2.049 3.993 2.013 7.457-.018 1.978-1.307 9.55-1.97 12.871-1.01 4.897-1.61 10.017-3.875 19.407-2.372 7.017-2.372 7.017-10.724 6.73-6.301-.34-11.287-.84-18.886-.84-4.995-.421-4.995-.421-5.855 5.845-1.074 7.84-1.826 15.736-2.73 23.604-.385 3.312.645 5.219 4.225 6.105 6.561 1.62 12.962 3.867 19.46 5.746 10.992 3.178 12.263 2.426 11.547 15.495-.152 6.293-1.772 14.67-4.055 20.355-1.566 3.885-4.314 3.92-12.836 3.509-5.308-.108-9.497-1.083-15.78-1.585-5.541-.438-5.747-.376-6.007 5.443-.42 9.434-.51 18.878-1.924 28.268-.663 4.395.322 4.968 4.672 4.654 7.394-.528 14.779-.886 22.11-2.282 6.176-1.182 8.888-2.077 10.177 7.16 1.576 11.315 2.99 22.71 2.014 34.194-.304 3.563.188 5.944-3.58 7.304-6.955 2.507-16.193 2.381-23.336 2.802-4.35.26-27.328.958-36.432 1.02-3.992.027-10.258 1.469-11.072-5.397-.779-6.58-1.746-14.823-2.113-23.336-.188-6.928-.895-18.072-.895-23.962.331-12.398.099-15.897.08-30.64-.026-18.735.26-40.46 1.701-59.123 1.155-14.921 2.167-29.852 4.593-44.657 1.423-8.683 2.407-17.446 3.303-26.2.886-5.72.474-8.03 4.52-9.23 3.473-.751 9.408-.321 15.71.457 13.551 1.674 26.03 2.543 38.92 4.816 4.958.877 12.432 1.826 17.024 4.01z"/></g></g></svg></div><div id="mightyshare-admin-header-buttons"><a href="?page=mightyshare" class="mightyshare-active" title="Options">Options</a><span style="color: rgba(255,255,255,0.5); margin: 0px 10px;">v'.MIGHTYSHARE_VERSION.'</span></div></div>';
  }

  public function page_layout() {
    
    echo '<div id="mightyshare-settings-page" class="wrap">';
    echo '<h2 style="display:none;"></h2>';
    // Check required user capability
    if ( !current_user_can( 'manage_options' ) )	{
      wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'mightyshare' ) );
    }

    // Admin Page Layout
    echo '	<form action="options.php" method="post">' . "\n";
    
    settings_fields( 'mightyshare' );
    
    $this->mightyshare_settings_section('mightyshare', 'general');
    $this->mightyshare_settings_section('mightyshare', 'display');
    
    submit_button();
    echo '	</form>' . "\n";
    
    echo '</div>' . "\n";
    
    echo '<script>
    jQuery(document).ready(function(){
      jQuery(function() {
        jQuery(\'.default_primary_color_field\').wpColorPicker();
      });
    });
    
    function toggleApiKeyFieldMask(field) {
      const selectedField = document.querySelector(field);
      selectedField.type = this.event.target.checked ? "text" : "password";
    }
    function renderMightyShareTemplatePreview(){
      const result = document.querySelector(".mightyshare-image-preview");
      const templateSelected = document.querySelector(".default_template_field").value;
    if(templateSelected != "screenshot-self"){
       result.innerHTML = `<img src="https://api.mightyshare.io/template/preview/${templateSelected}.png">`;
    }else{
    result.innerHTML = ``;
    }
    }
    
    const selectElement = document.querySelector(".default_template_field");
    selectElement.addEventListener("change", (event) => {
      renderMightyShareTemplatePreview();
    }); document.addEventListener("DOMContentLoaded",renderMightyShareTemplatePreview);</script>';
    
    echo '<style>#mightyshare-admin-header{display:flex;align-items:center;background:#2e66d2;margin:0 0 10px -20px;padding:0 20px 0 22px;overflow:hidden}#mightyshare-page-title{font-size:24px;color:#fff}#mightyshare-admin-header #mightyshare-admin-header-buttons{text-transform:uppercase;line-height:60px;height:60px;display:flex;flex-grow:1;justify-content:flex-end;align-items:center;margin:0 0 0 20px}#mightyshare-admin-header #mightyshare-admin-header-buttons a{padding:8px 10px;color:#fff;text-decoration:none;margin:0 0 0 10px;line-height:normal;font-size:13px;border-radius:3px}#mightyshare-admin-header #mightyshare-admin-header-buttons a.mightyshare-active,#mightyshare-admin-header #mightyshare-admin-header-buttons a:hover{background:rgba(0,0,0,.3)}#mightyshare-admin-header #mightyshare-admin-header-notice{float:right;color:#fff;line-height:60px}#mightyshare-admin-header #mightyshare-admin-header-notice a{color:#fff;font-weight:700}#mightyshare-settings-page .mightyshare-settings-section h2{font-weight:bold;font-size:18px;line-height:normal;margin:0;padding-bottom:15px;border-bottom:1px solid #e8eef1}@media all and (max-width:782px){#mightyshare-page-title{font-size:18px}}.mightyshare-image-preview img{margin-top:0.6em;max-width: 300px;max-height:200px;}#mightyshare-settings-page .notice {margin: 10px 0px;}
    .mightyshare-toggler-wrapper{display:block;width:45px;height:25px;cursor:pointer;position:relative}.mightyshare-toggler-wrapper input[type=checkbox]{display:none}.mightyshare-toggler-wrapper input[type=checkbox]:checked+.toggler-slider{background-color:#2e66d2}.mightyshare-toggler-wrapper .toggler-slider{background-color:#ccc;position:absolute;border-radius:100px;top:0;left:0;width:100%;height:100%;-webkit-transition:all .18s ease;transition:all .18s ease}.mightyshare-toggler-wrapper .toggler-knob{position:absolute;-webkit-transition:all .18s ease;transition:all .18s ease;width:calc(25px - 6px);height:calc(25px - 6px);border-radius:50%;left:3px;top:3px;background-color:#fff}.mightyshare-toggler-wrapper input[type=checkbox]:checked+.toggler-slider .toggler-knob{left:calc(100% - 19px - 3px)}.postbox{border-color:#d1d6d9;border-radius:2px;}
    </style>';

  }
  
  //Fields for admin page
  function render_enable_mightyshare_field() {

    // Retrieve data from the database.
    $options = get_option( 'mightyshare' );

    // Set default value.
    if(empty($options)){
      $value = isset( $options['enable_mightyshare'] ) ? $options['enable_mightyshare'] : 'checked';
    }else{
      $value = isset( $options['enable_mightyshare'] ) ? $options['enable_mightyshare'] : '';
    }

    // Field output.
    echo '<label class="mightyshare-toggler-wrapper"><input type="checkbox" name="mightyshare[enable_mightyshare]" class="enable_mightyshare_field" value="checked" ' . checked( $value, 'checked', false ) . '><div class="toggler-slider">
    <div class="toggler-knob"></div></div></label> ';
    echo '<p class="description">' . __( 'This controls if MightyShare is globally enabled on your site. <br /><small>Note you can manually enable/disable MightyShare per post type below, this value will overwrite everything if disabled.</small>', 'mightyshare' ) . '</p>';

  }

  function render_mightyshare_api_key_field() {

    // Retrieve data from the database.
    $options = get_option( 'mightyshare' );

    // Set default values.
    $value_api_key = isset( $options['mightyshare_api_key'] ) ? $options['mightyshare_api_key'] : '';
    $fieldType = isset( $options['mightyshare_api_key'] ) ? 'password' : 'text';
    $checked = isset( $options['mightyshare_api_key'] ) ? '' : 'checked';

    // Field output.
    echo '<input type="'.$fieldType.'" name="mightyshare[mightyshare_api_key]" class="regular-text	mightyshare_api_key_field" placeholder="' . esc_attr__( 'API KEY', 'mightyshare' ) . '" id="mightyshare_api_key_field" value="' . esc_attr( $value_api_key ) . '"> <label><input type="checkbox" onclick="toggleApiKeyFieldMask(\'.mightyshare_api_key_field\')" '.$checked.'> Display API Key</label>';
    echo '<p class="description">' . __( 'Your MightyShare.io API Key. <br /><small>Dont\' have an API Key? <a href="https://mightyshare.io/register" rel="nofollow noopener" target="_blank">Get a free MightyShare API Key</a></small>', 'mightyshare' ) . '</p>';

  }
  
  function render_post_types_field() {
  
    // Retrieve data from the database.
    $options = get_option( 'mightyshare' );
  
    // Field output.
    $post_types = get_post_types(array('public' => true), 'objects', 'and');
    if(!empty($post_types)) {
      $used_labels = array();
      foreach($post_types as $key => $value) {
        echo "<label for='mightyshare" . (!empty($args['section']) ? "-" . $args['section'] : "") . "-post-type-" . $key . "' style='margin-right: 10px;'>";
        echo "<input type='checkbox' name='mightyshare[enabled_on][post_types][]' id='mightyshare" . (!empty($args['section']) ? "-" . $args['section'] : "") . "-post-type-" . $key . "' value='" . $key ."' ";
    
        if(isset($options['enabled_on']['post_types']) && is_array($options['enabled_on']['post_types'])) {
          if(in_array($key, $options['enabled_on']['post_types'])) {
            echo "checked";
          }
        }elseif(empty($options)){
          echo "checked";
        }
    
        echo " />" . $value->label;
        if(in_array($value->label, $used_labels)) {
          echo " (" . $value->name . ")";
        }
        echo "</label>";
        array_push($used_labels, $value->label);
      }
    }

  }
  
  function render_default_template_field() {
    
    // Retrieve data from the database.
    $options = get_option( 'mightyshare' );

    // Set default value.
    $value = isset( $options['default_template'] ) ? $options['default_template'] : '';
    
    // Field output.
    echo '<select name="mightyshare[default_template]" class="default_template_field" id="default_template_field">';
  $mightyshareGlobals = new mightyshare_globals();
    $templateOptions = $mightyshareGlobals->mightyshare_templates();
    foreach($templateOptions as $themeOption){
      echo '	<option value="'.$themeOption['value'].'" ' . selected( $value, $themeOption['value'], false ) . '> ' . __( $themeOption['name'], 'mightyshare' ) . '</option>';
    }
    echo '</select>';
    echo '<p class="description">' . __( 'Default template used for renders. <br /><small>View <a href="https://mightyshare.io/templates/" rel="nofollow noopener" target="_blank">Template Examples</a></small><br /><div class="mightyshare-image-preview"></div>', 'mightyshare' ) . '</p>';

  }
  
  function render_default_primary_color_field() {

    // Retrieve data from the database.
    $options = get_option( 'mightyshare' );

    // Set default value.
    if(empty($options)){
      $value = '#ffca39';
    }else{
      $value = isset( $options['default_primary_color'] ) ? $options['default_primary_color'] : '#ffca39';
    }

    // Field output.
    echo '<input type="text" name="mightyshare[default_primary_color]" class="default_primary_color_field" id="default_primary_color_field" value="'.$value.'">';
    echo '<p class="description">' . __( 'Primary color used in templates (typically the border color)', 'mightyshare' ) . '</p>';

  }
  
  function render_logo_field() {
    // Retrieve data from the database.
    $options = get_option( 'mightyshare' );
    
    // Field output.
    echo render_simple_image_field( array(
      'id' => 'mightyshare[logo]'
    ), $options['logo'] );
    echo '<p class="description">' . __( 'Your logo used on renders.', 'mightyshare' ) . '</p>';
  }
  
  function render_opengraph_field() {
    // Retrieve data from the database.
    $options = get_option( 'mightyshare' );
    
    // Set default value.
    if(empty($options)){
      $value = 'no';
    }else{
      $value = ( $options['output_opengraph'] == 'on' ) ? 'yes' : '';
    }
    // Field output.
    echo render_simple_checkbox_field( array(
      'id' => 'mightyshare[output_opengraph]'
    ), $value );
    echo '<p class="description">' . __( 'Check this to have MightyShare output the og:image meta tag. <br /><small>Enable this if you aren\'t using Yoast SEO or RankMath.</small>', 'mightyshare' ) . '</p>';
  }
  
  //Render Template Section
  function mightyshare_settings_section($page, $section) {
    global $wp_settings_sections;
    if(!empty($wp_settings_sections[$page][$section])) {
      echo '<div class="postbox"><div class="mightyshare-settings-section inside">';
        echo '<h2>' . $wp_settings_sections[$page][$section]['title'] . '</h2>';
        echo '<table class="form-table">';
          echo '<tbody>';
            do_settings_fields($page, $section);
          echo '</tbody>';
        echo '</table>';
      echo '</div></div>';
    }
}

}

new mightyshare_plugin_options;

class mightyshare_generate_engine {
  //Core Screenshot Engine by MightyShare.io
  public function get_image_url($url, $options, $key){
    $api_key = substr($key['api_key'], 0, 16);
    $api_secret = substr($key['api_key'],16, 32);
    $options['page'] = $url;
    $format = 'png';
    unset($options['format']);
    $optionParts = array();
    foreach ($options as $key => $values) {
      $values = is_array($values) ? $values : array($values);
      foreach ($values as $value) {
        if(!empty($value)){
          $encodedValue = urlencode($value);
          $optionParts[] = "$key=$encodedValue";
        }
      }
    }
    $queryString = implode("&", $optionParts);
    $generatedSecret = hash_hmac("sha256", $queryString, $api_secret);
    
    return "https://api.mightyshare.io/v1/$api_key/$generatedSecret/$format?$queryString";
  }
}

class mightyshare_frontend {
  
  public function __construct() {
    //Replace OG image if using an SEO plugin
    add_action('template_redirect', array($this, 'mightyshare_opengraph_meta_tags'), 1);
  }
  
  //Replace OG image if using an SEO plugin
  public function mightyshare_opengraph_meta_tags(){
    if (in_array('wordpress-seo/wp-seo.php', apply_filters('active_plugins', get_option('active_plugins'))) || in_array('wordpress-seo-premium/wp-seo-premium.php', apply_filters('active_plugins', get_option('active_plugins')))) {
      //Using Yoast SEO
      add_filter( 'wpseo_opengraph_image', array($this, 'mightyshare_overwrite_yoast_opengraph_url') );
      add_filter('wpseo_twitter_image', array($this, 'mightyshare_overwrite_yoast_opengraph_url'));
    }else if (in_array('seo-by-rank-math/rank-math.php', apply_filters('active_plugins', get_option('active_plugins')))) {
      //Using Rank Math
      add_filter( 'rank_math/opengraph/facebook/image', array($this, 'mightyshare_overwrite_rankmath_opengraph_url') );
      add_filter('rank_math/opengraph/twitter/image', array($this, 'mightyshare_overwrite_rankmath_opengraph_url'));
    } else {
      //No plugin manually add og:image meta
        $options = get_option( 'mightyshare' );
        if(!empty($options['output_opengraph']) && $options['output_opengraph'] == 'on'){
          add_action('wp_head', array($this, 'mightyshare_render_opengraph'), 1);
        }
    }
  }
  
  //Using Yoast SEO
  function mightyshare_overwrite_yoast_opengraph_url( $image ) {
    $mightyshare_frontend = new mightyshare_frontend();
    $template_parts = $mightyshare_frontend->get_mightyshare_post_details();
    if($template_parts['is_enabled']){
      return $this->mightyshare_generate_og_image();
    }
    
    return $image;
  }
  
  //Using Rank Math
  function mightyshare_overwrite_rankmath_opengraph_url( $attachment_url ) {
    $mightyshare_frontend = new mightyshare_frontend();
    $template_parts = $mightyshare_frontend->get_mightyshare_post_details();
    if($template_parts['is_enabled']){
      return $this->mightyshare_generate_og_image();
    }
    
    return $attachment_url;
  }
  
  //No SEO plugin so render tags
  function mightyshare_render_opengraph( $attachment_url ) {
    $mightyshare_frontend = new mightyshare_frontend();
    $template_parts = $mightyshare_frontend->get_mightyshare_post_details();
    if($template_parts['is_enabled']){
      echo '<meta property="og:image" content="'.$this->mightyshare_generate_og_image().'" />';
    }
    
    return $attachment_url;
  }
  
  //Generate Social Image for Post using MightyShare
  public function mightyshare_generate_og_image( $template_parts = null ){
    global $wp;
    
    if(!$template_parts){
      $template_parts = $this->get_mightyshare_post_details();
    }
    
    //Get API Key
    $options = get_option( 'mightyshare' );
    $key['api_key'] = $options['mightyshare_api_key'];
    
    //Setup defaults for render
    $renderOptions['cache'] = 'true';
    $renderOptions['height'] = '630';
    $renderOptions['width'] = '1200';
    
    //Grab the template
    if(!empty($template_parts['template'])){
        $renderOptions['template'] = $template_parts['template'];
    }
    
    //Configure the template
    $template_json = [];
    if(!empty($template_parts['title'])){
      array_push($template_json, [
        "name" => "title",
        "text" => urlencode(htmlspecialchars_decode($template_parts['title']))
      ]);
    };
    
    if(!empty($template_parts['background']) && $template_parts['background']){
      array_push($template_json, [
        "name" => "background",
        "image_url" =>	urlencode($template_parts['background'])
      ]);
    }
    
    
    if(!empty($options['logo'])&& !empty(wp_get_attachment_image_src( $options['logo'], $size = 'full')[0])){
      array_push($template_json, [
        "name" => "logo",
        "image_url" => urlencode(htmlspecialchars_decode(wp_get_attachment_image_src( $options['logo'], $size = 'full')[0]))
      ]);
  };
    
    if(!empty($options['default_primary_color'])){
      array_push($template_json, [
        "name" => "primary_color",
        "color" => $options['default_primary_color']
      ]);
    }
    
    $renderOptions['template_values'] = json_encode($template_json);
    
    $mightyshare = new mightyshare_generate_engine();
    return $mightyshare->get_image_url(home_url( $wp->request ), $renderOptions, $key);
  }
  
  //Get the current post's details no matter type
  public function get_mightyshare_post_details(){
  $options = get_option( 'mightyshare' );
  
  //Don't use if MightyShare is disabled globally
  if(!$options['enable_mightyshare']){
    return $returned_template_parts['is_enabled'] = false;
  }
  
    global $wp_query;
    
    $template_parts = get_queried_object();
    $returned_template_parts = array();
    
    //Defaults
    $returned_template_parts['is_enabled'] = true;
    
    if ( $wp_query->is_singular ) {
      $returned_template_parts['ID'] = $template_parts->ID;
      $returned_template_parts['title'] = $template_parts->post_title;
    }
    if ( $wp_query->is_archive ) {
      $returned_template_parts['ID'] = $template_parts->term_id;
      $returned_template_parts['title'] = $template_parts->name;
      $returned_template_parts['type'] = 'taxonomy';
    }
    if ( $wp_query->is_page ) {
      $loop = is_front_page() ? 'front' : 'page';
    } elseif ( $wp_query->is_home ) {
    } elseif ( $wp_query->is_tag ) {
    } elseif ( $wp_query->is_tax ) {
    } elseif ( $wp_query->is_search ) {
    } elseif ( $wp_query->is_404 ) {
    }
    
    if(!empty($returned_template_parts['ID']) && $returned_template_parts['ID']){
    
      //Template variables
      $returned_template_parts['template'] = $options['default_template'];
      
      if($options['enabled_on']['post_types'] && get_post_type($returned_template_parts['ID']) && in_array(get_post_type($returned_template_parts['ID']), $options['enabled_on']['post_types'])){
        $returned_template_parts['is_enabled'] = true;
      }else{
        $returned_template_parts['is_enabled'] = false;
      }
      
      $returned_template_parts['background'] = get_the_post_thumbnail_url( $returned_template_parts['ID'], 'full' );
      
      //Check overwrites
      $post_display_overwrite = get_post_meta( $returned_template_parts['ID'], 'mightyshare_enabled', true );
      if(!empty($post_display_overwrite)){
        $post_display_overwrite = filter_var($post_display_overwrite, FILTER_VALIDATE_BOOL);
        $returned_template_parts['is_enabled'] = ($post_display_overwrite) ? true : false;
      }
      
      $post_template_overwrite = get_post_meta( $returned_template_parts['ID'], 'mightyshare_template', true );
      if(!empty($post_template_overwrite)){
        $returned_template_parts['template'] = $post_template_overwrite;
      }
    }
    
    //Apply filters of the render for devs
    $returned_template_parts = apply_filters( 'mightyshare_filter_post', $returned_template_parts);
    return $returned_template_parts;
  }
  
}

new mightyshare_frontend;

class mightyshare_globals {
  public function mightyshare_templates() {
  $themeOptions = [
    [
    "name" => "standard-1",
    "value" => "standard-1"
    ],
    [
    "name" => "standard-2",
    "value" => "standard-2"
    ],
    [
    "name" => "mighty-1",
    "value" => "mighty-1"
    ],
    [
    "name" => "mighty-2",
    "value" => "mighty-2"
    ],
    [
    "name" => "basic-1",
    "value" => "basic-1"
    ],
    [
    "name" => "basic-2",
    "value" => "basic-2"
    ],
    [
    "name" => "business-1",
    "value" => "business-1"
    ],
    [
    "name" => "Use a screenshot of the current page",
    "value" => "screenshot-self"
    ]
  ];
  
  return $themeOptions;
  }
}

// Example how to overwrite variables in MightyShare
/*
function mightyshare_filter_post_example( $post ) {
    //Overwrite the page's template used
    $post['template'] = 'mighty-2';
    
    //Overwrite the page's title used to render
    $post['title'] = 'My new title!';
    
    //Overwrite the page's background used to render
    $post['background'] = 'https://your-image-url.jpg';

    //Force the plugin to return a MightyShare image
    $post['is_enabled'] = false;
    return $post;
}
add_filter( 'mightyshare_filter_post', 'mightyshare_filter_post_example', 10, 3 );
*/