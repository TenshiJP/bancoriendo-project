<?php
echo "<pre>";
echo "whoami: "; var_dump(trim(shell_exec("whoami 2>&1")));
echo "id: "; var_dump(trim(shell_exec("id 2>&1")));
echo "sudo: "; var_dump(trim(shell_exec("/usr/bin/sudo -V 2>&1 | head -n 1")));
echo "</pre>";

