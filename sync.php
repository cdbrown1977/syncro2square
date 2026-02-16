<?php
/**
 * Main sync script - Run this to sync invoices from Syncro to Square
 * 
 * Usage:
 *   php sync.php                    - Sync all new invoices
 *   php sync.php --invoice-id=123   - Sync specific invoice by ID
 *   php sync.php --continuous       - Run continuously (check every 5 minutes)
 */

require_once 'Logger.php';
require_once 'SyncroClient.php';
require_once 'SquareClient.php';
require_once 'InvoiceSyncService.php';

// Load configuration
$config = require 'config.php';

// Initialize components
$logger = new Logger($config['settings']['log_file']);
$syncroClient = new SyncroClient($config['syncro'], $logger);
$squareClient = new SquareClient($config['square'], $logger);
$syncService = new InvoiceSyncService($syncroClient, $squareClient, $logger, $config['settings']);

// Parse command line arguments
$options = getopt('', ['invoice-id:', 'continuous']);

try {
    if (isset($options['invoice-id'])) {
        // Sync specific invoice
        $invoiceId = $options['invoice-id'];
        $logger->info("Syncing specific invoice: {$invoiceId}");
        
        $invoice = $syncroClient->getInvoice($invoiceId);
        $syncService->syncInvoice($invoice);
        
    } elseif (isset($options['continuous'])) {
        // Run continuously
        $logger->info("Starting continuous sync mode");
        $interval = $config['settings']['sync_interval'];
        
        while (true) {
            $syncService->syncNewInvoices();
            $logger->info("Waiting {$interval} seconds until next sync...");
            sleep($interval);
        }
        
    } else {
        // Single sync run
        $syncService->syncNewInvoices();
    }
    
    $logger->success("Sync completed successfully");
    
} catch (Exception $e) {
    $logger->error("Sync failed with error: " . $e->getMessage());
    exit(1);
}
