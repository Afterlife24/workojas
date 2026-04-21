/**
 * Google Apps Script for Workojas — Auto Candidate Creation
 *
 * AUTOMATIC: When "Call Outcome" column is set to "completed",
 * the candidate profile is created immediately.
 *
 * SETUP:
 * 1. Open Google Sheet → Extensions → Apps Script
 * 2. Paste this code, save (Ctrl+S)
 * 3. Go to Triggers (clock icon) → Add Trigger:
 *    - Function: onEditTrigger
 *    - Event source: From spreadsheet
 *    - Event type: On edit
 * 4. Authorize when prompted
 * 5. That's it — profiles auto-create when Call Outcome = completed
 */

// ===== CONFIGURATION =====
const WORDPRESS_API_URL = 'https://holly-ungambolling-herb.ngrok-free.dev/workojas/wp-json/workojas/v1/create-candidate';
const API_SECRET = 'workojas_secret_2026';
const TRIGGER_COLUMN = 'call outcome';
const TRIGGER_VALUE = 'completed';
// ==========================

/**
 * Fires on every cell edit. Only acts when "Call Outcome" is set to "completed".
 */
function onEditTrigger(e) {
  var sheet = e.source.getActiveSheet();
  var range = e.range;
  var editedRow = range.getRow();
  var editedCol = range.getColumn();

  // Ignore header row
  if (editedRow <= 1) return;

  // Get headers
  var headers = sheet.getRange(1, 1, 1, sheet.getLastColumn()).getValues()[0];
  var normalizedHeaders = headers.map(function(h) { return h.toString().trim().toLowerCase(); });

  // Check if the edited column is "Call Outcome"
  var triggerColIndex = normalizedHeaders.indexOf(TRIGGER_COLUMN);
  if (triggerColIndex === -1 || editedCol !== triggerColIndex + 1) {
    Logger.log('Ignored: edited column ' + editedCol + ' is not "Call Outcome" (col ' + (triggerColIndex + 1) + ')');
    return;
  }

  // Check if the new value is "completed"
  var newValue = e.value ? e.value.toString().trim().toLowerCase() : '';
  if (newValue !== TRIGGER_VALUE) {
    Logger.log('Ignored: value "' + newValue + '" is not "completed"');
    return;
  }

  Logger.log('Triggered: Row ' + editedRow + ' — Call Outcome set to "completed"');

  // Check Profile Status — skip if already created
  var statusCol = getOrCreateStatusCol(sheet, headers);
  var existingStatus = sheet.getRange(editedRow, statusCol).getValue().toString().trim();
  if (existingStatus === 'Created ✓') {
    Logger.log('Skipped: Row ' + editedRow + ' already created');
    return;
  }

  // Get row data
  var rowData = sheet.getRange(editedRow, 1, 1, sheet.getLastColumn()).getValues()[0];
  var raw = buildRawObject(normalizedHeaders, rowData);

  // Validate
  var validation = validateRow(raw, editedRow);
  if (validation) {
    Logger.log('Validation failed: ' + validation);
    sheet.getRange(editedRow, statusCol).setValue(validation);
    return;
  }

  // Check duplicate email
  var emailCol = normalizedHeaders.indexOf('email') + 1;
  if (emailCol > 0) {
    for (var r = 2; r < editedRow; r++) {
      var existingEmail = sheet.getRange(r, emailCol).getValue().toString().trim().toLowerCase();
      var existingRowStatus = sheet.getRange(r, statusCol).getValue().toString().trim();
      if (existingEmail === raw['email'].toLowerCase() && existingRowStatus === 'Created ✓') {
        var dupMsg = 'Skipped: duplicate email in row ' + r;
        Logger.log(dupMsg);
        sheet.getRange(editedRow, statusCol).setValue(dupMsg);
        return;
      }
    }
  }

  // Create candidate
  var candidate = mapToCandidate(raw);
  Logger.log('Creating candidate: ' + candidate.name + ' (' + candidate.email + ')');
  var result = createCandidate(candidate);
  Logger.log('Result: ' + result);
  sheet.getRange(editedRow, statusCol).setValue(result);
}

function validateRow(raw, rowNum) {
  if (!raw['full name'] && !raw['confirmed name']) {
    return 'Skipped row ' + rowNum + ': missing name';
  }
  if (!raw['email']) {
    return 'Skipped row ' + rowNum + ': missing email';
  }
  var userType = (raw['user type'] || '').toLowerCase();
  if (userType !== 'job_seeker') {
    return 'Skipped row ' + rowNum + ': user type is "' + (raw['user type'] || 'empty') + '"';
  }
  return null;
}

function mapToCandidate(raw) {
  var candidate = {};
  candidate.name = raw['full name'] || raw['confirmed name'] || '';
  candidate.email = raw['email'] || '';

  if (raw['phone']) candidate.phone = raw['phone'];
  if (raw['location city']) candidate.location = raw['location city'];
  if (raw['job roles']) candidate.job_title = raw['job roles'];
  if (raw['about yourself']) candidate.description = raw['about yourself'];
  if (raw['age']) candidate.age = raw['age'];
  if (raw['experience years']) candidate.experience_years = raw['experience years'];
  if (raw['languages']) candidate.languages = raw['languages'];
  if (raw['contract type']) candidate.contract_type = raw['contract type'];
  if (raw['online presence']) candidate.website = raw['online presence'];

  return candidate;
}

function buildRawObject(headers, rowData) {
  var obj = {};
  for (var i = 0; i < headers.length; i++) {
    var key = headers[i].toString().trim().toLowerCase();
    var value = rowData[i] ? rowData[i].toString().trim() : '';
    if (key && value) obj[key] = value;
  }
  return obj;
}

function getOrCreateStatusCol(sheet, headers) {
  var normalized = headers.map(function(h) { return h.toString().trim().toLowerCase(); });
  var idx = normalized.indexOf('profile status');
  if (idx === -1) {
    idx = headers.length;
    sheet.getRange(1, idx + 1).setValue('Profile Status');
  }
  return idx + 1;
}

function createCandidate(candidate) {
  var options = {
    method: 'post',
    contentType: 'application/json',
    headers: { 'X-API-Secret': API_SECRET },
    payload: JSON.stringify(candidate),
    muteHttpExceptions: true
  };

  try {
    var response = UrlFetchApp.fetch(WORDPRESS_API_URL, options);
    var responseCode = response.getResponseCode();
    var responseBody = JSON.parse(response.getContentText());

    if (responseCode === 201) {
      return 'Created ✓';
    } else {
      return 'Failed: ' + responseBody.message;
    }
  } catch (error) {
    return 'Error: ' + error.toString();
  }
}

function testAPI() {
  var testCandidate = {
    name: 'Test User',
    email: 'testuser_' + new Date().getTime() + '@example.com',
    phone: '+1234567890',
    location: 'Test City',
    job_title: 'Tester',
    description: 'Test about me'
  };
  var result = createCandidate(testCandidate);
  Logger.log('Test result: ' + result);
}
