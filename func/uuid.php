<?php
function uuidv4() {
  // https://stackoverflow.com/a/15875555/2933356
  $data = openssl_random_pseudo_bytes(16);
  // assert(strlen($data) == 16);

  $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
  $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10

  return bin2hex($data);
}

function uuidv4fmt() {
  return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(uuidv4(), 4));
}

?>