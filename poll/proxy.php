<?php
echo file_get_contents("https://frage.space/api/?" . $_SERVER['QUERY_STRING']);
file_put_contents("debug.txt", $_SERVER['QUERY_STRING']."\n", FILE_APPEND);