<?php
/**
 * @package Debug Objects
 * @author  Frank B&uuml;ltge
 */
 
/**
 * Plugin Name: Debug Objects
 * Plugin URI:  http://bueltge.de/debug-objects-wordpress-plugin/966/
 * Text Domain: debug_objects
 * Domain Path: /languages
 * Description: List filter and action-hooks, cache data, defined constants, qieries, included scripts and styles, php and memory informations and return of conditional tags only for admins; for debug, informations or learning purposes. Setting output in the settings of the plugin and use output via setting or url-param '<code>debug</code>' or set a cookie via url param '<code>debugcookie</code>' in days
 * Version:     2.0.1
 * License:     GPLv3
 * Author:      Frank B&uuml;ltge
 * Author URI:  http://bueltge.de/
 * Last Change: 01/08/2012
 */

//error_reporting(E_ALL);

//avoid direct calls to this file, because now WP core and framework has been used.
! defined( 'ABSPATH' ) and exit;

if ( ! class_exists( 'Debug_Objects' ) ) {
	
	// include plugin on hook
	add_action( 'plugins_loaded',       array( 'Debug_Objects', 'get_object' ) );
	register_activation_hook( __FILE__, array( 'Debug_Objects', 'on_activation' ) );
	
	class Debug_Objects {
		
		static private $classobj = NULL;
		
		public static $tabs = array();
		
		public static $option_string = 'debug_objects';
		
		static private $plugin;
		
		public static $by_settings = array( 'Wrap' );
		
		public static $exclude_class = array( 'Backend', 'Frontend' );
		
		/**
		 * Handler for the action 'init'. Instantiates this class.
		 * 
		 * @access  public
		 * @since   2.0.0
		 * @return  $classobj
		 */
		public function get_object() {
			
			if ( NULL === self :: $classobj ) {
				self :: $classobj = new self;
			}
			
			return self :: $classobj;
		}
		
		/**
		 * Init other methods via hook; install settings and capabilities
		 * 
		 * @since   2.0.0
		 * @return  void
		 */
		public function __construct() {
			
			// add and remove settings, the table for the plugin
			
			self :: $plugin = plugin_basename( __FILE__ );
			
			register_deactivation_hook( __FILE__, array( __CLASS__, 'on_deactivation' ) );
			register_uninstall_hook( __FILE__,    array( 'Debug_Objects', 'on_deactivation' ) );
			
			// Include settings
			require_once dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'inc/class-settings.php';
			
			self :: init_classes();
		}
		
		public function init_classes() {
			
			if ( is_multisite() && is_plugin_active_for_network( plugin_basename( __FILE__ ) ) )
				$options = get_site_option( self :: $option_string );
			else
				$options = get_option( self :: $option_string );
			
			// exclude options from include classes
			foreach ( self :: $exclude_class as $exclude_class )
				unset( $options[ strtolower( $exclude_class ) ] );
			
			if ( ! empty( $options ) ) {
				foreach ( $options as $class => $check ) {
					if ( '1' === $check )
						self :: $by_settings[] = ucwords( $class );
				}
			}
			$classes = apply_filters( 'debug_objects_classes', self :: $by_settings );
			
			foreach ( $classes as $key => $require )
				require_once dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'inc/class-' . strtolower( $require ) . '.php';
			
			foreach ( $classes as $class )
				add_action( 'init', array( 'Debug_Objects_' . $class, 'init' ) );
		}
		
		/**
		 * Return plugin comment data
		 * 
		 * @since  2.0.0
		 * @access public
		 * @param  $value string, default = 'TextDomain'
		 *         Name, PluginURI, Version, Description, Author, AuthorURI, TextDomain, DomainPath, Network, Title
		 * @return string
		 */
		public static function get_plugin_data( $value = 'TextDomain', $echo = FALSE ) {
			
			if ( ! function_exists( 'get_plugin_data' ) )
				require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
			
			$plugin_data  = get_plugin_data( __FILE__ );
			$plugin_value = $plugin_data[$value];
			
			if ( $echo )
				echo $plugin_value;
			else
				return $plugin_value;
		}
		
		public function get_plugin_string() {
			
			return self :: $plugin;
		}
		
		/**
		 * Add user rights and the db-table
		 * 
		 * @since   2.0.0
		 * @return  void
		 */
		public static function on_activation() {
			
			//add_option( self :: $option_string, array( 'php' => '1', 'hooks' => '1', 'about' => '1' ) );
			
			$GLOBALS['wp_roles'] -> add_cap( 'administrator', '_debug_objects' );
			// add table
			$table = $GLOBALS['wpdb'] -> prefix . 'hook_list';
			$GLOBALS['wpdb'] -> query(
				"CREATE TABLE $table (
				called_by varchar(96) NOT NULL,
				hook_name varchar(96) NOT NULL,
				hook_type varchar(15) NOT NULL,
				first_call int(11) NOT NULL,
				arg_count tinyint(4) NOT NULL,
				file_name varchar(128) NOT NULL,
				line_num smallint NOT NULL,
				PRIMARY KEY (first_call,hook_name) )"
			);
		}
		
		/**
		 * Delete user rights and the db-table
		 * 
		 * @since   2.0.0
		 * @return  void
		 */
		public static function on_deactivation() {
			
			unregister_setting( self :: $option_string . '_group', self :: $option_string );
			delete_option( self :: $option_string );
			
			$GLOBALS['wp_roles'] -> remove_cap( 'administrator', '_debug_objects' );
			// remove hook table
			$table = $GLOBALS['wpdb'] -> prefix . 'hook_list';
			$GLOBALS['wpdb'] -> query( "DROP TABLE IF EXISTS $table" );
		}
		
		public static function get_as_ul_tree( $arr, $root_name = '', $unserialized_string = FALSE ) {
			global $wp_object;
			
			$wp_object = 0;
			$output    = '';
			$wp_object ++;
			
			if ( ! is_object($arr) && ! is_array($arr) )
				return $output;
			
			if ($root_name) {
				$output .= '<ul class="root' . ($unserialized_string ? ' unserialized' : '' ) . '">' . "\n";
				if ( is_object($arr) ) {
					$output .= '<li class="vt-object"><span class="' . ($unserialized_string ? 'unserialized' : 'key' ) . '">' . $root_name . '</span>';
					if (!$unserialized_string)
						$output .= '<br />' . "\n";
					$output .= '<small><em>type</em>: object ( ' . get_class($arr) . ' )</small><br/><small><em>count</em>: ' . count( get_object_vars($arr) ) . '</small><ul>'; 
				} else {
					$output .= '<li class="vt-array"><span class="' . ($unserialized_string ? 'unserialized' : 'key' ) . '">' . $root_name . '</span>';
					if (!$unserialized_string)
						$output .= '<br />' . "\n";
					$output .= '<small><em>type</em>: array</small><br/><small><em>count</em>: ' . count($arr) . '</small><ul>'; 
				}
			}
			
			foreach($arr as $key => $val) {
				$wp_object ++;
				
				if ( is_numeric($key) )
					$key = "[". $key. "]"; 
				$vt = gettype($val);
				switch ($vt) {
					case "object":
						$output .= "<li class=\"vt-$vt\"><span class=\"key\">" . htmlspecialchars($key) . '</span>';
						$output .= "<br/><small><em>type</em>: $vt (" . get_class($val) . ") | <em>count</em>: " . count($val) . "</small>"; 
						if ($val) {
							$output .= '<ul>';
							$output .= Debug_Objects :: get_as_ul_tree($val);
							$output .= '</ul>';
						}
						$output .= '</li>';
					break;
					case "array":
						$output .= "<li class=\"vt-$vt\"><span class=\"key\">" . htmlspecialchars($key) . '</span>';
						$output .= "<br/><small><em>type</em>: $vt | <em>count</em>: " . count($val) . '</small>'; 
						if ($val) {
							$output .= '<ul>';
							$output .= Debug_Objects :: get_as_ul_tree($val);
							$output .= '</ul>';
						}
						$output .= '</li>';
					break;
					case "boolean":
						$output .= "<li class=\"vt-$vt\"><span class=\"key\">" . htmlspecialchars($key) . '</span>';
						$output .= "<br/><small><em>type</em>: $vt</small><br/><small><em>value</em>: </small><span class=\"value\">".($val?"true":"false"). '</span></li>';
					break;
					case "integer":
					case "double":
					case "float":
						$output .= "<li class=\"vt-$vt\"><span class=\"key\">" . htmlspecialchars($key) . '</span>';
						$output .= "<br/><small><em>type</em>: $vt</small><br/><small><em>value</em>: </small><span class=\"value\">$val</span></li>";
					break;
					case "string":
						$val = trim( $val );
						//$val = strtolower( stripslashes( $val ) );
						//$val = base64_decode($val);
						$val = preg_replace( '/;n;/', ';N;', $val );
						$val = str_replace( "\n", "", $val );
						$val = normalize_whitespace($val);
						if ( is_serialized_string( $val ) )
							$obj = unserialize( $val );
						else
							$obj = normalize_whitespace( $val );
						$is_serialized = ($obj !== false && preg_match("/^(O:|a:)/", $val));
						$output .= "<li class=\"vt-$vt\"><span class=\"key\">" . htmlspecialchars($key) . '</span>';
						$output .= "<br/><small><em>type</em>: $vt | <em>size</em>: ".strlen($val). " | <em>serialized</em>: ".(is_serialized($val) !== false?"true":"false"). '</small><br/>';
						if ( is_serialized($val) ) {
							$output .= Debug_Objects :: get_as_ul_tree($obj, "<small><em>value</em>:</small> <span class=\"value\">[unserialized]</span>", true);
						}
						else {
							if ($val)
								$output .= '<small><em>value</em>: </small><span class="value">' . htmlspecialchars($val) . '</span>';
							else
								$output .= '';
						}
						$output .= '</li>';
					break;
					default: //what the hell is this ?
						$output .= '<li id="hook_' . $wp_object . '_' . $vt . '" class="vt-' . $vt . '"><span class="key">' . htmlspecialchars($key) . '</span>';
						$output .= '<br/><small><em>type</em>: ' . $vt . '</small><br/><small><em>value</em>:</small><span class="value">' . @htmlspecialchars($val) . '</span></li>';
					break;
				}
			}
			
			if ($root_name)
				$output .= "\t" . '</ul>' . "\n\t" . '</li>' . "\n" . '</ul>' . "\n";
			
			return $output;
		}
		
	} // end class
	
} // end if class exists
