<?php
/**
 * Master Test Runner for UI-VSB (Vehicle Service Booking & Payment Platform)
 * Runs all unit, integration, concurrency, and security test suites.
 *
 * Usage: php tests/run_all.php
 */

echo "=====================================================================\n";
echo " UI-VSB : MASTER TEST SUITE (Phases B1 - B6)\n";
echo " University of Ibadan Maintenance Directorate\n";
echo "=====================================================================\n\n";

$phpBinary = PHP_BINARY ?: 'php';
$tests = [
    'Phase B2 & B3 (Vehicles, Services, Booking Engine & Slots)' => __DIR__ . '/test_b2_b3.php',
    'Phase B4 (Simulated Payment, Gateways & Cards)'             => __DIR__ . '/test_b4.php',
    'Phase B5 (Admin Workflows, Stepper & Dashboards)'           => __DIR__ . '/test_b5.php',
    'Phase B4 HTTP End-to-End Payment Integration'              => __DIR__ . '/test_http_payment.php',
    'Frontend Pages Render & Security Guards (All 17 Pages)'     => __DIR__ . '/test_pages.php',
    'Mobile Responsiveness & Breakpoint Standards'               => __DIR__ . '/test_mobile.php',
];

$totalSuites = count($tests);
$passedSuites = 0;
$failedSuites = 0;

foreach ($tests as $title => $script) {
    echo ">>> RUNNING: {$title}\n";
    echo "---------------------------------------------------------------------\n";

    $output = [];
    $exitCode = 0;
    exec("\"{$phpBinary}\" \"{$script}\" 2>&1", $output, $exitCode);

    echo implode("\n", $output) . "\n\n";

    if ($exitCode === 0) {
        $passedSuites++;
    } else {
        $failedSuites++;
    }
}

echo "=====================================================================\n";
echo " MASTER TEST RUN SUMMARY\n";
echo "=====================================================================\n";
echo " Total Test Suites Run : {$totalSuites}\n";
echo " Suites Passed         : {$passedSuites}\n";
echo " Suites Failed         : {$failedSuites}\n";
echo " Overall Status        : " . ($failedSuites === 0 ? "ALL SUITES PASSED (100% OK)" : "SOME SUITES FAILED") . "\n";
echo "=====================================================================\n";

exit($failedSuites === 0 ? 0 : 1);
