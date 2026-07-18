<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/pseudo_cron.php';
require_once __DIR__ . '/MoodleScraper.php';
require_once __DIR__ . '/Mailer.php';

// Run cron check
runPseudoCron();

// Return simple response for uptime monitors
header('Content-Type: text/plain');
echo 'OK - ' . date('Y-m-d H:i:s');
