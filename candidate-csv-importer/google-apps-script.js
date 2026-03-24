/**
 * Google Apps Script — paste this into your Google Sheet's script editor.
 * 
 * SETUP STEPS:
 * 1. Open your Google Sheet
 * 2. Go to Extensions → Apps Script
 * 3. Delete any existing code and paste this entire file
 * 4. Replace YOUR_NGROK_URL below with your actual ngrok URL
 * 5. Click Save
 * 6. Go to Triggers (clock icon on left sidebar) → Add Trigger:
 *    - Function: onNewRow
 *    - Event source: From spreadsheet
 *    - Event type: On form submit  (OR use "On change" if adding rows manually)
 * 7. Authorize the script when prompted
 *
 * GOOGLE SHEET FORMAT (Row 1 = headers):
 * | name       | email              | phone        | location  | job_title     | description          |
 * | John Doe   | john@example.com   | +1234567890  | New York  | Web Developer | Experienced dev      |
 */

// ===== CONFIGURATION =====
const WORDPRESS_API_URL = 'https://holly-ungambolling-herb.ngrok-free.dev/workojas/wp-json/workojas/v1/create-candidate';
const API_SECRET = 'workojas_secret_2026';
// ==========================

/**
 * Trigger: runs automatically when a new row is added.
 * Set this as an "On change" or "On form submit" trigger.
 */
function onNewRow(e) {
  var sheet = SpreadsheetApp.getActiveSpreadsheet().getActiveSheet();
  var lastRow = sheet.getLastRow();
  
  // Get headers from row 1
  var headers = sheet.getRange(1, 1, 1, sheet.getLastColumn()).getValues()[0];
  
  // Get the last row data
  var rowData = sheet.getRange(lastRow, 1, 1, sheet.getLastColumn()).getValues()[0];
  
  // Build the candidate object from headers + row data
  var candidate = {};
  for (var i = 0; i < headers.length; i++) {
    var key = headers[i].toString().trim().toLowerCase();
    var value = rowData[i] ? rowData[i].toString().trim() : '';
    if (key && value) {
      candidate[key] = value;
    }
  }
  
  // Skip if no name or email
  if (!candidate.name || !candidate.email) {
    Logger.log('Skipped row ' + lastRow + ': missing name or email');
    return;
  }
  
  // Send to WordPress API
  var result = createCandidate(candidate);
  
  // Log the result in a "Status" column (optional)
  var statusCol = headers.indexOf('status');
  if (statusCol === -1) {
    // Add "status" header if it doesn't exist
    statusCol = headers.length;
    sheet.getRange(1, statusCol + 1).setValue('status');
  }
  sheet.getRange(lastRow, statusCol + 1).setValue(result);
}

/**
 * Sends candidate data to the WordPress REST API.
 */
function createCandidate(candidate) {
  var options = {
    method: 'post',
    contentType: 'application/json',
    headers: {
      'X-API-Secret': API_SECRET
    },
    payload: JSON.stringify(candidate),
    muteHttpExceptions: true
  };
  
  try {
    var response = UrlFetchApp.fetch(WORDPRESS_API_URL, options);
    var responseCode = response.getResponseCode();
    var responseBody = JSON.parse(response.getContentText());
    
    if (responseCode === 201) {
      Logger.log('Created: ' + candidate.name + ' (' + candidate.email + ')');
      return 'Created ✓';
    } else {
      Logger.log('Failed: ' + responseBody.message);
      return 'Failed: ' + responseBody.message;
    }
  } catch (error) {
    Logger.log('Error: ' + error.toString());
    return 'Error: ' + error.toString();
  }
}

/**
 * Manual test function — run this to test the API connection.
 * Go to Apps Script editor → select testAPI → click Run
 */
function testAPI() {
  var testCandidate = {
    name: 'Test User',
    email: 'testuser_' + new Date().getTime() + '@example.com',
    phone: '+1234567890',
    location: 'Test City',
    job_title: 'Tester'
  };
  
  var result = createCandidate(testCandidate);
  Logger.log('Test result: ' + result);
}
