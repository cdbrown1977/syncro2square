<?php
/**
 * Example Configuration - Copy to config.php and fill in your values
 */

return [
    // Syncro API Configuration
    'syncro' => [
        // Get your API key from Syncro: Admin → API Tokens
        'api_key' => 'your_syncro_api_key_here',
        
        // Your Syncro subdomain (e.g., 'mycompany' from mycompany.syncromsp.com)
        'subdomain' => 'your_subdomain',
        
        // Base URL - Update with your subdomain
        'base_url' => 'https://your_subdomain.syncromsp.com/api/v1',
    ],
    
    // Square API Configuration
    'square' => [
        // Get from Square Developer Dashboard: https://developer.squareup.com/
        // Create an app, then go to Credentials tab
        'access_token' => 'your_square_access_token_here',
        
        // Get from Square Developer Dashboard: Locations tab
        'location_id' => 'your_square_location_id_here',
        
        // API Base URL (use sandbox for testing)
        'base_url' => 'https://connect.squareup.com/v2',  // Production
        // 'base_url' => 'https://connect.squareupsandbox.com/v2',  // Sandbox for testing
        
        // API Version
        'api_version' => '2023-08-16',
    ],
    
    // App Settings
    'settings' => [
        // Where to store logs
        'log_file' => __DIR__ . '/logs/sync.log',
        
        // Where to track processed invoices (to avoid duplicates)
        'processed_invoices_file' => __DIR__ . '/data/processed_invoices.json',
        
        // Should invoices be automatically emailed to customers?
        // false = create as draft only
        // true = automatically send email to customer
        'auto_send_invoice' => false,
        
        // How often to check for new invoices (in seconds) when running in continuous mode
        // 300 = 5 minutes
        'sync_interval' => 300,
    ],
];
