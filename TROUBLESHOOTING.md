# Troubleshooting Guide

## Common Issues and Solutions

### SSL Certificate Errors

**Error:** "SSL certificate problem: unable to get local issuer certificate"

**Solution:** The code already includes SSL verification bypass for development. This is normal for WAMP and other local environments.

**For Production:** Follow the instructions in `WAMP_INSTALLATION.md` to properly configure SSL certificates.

---

### Customer Not Found / Duplicate Customers

**Issue:** New customers being created instead of using existing ones

**Explanation:** The system looks up customers by email address only. If the email in Syncro doesn't exactly match the email in Square, it will create a new customer.

**Solution:** 
- Ensure emails match exactly between Syncro and Square
- Check capitalization and spaces
- Look at logs to see what email was searched: `logs/sync.log`

---

### No Invoice ID Found

**Error:** "No invoice ID found in invoice data"

**Solution:** This means the invoice data from Syncro is not in the expected format. Check:
1. Is the invoice number valid in Syncro?
2. Check `logs/sync.log` to see the raw invoice data
3. The invoice might be in a different status (not created yet)

---

### Invoice Already Processed

**Message:** "Invoice XXXX already processed, skipping"

**Explanation:** The system tracks synced invoices to prevent duplicates in `data/processed_invoices.json`

**Solution:** 
- To re-sync an invoice, remove its entry from `data/processed_invoices.json`
- Or delete the entire file to start fresh (all invoices will sync again)

---

### Order Must Be in OPEN State

**Error:** "The order must be in the OPEN state to create an invoice"

**Solution:** This has been fixed in the current version. Make sure you're using the latest `SquareClient.php` file where orders are created with `state: 'OPEN'` instead of `state: 'DRAFT'`.

---

### Customer Must Have Name/Email/Phone

**Error:** "Customer must have at least one of: name, company, email, or phone"

**Explanation:** Square requires at least one identifying field for a customer.

**Solution:**
- Check that your Syncro customer has at least an email, phone, or name
- Look at the log to see what customer data was received
- The system should handle this automatically, but some Syncro customers might be incomplete

---

### Line Items Not Showing

**Issue:** Invoice created but line items are missing or wrong

**Solution:**
1. Check that line items exist in the Syncro invoice
2. Verify prices are being converted correctly (Square uses cents, not dollars)
3. Check `logs/sync.log` to see what line items were sent to Square

---

### API Rate Limits

**Error:** HTTP 429 or rate limit errors

**Solution:**
- Add delays between syncs if processing many invoices
- Use the continuous mode with longer intervals
- Contact Square support if limits are too restrictive

---

### Authentication Failed

**Error:** HTTP 401 Unauthorized

**Solution:**
- Verify your API keys in `config.php`
- Make sure you're using the Production access token (not Sandbox) for live data
- Check that your Syncro subdomain is correct
- Regenerate API keys if needed

---

## Debugging Tips

### Enable Detailed Logging

The system already logs everything to `logs/sync.log`. To view in real-time:

**Windows (WAMP):**
```cmd
type logs\sync.log
```

**Linux/Mac:**
```bash
tail -f logs/sync.log
```

### Test Single Invoice

Always test with a single invoice first:
```bash
php sync.php --invoice-id=1007
```

### Check API Credentials

Run the test script:
```bash
php test_credentials.php
```

### View Processed Invoices

Check what's been synced:
```bash
type data\processed_invoices.json
```

### Clear Sync History

To start fresh:
```bash
del data\processed_invoices.json
```

---

## Getting Help

1. Check `logs/sync.log` for detailed error messages
2. Run `php test_credentials.php` to verify setup
3. Test with a single invoice first
4. Review this troubleshooting guide
5. Check that your Syncro and Square accounts are properly configured

## Log File Locations

- **Sync logs:** `logs/sync.log`
- **Test logs:** `logs/test.log`
- **Processed invoices:** `data/processed_invoices.json`

All directories are created automatically on first run.
