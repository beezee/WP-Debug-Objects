<?php
/**
 * Add small screen with informations about hooks on current page of WP
 *
 * @package     Debug Objects
 * @subpackage  Current Hooks
 * @author      Frank B&uuml;ltge
 * @since       2.0.0
 */

if ( ! class_exists( 'Debug_Objects_Page_Hooks' ) ) {
	
	class Debug_Objects_Page_Hooks extends Debug_Objects {
		
		static private $table;
		
		public static function init() {
			
			if ( ! current_user_can( '_debug_objects' ) )
				return;
			
			self :: $table = $GLOBALS['wpdb'] -> prefix . 'hook_list';
			
			// self :: control_schedule_record();
			// add_action( 'record_hook_usage', array( 'Debug_Objects_Page_Hooks', 'control_record' ) );
			
			add_action( 'all', array( __CLASS__, 'record_hook_usage' ) );
			add_filter( 'debug_objects_tabs', array( __CLASS__, 'get_conditional_tab' ) );
		}
		
		public static function get_conditional_tab( $tabs ) {
			
			$tabs[] = array( 
				'tab' => __( 'Page Hooks', parent :: get_plugin_data() ),
				'function' => array( __CLASS__, 'get_page_hooks' )
			);
			
			return $tabs;
		}
		
		public function control_schedule_record() {
			// wp_clear_scheduled_hook('record_hook_usage');
			if ( ! wp_next_scheduled( 'record_hook_usage' ) )
				wp_schedule_event( time(), 'daily', 'record_hook_usage' ); // hourly, daily and twicedaily
			
		}
		
		public function control_record() {
			
			add_action( 'all', array( __CLASS__, 'record_hook_usage' ) );
		}
		
		public function get_page_hooks( $echo = TRUE ) {
			global $wpdb;
			
			$hooks = $wpdb -> get_results( 'SELECT * FROM ' . self :: $table . ' ORDER BY first_call' );
			
			$html = array();
			$html[] = '<table>
			<tr>
				<th>1.Call</th>
				<th>Hook-Name</th>
				<th>-Type</th>
				<th>Arguments</th>
				<th>Called by</th>
				<th>Line</th>
				<th>File Name</th>
			</tr>';
			
			$class = '';
			foreach( $hooks as $hook ) {
				$class = ( ' class="alternate"' == $class ) ? '' : ' class="alternate"';
				if ( 30 < (int) strlen( $hook -> hook_name ) )
					$hook->hook_name = '<span title="' . $hook -> hook_name . '">' . substr($hook -> hook_name, 0, 36) . '</span>';
				/*
				if ( 20 < (int) strlen( $hook -> file_name ) )
					$hook->file_name = '<span title="' . $hook -> file_name . '">' . substr($hook -> file_name, -30, 30) . '</span>';
				*/
				$html[] = "<tr{$class}>
					<td>{$hook->first_call}</td>
					<td>{$hook->hook_name}</td>
					<td>{$hook->hook_type}</td>
					<td>{$hook->arg_count}</td>
					<td>{$hook->called_by}</td>
					<td>{$hook->line_num}</td>
					<td>{$hook->file_name}</td>
				</tr>";
			}
			$html[] = '</table>';
			
			$output = implode( "\n", $html );
			
			if ( $echo )
				echo $output;
			else
				return $output;
		}
		
		function record_hook_usage( $hook ) {
			global $wpdb;
			
			static $in_hook = FALSE;
			static $first_call = 1;
			static $doc_root;
			
			$callstack = debug_backtrace();
			
			if ( ! $in_hook ) {
				$in_hook = TRUE;
				
				if ( 1 == $first_call ) {
					$doc_root = esc_attr( $_SERVER['DOCUMENT_ROOT'] );
					
					$results = $wpdb -> get_results( 'SHOW TABLE STATUS LIKE \'' . self :: $table . '\'');
					if ( 1 == count($results) ) {
						$wpdb -> query( 'TRUNCATE TABLE ' . self :: $table );
					} else {
						$wpdb -> query(
							"CREATE TABLE self :: $table (
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
				}
				
				$args = func_get_args();
				$arg_count = count($args) - 1;
				$hook_type = str_replace( 'do_', '',
					str_replace(
						'apply_filters', 'filter',
						str_replace( '_ref_array', '[]', $callstack[3]['function'] )
					)
				);
				$str_replace = $doc_root . preg_replace('|https?://[^/]+|i', '', get_option('home') . '/' );
				$file_name = addslashes( str_replace( $str_replace, '', $callstack[3]['file'] ) );
				$line_num  = $callstack[3]['line'];
				
				if ( ! isset( $callstack[4] ) )
					$called_by = __( 'Undefinded', parent :: get_plugin_data() );
				else
					$called_by = $callstack[4]['function'] . '()';
				
				$wpdb -> query( "INSERT " . self :: $table . "
					(first_call,called_by,hook_name,hook_type,arg_count,file_name,line_num)
					VALUES ( $first_call,'$called_by','$hook','$hook_type',$arg_count,'$file_name',$line_num )"
				);
				
				$first_call ++;
				$in_hook = FALSE;
			}
		}
		
	} // end class
}// end if class exists
