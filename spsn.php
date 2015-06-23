<?php 
/*
Plugin Name: Simple post status notifications
Plugin URI: http://dientuki.com.ar/simple-post-status-notifications
Description: Send post status notifications by email to Editors when posts are submitted for review and to Author when the Editor publish or send to draf.
version: 1.2
Author: Dientuki
Author URI: http://dientuki.com.ar/
*/

class SPSN
{
  /**
   * Holds the values to be used in the fields callbacks
   *
   * @var array
   */
  private $options;
  
  /**
   * Notice status and message
   * 
   * @var array
   */
  private $notice = array();
  
  /**
   * Replace values
   *  
   * @var array
   */
  private $replaces = array();
  
  /**
   * Start up
   */
  public function __construct()
  {
    add_action('init', array($this, 'add_language') );
    add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
    add_action( 'admin_init', array( $this, 'page_init' ) );
    add_action( 'transition_post_status', array( $this, 'spsn_save_post') , 10, 3 );
    add_action('admin_notices', array( $this, 'spsn_admin_messages') );
  }
  
  /**
   * Set the default values
   * 
   * @return array
   */
  public static function get_defaults(){
    return array('subject2editor' => '[%BLOGNAME%] New post to check: "%POSTNAME%"',
               'template2editor' => "%AUTHOR% wrote: \"%POSTNAME%\".\n\nCheck it %POST_EDIT%",
               'subject2author_ok' => '[%BLOGNAME%] "%POSTNAME%" is published! :)',
               'template2author_ok' => "%EDITOR% reviewed: \"%POSTNAME%\" and published !\n\nLook out %PERMALINK% and share!",
               'subject2author_fail' => '[%BLOGNAME%] "%POSTNAME%" has returning to draft :(',
               'template2author_fail' => "%EDITOR% reviewed: \"%POSTNAME%\" and had found problems.\n\nCheck it %POST_EDIT%");
  }
    
  /**
   * Add language
   */
  public function add_language(){
    // Localization
    load_plugin_textdomain('spsn', false, dirname(plugin_basename(__FILE__)) . '/languages' );
  }  
  
  /**
   * Show notice
   */
  public function spsn_admin_messages() {
    
    
    if (isset($_GET) && isset($_GET['spsn-notice'])){

      $this->notice = $_GET['spsn-notice'];
      
      add_settings_error( 'spsn-notice',
                          'spsn-notice-' . $this->notice['status'],
                          urldecode($this->notice['message']),
                          $this->notice['status'] );  
      
      settings_errors( 'spsn-notice' );
    }
  }

  /**
   * Add options page
   */
  public function add_plugin_page()
  {
    // This page will be under "Settings"
    $spsn_admin_page = add_options_page( 'Settings Admin',
                                        'Simple Post Status Notifications',
                                        'manage_options',
                                        'spsn-settings',
                                        array( $this, 'create_admin_page' )
                                        );
    add_action('load-'.$spsn_admin_page, array($this, 'add_help_tab'));
  }
  
  /**
   * Add help tab
   */  
  public function add_help_tab(){
    $screen = get_current_screen();
    
    $options = array();

    $screen->add_help_tab( array(
        'id'	=> 'spsn_help_overview',
        'title'	=> __('Overview', 'spsn'),
        'content'	=> '<p>'.__('On this screen, you can manage Simple Post Status Notifications templates to use in the email exchange', 'spsn') .'</p>',
    ) );
    
    $options[] = '<li>AUTHOR: ' .__('AUTHOR', 'spsn') .'</li>';
    $options[] = '<li>BLOGNAME: ' .__('BLOGNAME', 'spsn') .'</li>';
    $options[] = '<li>EDITOR: ' .__('EDITOR', 'spsn') .'</li>';
    $options[] = '<li>PERMALINK: ' .__('PERMALINK', 'spsn') .'</li>';
    $options[] = '<li>POST_EDIT: ' .__('POST_EDIT', 'spsn') .'</li>';
    $options[] = '<li>POSTNAME: ' .__('POSTNAME', 'spsn') .'</li>';
    
    $options = '<ul>' . join('',$options) . '</ul>';
    
    $screen->add_help_tab( array(
        'id'	=> 'spsn_help_tags',
        'title'	=> __('Template-tags', 'spsn'),
        'content'	=> '<p>'.__('A Template-tag is a short code enclosed in percentage used in the emails.', 'spsn') . '</p>' . $options,
    ) );
         
  }

  /**
   * Options page callback
   */
  public function create_admin_page()
  {
    // Set class property
    $this->options = get_option( 'spsn_options' );
    ?>
        <div class="wrap">
            <h2>Simple post status notifications Settings</h2>  
            <form method="post" action="options.php">
            <?php
                // This prints out all hidden setting fields
                settings_fields( 'spsn_option_group' );   
                do_settings_sections( 'spsn-setting' );
                submit_button(); 
            ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register and add settings
     */
    public function page_init()
    {        
        register_setting(
            'spsn_option_group', // Option group
            'spsn_options', // Option name
            array( $this, 'sanitize' ) // Sanitize
        );

        add_settings_section(
            'spsn-setting-section', // ID
            null, // Title
            null, // Callback
            'spsn-setting' // Page
        );  

        add_settings_field(
          'subject_to_editor', // ID
          __('Subject to editor', 'spsn'), // Title
          array( $this, 'subject_to_editor_callback' ), // Callback
          'spsn-setting', // Page
          'spsn-setting-section' // Section
        );
                
        add_settings_field(
            'template_to_editor', // ID
            __('Text to editor', 'spsn'), // Title 
            array( $this, 'template_to_editor_callback' ), // Callback
            'spsn-setting', // Page
            'spsn-setting-section' // Section           
        );   

        add_settings_field(
          'subject_to_author_ok', // ID
          __('Subject to author when published', 'spsn'), // Title
          array( $this, 'subject_to_author_ok_callback' ), // Callback
          'spsn-setting', // Page
          'spsn-setting-section' // Section
        );        

        add_settings_field(
            'template_to_author_ok', // ID
            __('Text to author when published', 'spsn'), 
            array( $this, 'template_to_author_ok_callback' ), 
            'spsn-setting', 
            'spsn-setting-section'
        );  

        add_settings_field(
          'subject_to_author_fail', // ID
          __('Subject to author when returning to draft', 'spsn'), // Title
          array( $this, 'subject_to_author_fail_callback' ), // Callback
          'spsn-setting', // Page
          'spsn-setting-section' // Section
        );
        
        add_settings_field(
          'template_to_author_fail', // ID
          __('Text to author when returning to draft', 'spsn'),
          array( $this, 'template_to_author_fail_callback' ),
          'spsn-setting',
          'spsn-setting-section'
        );        
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize( $input )
    {
        $new_input = array();
        
        $defaults = $this->get_defaults();
               
        foreach ($input as $key => $value){
          //$new_input[$key] = sanitize_text_field( $value );
          $new_input[$key] = trim($value) == '' ? $defaults[$key] : $value;
        }

        return $new_input;
    }

    /**
     * Get the settings option array and print one of its values
     */
    public function subject_to_editor_callback()
    {
      printf(
      '<input id="subject2editor" name="spsn_options[subject2editor]" value="%s" class="large-text" />',
      isset( $this->options['subject2editor'] ) ? esc_attr( $this->options['subject2editor']) : ''
          );
    }
        
    /** 
     * Get the settings option array and print one of its values
     */
    public function template_to_editor_callback()
    {
        printf(
            '<textarea id="template2editor" name="spsn_options[template2editor]" class="large-text" rows="6" >%s</textarea>',
            isset( $this->options['template2editor'] ) ? esc_attr( $this->options['template2editor']) : ''
        );
    }
    
    /**
     * Get the settings option array and print one of its values
     */
    public function subject_to_author_ok_callback()
    {
      printf(
      '<input id="subject2author_ok" name="spsn_options[subject2author_ok]" value="%s" class="large-text" />',
      isset( $this->options['subject2author_ok'] ) ? esc_attr( $this->options['subject2author_ok']) : ''
          );
    }    

    /** 
     * Get the settings option array and print one of its values
     */
    public function template_to_author_ok_callback()
    {
        printf(
            '<textarea id="template2author_ok" name="spsn_options[template2author_ok]" class="large-text" rows="6" >%s</textarea>',
            isset( $this->options['template2author_ok'] ) ? esc_attr( $this->options['template2author_ok']) : ''
        );
    }
    
    /**
     * Get the settings option array and print one of its values
     */
    public function subject_to_author_fail_callback()
    {
      printf(
      '<input id="subject2author_fail" name="spsn_options[subject2author_fail]" value="%s" class="large-text" />',
      isset( $this->options['subject2author_fail'] ) ? esc_attr( $this->options['subject2author_fail']) : ''
          );
    }
    
    /**
     * Get the settings option array and print one of its values
     */
    public function template_to_author_fail_callback()
    {
      printf(
      '<textarea id="template2author_fail" name="spsn_options[template2author_fail]" class="large-text" rows="6" >%s</textarea>',
      isset( $this->options['template2author_fail'] ) ? esc_attr( $this->options['template2author_fail']) : ''
          );
    }    
    
    /**
     * Generate the replace data
     * 
     * @param object $post_type Post type object
     */
    private function generate_replaces($post){
      
      $author = get_userdata($post->post_author);
      $this->replaces['%BLOGNAME%'] =  get_bloginfo();
      $this->replaces['%POSTNAME%'] =  $post->post_title;
      $this->replaces['%POST_EDIT%'] =  get_edit_post_link($post->ID, '');
      $this->replaces['%AUTHOR%'] =  ucfirst($author->user_login);
      $this->replaces['%PERMALINK%'] =  get_permalink($post->ID);
    
    }
    
    /**
     * Add notice query var for info
     *
     * @param mixed $location dunno
     */    
    public function add_notice_query_var( $location ) {
      remove_filter( 'redirect_post_location', array( $this, 'add_notice_query_var' ), 99 );      

      $this->notice['message'] = urlencode($this->notice['message']);
      
      return add_query_arg( array('spsn-notice' => $this->notice) , $location );
    }    
    
    /**
     * Check status and send emails
     *
     * @param string $new_status New post status after an update
     * @param string $old_status Previous post status.
     * @param object $post_type Post type object
     */    
    public function spsn_save_post($new_status, $old_status, $post){
      //Check change status
      if ($new_status == $old_status) {
        return false;
      }

      global $current_user;
      
      //Check if the author is publish to prod
      if ( ($new_status == 'publish') &&  ($current_user->ID == $post->post_author)) {
        return false;
      }      
      
      
      $this->options = get_option( 'spsn_options' );
      
      $headers[] = 'From: ' . get_bloginfo('name') . ' <' . get_bloginfo('admin_email') . '>';
      
      //Draf to pending, let email to editor
      if ( ($old_status == 'draft') && ($new_status == 'pending') ) {
        $this->generate_replaces($post);
        
        $editors = get_users( array('role' => 'editor') );
        $search = array_keys($this->replaces);
        
        foreach ($editors as $editor) {
          $this->replaces['%EDITOR%'] = ucfirst($editor->user_nicename);
          $replace = array_values($this->replaces);
          
          $subject = str_replace($search, $replace, $this->options['subject2editor']);
          $message = str_replace($search, $replace, $this->options['template2editor']);

          $tmp[] = wp_mail($editor->user_email, $subject, $message, $headers);
        }
        
        $notice['status'] = true;
        
        foreach ($tmp as $t){
          if ($t == false) {
            $notice['status'] = false;
            break;
          }
        }
        
        if ($notice['status']) {
          $notice['message'] = __('Notice was given to the editors', 'spsn'); //'Mensaje enviado a los editores';
        } else {
          $notice['message'] = __('Failed to notify any or all editors', 'spsn');
        }
        
      }
      
      //Pending to another state, let email to author
      if ( $old_status == 'pending') {
        
        $this->generate_replaces($post);
        $this->replaces['%EDITOR%'] = ucfirst($current_user->user_login);
        $author = get_userdata($post->post_author);
        $email = $author->user_email;
        
        $search = array_keys($this->replaces);
        $replace = array_values($this->replaces);
        
        
        if ($new_status == 'draft') {
          //send fail email
          $subject = str_replace($search, $replace, $this->options['subject2author_fail']);
          $message = str_replace($search, $replace, $this->options['template2author_fail']);

          $notice['status'] = wp_mail($email, $subject, $message, $headers);
        }
        
        if ($new_status == 'publish') {
          //send ok email
          $subject = str_replace($search, $replace, $this->options['subject2author_ok']);
          $message = str_replace($search, $replace, $this->options['template2author_ok']);
                    
          $notice['status'] = wp_mail($email, $subject, $message, $headers);
        }
        
        if (isset($notice) && isset($notice['status'])){
          if ($notice['status']) {
            $notice['message'] = __('Notice was given to the author', 'spsn');
          } else {
            $notice['message'] = __('Failed to notify the author', 'spsn');
          }
        }
      }
            
      if (isset($notice)){
        $notice['status'] = $notice['status'] == true ? 'updated' : 'error';
        $this->notice = $notice;
        
        add_filter( 'redirect_post_location', array( $this, 'add_notice_query_var' ), 99 );
      }
    }
}

if( is_admin() ) {
  $SPSN_page = new SPSN();
}

/**
 * Activatation / Deactivation
 */

function spsn_activate(){
  add_option( 'spsn_options', SPSN::get_defaults(), '', 'no' );
}

function spsn_deactivate(){
  delete_option( 'spsn_options' );
}

register_activation_hook(__FILE__ , 'spsn_activate' );
register_deactivation_hook(__FILE__ , 'spsn_deactivate' );
