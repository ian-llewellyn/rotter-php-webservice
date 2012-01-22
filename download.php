<?php
/*
 * Created on Oct 20, 2010
 *
 * To change the template for this generated file go to
 * Window - Preferences - PHPeclipse - PHP - Code Templates
 */

// All dates and times are in UTC
date_default_timezone_set('UTC');

// Log levels
// 0 - nothing
// 1 - fatal errors
// 2 - one line record
// 3 - operations
// 4 - operations incl. repeated ones
// 5 - lots of info
$log_level = 4;

// Logging function
function log_message($msg_level, $msg, $bare_log = false) {
	global $log_level;

	// Should this go any further
	if ($log_level < $msg_level) return true;
        // open file
        $fd = fopen(dirname($_SERVER['SCRIPT_FILENAME']) . '/download.log', 'a');
	if (!$fd) return false;
        // append date/time to message
        $str = ($bare_log === false ? '[' . date('Y/m/d H:i:s', mktime()) . '] ' . $_SERVER['REMOTE_ADDR'] . ' [' . $msg_level . '] ' : '') . $msg;
        // write string
        if (fwrite($fd, $str . "\n") === false) return false;
        // close file
        fclose($fd);
	return true;
}

// Include Common configuration parameters
require_once('common-config.php');

// Mpgedit binary
$mpgedit = '/usr/local/bin/mpgedit_nocurses';
//$mpgedit = '/bin/echo';

// Arguments we want to get and any defaults we want to set
// Service
$service = isset($_GET['service']) ? $_GET['service'] : ( isset($_POST['service']) ? $_POST['service'] : 'radio1' );
log_message(4, 'Service: ' . $service);

// Start Date and Time (Date = YYYY-MM-DD, Time = HH-mm-ss-hh - UTC)
$request_start = isset($_GET['start']) ? $_GET['start'] : ( isset($_POST['start']) ? $_POST['start'] : gmdate("Y-m-d-H-i-s-00", time()-300) );
//$request_start = '2010-10-22-09-00-00-00';
preg_match('/^((\d{4})-(\d{2})-(\d{2}))[ -]((\d{2})[:-](\d{2})[:-](\d{2})[.-](\d{2}))$/', $request_start, $matches);
list(, $start_date, $start_yr, $start_mo, $start_dy, $start_time, $start_hr, $start_mi, $start_se, $start_hs) = $matches;
log_message(4, 'Start date: ' . $start_date . ', time: ' . $start_time);

// End Date and Time
$request_end = isset($_GET['end']) ? $_GET['end'] : ( isset($_POST['end']) ? $_POST['end'] : gmdate("Y-m-d-H-i-s-00") );
//$request_end = '2010-10-22-10-00-00-01';
preg_match('/^((\d{4})-(\d{2})-(\d{2}))[ -]((\d{2})[:-](\d{2})[:-](\d{2})[.-](\d{2}))$/', $request_end, $matches);
list(, $end_date, $end_yr, $end_mo, $end_dy, $end_time, $end_hr, $end_mi, $end_se, $end_hs) = $matches; 
log_message(4, 'End date: ' . $end_date . ', time: ' . $end_time);

// Before doing anything else, is the request for anything over 24 hours?
if ( get_time_diff($request_start, $request_end) > 86400 ) {
	log_message(1, 'Request for audio file larger than 24 hours - die()ing');
	die('You cannot request audio longer than 24 hours long - That would be ridiculous.');
}

/**
 * dir_listing_filter - returns true only if file is an audio file that we care about
 */
function dir_listing_filter($file) {
	global $recording_suffix;
	log_message(5, 'dir_listing_filter($file = \'' . $file . '\')');

	$pmatch = '/^\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2}'. $recording_suffix .'/';
	if ( preg_match($pmatch, $file) != 1 ) {
		return false;
	} else {
		return true;
	}
}

/* For an end date_time that corresponds to a filename,
 * should the editspec for mpgedit be: -e - ?
 * 
 * Also, I need to check if there is a way to determine the length of a file quickly so that
 * missing patches can be detected and replaced with silence.
 */
function get_filename_for_date_time($date_time, $is_end = false) {
	global $rotter_base_dir, $service, $recording_suffix;
	log_message(4, 'get_filename_for_date_time($date_time = \'' . $date_time . '\', $is_end = \'' . $is_end . '\')');

	// Get the date out of date_time
	preg_match('/^((\d{4})-(\d{2})-(\d{2}))[ -]((\d{2})[:-](\d{2})[:-](\d{2})[.-](\d{2}))$/', $date_time, $matches);
	list(, $date, $yr, $mo, $dy, $time, $hr, $mi, $se, $hs) = $matches;

	// List the directory for date_time
	$dir_listing = scandir($rotter_base_dir . $service . '/' . $date);

	// Get date_time - 1 day
	$day_before_date_time = strftime("%Y-%m-%d", gmmktime(0, 0, 0, $mo, $dy-1, $yr));

	// Get yesterday's directory listing if it exists
	$yday_dir_listing = scandir($rotter_base_dir . $service . '/' . $day_before_date_time);

	// Add yesterday's directory listing too if it exists
	if ( $yday_dir_listing !== false ) $dir_listing = array_merge($dir_listing, $yday_dir_listing);

	// Clear out any unwanted files ('.idx', '.', '..', etc.)
	$dir_listing = array_filter($dir_listing, "dir_listing_filter");

	// Insert our date_time as a fake file into the directory listing
	$fake_file = str_replace(array(' ', ':', '.'), '-', $date_time) . $recording_suffix;
	$dir_listing[] = $fake_file;

	// Reverse sort the array so that index + 1 will be the file we want
	rsort($dir_listing);

	// The most likely candidate
	$file = $dir_listing[array_search($fake_file, $dir_listing)+1];

	// If is_end is set and the timestamp of the file is the same as that of the fake file, we want the next earliest file
	if ( $file == $date_time.$recording_suffix && $is_end === true ) {
		$file = $dir_listing[array_search($fake_file, $dir_listing)+2];
	}

	return $file;
}

function get_intermediate_files($file_a, $file_y) {
	global $rotter_base_dir, $service, $recording_suffix;
	log_message(4, 'get_intermediate_files($file_a = \'' . $file_a . '\', $file_b = \'' . $file_y . '\')');

	preg_match('/^((\d{4})-(\d{2})-(\d{2}))[ -]((\d{2})[:-](\d{2})[:-](\d{2})[.-](\d{2}))' . $recording_suffix . '$/', $file_a, $matches);
	list(, $start_date, $start_yr, $start_mo, $start_dy, $start_time, $start_hr, $start_mi, $start_se, $start_hs) = $matches;
	
	preg_match('/^((\d{4})-(\d{2})-(\d{2}))[ -]((\d{2})[:-](\d{2})[:-](\d{2})[.-](\d{2}))' . $recording_suffix . '$/', $file_y, $matches);
	list(, $end_date, $end_yr, $end_mo, $end_dy, $end_time, $end_hr, $end_mi, $end_se, $end_hs) = $matches; 
	
	// Scan the relavant directory / directories
	$dir_listing = scandir("$rotter_base_dir/$service/$start_date");
	if ( $start_date != $end_date ) $dir_listing = array_merge($dir_listing, scandir("$rotter_base_dir/$service/$end_date"));
	
	// Clear out any unwanted files ('.idx', '.', '..', etc.)
	$dir_listing = array_filter($dir_listing, "dir_listing_filter");

	rsort($dir_listing);
	$index = count($dir_listing)-1;
	while ( $dir_listing[$index] != $file_a ) {
		array_pop($dir_listing);
		$index--;
	}
	array_pop($dir_listing);

	sort($dir_listing);
	$index = count($dir_listing)-1;
	while ( $dir_listing[$index] != $file_y ) {
		array_pop($dir_listing);
		$index--;
	}
	array_pop($dir_listing);
	
	return $dir_listing;
}

function get_time_diff($date_time1, $date_time2) {
	// Accepts date_times in the form:
	// YYYY-MM-DD-HH-mm-ss-hh or
	// YYYY-MM-DD HH:mm:ss.hh
	log_message(4, 'get_time_diff($date_time1 = \'' . $date_time1 . '\', $date_time2 = \'' . $date_time2 . '\')');

	preg_match('/^(\d{4})-(\d{2})-(\d{2})[ -](\d{2})[:-](\d{2})[:-](\d{2})[.-](\d{2})$/', $date_time1, $matches);
	list(, $yr, $mo, $dy, $hr, $mi, $se, $hs) = $matches;
	$days1 = gregoriantojd($mo, $dy, $yr);
	$time1 = floatval(intval($hr)*3600 + intval($mi)*60 + intval($se) . '.' . $hs);

	preg_match('/^(\d{4})-(\d{2})-(\d{2})[ -](\d{2})[:-](\d{2})[:-](\d{2})[.-](\d{2})$/', $date_time2, $matches);
	list(, $yr, $mo, $dy, $hr, $mi, $se, $hs) = $matches;
	$days2 = gregoriantojd($mo, $dy, $yr);
	$time2 = floatval(intval($hr)*3600 + intval($mi)*60 + intval($se) . '.' . $hs);

	$days_diff = $days2 - $days1;
	$time_diff = $time2 - $time1;

	return round($days_diff*3600*24 + $time_diff, 2);
}

// Here's the science!!
/*
 * a - the start date_time of the first useful file 
 * b - the requested start date_time
 * ( -e(b-a)- )
 * 
 * y - the start date_time of the last useful file
 * z - the requested end date_time
 * ( -e-(z-y) )
 */

// Get the file that contains the start
$file_a = get_filename_for_date_time($request_start);

// How far in is the start we want
$offset_start = get_time_diff(str_replace($recording_suffix, '', $file_a), $request_start);

// Get the file that contains the end
$file_y = get_filename_for_date_time($request_end, true);

// How far in is the end we want
$offset_end = get_time_diff(str_replace($recording_suffix, '', $file_y), $request_end);

// Get any "in between" files
$files = $file_a != $file_y ? get_intermediate_files($file_a, $file_y) : array();

// Prepare a temp file
$temp_file = tempnam('/tmp/', 'mpgedit_op_');
// Check for faillure
if ($temp_file === FALSE) {
	log_message(1, 'Failed to create temporary file');
	die('Failed to create temporary file!');
}
// mpgedit doesn't like overwriting files, so...
unlink($temp_file);
log_message(3, 'File: ' . $temp_file . ' allocated for audio data');

// This needs to run when the script is finnishing
function tidy_up_func($temp_file) {
	// This message never gets logged
	log_message(3, 'Deleting file: ' . $temp_file);
	// Remove the file
	if ( file_exists($temp_file) ) unlink($temp_file);
}
register_shutdown_function('tidy_up_func', $temp_file);

// Build the command line we'll use
$cmd = "$mpgedit -o $temp_file -e $offset_start-";
log_message(4, 'Command line: ' . $cmd);

if ( $file_a != $file_y ) {
	// Start and end are in different files
	$cmd .= " -f $rotter_base_dir$service/$start_date/$file_a";
	log_message(4, "\t:  -f $rotter_base_dir$service/$start_date/$file_a", true);
	foreach ( $files as $file ) {
		// For every intermediary file, we can just append the whole thing
		preg_match('/^\d{4}-\d{2}-\d{2}/', $file, $matches);
		$file_date = $matches[0];
		$cmd .= " -e - -f $rotter_base_dir$service/$file_date/$file";
		log_message(4, "\t:  -e - -f $rotter_base_dir$service/$file_date/$file", true);
	}
	// Because the end is in a different file to the beginning, we must restart it's editspec
	$cmd .= " -e -";
	log_message(4, "\t:  -e -", true);
}
$cmd .= "$offset_end -f $rotter_base_dir$service/$end_date/$file_y";
log_message(4, "\t: $offset_end -f $rotter_base_dir$service/$end_date/$file_y", true);

// Not sure if these are actually needed - seemed to be working ok without
ini_set('memory_limit', '-1');
set_time_limit(0);

// Run the mpgedit application (timespecs can be in [mm:]ss[.hh] format)
log_message(2, 'Executing: ' . $cmd);
exec($cmd, $mpgedit_op, $ret_val);

function readfile_chunked($filename, $retbytes=true) {
	$chunksize = 1*(1024*1024); // how many bytes per chunk
	$buffer = '';
	$cnt =0;
	// $handle = fopen($filename, 'rb');
	$handle = fopen($filename, 'rb');
	if ($handle === false) {
		return false;
	}
	while (!feof($handle)) {
		$buffer = fread($handle, $chunksize);
		echo $buffer;
		ob_flush();
		flush();
		if ($retbytes) {
			$cnt += strlen($buffer);
		}
	}
	$status = fclose($handle);
	if ($retbytes && $status) {
		return $cnt; // return num. bytes delivered like readfile() does.
	}
	return $status;

}

if ($ret_val == 0) {
	log_message(5, "$mpgedit output:\n" . $mpgedit_op);
	//header('Expires: ' . gmdate('D, d M Y H:i:s e'));
	header('Content-Type: audio/mpeg');
	header('Content-Disposition: attachment; filename=output' . $recording_suffix);
	header('Content-Length: ' . filesize($temp_file));
	log_message(3, 'About to readfile_chunked()');
	readfile_chunked($temp_file);
	exit;
} else {
	log_message(1, $mpgedit . ' error: return code: ' . $ret_val);
	log_message(4, $mpgedit_op);
	echo 'There was a problem creating the audio file.';
	exit;
}

?>
