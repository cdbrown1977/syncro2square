# Syncro to Square Invoice Sync

Automatically sync invoices from Syncro MSP to Square as draft invoices.

## Features

- ✅ Automatically syncs unpaid Syncro invoices to Square
- ✅ Creates draft invoices in Square (not automatically sent)
- ✅ Handles multiple line items per invoice
- ✅ Finds existing customers in Square by email
- ✅ Creates new customers if they don't exist
- ✅ Prevents duplicate syncing
- ✅ Detailed logging for debugging
- ✅ Can run continuously or as a one-time sync

## Requirements

- PHP 7.4 or higher
- cURL extension enabled
- Syncro MSP account with API access
- Square account with API credentials

### Tested Configurations

**WAMP (Windows):**
- ✅ Apache 2.4.65
- ✅ PHP 8.3.28
- ✅ MySQL 8.4.7
- ✅ MariaDB 11.4.9

**Other platforms:** Should work on any PHP environment with cURL support.

## Installation

1. **Clone or download this project**

2. **Configure your API credentials**
   
   Edit `config.php` and add your API keys:
   
   ```php
   'syncro' => [
       'api_key' => 'YOUR_SYNCRO_API_KEY',
       'subdomain' => 'yourcompany', // from yourcompany.syncromsp.com
       'base_url' => 'https://yourcompany.syncromsp.com/api/v1',
   ],
   
   'square' => [
       'access_token' => 'YOUR_SQUARE_ACCESS_TOKEN',
       'location_id' => 'YOUR_SQUARE_LOCATION_ID',
   ],
   ```

3. **Get your API credentials**

   **Syncro API Key:**
   - Log into Syncro
   - Go to Admin → API Tokens
   - Create a new API token
   - Copy the token to `config.php`

   **Square Access Token:**
   - Log into Square Developer Dashboard: https://developer.squareup.com/
   - Create a new application (or use existing)
   - Go to "Credentials" tab
   - Copy the Access Token (use Production token for live data)
   
   **Square Location ID:**
   - In Square Developer Dashboard, go to "Locations" tab
   - Copy your Location ID

## Usage

### Sync all new invoices (one time):
```bash
php sync.php
```

### Sync a specific invoice by ID:
```bash
php sync.php --invoice-id=12345
```

### Run continuously (checks every 5 minutes):
```bash
php sync.php --continuous
```

### Run on a schedule with cron:

Add to your crontab to run every 15 minutes:
```
*/15 * * * * /usr/bin/php /path/to/sync.php
```

## How It Works

1. **Fetches unpaid invoices** from Syncro MSP
2. **Checks if already synced** to avoid duplicates
3. **Finds existing customer** in Square by email address (exact match)
   - If email matches → uses existing Square customer
   - If no match → creates new customer in Square
4. **Converts line items** from Syncro format to Square format
5. **Creates order** in Square (in OPEN state)
6. **Publishes as invoice** (draft, not automatically sent)
7. **Logs everything** for debugging

**Customer Matching:** The system looks up customers in Square by email address. If the email from Syncro matches an existing Square customer, it will use that customer instead of creating a duplicate.

## Configuration Options

In `config.php`:

- `auto_send_invoice`: Set to `true` to automatically email invoices to customers (default: `false`)
- `sync_interval`: Seconds between syncs in continuous mode (default: 300 = 5 minutes)
- `log_file`: Path to log file
- `processed_invoices_file`: Tracks which invoices have been synced

## File Structure

```
├── config.php                    # Configuration file (YOU EDIT THIS)
├── sync.php                      # Main script to run
├── Logger.php                    # Logging utility
├── SyncroClient.php             # Syncro API wrapper
├── SquareClient.php             # Square API wrapper
├── InvoiceSyncService.php       # Main sync logic
├── logs/
│   └── sync.log                 # Log file (auto-created)
└── data/
    └── processed_invoices.json  # Tracks synced invoices (auto-created)
```

## Troubleshooting

### Check the logs
```bash
tail -f logs/sync.log
```

### Common issues:

**"Invalid API Key"**
- Double-check your API keys in `config.php`
- Make sure Syncro subdomain is correct

**"Customer not found"**
- The script will automatically create customers in Square
- Make sure customer has an email address in Syncro

**"Location ID invalid"**
- Verify your Square Location ID in the Square Developer Dashboard

**Line items not showing**
- Check that your Syncro invoices have line items
- Check logs to see what data is being sent

### Test mode

To test without sending real data, you can use Square's Sandbox mode:
1. Use Sandbox credentials from Square Developer Dashboard
2. Change `base_url` in config to `https://connect.squareupsandbox.com/v2`

## Customization

### Change which invoices to sync

In `InvoiceSyncService.php`, modify this line:
```php
$syncroInvoices = $this->syncroClient->getInvoices(['status' => 'Unpaid']);
```

Options: `'Unpaid'`, `'Paid'`, `'Partial'`, `'Draft'`

### Automatically send invoices

In `config.php`, set:
```php
'auto_send_invoice' => true,
```

## Support

If you run into issues:
1. Check `logs/sync.log` for detailed error messages
2. Verify your API credentials are correct
3. Test with a single invoice first: `php sync.php --invoice-id=123`

## License

Free to use and modify as needed.
