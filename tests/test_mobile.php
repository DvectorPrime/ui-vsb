<?php
/**
 * tests/test_mobile.php
 * Automated verification of mobile responsiveness rules, breakpoints, and accessibility.
 */

$css = file_get_contents(__DIR__ . '/../assets/css/style.css');
$js  = file_get_contents(__DIR__ . '/../assets/js/app.js');
$hdr = file_get_contents(__DIR__ . '/../includes/header.php');

$checks = [
    'Viewport meta tag in header.php' => strpos($hdr, 'name="viewport"') !== false,
    'Responsive breakpoint 820px (tablet/nav)' => strpos($css, 'max-width: 820px') !== false,
    'Responsive breakpoint 640px (mobile)' => strpos($css, 'max-width: 640px') !== false,
    'Responsive breakpoint 480px (small phones)' => strpos($css, 'max-width: 480px') !== false,
    'iOS auto-zoom prevention (font-size 16px)' => strpos($css, 'font-size: 16px !important') !== false,
    'Mobile momentum touch scroll (-webkit-overflow-scrolling)' => strpos($css, '-webkit-overflow-scrolling: touch') !== false,
    'Mobile drawer toggle listener in app.js' => strpos($js, 'menuBtn.addEventListener') !== false,
    'ESC key dismiss modal listener in app.js' => strpos($js, 'e.key === \'Escape\'') !== false,
    'Close on outside tap listener in app.js' => strpos($js, 'nav.contains(e.target)') !== false,
    'Tap target sizing (min-height >= 40px)' => strpos($css, 'min-height: 44px') !== false,
    'Horizontal swipe helper for tables' => strpos($css, 'Swipe horizontally to view full table') !== false,
    'Mobile 2-column slot grid for narrow screens' => strpos($css, 'repeat(2, 1fr)') !== false
];

echo "=== MOBILE RESPONSIVENESS AND ACCESSIBILITY VERIFICATION ===" . PHP_EOL;
$passed = 0;
foreach ($checks as $name => $ok) {
    if ($ok) {
        echo "PASS: {$name}" . PHP_EOL;
        $passed++;
    } else {
        echo "FAIL: {$name}" . PHP_EOL;
    }
}

echo "Result: {$passed} / " . count($checks) . " checks passed." . PHP_EOL;
exit($passed === count($checks) ? 0 : 1);
