<?php
	header('Content-type: text/html; charset=utf8');
?>
<!doctype HTML>
<html>
	<head>
		<title>Room-map. Дипломный проект.</title>
		<script type="text/javascript" src="/Room-map/Room-map.js"></script>
		<script type="text/javascript">
			RoomMap.position_X = <?= (float)$_GET['x']; ?>;
			RoomMap.position_Y = <?= (float)$_GET['y']; ?>;
			RoomMap.scale = 100;
			RoomMap.preInitMap();
		</script>
 	</head>
	<body>
		<div id="Room-map"></div>
	</body>
</html>