<?php
$max = 10; // number of random values
$test = 1000000;

$array = array_fill(0, $max, 0);

for ($i = 0; $i < $test; ++$i) {
  ++$array[random_int(0, $max-1)];
}

function arrayFormatResult(&$item) {
  global $test, $max; // try to avoid this nowdays ;)

  $perc = ($item/($test/$max))-1;
  $item .= ' '. number_format($perc, 4, '.', '') .'%';
}

array_walk($array, 'arrayFormatResult');

print_r($array);
?>