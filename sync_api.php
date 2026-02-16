<?php
/**
 * API endpoint for web interface
 * Handles sync requests from the web dashboard
 */

header('Content-Type: application/json');

require_once 'Logger.php';
require_once 'SyncroClient.php';
require_once 'SquareClient.php';
require_once 'InvoiceSyncService.php';

// Load configuration
$config = require 'config.php';

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? null;

// Initialize components
$logger = new Logger($config['settings']['log_file']);
$syncroClient = new SyncroClient($config['syncro'], $logger);
$squareClient = new SquareClient($config['square'], $logger);
$syncService = new InvoiceSyncService($syncroClient, $squareClient, $logger, $config['settings']);

$response = [
    'success' => false,
    'message' => '',
    'details' => []
];

try {
    switch ($action) {
        case 'sync_all':
            // Start output buffering to capture logs
            ob_start();
            
            $syncService->syncNewInvoices();
            
            $logs = ob_get_clean();
            
            $response['success'] = true;
            $response['message'] = 'Sync completed successfully';
            $response['details'] = explode("\n", trim($logs));
            break;
            
        case 'sync_specific':
            $invoiceId = $input['invoice_id'] ?? null;
            
            if (!$invoiceId) {
                throw new Exception('Invoice ID is required');
            }
            
            ob_start();
            
            $invoice = $syncroClient->getInvoice($invoiceId);
            $result = $syncService->syncInvoice($invoice);
            
            $logs = ob_get_clean();
            
            $response['success'] = true;
            $response['message'] = "Invoice {$invoiceId} synced successfully";
            $response['details'] = explode("\n", trim($logs));
            $response['square_invoice'] = $result['invoice_number'] ?? null;
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    $logger->error("API error: " . $e->getMessage());
}

echo json_encode($response);
