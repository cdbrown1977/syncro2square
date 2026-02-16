<?php
/**
 * Syncro MSP API Client
 */

class SyncroClient {
    private $apiKey;
    private $baseUrl;
    private $logger;
    
    public function __construct($config, Logger $logger) {
        $this->apiKey = $config['api_key'];
        $this->baseUrl = $config['base_url'];
        $this->logger = $logger;
    }
    
    /**
     * Get invoices from Syncro
     * @param array $params Query parameters (e.g., ['status' => 'Unpaid'])
     * @return array
     */
    public function getInvoices($params = []) {
        $url = $this->baseUrl . '/invoices';
        
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        $this->logger->info("Fetching invoices from Syncro", ['url' => $url]);
        
        $response = $this->makeRequest($url, 'GET');
        
        if (isset($response['invoices'])) {
            $this->logger->info("Retrieved " . count($response['invoices']) . " invoices from Syncro");
            return $response['invoices'];
        }
        
        return [];
    }
    
    /**
     * Get a specific invoice by ID
     */
    public function getInvoice($invoiceId) {
        $url = $this->baseUrl . '/invoices/' . $invoiceId;
        
        $this->logger->info("Fetching invoice {$invoiceId} from Syncro");
        
        $response = $this->makeRequest($url, 'GET');
        
        // Syncro returns nested structure: {"invoice": {"invoice": {...}}}
        // Unwrap to get the actual invoice data
        if (isset($response['invoice']['invoice'])) {
            return $response['invoice']['invoice'];
        } elseif (isset($response['invoice'])) {
            return $response['invoice'];
        }
        
        return $response;
    }
    
    /**
     * Get customer details
     */
    public function getCustomer($customerId) {
        $url = $this->baseUrl . '/customers/' . $customerId;
        
        $this->logger->info("Fetching customer {$customerId} from Syncro");
        
        $response = $this->makeRequest($url, 'GET');
        
        // Syncro returns nested structure: {"customer": {"customer": {...}}}
        // Unwrap to get the actual customer data
        if (isset($response['customer']['customer'])) {
            return $response['customer']['customer'];
        } elseif (isset($response['customer'])) {
            return $response['customer'];
        }
        
        return $response;
    }
    
    /**
     * Make HTTP request to Syncro API
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
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
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
            $this->logger->error("Syncro API request failed", ['error' => $error]);
            throw new Exception("Syncro API Error: {$error}");
        }
        
        curl_close($ch);
        
        $decoded = json_decode($response, true);
        
        if ($httpCode >= 400) {
            $this->logger->error("Syncro API returned error", [
                'http_code' => $httpCode,
                'response' => $decoded
            ]);
            throw new Exception("Syncro API Error: HTTP {$httpCode}");
        }
        
        return $decoded;
    }
}
