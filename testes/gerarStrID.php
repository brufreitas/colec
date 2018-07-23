<?php
function generate_string($length) {
  $nps = "";
  for ($i=0;$i<$length;$i++) {
      $nps .= chr( (mt_rand(1, 36) <= 26) ? mt_rand(97, 122) : mt_rand(48, 57 ));
  }
  return $nps;
}

for ($i = 0; $i < $argv[1]; $i++) {
  print generate_string($argv[2])."\n";
}

?>