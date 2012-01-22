<?php

// Is a callback function referenced?
$callback = isset($_GET['callback']) ? $_GET['callback'] : ( isset($_POST['callback']) ? $_POST['callback'] : false );

// Is there a callback?
if ( $callback !== false ) {
	echo $callback, '(';
}

?>{
	"services":[
		{
			"title":"RTÉ Radio 1 FM",
			"id":"radio1"
		},
		{
			"title":"RTÉ 2fm",
			"id":"2fm"
		},
		{
			"title":"RTÉ lyric fm",
			"id":"lyricfm"
		},
		{
			"title":"RTÉ Raidió na Gaeltachta",
			"id":"rnag"
		},
		{
			"title":"RTÉ Gold",
			"id":"gold"
		},
		{
			"title":"RTÉ 2XM",
			"id":"2xm"
		},
		{
			"title":"RTÉ Choice",
			"id":"choice"
		},
		{
			"title":"RTÉ Junior/Chill",
			"id":"junior-chill"
		},
		{
			"title":"RTÉ Pulse",
			"id":"pulse"
		},
		{
			"title":"RTÉ Radio 1 extra",
			"id":"radio1extra"
		},
		{
			"title":"RTÉ Radio 1 LW",
			"id":"radio1lw"
		},
		{
			"title":"Newstalk",
			"id":"newstalk"
		},
		{
			"title":"Today fm",
			"id":"todayfm"
		},
		{
			"title":"4fm",
			"id":"4fm"
		},
		{
			"title":"Test",
			"id":"radio1test"
		}
	]
}<?php

// Is there a callback?
if ( $callback !== false ) {
	echo ');';
}

?>
