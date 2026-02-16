<?php
/**
 * Test Script - Verify your API credentials are working
 * 
 * Usage: php test_credentials.php
 */

require_once 'Logger.php';
require_once 'SyncroClient.php';
require_once 'SquareClient.php';

echo "\n=== Testing API Credentials ===\n\n";

// Load configuration
if (!file_exists('config.php')) {
    die("❌ Error: config.php not found. Copy config.example.php to config.php and add your credentials.\n");
}

$config = require 'config.php';
$logger = new Logger(__DIR__ . '/logs/test.log');

// Test Syncro connection
echo "Testing Syncro MSP connection...\n";
try {
    $syncroClient = new SyncroClient($config['syncro'], $logger);
    $invoices = $syncroClient->getInvoices(['page' => 1]);
    
    echo "✅ Syncro connection successful!\n";
    echo "   Found " . count($invoices) . " invoices\n";
    
    if (count($invoices) > 0) {
        echo "   Sample invoice: #" . ($invoices[0]['number'] ?? $invoices[0]['id']) . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Syncro connection failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Test Square connection
echo "Testing Square connection...\n";
try {
    $squareClient = new SquareClient($config['square'], $logger);
    
    // Try to search for a customer (this will work even if no customers exist)
    $testEmail = 'test@example.com';
    $result = $squareClient->findOrCreateCustomer([
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => $testEmail,
        'phone' => '',
    ]);
    
    echo "✅ Square connection successful!\n";
    echo "   Location ID: " . $config['square']['location_id'] . "\n";
    
} catch (Exception $e) {
    echo "❌ Square connection failed: " . $e->getMessage() . "\n";
    
    // Provide helpful hints
    if (strpos($e->getMessage(), '401') !== false || strpos($e->getMessage(), 'Unauthorized') !== false) {
        echo "\n   💡 Hint: Check your Square access token\n";
    }
    if (strpos($e->getMessage(), 'location') !== false) {
        echo "\n   💡 Hint: Check your Square location ID\n";
    }
}

echo "\n=== Test Complete ===\n\n";
echo "If both tests passed, you're ready to run: php sync.php\n\n";
