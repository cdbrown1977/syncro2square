<?php
header('Content-Type: application/json');

require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/SyncroClient.php';
require_once __DIR__ . '/SquareClient.php';
require_once __DIR__ . '/InvoiceSyncService.php';

$config = require __DIR__ . '/config.php';
$logger = new Logger($config['settings']['log_file']);

// Capture all logger echo output so it doesn't corrupt JSON responses
ob_start();

try {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $input['action'] ?? ($_GET['action'] ?? '');

    $syncroClient = new SyncroClient($config['syncro'], $logger);
    $squareClient = new SquareClient($config['square'], $logger);
    $syncService = new InvoiceSyncService($syncroClient, $squareClient, $logger, $config['settings']);

    switch ($action) {
        case 'sync_all':
            $syncService->syncNewInvoices();
            $output = ob_get_clean();
            echo json_encode([
                'success' => true,
                'message' => 'Sync completed successfully',
                'details' => array_filter(explode("\n", trim($output))),
                'log'     => $output,
            ]);
            break;

        case 'sync_specific':
            $invoiceId = $input['invoice_id'] ?? null;
            if (!$invoiceId) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Invoice ID is required']);
                break;
            }
            $invoice = $syncroClient->getInvoice($invoiceId);
            $result = $syncService->syncInvoice($invoice);
            $output = ob_get_clean();
            echo json_encode([
                'success' => true,
                'message' => "Invoice {$invoiceId} synced successfully",
                'details' => array_filter(explode("\n", trim($output))),
                'log'     => $output,
                'square_invoice' => $result['invoice_number'] ?? null,
            ]);
            break;

        case 'status':
            ob_end_clean();
            $processedFile = $config['settings']['processed_invoices_file'];
            $processed = [];
            if (file_exists($processedFile)) {
                $processed = json_decode(file_get_contents($processedFile), true) ?? [];
            }
            $lastSynced = null;
            foreach ($processed as $inv) {
                $ts = $inv['synced_at'] ?? null;
                if ($ts && (!$lastSynced || $ts > $lastSynced)) {
                    $lastSynced = $ts;
                }
            }
            echo json_encode([
                'success' => true,
                'data' => [
                    'total_synced'   => count($processed),
                    'last_sync'      => $lastSynced,
                ],
            ]);
            break;

        case 'test_syncro':
            try {
                $invoices = $syncroClient->getInvoices(['page' => 1]);
                $output = ob_get_clean();
                echo json_encode([
                    'success' => true,
                    'message' => 'Syncro API connection successful',
                    'data'    => ['invoice_count' => count($invoices)],
                    'log'     => $output,
                ]);
            } catch (Exception $e) {
                $output = ob_get_clean();
                echo json_encode([
                    'success' => false,
                    'message' => 'Syncro API connection failed: ' . $e->getMessage(),
                    'log'     => $output,
                ]);
            }
            break;

        case 'test_square':
            try {
                // Test by listing locations (lightweight call)
                $baseUrl = $config['square']['base_url'];
                $ch = curl_init($baseUrl . '/locations');
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_HTTPHEADER => [
                        'Authorization: Bearer ' . $config['square']['access_token'],
                        'Content-Type: application/json',
                        'Square-Version: ' . $config['square']['api_version'],
                    ],
                ]);
                $resp = curl_exec($ch);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                $output = ob_get_clean();
                if ($code >= 200 && $code < 300) {
                    $data = json_decode($resp, true);
                    $locationCount = count($data['locations'] ?? []);
                    echo json_encode([
                        'success' => true,
                        'message' => 'Square API connection successful',
                        'data'    => ['location_count' => $locationCount],
                        'log'     => $output,
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => "Square API error: HTTP {$code}",
                        'log'     => $output,
                    ]);
                }
            } catch (Exception $e) {
                $output = ob_get_clean();
                echo json_encode([
                    'success' => false,
                    'message' => 'Square API connection failed: ' . $e->getMessage(),
                    'log'     => $output,
                ]);
            }
            break;

        case 'get_logs':
            ob_end_clean();
            $logFile = $config['settings']['log_file'];
            if (file_exists($logFile)) {
                $allLines = file($logFile, FILE_IGNORE_NEW_LINES);
                $recent = array_slice($allLines, -100);
                echo json_encode([
                    'success' => true,
                    'data'    => implode("\n", $recent),
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'data'    => 'No logs yet.',
                ]);
            }
            break;

        default:
            ob_end_clean();
            echo json_encode([
                'success' => false,
                'message' => 'Unknown action: ' . $action,
            ]);
            break;
    }
} catch (Exception $e) {
    if (ob_get_level() > 0) ob_end_clean();
    $logger->error("API error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
    ]);
}
