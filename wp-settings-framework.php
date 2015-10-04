<?php
/**
 * WordPress Settings Framework
 *
 * @author Gilbert Pellegrom, James Kemp
 * @link https://github.com/gilbitron/WordPress-Settings-Framework
 * @version 1.6.0
 * @license MIT
 */

if( !class_exists('WordPressSettingsFramework') ){
    /**
     * WordPressSettingsFramework class
     */
    class WordPressSettingsFramework {

        /**
         * @access private
         * @var array
         */
        private $settings_wrapper;
        
        /**
         * @access private
         * @var array
         */
        private $settings;
        
        /**
         * @access private
         * @var array
         */
        private $tabs;

        /**
         * @access private
         * @var string
         */
        private $option_group;

        /**
         * @access protected
         * @var array
         */
        protected $setting_defaults = array(
            'id'     	  => 'default_field',
            'title'  	  => 'Default Field',
            'desc'  	  => '',
            'std'    	  => '',
            'type'   	  => 'text',
            'placeholder' => '',
            'choices'     => array(),
            'class'       => ''
        );

        /**
         * Constructor
         *
         * @param string path to settings file
         * @param string optional "option_group" override
         */
        public function __construct( $settings_file, $option_group = '' ) {
            
            if( !is_file($settings_file) ) return;
            require_once( $settings_file );

            $this->option_group = preg_replace("/[^a-z0-9]+/i", "", basename($settings_file, '.php'));
            if( $option_group ) $this->option_group = $option_group;

            $this->construct_settings();

            add_action( 'admin_init',            array( $this, 'admin_init') );
            add_action( 'admin_notices',         array( $this, 'admin_notices') );
            add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts') );
            
            // if we have tabs to display
            if( !empty( $this->tabs ) ) {
                add_action( 'wpsf_before_settings',  array( $this, 'tab_links' ) );
            }
            
        }
        
        /**
         * Construct Settings
         *
         * @return array
         */
        public function construct_settings() {
            
            $this->settings_wrapper = array();
            $this->settings_wrapper = apply_filters( 'wpsf_register_settings', $this->settings_wrapper );
            
            if( !is_array($this->settings_wrapper) ){
                return new WP_Error( 'broke', __( 'WPSF settings must be an array' ) );
            }
            
            // If "sections" is set, this settings group probably has tabs
            if( isset( $this->settings_wrapper['sections'] ) ) {
            
                $this->tabs = (isset( $this->settings_wrapper['tabs'] )) ? $this->settings_wrapper['tabs'] : array();
                $this->settings = $this->settings_wrapper['sections'];
            
            // If not, it's probably just an array of settings
            } else {
                
                $this->settings = $this->settings_wrapper;
                
            }
            
        }

        /**
         * Get the option group for this instance
         *
         * @return string the "option_group"
         */
        public function get_option_group() {
            
            return $this->option_group;
            
        }

        /**
         * Registers the internal WordPress settings
         */
        public function admin_init() {
            
    		register_setting( $this->option_group, $this->option_group .'_settings', array( $this, 'settings_validate') );
    		$this->process_settings();
    		
    	}

        /**
         * Displays any errors from the WordPress settings API
         */
        public function admin_notices() {
            
        	settings_errors();
        	
    	}

    	/**
         * Enqueue scripts and styles
         */
    	public function admin_enqueue_scripts() {
        	
            wp_enqueue_style('farbtastic');
            wp_enqueue_style('thickbox');

            wp_enqueue_script('jquery');
            wp_enqueue_script('farbtastic');
            wp_enqueue_script('media-upload');
            wp_enqueue_script('thickbox');
            
    	}

    	/**
         * Adds a filter for settings validation
         *
         * @param array the un-validated settings
         * @return array the validated settings
         */
    	public function settings_validate( $input ) {
        	
    		return apply_filters( $this->option_group .'_settings_validate', $input );
    		
    	}

    	/**
         * Displays the "section_description" if specified in $this->settings
         *
         * @param array callback args from add_settings_section()
         */
    	public function section_intro( $args ) {
        	
        	if(!empty($this->settings)){
        		foreach($this->settings as $section){
                    if($section['section_id'] == $args['id']){
                        if(isset($section['section_description']) && $section['section_description']) echo '<p>'. $section['section_description'] .'</p>';
                        break;
                    }
        		}
            }
            
    	}

    	/**
         * Processes $this->settings and adds the sections and fields via the WordPress settings API
         */
    	private function process_settings() {
        	
        	if( !empty($this->settings) ){
        	    usort($this->settings, array( $this, 'sort_array'));
        		foreach( $this->settings as $section ){
            		if( isset($section['section_id']) && $section['section_id'] && isset($section['section_title']) ){
                		add_settings_section( $section['section_id'], $section['section_title'], array( $this, 'section_intro'), $this->option_group );
                		if( isset($section['fields']) && is_array($section['fields']) && !empty($section['fields']) ){
                    		foreach( $section['fields'] as $field ){
                        		if( isset($field['id']) && $field['id'] && isset($field['title']) ){
                        		    add_settings_field( $field['id'], $field['title'], array( $this, 'generate_setting'), $this->option_group, $section['section_id'], array('section' => $section, 'field' => $field) );
                        		}
                    		}
                		}
            		}
        		}
    		}
    		
    	}

    	/**
         * Usort callback. Sorts $this->settings by "section_order"
         *
         * @param mixed section order a
         * @param mixed section order b
         * @return int order
         */
    	public function sort_array( $a, $b ) {
        	
        	return $a['section_order'] > $b['section_order'];
        	
    	}

    	/**
         * Generates the HTML output of the settings fields
         *
         * @param array callback args from add_settings_field()
         */
    	public function generate_setting( $args ) {
        	
    	    $section = $args['section'];
        	$this->setting_defaults = apply_filters( 'wpsf_defaults', $this->setting_defaults );
        	extract( wp_parse_args( $args['field'], $this->setting_defaults ) );

        	$options = get_option( $this->option_group .'_settings' );
        	$el_id = $this->option_group .'_'. $section['section_id'] .'_'. $id;
        	$val = (isset($options[$el_id])) ? $options[$el_id] : $std;

        	do_action( 'wpsf_before_field' );
        	do_action( 'wpsf_before_field_'. $el_id );
    		switch( $type ){
    		    case 'text':
    		        $val = esc_attr(stripslashes($val));
    		        echo '<input type="text" name="'. $this->option_group .'_settings['. $el_id .']" id="'. $el_id .'" value="'. $val .'" placeholder="'. $placeholder .'" class="regular-text '. $class .'" />';
    		        if($desc)  echo '<p class="description">'. $desc .'</p>';
    		        break;
                case 'password':
                    $val = esc_attr(stripslashes($val));
                    echo '<input type="password" name="'. $this->option_group .'_settings['. $el_id .']" id="'. $el_id .'" value="'. $val .'" placeholder="'. $placeholder .'" class="regular-text '. $class .'" />';
                    if($desc)  echo '<p class="description">'. $desc .'</p>';
                    break;
    		    case 'textarea':
    		        $val = esc_html(stripslashes($val));
    		        echo '<textarea name="'. $this->option_group .'_settings['. $el_id .']" id="'. $el_id .'" placeholder="'. $placeholder .'" rows="5" cols="60" class="'. $class .'">'. $val .'</textarea>';
    		        if($desc)  echo '<p class="description">'. $desc .'</p>';
    		        break;
    		    case 'select':
    		        $val = esc_html(esc_attr($val));
    		        echo '<select name="'. $this->option_group .'_settings['. $el_id .']" id="'. $el_id .'" class="'. $class .'">';
    		        foreach($choices as $ckey=>$cval){
        		        echo '<option value="'. $ckey .'"'. (($ckey == $val) ? ' selected="selected"' : '') .'>'. $cval .'</option>';
    		        }
    		        echo '</select>';
    		        if($desc)  echo '<p class="description">'. $desc .'</p>';
    		        break;
    		    case 'radio':
    		        $val = esc_html(esc_attr($val));
    		        foreach($choices as $ckey=>$cval){
        		        echo '<label><input type="radio" name="'. $this->option_group .'_settings['. $el_id .']" id="'. $el_id .'_'. $ckey .'" value="'. $ckey .'" class="'. $class .'"'. (($ckey == $val) ? ' checked="checked"' : '') .' /> '. $cval .'</label><br />';
    		        }
    		        if($desc)  echo '<p class="description">'. $desc .'</p>';
    		        break;
    		    case 'checkbox':
    		        $val = esc_attr(stripslashes($val));
    		        echo '<input type="hidden" name="'. $this->option_group .'_settings['. $el_id .']" value="0" />';
    		        echo '<label><input type="checkbox" name="'. $this->option_group .'_settings['. $el_id .']" id="'. $el_id .'" value="1" class="'. $class .'"'. (($val) ? ' checked="checked"' : '') .' /> '. $desc .'</label>';
    		        break;
    		    case 'checkboxes':
    		        foreach($choices as $ckey=>$cval){
    		            $val = '';
    		            if(isset($options[$el_id .'_'. $ckey])) $val = $options[$el_id .'_'. $ckey];
    		            elseif(is_array($std) && in_array($ckey, $std)) $val = $ckey;
    		            $val = esc_html(esc_attr($val));
        		        echo '<input type="hidden" name="'. $this->option_group .'_settings['. $el_id .'_'. $ckey .']" value="0" />';
        		        echo '<label><input type="checkbox" name="'. $this->option_group .'_settings['. $el_id .'_'. $ckey .']" id="'. $el_id .'_'. $ckey .'" value="'. $ckey .'" class="'. $class .'"'. (($ckey == $val) ? ' checked="checked"' : '') .' /> '. $cval .'</label><br />';
    		        }
    		        if($desc)  echo '<p class="description">'. $desc .'</p>';
    		        break;
    		    case 'color':
                    $val = esc_attr(stripslashes($val));
                    echo '<div style="position:relative;">';
    		        echo '<input type="text" name="'. $this->option_group .'_settings['. $el_id .']" id="'. $el_id .'" value="'. $val .'" class="'. $class .'" />';
    		        echo '<div id="'. $el_id .'_cp" style="position:absolute;top:0;left:190px;background:#fff;z-index:9999;"></div>';
    		        if($desc)  echo '<p class="description">'. $desc .'</p>';
    		        echo '<script type="text/javascript">
    		        jQuery(document).ready(function($){
                        var colorPicker = $("#'. $el_id .'_cp");
                        colorPicker.farbtastic("#'. $el_id .'");
                        colorPicker.hide();
                        $("#'. $el_id .'").live("focus", function(){
                            colorPicker.show();
                        });
                        $("#'. $el_id .'").live("blur", function(){
                            colorPicker.hide();
                            if($(this).val() == "") $(this).val("#");
                        });
                    });
                    </script></div>';
    		        break;
    		    case 'file':
                    $val = esc_attr($val);
    		        echo '<input type="text" name="'. $this->option_group .'_settings['. $el_id .']" id="'. $el_id .'" value="'. $val .'" class="regular-text '. $class .'" /> ';
                    echo '<input type="button" class="button wpsf-browse" id="'. $el_id .'_button" value="Browse" />';
                    echo '<script type="text/javascript">
                    jQuery(document).ready(function($){
                		$("#'. $el_id .'_button").click(function() {
                			tb_show("", "media-upload.php?post_id=0&amp;type=image&amp;TB_iframe=true");
                			window.original_send_to_editor = window.send_to_editor;
                        	window.send_to_editor = function(html) {
                        		var imgurl = $("img",html).attr("src");
                        		$("#'. $el_id .'").val(imgurl);
                        		tb_remove();
                        		window.send_to_editor = window.original_send_to_editor;
                        	};
                			return false;
                		});
                    });
                    </script>';
                    break;
                case 'editor':
    		        wp_editor( $val, $el_id, array( 'textarea_name' => $this->option_group .'_settings['. $el_id .']' ) );
    		        if($desc)  echo '<p class="description">'. $desc .'</p>';
    		        break;
    		    case 'custom':
    		        echo $std;
    		        break;
        		default:
        		    break;
    		}
    		do_action( 'wpsf_after_field' );
        	do_action( 'wpsf_after_field_'. $el_id );
        	
    	}

    	/**
         * Output the settings form
         */
        public function settings() {
            
            do_action( 'wpsf_before_settings' );
            ?>
            <form action="options.php" method="post">
                <?php do_action( 'wpsf_before_settings_fields' ); ?>
                <?php settings_fields( $this->option_group ); ?>
        		<?php do_settings_sections( $this->option_group ); ?>
        		<p class="submit"><input type="submit" class="button-primary" value="<?php _e( 'Save Changes' ); ?>" /></p>
			</form>
    		<?php
    		do_action( 'wpsf_after_settings' );
    		
        }
        
        /**
         * Output the tab links
         */
        public function tab_links() {
            
            do_action( 'wpsf_before_tab_links' );
		
		    screen_icon();
		    ?>
		    <h2 class="nav-tab-wrapper">
    		    <?php
                $i = 0; 
                foreach ( $this->tabs as $tab_data ) {
    		        $active = $i == 0 ? 'nav-tab-active' : ''; 
    		        ?>
    		        <a class="nav-tab triggerTab <?php echo $active; ?>" href="#tab-<?php echo $tab_data['id']; ?>"><?php echo $tab_data['title']; ?></a>
                    <?php 
                $i++;
                }
    		    ?>
		    </h2>            
            <?php 
            do_action( 'wpsf_after_tab_links' );
            
        }

    }
}

if( !function_exists('wpsf_get_option_group') ){
    
    /**
     * Converts the settings file name to option group id
     *
     * @param string settings file
     * @return string option group id
     */
    function wpsf_get_option_group( $settings_file ){
        $option_group = preg_replace("/[^a-z0-9]+/i", "", basename( $settings_file, '.php' ));
        return $option_group;
    }
    
}

if( !function_exists('wpsf_get_settings') ){
    
    /**
     * Get the settings from a settings file/option group
     *
     * @param string option group id
     * @return array settings
     */
    function wpsf_get_settings( $option_group ){
        return get_option( $option_group .'_settings' );
    }
    
}

if( !function_exists('wpsf_get_setting') ){
    
    /**
     * Get a setting from an option group
     *
     * @param string option group id
     * @param string section id
     * @param string field id
     * @return mixed setting or false if no setting exists
     */
    function wpsf_get_setting( $option_group, $section_id, $field_id ){
        $options = get_option( $option_group .'_settings' );
        if(isset($options[$option_group .'_'. $section_id .'_'. $field_id])){
            return $options[$option_group .'_'. $section_id .'_'. $field_id];
        }
        return false;
    }
    
}

if( !function_exists('wpsf_delete_settings') ){
    
    /**
     * Delete all the saved settings from a settings file/option group
     *
     * @param string option group id
     */
    function wpsf_delete_settings( $option_group ){
        delete_option( $option_group .'_settings' );
    }
    
}
