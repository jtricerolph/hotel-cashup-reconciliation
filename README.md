# Hotel Cash Up & Reconciliation Reporting Plugin

**Version:** 1.0.0
**Currency:** GBP (£)
**Requires:** WordPress 5.0+, PHP 7.4+

## Overview

A comprehensive WordPress plugin for daily cash reconciliation and multi-day financial reporting integrated with Newbook PMS. Designed for hotel operations to streamline cash counting, card machine reconciliation, and weekly reporting for the accounts team.

---

## Features

### Daily Cash Up
- **Cash Denomination Counting** (GBP notes and coins)
  - Enter EITHER quantity OR value per denomination
  - Real-time calculation of totals
  - Notes: £50, £20, £10, £5
  - Coins: £2, £1, £0.50, £0.20, £0.10, £0.05, £0.02, £0.01

- **Card Machine Reconciliation** (2 machines)
  - Front Desk Machine
  - Restaurant/Bar Machine
  - Total and Amex input with auto-calculated Visa/Mastercard
  - Combined PDQ totals displayed

- **Newbook Payment Integration**
  - Fetch payment data from Newbook PMS API
  - Automatic categorization (Cash, Manual Cards, Gateway)
  - Split card payments (Visa/MC vs Amex)
  - Distinguish manual vs gateway transactions

- **Reconciliation Summary**
  - Compare banked vs reported amounts
  - 5 categories: Cash, PDQ Visa/MC, PDQ Amex, Gateway Visa/MC, Gateway Amex
  - Color-coded variances (green/red/gray)

- **Draft/Final Workflow**
  - Save as draft (editable)
  - Submit as final (locked, cannot be edited)
  - Load and update existing drafts

### Multi-Day Reporting
- **Flexible Date Ranges**
  - Default: 7 days (weekly)
  - Configurable: 1-365 days
  - Start from any date (rolling periods)

- **Three Consolidated Tables**
  1. **Daily Reconciliation Summary**
     - Days on Y-axis (ascending order)
     - All cash up and Newbook data
     - Gross sales and debtors/creditors balance
     - Row totals

  2. **Sales Breakdown** (Net Values)
     - Days on X-axis
     - Categories on Y-axis (Accommodation, Food, Beverage, Other)
     - Column and row totals

  3. **Occupancy Statistics**
     - Days on X-axis
     - Metrics on Y-axis (Rooms Sold, Total People)
     - Averages calculated

- **Excel-Friendly Output**
  - Copy/paste directly into Excel
  - Print-optimized layout
  - Export functionality (planned)

### History & Reporting
- View all past cash ups
- Filter by status (Draft/Final) and date range
- Edit drafts or view final submissions
- Delete draft entries
- CSV export (planned)

### Settings & Configuration
- Newbook API credentials (username, password, API key, region)
- Hotel ID
- Currency (GBP fixed)
- Auto-sync settings
- Test connection button

---

## Installation

### Activate the Plugin

1. Navigate to **WordPress Admin → Plugins**
2. Find "Hotel Cash Up & Reconciliation Reporting"
3. Click **Activate**

The plugin will automatically:
- Create 7 database tables
- Set default options
- Initialize GBP denominations
- Create admin menu

### Database Tables Created

- `wp_hcr_cash_ups` - Daily cash up sessions
- `wp_hcr_denominations` - Currency denomination counts
- `wp_hcr_card_machines` - Card machine data (Front Desk, Restaurant/Bar)
- `wp_hcr_payment_records` - Newbook payment cache
- `wp_hcr_reconciliation` - Daily reconciliation results
- `wp_hcr_daily_stats` - Gross sales, occupancy, debtors/creditors
- `wp_hcr_sales_breakdown` - Net sales by category

---

## Configuration

### 1. Configure Newbook API

1. Go to **Cash Up → Settings**
2. Enter your Newbook API credentials:
   - API Username
   - API Password
   - API Key
   - Region (UK, AU, NZ, EU, US)
   - Hotel ID
3. Click **Test Connection** to verify
4. Click **Save Settings**

### 2. Adjust Settings (Optional)

- **Default Report Days:** Set default for multi-day reports (1-365)
- **Auto Sync:** Enable/disable automatic payment syncing
- **Sync Frequency:** Hourly or Daily

---

## Usage

### Daily Cash Up Workflow

#### For Staff (Daily Operations)

1. **Navigate to Cash Up Page**
   - Admin: **Cash Up → Daily Cash Up**
   - Or use shortcode: `[hcr_cash_up_form]` on any page

2. **Select Date**
   - Choose the business date
   - Click "Load Existing" if a draft exists

3. **Count Cash**
   - For each denomination, enter EITHER:
     - **Quantity** (number of notes/coins), OR
     - **Value** (total amount for that denomination)
   - Total automatically calculates
   - Grand total displays at bottom

4. **Enter Card Machine Totals**
   - For each machine (Front Desk, Restaurant/Bar):
     - Enter **Total** amount from EOD report
     - Enter **Amex** amount from EOD report
     - Visa/Mastercard auto-calculates (Total - Amex)
   - Combined PDQ totals shown

5. **Fetch Newbook Data** (Optional but Recommended)
   - Click "Fetch Newbook Payments"
   - System retrieves payment data for the date
   - Reconciliation table appears showing variances

6. **Add Notes** (Optional)
   - Explain any significant variances
   - Note any issues or adjustments

7. **Save or Submit**
   - **Save as Draft:** Can edit later
   - **Submit Final:** Locks the record (cannot edit)

#### Reconciliation Table

After fetching Newbook data, you'll see:

| Category | Banked | Reported | Variance |
|----------|--------|----------|----------|
| Cash | £500.00 | £495.00 | +£5.00 |
| PDQ Visa/Mastercard | £1,200.00 | £1,200.00 | £0.00 |
| PDQ Amex | £300.00 | £305.00 | -£5.00 |
| Gateway Visa/Mastercard | £800.00 | £800.00 | £0.00 |
| Gateway Amex | £200.00 | £200.00 | £0.00 |

**Variance Colors:**
- **Green:** Positive variance (over)
- **Red:** Negative variance (short)
- **Gray:** Zero variance (match)

### Multi-Day Reporting (Weekly Reconciliation)

#### For Accounts Team

1. **Navigate to Reports**
   - Go to **Cash Up → Multi-Day Report**

2. **Set Parameters**
   - **Start Date:** Select beginning of period (e.g., Monday)
   - **Number of Days:** Enter days to include (e.g., 7 for weekly)

3. **Generate Report**
   - Click "Generate Report"
   - System fetches all data for the period
   - Three tables populate automatically

4. **Review Tables**
   - **Table 1:** Daily reconciliation summary with variances
   - **Table 2:** Sales breakdown by category
   - **Table 3:** Occupancy statistics

5. **Copy to Excel**
   - Select table content
   - Copy (Ctrl+C / Cmd+C)
   - Paste into Excel (Ctrl+V / Cmd+V)
   - Formatting preserved for easy analysis

6. **Print** (Optional)
   - Click "Print Report"
   - Optimized print layout with page breaks

### History & Management

1. **View History**
   - Go to **Cash Up → History**
   - See all cash ups with status, date, totals

2. **Filter Results**
   - Filter by Status (Draft/Final)
   - Filter by Date Range (From/To)

3. **Manage Entries**
   - **Edit:** Click "Edit" on draft entries
   - **View:** Click "View" on final entries
   - **Delete:** Click "Delete" on drafts (confirmation required)

---

## Shortcodes

### `[hcr_cash_up_form]`

Renders the cash up form on any page for front-end use.

**Usage:**
```
[hcr_cash_up_form]
```

**Optional Attributes:**
```
[hcr_cash_up_form date="2025-11-04"]
```

**Access Control:**
- Requires logged-in user
- Minimum capability: `edit_posts`

---

## Technical Details

### Plugin Architecture

```
hotel-cash-up-reconciliation/
├── hotel-cash-up-reconciliation.php  # Main plugin file
├── includes/
│   ├── class-hcr-core.php            # Core orchestration
│   ├── class-hcr-activator.php       # Database setup
│   ├── class-hcr-deactivator.php     # Cleanup
│   ├── class-hcr-ajax.php            # AJAX handlers
│   └── class-hcr-newbook-api.php     # Newbook API integration
├── admin/
│   ├── class-hcr-admin.php           # Admin functionality
│   └── views/                        # Admin page templates
├── public/
│   ├── class-hcr-public.php          # Front-end functionality
│   └── views/                        # Public templates
└── assets/
    ├── css/                          # Stylesheets
    └── js/                           # JavaScript
```

### AJAX Actions

**Cash Up Actions:**
- `hcr_save_cash_up` - Save/update cash up (draft or final)
- `hcr_load_cash_up` - Load existing cash up by date
- `hcr_delete_cash_up` - Delete draft cash up

**Newbook Data:**
- `hcr_fetch_newbook_payments` - Fetch payments for date
- `hcr_sync_daily_stats` - Sync sales/occupancy/balances

**Reports:**
- `hcr_generate_multi_day_report` - Generate consolidated report
- `hcr_export_to_excel` - Export to Excel (planned)

**Settings:**
- `hcr_test_connection` - Test Newbook API connection

### Security

- WordPress nonces for all forms/AJAX
- Capability checks (`edit_posts`, `manage_options`)
- Input sanitization (`sanitize_text_field`, `sanitize_textarea_field`)
- Output escaping (`esc_html`, `esc_attr`, `esc_url`)
- Prepared statements for database queries
- CSRF protection

### Data Precision

- All monetary values: `decimal(10,2)`
- Currency calculations: Always use decimal, never float
- JavaScript: Multiply by 100 for pence calculations
- Display: Always format to 2 decimal places

---

## Newbook API Integration

### Payment Endpoints

**Currently Implemented:**
- `payments_list` - Fetch payment records
- `bookings_list` - Fetch booking data for occupancy

**Payment Categorization:**
- **Transaction Method:** `manual` vs `automated`/`gateway`
- **Card Type:** `cash`, `visa_mc`, `amex`
- **Filtering:** Splits payments into 5 reconciliation categories

### Data Sync Workflow

1. User clicks "Fetch Newbook Payments"
2. Plugin calls Newbook API for selected date
3. Payments retrieved and categorized
4. Stored in `wp_hcr_payment_records` table
5. Totals calculated by category
6. Reconciliation table generated

### Placeholder Methods (To Be Implemented)

The following Newbook API methods are placeholders and need real endpoints:
- `fetch_gross_sales()` - Total gross sales for day
- `fetch_sales_breakdown()` - Net sales by category
- `fetch_debtors_creditors_balance()` - Combined balance

**Action Required:**
Research Newbook API documentation to identify correct endpoints for these data points. Update methods in [class-hcr-newbook-api.php](includes/class-hcr-newbook-api.php).

---

## Troubleshooting

### Plugin Not Showing in Admin

1. Check file permissions: `ls -la wp-content/plugins/hotel-cash-up-reconciliation/`
2. Verify main file exists: `hotel-cash-up-reconciliation.php`
3. Check debug log: `tail -f wp-content/debug.log`

### Database Tables Not Created

1. Deactivate and reactivate plugin (runs activation script)
2. Check database:
   ```bash
   mysql -h DB_HOST -P DB_PORT -u DB_USER -p'DB_PASSWORD' -e "SHOW TABLES LIKE 'wp_hcr_%'" DB_NAME
   ```
3. Review debug log for errors

### Newbook API Connection Fails

1. Go to **Cash Up → Settings**
2. Click "Test Connection"
3. Verify credentials are correct
4. Check API region matches your account
5. Ensure server can reach `https://api.newbook.cloud/`

### Cash Up Not Saving

1. Open browser console (F12)
2. Check for JavaScript errors
3. Check Network tab for AJAX request
4. Review `wp-content/debug.log` for PHP errors
5. Verify user has `edit_posts` capability

### Reconciliation Table Not Showing

1. Ensure Newbook payments were fetched successfully
2. Check browser console for JavaScript errors
3. Verify API returned payment data
4. Check debug log for API errors

---

## Future Enhancements

### Planned Features

1. **Excel Export**
   - Direct .xlsx file generation
   - Formatted multi-day reports
   - Implemented via PHPSpreadsheet

2. **Float Management**
   - Opening float recording
   - Closing float calculation
   - Float variance tracking

3. **Email Notifications**
   - Submit notifications to managers
   - Variance alerts over threshold
   - Daily summary emails

4. **Advanced Variance Analysis**
   - Suggest transactions related to variances
   - Link to specific Newbook bookings
   - Drill-down reporting

5. **Mobile App Integration**
   - REST API endpoints
   - JWT authentication
   - Mobile-optimized interface

6. **Dashboard Widgets**
   - Today's cash status
   - Pending approvals
   - Week-to-date variance graph

---

## Support & Development

### Debug Logging

All plugin operations log to WordPress debug log with `HCR:` prefix:
```bash
tail -f wp-content/debug.log | grep "HCR:"
```

### Developer Notes

- Follow WordPress coding standards
- Use `$wpdb` global for database queries
- Prefix custom tables with `$wpdb->prefix`
- All AJAX uses `wp_ajax_` hooks
- Enqueue assets with `wp_enqueue_script/style()`

### File Modifications

When editing plugin files:
1. Test changes in development first
2. Clear browser cache (Ctrl+F5)
3. Check debug log for errors
4. Increment version number in main plugin file

---

## Credits

**Developed for:** Hotel Operations
**Integration:** Newbook PMS
**Currency:** GBP (£)
**WordPress Standards:** Fully compliant

---

## Changelog

### Version 1.0.0 (2025-11-02)
- Initial release
- Daily cash up with GBP denominations
- 2 card machines (Front Desk, Restaurant/Bar)
- Newbook payment integration
- Multi-day reporting (1-365 days)
- Draft/final workflow
- History and filtering
- Settings page with API configuration
- Responsive design
- Print-optimized reports
