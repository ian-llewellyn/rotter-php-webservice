<?php
/**
 * Common Configuration for rotter web services
 */

// Was a format parameter specified
$format = isset($_GET['format']) ? $_GET['format'] : ( isset($_POST['format']) ? $_POST['format'] : 'mp3' );

// The base directory for rotter recordings
$rotter_base_dir = '/var/audiofile/audio/' . $format . '/';

// The suffix on the recording filenames
$recording_suffix = '.' . $format;

?>
