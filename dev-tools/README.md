# Development Tools

This directory contains debug and testing scripts for development purposes. These tools are not required for production use.

## Available Tools

### check-tables.php
**Purpose**: Database health check

**What it does**:
- Checks if all HCR database tables exist
- Shows row counts for each table
- Displays API configuration status

**Usage**:
```bash
cd wp-content/plugins/hotel-cash-up-reconciliation/dev-tools
php check-tables.php
```

**When to use**:
- After plugin installation to verify tables were created
- Troubleshooting database issues
- Verifying API credentials are configured

---

### debug-api-response.php
**Purpose**: Raw API response inspection

**What it does**:
- Calls Newbook API directly
- Shows raw response structure
- Displays available fields
- Shows first transaction detail

**Usage**:
```bash
cd wp-content/plugins/hotel-cash-up-reconciliation/dev-tools
php debug-api-response.php [YYYY-MM-DD]

# Example:
php debug-api-response.php 2025-11-01
```

**When to use**:
- Troubleshooting API integration issues
- Understanding API response structure
- Verifying API credentials work
- Investigating missing or incorrect data

---

### test-newbook-fetch.php
**Purpose**: End-to-end payment data fetch test

**What it does**:
- Tests API connection
- Fetches payment data for a specific date
- Displays payment details
- Calculates and shows totals

**Usage**:
```bash
cd wp-content/plugins/hotel-cash-up-reconciliation/dev-tools
php test-newbook-fetch.php [YYYY-MM-DD]

# Example:
php test-newbook-fetch.php 2025-11-01
```

**When to use**:
- Validating API integration
- Testing payment categorization logic
- Verifying totals calculation
- Troubleshooting payment data issues

---

### update-database.php
**Purpose**: Manual database schema updates

**What it does**:
- Updates HCR database tables with schema changes
- Adds new columns or tables from plugin updates
- Provides alternative to deactivate/reactivate cycle
- Requires admin authentication

**Usage**:
```bash
# Access via browser (requires admin login):
# https://your-domain.com/wp-content/plugins/hotel-cash-up-reconciliation/dev-tools/update-database.php
```

**When to use**:
- After plugin updates that include schema changes
- When you need to update database without deactivating plugin
- Troubleshooting database structure issues
- When deactivate/reactivate cycle is not convenient

**Alternative Method**:
- Deactivate and reactivate the plugin from WordPress admin
- This automatically runs the activation script with schema updates

---

## Requirements

- PHP CLI access
- WordPress installation
- Valid Newbook API credentials configured in plugin settings

## Notes

- All scripts require access to WordPress via `wp-load.php`
- Most scripts use configured API credentials from plugin settings
- Output is sent to console/terminal
- Debug/test scripts are read-only (check-tables.php, debug-api-response.php, test-newbook-fetch.php)
- **update-database.php modifies database schema** - use with caution

## Production Deployment

These tools are **not required** for production and can be:
- Excluded from production deployments
- Left in place (they only run via command line)
- Deleted if desired (won't affect plugin functionality)

## See Also

- Main plugin documentation: [../README.md](../README.md)
- Database update script: [update-database.php](update-database.php) (in this directory)
- Development guide: [../CLAUDE.md](../CLAUDE.md)
