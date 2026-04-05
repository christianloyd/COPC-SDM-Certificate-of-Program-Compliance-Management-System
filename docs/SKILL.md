---
name: copc-prd
description: "Use this skill whenever the user asks to create, update, revise, or regenerate any Product Requirements Document (PRD) for the COPC Document Management and Search System. Triggers include: any mention of 'COPC', 'PRD', 'product requirements', 'document management system', or requests to add/change/remove features, modules, tech stack choices, or sections in the COPC system spec. Also use when the user asks to produce a .docx version of the PRD, update the database schema, revise functional requirements, add new modules, or change any part of the system specification. Do NOT use for general coding tasks, unrelated document management systems, or non-COPC PRD work."
---

# COPC Document Management and Search System — PRD Skill

A skill for generating, updating, and maintaining the Product Requirements Document for the **COPC Document Management and Search System** — a web-based platform that centralizes COPC and COPC Exemption records issued by CHED (Commission on Higher Education), replacing manual Excel-based tracking with a structured, searchable database.

---

## Quick Reference

| Task | Approach |
|---|---|
| Generate full PRD as `.docx` | Use the `docx` skill + this spec as content source |
| Update a specific section | Identify affected sections → patch generator script → re-run |
| Add a new feature/module | Update Scope, FR section, Data Model, User Flow, Risks, Metrics |
| Change tech stack component | Find all references via grep → patch all affected sections |
| Version bump | Update cover, header, `Replaces` field, and output filename |

---

## Project Context

The system manages two document types:

- **COPC** — Certificate of Program Compliance (official program approval)
- **COPC Exemption** — Exemption from COPC requirement

Records originate from scanned files (PDF, JPG, PNG), Excel spreadsheets, or are entered manually when no physical document exists. All records must be centrally stored, consistently tagged, and full-text searchable.

---

## Confirmed Tech Stack

Always use these exact values in the PRD. Never revert to older choices.

| Layer | Technology | Notes |
|---|---|---|
| Frontend | HTML5, **Tailwind CSS**, JavaScript | Not Bootstrap. Not plain CSS. Tailwind CSS only. |
| Backend | **Vanilla PHP** | No frameworks (no Laravel, no Symfony) |
| Database | **MySQL (InnoDB)** | Not PostgreSQL. Not Supabase DB. |
| File Storage | **Local server filesystem** (`uploads/`) | Not Supabase Storage. Not S3. |
| PDF Extraction | PHP `pdfparser` library | |
| Image OCR | Tesseract OCR (via PHP `exec`) | |
| Excel Reading | `PhpSpreadsheet` | |

> **Critical:** Supabase is **not used** in this project at all (neither for database nor storage). If a previous PRD version mentioned Supabase PostgreSQL or Supabase Storage, those are outdated and must not be carried forward.

---

## Current PRD Version

| Field | Value |
|---|---|
| Version | 2.1 |
| Date | March 2026 |
| Generator script | `/home/claude/gen_prd_v2_tw.mjs` |
| Output file | `COPC_PRD_v2.1.docx` |
| Replaces | PRD v2.0 (Bootstrap edition) |

### Version History

| Version | Key Change |
|---|---|
| v1.0 | Initial — PostgreSQL + Supabase Storage |
| v1.1 | Corrected to MySQL + Supabase Storage (hybrid) |
| v2.0 | Full rewrite — MySQL + local storage + Manual Entry Module |
| v2.1 | CSS framework changed from Bootstrap to **Tailwind CSS** |

---

## Confirmed Modules (In Scope)

All six modules below are confirmed in scope. Do not remove any without explicit instruction.

### 1. File Upload Module (FR-1)
- Accepts: PDF, JPG, PNG, XLSX
- Validates: MIME type + file size (max 20 MB) server-side
- Stores: file to `uploads/copc/` or `uploads/exemptions/` based on category
- Saves: `file_path` (server-relative) in MySQL

### 2. Manual Data Entry Module (FR-2) ⭐ Added in v2.0
- Admin can create records **without uploading any file**
- Same metadata fields as upload: school name, program, category, date approved
- `file_path` and `extracted_text` stored as `NULL`
- Records visually distinguished in search results with a "Manual Entry — No File" badge
- Admin can later attach a file to a manual-entry record
- Optional `notes` field for free-text context

### 3. Text Extraction Engine (FR-3)
- PDF → `pdfparser`
- Image (JPG/PNG) → Tesseract OCR via `exec()`
- XLSX → `PhpSpreadsheet`
- Extraction failure does **not** block record save; failure is logged
- Admin can manually re-trigger extraction per record

### 4. Search Module (FR-4)
- MySQL `FULLTEXT` search using `MATCH ... AGAINST`
- Searches across: `school_name`, `program`, `extracted_text`
- Filters: Category, Program, Approval Year
- Results include: record type badge, preview/download (file-backed) or "No File" (manual)
- Response time target: **< 2 seconds** for up to 10,000 records

### 5. Admin Controls (FR-5)
- Full CRUD on all records (both upload and manual-entry)
- Edit includes option to attach file to manual-entry records
- Delete removes DB record + associated file (if any)
- Confirmation dialog required before delete

### 6. Authentication and Access Control (FR-6)
- Session-based PHP login for admin users
- Unauthenticated users: read-only search access only
- Passwords: bcrypt hash (`password_hash` / `PASSWORD_BCRYPT`)
- CSRF tokens on all state-changing forms
- Session timeout: 60 minutes inactivity

---

## Data Model

### Primary table: `copc_documents`

```sql
CREATE TABLE copc_documents (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  school_name    VARCHAR(255)    NOT NULL,
  program        VARCHAR(255)    NOT NULL,
  category       ENUM('COPC', 'COPC Exemption') NOT NULL,
  date_approved  DATE            NOT NULL,
  file_path      VARCHAR(500)    NULL,        -- NULL for manual-entry records
  file_type      VARCHAR(10)     NULL,        -- 'pdf', 'jpg', 'png', 'xlsx' or NULL
  file_name      VARCHAR(255)    NULL,
  file_size_kb   INT UNSIGNED    NULL,
  extracted_text LONGTEXT        NULL,        -- NULL if no file or extraction failed
  notes          TEXT            NULL,        -- Optional admin notes
  entry_type     ENUM('upload', 'manual') NOT NULL DEFAULT 'upload',
  uploaded_by    VARCHAR(100)    NULL,
  created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME        NULL ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_school_name  (school_name),
  INDEX idx_program      (program),
  INDEX idx_category     (category),
  INDEX idx_date_approved(date_approved),
  INDEX idx_entry_type   (entry_type),
  FULLTEXT INDEX ft_search (school_name, program, extracted_text)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Supporting table: `admin_users`

```sql
CREATE TABLE admin_users (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  username       VARCHAR(100)    NOT NULL UNIQUE,
  password_hash  VARCHAR(255)    NOT NULL,
  full_name      VARCHAR(200)    NULL,
  created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login_at  DATETIME        NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### File storage layout

```
uploads/
├── copc/           ← COPC files
│   └── {YYYYMMDD}_{hex}_{sanitized_name}.{ext}
└── exemptions/     ← COPC Exemption files
    └── {YYYYMMDD}_{hex}_{sanitized_name}.{ext}
```

All file downloads are routed through `download.php` — files are never served directly from the filesystem URL.

---

## User Personas

| Persona | Role | Primary Actions |
|---|---|---|
| Admin Staff | Records / Administrative Officer | Upload files, manually enter records, edit/delete, manage metadata |
| Office Personnel | Compliance staff, faculty coordinators | Search, filter, preview, download |

---

## Non-Functional Requirements (Confirmed Targets)

| Category | Requirement | Target |
|---|---|---|
| Performance | Search response time | < 2 seconds (10,000 records) |
| Performance | File upload + save (excl. OCR) | < 5 seconds (< 10 MB files) |
| Performance | OCR per image | < 30 seconds |
| Performance | Manual entry save | < 1 second |
| Security | File validation | MIME type + extension whitelist, server-side |
| Security | SQL injection | PDO prepared statements on all queries |
| Security | CSRF | Token on every POST form |
| Availability | Uptime | > 99.5% monthly |
| Storage | Max file size | 20 MB per file |
| Backup | MySQL dump | Daily automated |

---

## PRD Document Structure

The PRD is generated as a `.docx` using the `docx` npm library (Node.js ESM). The 13 sections are:

1. Executive Summary
2. Problem Statement
3. Objectives
4. Scope (In Scope / Out of Scope)
5. User Personas
6. Functional Requirements (FR-1 through FR-6)
7. Non-Functional Requirements
8. System Architecture Overview
9. Data Model
10. User Flow (4 flows: Upload, Manual Entry, Search, Edit/Delete)
11. Success Metrics
12. Risks and Mitigations
13. Future Enhancements

### Document styling conventions

| Element | Style |
|---|---|
| H1 color | `#1F3864` (dark blue) |
| H2 color | `#2E75B6` (mid blue) |
| Accent (Manual Entry) | `#2196A6` (teal) |
| Table header fill | `#1F3864` with white text |
| Alternating row fill | `#EBF3FB` (light blue) |
| FR priority badges | Green = Must Have, Blue = Should Have, Brown = Nice to Have |
| Page size | US Letter (12240 × 15840 DXA) |
| Margins | 1080 DXA all sides (~0.75 inch) |
| Font | Arial throughout |

---

## How to Update the PRD

### Changing a tech stack item

1. `grep` the generator script for all occurrences of the old technology name
2. Replace in: Executive Summary body, info box, Scope table, Architecture table, Architecture bullets, NFR table
3. Bump the version number in: cover page, header text, `Replaces` field, output filename
4. Re-run: `node gen_prd_v2_tw.mjs`
5. Validate: `python3 /mnt/skills/public/docx/scripts/office/validate.py COPC_PRD_vX.Y.docx`

### Adding a new module

Update **all** of the following sections when a new module is added:

- [ ] Section 4 — Scope (In Scope table)
- [ ] Section 6 — Functional Requirements (new FR-N subsection)
- [ ] Section 8 — Architecture (component diagram text)
- [ ] Section 9 — Data Model (new columns if needed)
- [ ] Section 10 — User Flow (new flow if user-facing)
- [ ] Section 11 — Success Metrics (new metric if measurable)
- [ ] Section 12 — Risks (new risk if applicable)
- [ ] Section 13 — Future Enhancements (remove from future if now in scope)

### Removing a module

Move it from Section 4 (In Scope) → Section 4 (Out of Scope) with a reason. Remove its FR subsection. Update Architecture and Data Model accordingly.

---

## Out of Scope (Do Not Add Without Instruction)

These are explicitly deferred. Do not add them to In Scope unless the user requests it:

- Mobile native app
- Automated CHED API integration
- Document version history / audit log (planned v1.1)
- Batch reporting / export dashboard (planned v2.0)
- Email / SMS notifications (planned v2.0)
- Cloud file storage migration (planned v2.0)
- Third-party SSO
- Multi-language UI
- AI-assisted metadata suggestion (planned v3.0)
- Public-facing portal (planned v3.0)

---

## Planned Future Enhancements (Section 13 Summary)

| Enhancement | Target Version |
|---|---|
| Async extraction job queue | v1.1 |
| Audit log | v1.1 |
| Duplicate detection (SHA-256 hash) | v1.1 |
| File attachment for manual records | v1.1 |
| Bulk data import from Excel | v1.1 |
| Reporting / analytics dashboard | v2.0 |
| Role-based access control (RBAC) | v2.0 |
| Email notifications | v2.0 |
| Cloud file storage migration | v2.0 |
| AI-assisted metadata suggestion | v3.0 |
| Digital signature verification | v3.0 |
| Public-facing search portal | v3.0 |

---

## Common Pitfalls to Avoid

- **Never use Supabase** — it was used in v1.0/v1.1 only; this project uses MySQL + local storage
- **Never use Bootstrap** — the project uses Tailwind CSS since v2.1
- **Never use PostgreSQL types** (`UUID`, `TSVECTOR`, `TIMESTAMPTZ`) — use MySQL types (`BIGINT UNSIGNED AUTO_INCREMENT`, `LONGTEXT`, `DATETIME`)
- **Never use `WidthType.PERCENTAGE`** in docx tables — always use `WidthType.DXA`
- **Never use unicode bullet characters** in docx — always use `LevelFormat.BULLET` with numbering config
- **Manual entry records share the same table** as upload records — differentiated by `entry_type` ENUM and `file_path IS NULL`
- **All file downloads must go through `download.php`** — never expose raw filesystem paths to the browser

---

## Dependencies

- `docx` npm package (v9.5.3): `npm install -g docx` — for `.docx` generation
- `node` (v18+): ESM module support required
- `python3`: for validation script
- Validation script: `/mnt/skills/public/docx/scripts/office/validate.py`
- Generator script: `/home/claude/gen_prd_v2_tw.mjs`

> When generating the `.docx`, always validate after generation. If validation fails, check for: mismatched column widths in tables, invalid `PageNumber` usage, or missing `outlineLevel` on heading paragraphs.
