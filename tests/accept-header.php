<?php
/** Request preferences are tested without requiring an image encoder. */
$accept_method = new ReflectionMethod($plugin, 'prefersWebp');
$accept_cases = [
    '' => false,
    '*/*' => false,
    'image/*' => false,
    'image/avif,image/png' => false,
    'image/webp' => true,
    'image/png,image/webp' => true,
    'image/avif,image/webp,image/*;q=0.8,*/*;q=0.5' => true,
    'image/webp;q=0,image/png;q=1' => false,
    'image/webp;q=0,*/*;q=1' => false,
    'image/webp;q=0.000,image/*;q=1' => false,
    'image/webp;q=0.5,image/png;q=1' => false,
    'image/png;q=0.9,image/webp;q=1' => true,
    'image/webp;q=0.5,image/*;q=0.8' => false,
    'image/png;q=0,image/webp;q=0.5,image/*;q=1' => true,
    ' IMAGE/WEBP ; Q = 0.8 , IMAGE/PNG ; q=0.5 ' => true,
    'image/webp;q=0.001,image/png;q=0' => true,
    'image/webp;q=1.000,image/png;q=1' => true,
    'image/webp;q=banana,image/png' => false,
    'image/webp;q=1.1,image/png' => false,
    'image/webp;q=-1,image/png' => false,
    'image/webp;q=.8,image/png' => false,
    'image/webp;q=0.1234,image/png' => false,
    'image/webp;q=,image/png' => false,
    'image/webp;q="1",image/png' => false,
    'image/webp2,image/png' => false,
    'application/example;name=image/webp,image/png' => false,
    'image/webp;q=1,image/webp;q=0,image/png' => false,
    'image/webp;q=1;q=0,image/png' => false,
];
foreach ($accept_cases as $accept => $expected) {
    ok($accept_method->invoke(null, $accept) === $expected, 'static Accept preference: '.$accept);
}
unset($accept_method, $accept_cases, $accept, $expected);
