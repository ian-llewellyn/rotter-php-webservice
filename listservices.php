<?php

// Include common configuration parameters
require_once('common-config.php');

$dir_listing = scandir($rotter_base_dir);

$i = 0;
while ( $i < count($dir_listing) ) {
	if ( preg_match('/^\./', $dir_listing[$i]) != 1 ) {
		$i++;
	} else {
		array_splice($dir_listing, $i, 1);
	}
}

// Begin Output
echo '{
	services:[
';

// Per file output
for ($i = 0; $i < count($dir_listing); $i++) {
	echo '		"' . $dir_listing[$i] . '"';
	if ($i != count($dir_listing)-1) {
		echo ',';
	}
	echo "\n";
}

// End output
echo '	]
}';

?>
