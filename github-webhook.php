<?php

$repo_dir = '/home/azarakhsh2/sadeghizade';


$command = "cd $repo_dir && git pull 2>&1";


$output = shell_exec($command);


file_put_contents('webhook_log.txt', date('Y-m-d H:i:s') . " - " . $output . PHP_EOL, FILE_APPEND);

echo "Webhook processed.";
?>