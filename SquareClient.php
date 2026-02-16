<?php
/**
 * Square API Client
 */

class SquareClient {
    private $accessToken;
    private $locationId;
    private $baseUrl;
    private $apiVersion;
    private $logger;
    
    public function __construct($config, Logger $logger) {
        $this->accessToken = $config['access_token'];
        $this->locationId = $config['location_id'];
        $this->baseUrl = $config['base_url'];
        $this->apiVersion = $config['api_version'];
        $this->logger = $logger;
    }
    
    /**
     * Create or find a customer in Square
     */
    public function findOrCreateCustomer($customerData) {
        // First, try to search for existing customer by email
        if (!empty($customerData['email'])) {
            $existingCustomer = $this->searchCustomerByEmail($customerData['email']);
            if ($existingCustomer) {
                $this->logger->info("Found existing Square customer", ['customer_id' => $existingCustomer['id']]);
                return $existingCustomer;
            }
        }
        
        // Customer doesn't exist, create new one
        $url = $this->baseUrl . '/customers';
        
        $data = [
            'idempotency_key' => $this->generateIdempotencyKey(),
        ];
        
        // Build customer data, only including non-empty fields
        if (!empty($customerData['given_name'] ?? $customerData['first_name'])) {
            $data['given_name'] = $customerData['given_name'] ?? $customerData['first_name'];
        }
        
        if (!empty($customerData['family_name'] ?? $customerData['last_name'])) {
            $data['family_name'] = $customerData['family_name'] ?? $customerData['last_name'];
        }
        
        if (!empty($customerData['company_name'])) {
            $data['company_name'] = $customerData['company_name'];
        }
        
        if (!empty($customerData['email_address'] ?? $customerData['email'])) {
            $data['email_address'] = $customerData['email_address'] ?? $customerData['email'];
        }
        
        if (!empty($customerData['phone_number'] ?? $customerData['phone'])) {
            $data['phone_number'] = $customerData['phone_number'] ?? $customerData['phone'];
        }
        
        // Square requires at least one of these fields
        if (empty($data['given_name']) && empty($data['family_name']) && 
            empty($data['company_name']) && empty($data['email_address']) && 
            empty($data['phone_number'])) {
            throw new Exception("Customer must have at least one of: name, company, email, or phone");
        }
        
        $this->logger->info("Creating new Square customer", ['data' => $data]);
        
        $response = $this->makeRequest($url, 'POST', $data);
        
        if (isset($response['customer'])) {
            $this->logger->success("Created Square customer", ['customer_id' => $response['customer']['id']]);
            return $response['customer'];
        }
        
        throw new Exception("Failed to create customer in Square");
    }
    
    /**
     * Search for customer by email
     */
    private function searchCustomerByEmail($email) {
        $url = $this->baseUrl . '/customers/search';
        
        $data = [
            'query' => [
                'filter' => [
                    'email_address' => [
                        'exact' => $email
                    ]
                ]
            ]
        ];
        
        $response = $this->makeRequest($url, 'POST', $data);
        
        if (isset($response['customers']) && count($response['customers']) > 0) {
            return $response['customers'][0];
        }
        
        return null;
    }
    
    /**
     * Create a draft order in Square
     */
    public function createOrder($orderData) {
        $url = $this->baseUrl . '/orders';
        
        $data = [
            'idempotency_key' => $this->generateIdempotencyKey(),
            'order' => [
                'location_id' => $this->locationId,
                'reference_id' => $orderData['reference_id'] ?? null,
                'customer_id' => $orderData['customer_id'],
                'line_items' => $orderData['line_items'],
                'state' => 'OPEN', // Changed from DRAFT to OPEN so it can be invoiced
            ]
        ];
        
        $this->logger->info("Creating Square order", ['reference_id' => $orderData['reference_id']]);
        
        $response = $this->makeRequest($url, 'POST', $data);
        
        if (isset($response['order'])) {
            $this->logger->success("Created Square order", ['order_id' => $response['order']['id']]);
            return $response['order'];
        }
        
        throw new Exception("Failed to create order in Square");
    }
    
    /**
     * Publish order as invoice
     */
    public function createInvoice($orderId, $customerId, $dueDate = null, $autoSend = false) {
        $url = $this->baseUrl . '/invoices';
        
        $data = [
            'idempotency_key' => $this->generateIdempotencyKey(),
            'invoice' => [
                'location_id' => $this->locationId,
                'order_id' => $orderId,
                'primary_recipient' => [
                    'customer_id' => $customerId
                ],
                'payment_requests' => [
                    [
                        'request_type' => 'BALANCE',
                        'due_date' => $dueDate ?? date('Y-m-d', strtotime('+30 days')),
                        'automatic_payment_source' => 'NONE'
                    ]
                ],
                'delivery_method' => $autoSend ? 'EMAIL' : 'SHARE_MANUALLY',
                'accepted_payment_methods' => [
                    'card' => true,
                    'square_gift_card' => false,
                    'bank_account' => false,
                    'buy_now_pay_later' => false,
                    'cash_app_pay' => false
                ]
            ]
        ];
        
        $this->logger->info("Creating Square invoice", ['order_id' => $orderId]);
        
        $response = $this->makeRequest($url, 'POST', $data);
        
        if (isset($response['invoice'])) {
            $this->logger->success("Created Square invoice", [
                'invoice_id' => $response['invoice']['id'],
                'invoice_number' => $response['invoice']['invoice_number']
            ]);
            return $response['invoice'];
        }
        
        throw new Exception("Failed to create invoice in Square");
    }
    
    /**
     * Make HTTP request to Square API
     */
    private function makeRequest($url, $method = 'GET', $data = null) {
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        
        // SSL certificate handling
        // For production, remove these lines and ensure CA certificates are properly installed
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $headers = [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json',
            'Square-Version: ' . $this->apiVersion,
        ];
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            $this->logger->error("Square API request failed", ['error' => $error]);
            throw new Exception("Square API Error: {$error}");
        }
        
        curl_close($ch);
        
        $decoded = json_decode($response, true);
        
        if ($httpCode >= 400) {
            $this->logger->error("Square API returned error", [
                'http_code' => $httpCode,
                'response' => $decoded
            ]);
            throw new Exception("Square API Error: HTTP {$httpCode} - " . json_encode($decoded));
        }
        
        return $decoded;
    }
    
    /**
     * Generate unique idempotency key
     */
    private function generateIdempotencyKey() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
