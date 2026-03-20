<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<pre>";
echo "whoami:\n";
var_dump(shell_exec('whoami 2>&1'));

echo "\nls /:\n";
var_dump(shell_exec('ls / 2>&1'));

echo "\nwhich timeout:\n";
var_dump(shell_exec('which timeout 2>&1'));

echo "\nwhich wscat:\n";
var_dump(shell_exec('which wscat 2>&1'));
echo "</pre>";

