# Candidate Auto-Creation — Setup Guide

This plugin automatically creates candidate profiles in WordPress when a new row is marked as "completed" in a Google Sheet.

---

## Part 1: Install the WordPress Plugin

1. Log in to your WordPress admin panel (e.g., `https://workajos.com/v21/wp-admin/`)
2. Go to **Plugins → Add New → Upload Plugin**
3. Upload the `candidate-csv-importer.zip` file
4. Click **Install Now**, then **Activate**
5. Verify: you should see "Import Candidates" in the left sidebar menu

> **Note:** The API secret is set to `workojas_secret_2026` in the plugin. To change it, edit `candidate-csv-importer.php` line 16.

---

## Part 2: Set Up the Google Apps Script

### Step 1: Open the Script Editor
1. Open your Google Sheet (the one with candidate data)
2. Click **Extensions → Apps Script**

### Step 2: Paste the Code
1. Delete any existing code in the editor (Ctrl+A → Delete)
2. Open the file `google-apps-script.js` (included in the zip)
3. Copy the entire content and paste it into the Apps Script editor

### Step 3: Update the URL
Find this line near the top of the script:
```
const WORDPRESS_API_URL = 'https://holly-ungambolling-herb.ngrok-free.dev/workojas/wp-json/workojas/v1/create-candidate';
```
Replace it with your actual site URL:
```
const WORDPRESS_API_URL = 'https://workajos.com/v21/wp-json/workojas/v1/create-candidate';
```

### Step 4: Save
Press **Ctrl+S** to save the script.

### Step 5: Set Up the Trigger
1. In the left sidebar, click the **clock icon** (Triggers)
2. Click **+ Add Trigger** (bottom right)
3. Set these values:
   - Choose which function: **onEditTrigger**
   - Choose which deployment: **Head**
   - Select event source: **From spreadsheet**
   - Select event type: **On edit**
4. Click **Save**
5. Google will ask you to authorize — click through:
   - Choose your Google account
   - Click **Advanced → Go to Untitled project (unsafe)**
   - Click **Allow**

---

## Part 3: Google Sheet Column Structure

Your Google Sheet should have these column headers in Row 1:

| Column | Required | Used For |
|--------|----------|----------|
| Full Name | Yes | Candidate name |
| Email | Yes | Login email & candidate email |
| Phone | No | Candidate phone |
| Call Outcome | Yes | Trigger — must be "completed" to create profile |
| User Type | Yes | Must be "job_seeker" to create profile |
| Location City | No | Candidate address |
| Job Roles | No | Candidate job title |
| About Yourself | No | "About Me" section on profile |
| Age | No | Stored as custom meta |
| Experience Years | No | Stored as custom meta |
| Languages | No | Stored as custom meta |
| Contract Type | No | Stored as custom meta |
| Online Presence | No | Candidate website |
| Confirmed Name | No | Fallback if Full Name is empty |

Other columns (Submitted At, Language, Consent, Source, Call Status, Retell Call ID, Business Name, Language Used, Call Transcript, Profile Summary EN/ES) are ignored by the script.

A **Profile Status** column will be auto-created to track results.

---

## How It Works

1. Fill in a row with candidate data in the Google Sheet
2. When you type **"completed"** in the **Call Outcome** column, the script automatically:
   - Checks that the row has a valid name and email
   - Checks that User Type is "job_seeker"
   - Checks for duplicate emails
   - Sends the data to WordPress via REST API
   - Creates a WordPress user + candidate profile
   - Updates the "Profile Status" column with "Created ✓" or an error message
3. View created candidates at: `https://workajos.com/v21/wp-admin/edit.php?post_type=candidate`

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| Profile Status shows "Error: SyntaxError..." | Check that the WordPress URL is correct and the site is accessible |
| Profile Status shows "Failed: Email already exists" | That email already has an account — use a different email |
| Profile Status shows "Skipped: user type is..." | The User Type column must contain "job_seeker" |
| Profile Status stays empty after typing "completed" | Make sure the trigger is set up (Step 5) and the column header is exactly "Call Outcome" |
| "Authorization required" popup | Click OK and authorize the script (one-time setup) |
| Plugin not working after upload | Make sure it's activated in Plugins page |

---

## Security

- The API is protected by a secret key (`X-API-Secret` header)
- Only requests with the correct secret can create candidates
- To change the secret: update `API_SECRET` in both the PHP plugin and the Google Apps Script
