# Prevent Duplicate Records

This plan outlines the approach to prevent the system from accepting double entries when uploading, importing, or manually entering records.

## Decisions

- **Duplicate Behavior:** Option B — **Update** existing records with the new data when a duplicate is detected.
- **Duplicate Key:** `school_name` + `program` + `category`. For `COPC Exemption` records, also includes `student_list` (individual student name).

## Proposed Changes

### `api/bulk_pdf_handler.php`

- [MODIFY] `bulk_pdf_handler.php`
  - Add logic to check if a record with the same `school_name`, `program`, `category`, and `student_list` (for exemptions) already exists.
  - Implement the chosen behavior (skip or update) for the duplicate records instead of blindly inserting.

### `api/import_handler.php`

- [MODIFY] `import_handler.php`
  - Similarly, add a `SELECT` check or `INSERT ... ON DUPLICATE KEY UPDATE` query to verify if the row from the CSV/XLSX already exists.
  - Apply the chosen behavior (skip or update).

### `admin/manual-entry.php`

- [MODIFY] `manual-entry.php`
  - Add a check before the `INSERT` statement.
  - If the record exists, either show an error message "Record already exists" or update it based on your preference.

### `admin/edit.php` (If necessary)

- [MODIFY] `edit.php`
  - Ensure that modifying an existing record does not clash with another existing record.

## Verification Plan

### Automated Tests
- Uploading the same PDF twice to ensure only one set of records is created or updated.
- Importing the same CSV/XLSX twice to ensure the count of records remains stable.
- Manually entering identical details twice to confirm the duplication block is active.

### Manual Verification
- You will be asked to manually attempt uploading a duplicate file and manually entering a duplicate record to verify the new behavior.
