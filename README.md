# COPC Document Management System

## Overview
The **Certificate of Program Compliance (COPC) Document Management System** is a robust web-based application designed to digitally track, extract, manage, and store compliance documents. The system leverages automated OCR text extraction to streamline operations, featuring dynamic administrative tools, live search indexing, and a premium, responsive interface tailored for institutional workflows.

## Key Features
- **Automated Bulk Document Processing**: Upload bulk PDFs and extract details seamlessly using integrated Tesseract OCR and Poppler tooling.
- **Advanced Admin Dashboard**: A feature-rich dashboard with real-time statistics, notifications, interactive sidebars, and customizable modals.
- **Role-Based Access Control**: Secure multi-tiered user privileges including administrative controls, auditing, and moderation mechanisms.
- **Live Search & Indexing**: Instant data retrieval leveraging high-performance asynchronous JavaScript and database optimizations.
- **Mobile-Responsive UI**: Fully styled, dynamic frontend utilizing modern glassmorphism, tailored color palettes, and interactive elements.
- **Maintenance Mode & Diagnostic Systems**: Built-in environment diagnostic tooling and dynamic maintenance toggles.

## Tech Stack
- **Frontend**: HTML5, Vanilla JavaScript, Tailwind CSS (via PostCSS)
- **Backend**: PHP 8+
- **Database**: MySQL
- **Tooling/Dependencies**: 
  - Composer & NPM Packages 
  - Tesseract OCR (Optical Character Recognition)
  - Poppler (PDF Utilities)

## Installation & Setup

1. **Clone the Repository**
   ```bash
   git clone https://github.com/christianloyd/COPC-SDM-Certificate-of-Program-Compliance-Management-System.git
   ```

2. **Install Dependencies**
   ```bash
   composer install
   npm install
   ```

3. **Configure the Database**
   - Import the database schema from `db/schema.sql` into your local MySQL server.
   - Update the database credentials in `includes/config.php` (or `.env` equivalent) to match your environment.

4. **Setup Optical Character Recognition (OCR)**
   - Ensure you have **Tesseract OCR** and **Poppler** (pdftoppm/pdftotext) installed on your operating system.
   - Verify the paths for these global binaries match the internal application configuration, allowing background extractions to seamlessly process PDFs.

5. **Start the Development Environment**
   If you are running Laragon, XAMPP, or a similar stack, start your Apache/Nginx web server and navigate to the application URL in your browser.

## Deployment Notes
- **Hosting**: Designed with portability in mind, optimized out-of-the-box for cloud platforms (e.g., InfinityFree, cPanel hosts).
- **Security Checklists**: Application incorporates `.htaccess` configurations and specific client protection scripts. Ensure root directories are appropriately permissioned on production environments.

## License
This project is licensed under the MIT License - see the `LICENSE` file for details.
