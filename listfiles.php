<?php

// Include the common configuration parameters
require_once('common-config.php');

// Parse the service from the GET or POST variables
$service = isset($_GET['service']) ? $_GET['service'] : ( isset($_POST['service']) ? $_POST['service'] : 'radio1' );

// Parse the date from the GET or POST variables
$date = isset($_GET['date']) ? $_GET['date'] : ( isset($_POST['date']) ? $_POST['date'] : date('Y-m-d') );

// Is a callback function referenced?
$callback = isset($_GET['callback']) ? $_GET['callback'] : ( isset($_POST['callback']) ? $_POST['callback'] : false );

// Get the directory listing and safely exit if there's a problem
if ( ($dir_listing = scandir($rotter_base_dir . $service . '/' . $date)) === FALSE) die('{
	"files":[
	]
}');

// Loop through and only keep the relevant audio files
$i = 0;
while ( $i < count($dir_listing) ) {
	if ( preg_match('/^\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2}' . $recording_suffix . '$/', $dir_listing[$i]) != 1 ) {
		array_splice($dir_listing, $i, 1);
	} else {
		$i++;
	}
}

// Is there a callback?
if ( $callback !== false ) {
	echo $callback, '(';
}

// Begin Output
echo '{
	"files":[
';

// Per file output
for ($i = 0; $i < count($dir_listing); $i++) {
	preg_match('/^\d{4}-\d{2}-\d{2}-(\d{2}-\d{2}-\d{2})-\d{2}' . $recording_suffix . '$/', $dir_listing[$i], $matches);
	$title = str_replace('-', ':', $matches[1]);
	echo '		{
			"title":"' . $title . '",
			"file":"' . $dir_listing[$i] . '",
			"size":' . filesize($rotter_base_dir . $service . '/' . $date . '/' . $dir_listing[$i]) . '
		}';
	if ($i != count($dir_listing)-1) {
		echo ',';
	}
	echo "\n";
}

// End output
echo '	]
}';

// Is there a callback?
if ( $callback !== false ) {
        echo ');';
}


?>
