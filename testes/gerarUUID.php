<?php
require_once("func/uuid.php");


for ($i = 0; $i <= 10; $i++) {
  print uuidv4()."\n";
}

print "---------------\n";

for ($i = 0; $i <= 10; $i++) {
  print uuidv4fmt()."\n";
}

?>