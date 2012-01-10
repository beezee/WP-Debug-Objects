<?php
/**
 * Add small screen with informations about Memory and load Time of WP
 *
 * @package     Debug Objects
 * @subpackage  Memory and Load Time
 * @author      Frank B&uuml;ltge
 * @since       2.0.1
 */

if ( class_exists( 'Debug_Objects_Memory' ) )
	return NULL;

class Debug_Objects_Memory extends Debug_Objects {
	
	static public $start_time;
	
	public static function init() {
		
		if ( ! current_user_can( '_debug_objects' ) )
			return;
		
		self :: $start_time = self :: get_micro_time();
		
		add_filter( 'debug_objects_tabs', array( __CLASS__, 'get_conditional_tab' ) );
	}
	
	public static function get_conditional_tab( $tabs ) {
		
		$tabs[] = array( 
			'tab' => __( 'Time, Mem & Files', parent :: get_plugin_data() ),
			'function' => array( __CLASS__, 'get_output' )
		);
		
		return $tabs;
	}
	
	public function get_output( $echo = TRUE ) {
		
		$class = '';
		$data = array();
		$data = array_merge( $data, self :: get_speed_data() );
		$data = array_merge( $data, self :: get_memory_data() );
		
		$output = '<h4>' . __( 'Memory & Load Time' ) .' </h4>';
		$mem_speed = '';
		foreach ( $data as $key => $item ) {
			$class = ( ' class="alternate"' == $class ) ? '' : ' class="alternate"';
			
			$mem_speed .= '<li' . $class . '>' . ucwords( str_replace( '_', ' ', $key ) ) . ': ' . $item . '</li>';
		}
		$output .= '<ul>' . $mem_speed . '</ul>';
		
		$output .= '<h4>' . __( 'Included Files' ) .' </h4>';
		$file_data = self :: get_file_data();
		$file_totals = '';
		foreach ( $file_data[ 'file_totals' ] as $key => $value ) {
			$class = ( ' class="alternate"' == $class ) ? '' : ' class="alternate"';
			
			$file_totals .= '<li' . $class . '>' . ucwords( str_replace( '_', ' ', $key ) ) . ': ' . $value . '</li>';
		}
		$output .= '<ul>' . $file_totals . '</ul>';
		
		$output .= '<h4>' . __( 'Files' ) .' </h4>';
		$files = '';
		foreach ( $file_data[ 'files' ] as $key => $value ) {
			$class = ( ' class="alternate"' == $class ) ? '' : ' class="alternate"';
			
			$files .= '<tr' . $class . '><td>' . ucwords( str_replace( '_', ' ', $key ) ) . '</td><td>' . $value['name'] . '</td><td>(' . $value['size'] . ')</td></tr>';
		}
		$output .= '<table>' . $files . '</table>';
		
		if ( $echo )
			echo $output;
		else
			return $output;
	}
	
	public function get_speed_data() {
		
		$speed_totals = array();
		$speed_totals['load_time'] = self :: get_readable_time( (self :: get_micro_time() - self :: $start_time) * 1000 );
		$speed_totals['max_execution_time'] = ini_get("max_execution_time");
		
		return $speed_totals;
	}
	
	function get_micro_time() {
		
		$time = microtime();
		$time = explode( ' ', $time );
		
		return $time[1] + $time[0];
	}
	
	public function get_memory_data() {
		$memory_totals = array();
		$memory_totals['memory_used'] = self :: get_readable_file_size( memory_get_peak_usage() );
		$memory_totals['memery_total'] = ini_get( 'memory_limit' );
		
		return $memory_totals;
	}
	
	/**
	 * Return File Size
	 * adapted from code at http://aidanlister.com/repos/v/function.size_readable.php
	 * 
	 */
	public function get_readable_file_size( $size, $retstring = NULL ) {
		
		$sizes = array( 'bytes', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB' );
		
		if ( null === $retstring )
			$retstring = '%01.2f%s';
		
		$lastsizestring = end( $sizes );
		
		foreach ( $sizes as $sizestring ) {
			if ( $size < 1024 )
				break;
			if ( $sizestring != $lastsizestring )
			$size /= 1024;
		}
		
		if ( $sizestring == $sizes[0] ) 
			$retstring = '%01d%s'; // Bytes aren't normally fractional
		
		return sprintf( $retstring, $size, $sizestring );
	}
	
	public function get_readable_time( $time ) {
		
		$ret = $time;
		$formatter = 0;
		$formats = array( 'ms', 's', 'm' );
		if ( $time >= 1000 && $time < 60000 ) {
			$formatter = 1;
			$ret = ( $time / 1000 );
		}
		if ( $time >= 60000 ) {
			$formatter = 2;
			$ret = ($time / 1000) / 60;
		}
		$ret = number_format( $ret, 3, '.', '' ) . ' ' . $formats[$formatter];
		
		return $ret;
	}
	
	public function get_file_data() {
		
		$files = get_included_files();
		$file_list = array();
		$file_totals = array(
			'total_files' => count($files),
			'total_size'  => 0,
			'largest'     => 0,
		);

		foreach ( $files as $key => $file ) {
			$size = filesize($file);
			$file_list[] = array(
				'name' => $file,
				'size' => self :: get_readable_file_size( $size )
			);
			$file_totals['total_size'] += $size;
			if ( $size > $file_totals['largest'] )
				$file_totals['largest'] = $size;
		}
		
		$file_totals['total_size'] = self :: get_readable_file_size( $file_totals['total_size'] );
		$file_totals['largest'] = self :: get_readable_file_size( $file_totals['largest'] );
		
		$file_data = array();
		$file_data['file_totals'] = $file_totals;
		$file_data['files'] = $file_list;
		
		return $file_data;
	}
	
} // end class
