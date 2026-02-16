# Quick Start Guide

Get your Syncro to Square invoice sync running in 5 minutes!

## Tested Environment

This application has been successfully tested with:
- **WAMP Server 3.4.0** (Windows)
- **Apache 2.4.65**
- **PHP 8.3.28**
- **MySQL 8.4.7 / MariaDB 11.4.9**

Should work on any PHP environment with cURL support.

## Step 1: Download Files

You should have these files:
- `config.php` (or rename `config.example.php`)
- `sync.php`
- `Logger.php`
- `SyncroClient.php`
- `SquareClient.php`
- `InvoiceSyncService.php`
- `test_credentials.php`
- `index.html` (optional - web interface)
- `sync_api.php` (optional - for web interface)

## Step 2: Get API Credentials

### Syncro API Key
1. Log into your Syncro MSP account
2. Go to **Admin → API Tokens**
3. Click **"New API Token"**
4. Give it a name (e.g., "Square Sync")
5. Copy the API key

### Square Access Token & Location ID
1. Go to https://developer.squareup.com/
2. Log in with your Square account
3. Click **"Open Developer Dashboard"**
4. Create a new application (or select existing)
5. Go to **"Credentials"** tab
6. Copy your **Access Token** (use Production for live data)
7. Go to **"Locations"** tab
8. Copy your **Location ID**

## Step 3: Configure

Edit `config.php`:

```php
'syncro' => [
    'api_key' => 'paste_your_syncro_key_here',
    'subdomain' => 'yourcompany', // from yourcompany.syncromsp.com
    'base_url' => 'https://yourcompany.syncromsp.com/api/v1',
],

'square' => [
    'access_token' => 'paste_your_square_token_here',
    'location_id' => 'paste_your_location_id_here',
],
```

## Step 4: Test Your Setup

Run the test script:

```bash
php test_credentials.php
```

You should see:
```
✅ Syncro connection successful!
✅ Square connection successful!
```

If you see errors, double-check your API credentials.

## Step 5: Run Your First Sync

**For WAMP users:**

Open Command Prompt and navigate to your project folder:
```cmd
cd C:\wamp64\www\syncro-square-sync
C:\wamp64\bin\php\php8.3.28x\php.exe sync.php --invoice-id=YOUR_INVOICE_NUMBER
```

Replace `YOUR_INVOICE_NUMBER` with an actual invoice number from Syncro (like `1007`).

**For other systems:**

Sync all new invoices:
```bash
php sync.php
```

Or sync a specific invoice:
```bash
php sync.php --invoice-id=12345
```

## Step 6: Check Results

1. Check the console output
2. Look in `logs/sync.log` for details
3. Log into Square and check your invoices

## Optional: Set Up Automatic Syncing

### Option A: Run Continuously
```bash
php sync.php --continuous
```
This will check for new invoices every 5 minutes.

### Option B: Use Cron (Linux/Mac)
Add to crontab to run every 15 minutes:
```bash
crontab -e
```
Add this line:
```
*/15 * * * * /usr/bin/php /path/to/sync.php >> /path/to/logs/cron.log 2>&1
```

### Option C: Use Task Scheduler (Windows)
1. Open Task Scheduler
2. Create Basic Task
3. Set trigger (e.g., every 15 minutes)
4. Action: Start a program
5. Program: `C:\path\to\php.exe`
6. Arguments: `C:\path\to\sync.php`

## Optional: Web Interface

If you want to trigger syncs from a web browser:

1. Make sure `index.html` and `sync_api.php` are in the same folder
2. Put all files on a web server with PHP
3. Open `index.html` in your browser
4. Click the sync buttons

## Troubleshooting

**Can't find PHP?**
```bash
which php  # Linux/Mac
where php  # Windows
```

**Permission errors?**
```bash
chmod +x sync.php
chmod 755 logs/
chmod 755 data/
```

**Still having issues?**
Check `logs/sync.log` for detailed error messages.

## What's Next?

- Adjust sync frequency in `config.php`
- Enable auto-send emails by setting `auto_send_invoice` to `true`
- Customize which invoices to sync (edit `InvoiceSyncService.php`)

## Need Help?

Check the full README.md for more details and troubleshooting tips.
