<!DOCTYPE html>
<html>
    <head>
        <title>Refreshing Wikipedias statistics data</title>
    <head>
    <body>
        <h1>Refreshing Wikipedias statistics data</h1>
        <p>Wait please...</p>
	<?php
		$command="python3 refresh-wikipedias-data.py";
		$output=shell_exec($command);
		echo "<pre>".$output."</pre>";
	?>
    </body>
</html>


