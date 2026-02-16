<?php
/**
 * Invoice Sync Service - coordinates syncing between Syncro and Square
 */

class InvoiceSyncService {
    private $syncroClient;
    private $squareClient;
    private $logger;
    private $processedFile;
    private $autoSendInvoice;
    
    public function __construct(SyncroClient $syncroClient, SquareClient $squareClient, Logger $logger, $config) {
        $this->syncroClient = $syncroClient;
        $this->squareClient = $squareClient;
        $this->logger = $logger;
        $this->processedFile = $config['processed_invoices_file'];
        $this->autoSendInvoice = $config['auto_send_invoice'];
        
        // Ensure data directory exists
        $dataDir = dirname($this->processedFile);
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
    }
    
    /**
     * Sync new invoices from Syncro to Square
     */
    public function syncNewInvoices() {
        $this->logger->info("=== Starting invoice sync ===");
        
        try {
            // Get new/unpaid invoices from Syncro
            $syncroInvoices = $this->syncroClient->getInvoices(['status' => 'Unpaid']);
            
            $processedInvoices = $this->getProcessedInvoices();
            $syncCount = 0;
            
            foreach ($syncroInvoices as $invoice) {
                $invoiceId = $invoice['id'];
                
                // Skip if already processed
                if (isset($processedInvoices[$invoiceId])) {
                    $this->logger->info("Invoice {$invoiceId} already processed, skipping");
                    continue;
                }
                
                try {
                    $this->syncInvoice($invoice);
                    $this->markInvoiceProcessed($invoiceId, $invoice['number'] ?? $invoiceId);
                    $syncCount++;
                } catch (Exception $e) {
                    $this->logger->error("Failed to sync invoice {$invoiceId}", [
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            $this->logger->info("=== Sync complete: {$syncCount} invoice(s) synced ===");
            
        } catch (Exception $e) {
            $this->logger->error("Sync failed", ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    /**
     * Sync a specific invoice from Syncro to Square
     */
    public function syncInvoice($invoice) {
        $this->logger->info("Processing Syncro invoice", [
            'invoice_id' => $invoice['id'] ?? null,
            'invoice_number' => $invoice['number'] ?? 'N/A'
        ]);
        
        // Get full invoice details if needed
        if (!isset($invoice['line_items'])) {
            $invoiceId = $invoice['id'] ?? null;
            if (!$invoiceId) {
                $this->logger->error("Invoice data received", ['invoice' => $invoice]);
                throw new Exception("No invoice ID found in invoice data");
            }
            $this->logger->info("Fetching full invoice details for ID: {$invoiceId}");
            $invoice = $this->syncroClient->getInvoice($invoiceId);
            $this->logger->info("Full invoice data", ['invoice' => $invoice]);
        }
        
        // Get customer details
        $customerId = $invoice['customer_id'] ?? $invoice['customer']['id'] ?? null;
        
        if (!$customerId) {
            $this->logger->error("No customer ID found", [
                'available_keys' => array_keys($invoice),
                'invoice_sample' => array_slice($invoice, 0, 5)
            ]);
            throw new Exception("No customer ID found in invoice. Available keys: " . implode(', ', array_keys($invoice)));
        }
        
        $customer = $this->syncroClient->getCustomer($customerId);
        
        $this->logger->info("Customer data from Syncro", ['customer' => $customer]);
        
        // Try different possible field names from Syncro
        $firstName = $customer['firstname'] ?? $customer['first_name'] ?? $customer['business_name'] ?? '';
        $lastName = $customer['lastname'] ?? $customer['last_name'] ?? '';
        $email = $customer['email'] ?? $customer['email_address'] ?? '';
        $phone = $customer['phone'] ?? $customer['phone_number'] ?? $customer['mobile_phone'] ?? '';
        $companyName = $customer['business_name'] ?? $customer['company_name'] ?? '';
        
        // If we have a company name but no first/last name, use company as first name
        if ($companyName && !$firstName) {
            $firstName = $companyName;
        }
        
        // Find or create customer in Square
        $squareCustomer = $this->squareClient->findOrCreateCustomer([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'company_name' => $companyName,
        ]);
        
        // Convert Syncro line items to Square format
        $squareLineItems = $this->convertLineItems($invoice['line_items'] ?? []);
        
        // Create order in Square
        $order = $this->squareClient->createOrder([
            'reference_id' => $invoice['number'] ?? $invoice['id'],
            'customer_id' => $squareCustomer['id'],
            'line_items' => $squareLineItems,
        ]);
        
        // Create invoice from order
        $dueDate = isset($invoice['due_date']) ? date('Y-m-d', strtotime($invoice['due_date'])) : null;
        
        $squareInvoice = $this->squareClient->createInvoice(
            $order['id'],
            $squareCustomer['id'],
            $dueDate,
            $this->autoSendInvoice
        );
        
        $this->logger->success("Successfully synced invoice", [
            'syncro_invoice' => $invoice['number'] ?? $invoice['id'],
            'square_invoice' => $squareInvoice['invoice_number']
        ]);
        
        return $squareInvoice;
    }
    
    /**
     * Convert Syncro line items to Square format
     */
    private function convertLineItems($syncroLineItems) {
        $squareLineItems = [];
        
        foreach ($syncroLineItems as $item) {
            // Calculate price in cents (Square uses smallest currency unit)
            $priceInCents = round(($item['price'] ?? 0) * 100);
            
            $squareLineItems[] = [
                'name' => $item['name'] ?? $item['description'] ?? 'Item',
                'quantity' => (string)($item['quantity'] ?? 1),
                'base_price_money' => [
                    'amount' => $priceInCents,
                    'currency' => 'USD'
                ],
            ];
        }
        
        return $squareLineItems;
    }
    
    /**
     * Get list of already processed invoices
     */
    private function getProcessedInvoices() {
        if (file_exists($this->processedFile)) {
            $content = file_get_contents($this->processedFile);
            return json_decode($content, true) ?? [];
        }
        return [];
    }
    
    /**
     * Mark an invoice as processed
     */
    private function markInvoiceProcessed($invoiceId, $invoiceNumber) {
        $processed = $this->getProcessedInvoices();
        $processed[$invoiceId] = [
            'invoice_number' => $invoiceNumber,
            'synced_at' => date('Y-m-d H:i:s')
        ];
        
        file_put_contents($this->processedFile, json_encode($processed, JSON_PRETTY_PRINT));
    }
}
