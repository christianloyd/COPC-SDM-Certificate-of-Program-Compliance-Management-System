<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();
$pageTitle = "Secure Upload & Import";
require_once __DIR__ . '/../includes/admin_header.php';

$csrfToken = generateCsrfToken();
?>

<div class="max-w-6xl mx-auto">
    <div class="mb-10">
        <h1 class="text-3xl font-extrabold text-prcnavy tracking-tight">Data Integration Center</h1>
        <p class="text-gray-500 mt-2">Manage single document uploads or perform a high-speed batch import from your master lists.</p>
    </div>

    <section class="mb-8 rounded-3xl border border-gray-100 bg-white p-6 shadow-soft">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-lg font-bold text-prcnavy">Choose Upload Mode</h2>
                <p class="text-sm text-gray-500">Single upload is selected by default. Switch to batch import only when needed.</p>
            </div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <label class="flex cursor-pointer items-center rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 transition hover:border-prcgold">
                    <input type="radio" name="upload_mode" value="single" class="h-4 w-4 border-gray-300 text-prcnavy focus:ring-prcgold" checked>
                    <span class="ml-3">
                        <span class="block text-sm font-bold text-prcnavy">Single Upload</span>
                        <span class="block text-xs text-gray-400">One record with attached file</span>
                    </span>
                </label>
                <label class="flex cursor-pointer items-center rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 transition hover:border-prcgold">
                    <input type="radio" name="upload_mode" value="batch" class="h-4 w-4 border-gray-300 text-prcgold focus:ring-prcgold">
                    <span class="ml-3">
                        <span class="block text-sm font-bold text-prcnavy">Batch Import</span>
                        <span class="block text-xs text-gray-400">Excel or CSV masterlist</span>
                    </span>
                </label>
                <label class="flex cursor-pointer items-center rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 transition hover:border-prcgold">
                    <input type="radio" name="upload_mode" value="bulkpdf" class="h-4 w-4 border-gray-300 text-prcgold focus:ring-prcgold">
                    <span class="ml-3">
                        <span class="block text-sm font-bold text-prcnavy">Bulk PDF Extraction</span>
                        <span class="block text-xs text-gray-400">Auto-Extract from PDFs</span>
                    </span>
                </label>
            </div>
        </div>
    </section>

    <div class="space-y-10">
        <!-- Single Upload Section -->
        <section id="singleUploadPanel" class="bg-white rounded-3xl shadow-soft p-8 border border-gray-100 flex flex-col h-full">
            <div class="mb-6 flex items-center">
                <div class="w-12 h-12 bg-prcnavy/5 rounded-2xl flex items-center justify-center text-prcnavy mr-4 shadow-inner">
                    <i class="fa-solid fa-file-arrow-up text-xl"></i>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-prcnavy">Single Document Upload</h2>
                    <p class="text-xs text-gray-400 font-medium uppercase tracking-wider">COPC: one record | Exemption: one record per student</p>
                </div>
            </div>

            <form id="singleUploadForm" enctype="multipart/form-data" class="space-y-5 flex-1">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">Region</label>
                        <input type="text" name="region" placeholder="e.g. NCR, Region IV-A" class="w-full bg-gray-50 border-none rounded-xl py-3 px-4 text-prcnavy focus:ring-2 focus:ring-prcgold focus:outline-none transition">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">School Name *</label>
                        <input type="text" name="school_name" required class="w-full bg-gray-50 border-none rounded-xl py-3 px-4 text-prcnavy focus:ring-2 focus:ring-prcgold focus:outline-none transition">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">Program *</label>
                        <input type="text" name="program" required class="w-full bg-gray-50 border-none rounded-xl py-3 px-4 text-prcnavy focus:ring-2 focus:ring-prcgold focus:outline-none transition">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">Category *</label>
                        <select name="category" required class="w-full bg-gray-50 border-none rounded-xl py-3 px-4 text-prcnavy focus:ring-2 focus:ring-prcgold focus:outline-none transition">
                            <option value="COPC">COPC</option>
                            <option value="GR">Government Recognition (GR)</option>
                            <option value="COPC Exemption">COPC Exemption</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">Date Approved *</label>
                        <input type="date" name="date_approved" required class="w-full bg-gray-50 border-none rounded-xl py-3 px-4 text-prcnavy focus:ring-2 focus:ring-prcgold focus:outline-none transition">
                    </div>
                </div>

                <div id="studentListContainer" class="hidden transition-all duration-300">
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">Exempted Students / Student List</label>
                    <textarea name="student_list" rows="3" placeholder="Enter student names, SRN, or list details..." class="w-full bg-gray-50 border-none rounded-xl py-3 px-4 text-prcnavy focus:ring-2 focus:ring-prcgold focus:outline-none transition"></textarea>
                    <p class="text-[10px] text-gray-400 mt-1 italic">Optional: if provided, each detected student name will be saved as a separate exemption record.</p>
                </div>

                <div class="relative group">
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Attached File (PDF, Image, Excel, CSV)</label>
                    <div id="dropZone" class="border-2 border-dashed border-gray-200 rounded-2xl py-8 px-6 text-center hover:border-prcgold transition-colors cursor-pointer group-hover:bg-prclight/30 relative">
                        <input type="file" id="fileInput" name="document_file" accept=".pdf,.jpg,.jpeg,.png,.xlsx,.csv" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                        <i class="fa-solid fa-cloud-arrow-up text-3xl text-gray-300 mb-2 group-hover:text-prcgold transition-colors"></i>
                        <p class="text-sm text-gray-500 font-medium" id="fileStatus">Drag and drop file here or click to browse</p>
                        <p class="text-[10px] text-gray-400 mt-1 uppercase">Max Size: <?php echo UPLOAD_MAX_SIZE_MB; ?>MB</p>
                    </div>
                </div>

                <button type="submit" class="w-full bg-prcnavy hover:bg-prcaccent text-white font-bold py-4 rounded-2xl shadow-md hover:shadow-lg transition-all transform hover:-translate-y-0.5 flex items-center justify-center">
                    <i class="fa-solid fa-paper-plane mr-2 text-prcgold"></i> Upload & Process Record
                </button>
            </form>
        </section>

        <!-- Batch Import Section -->
        <section id="batchImportPanel" class="hidden bg-white rounded-3xl shadow-soft p-8 border border-gray-100 flex-col h-full border-t-4 border-t-prcgold">
            <div class="mb-6 flex items-center">
                <div class="w-12 h-12 bg-prcgold/10 rounded-2xl flex items-center justify-center text-prcgold mr-4 shadow-inner">
                    <i class="fa-solid fa-list-check text-xl"></i>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-prcnavy">Batch Masterlist Import</h2>
                    <p class="text-xs text-gray-400 font-medium uppercase tracking-wider">Excel / CSV High-Speed Ingestion</p>
                </div>
            </div>

            <div class="bg-prclight rounded-2xl p-5 mb-8 border border-blue-50">
                <h3 class="text-sm font-bold text-prcnavy mb-2 flex items-center">
                    <i class="fa-solid fa-circle-info mr-2 text-prcgold"></i> Import Instructions
                </h3>
                <ul class="text-xs text-gray-600 space-y-2 list-inside list-disc">
                    <li>COPC masterlists should include School and Program columns. Region, Date, Major, Student List, and Category are optional.</li>
                    <li>Institution-only lists are also accepted. They will be imported with an auto-generated placeholder program based on institution metadata.</li>
                    <li>Date format should be YYYY-MM-DD or a standard Excel date when a date column is present.</li>
                    <li>Select the correct category before uploading.</li>
                </ul>
                <a href="#" class="inline-block mt-4 text-xs font-bold text-prcgold hover:text-prcnavy transition uppercase tracking-widest"><i class="fa-solid fa-download mr-1"></i> Download Template</a>
            </div>

            <form id="batchImportForm" enctype="multipart/form-data" class="space-y-6 flex-1">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                
                <div class="relative">
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Target Category</label>
                    <select name="category" required class="w-full bg-gray-50 border-none rounded-xl py-3 px-4 text-prcnavy font-medium focus:ring-2 focus:ring-prcgold focus:outline-none transition cursor-pointer shadow-sm">
                        <option value="COPC">COPC Records</option>
                        <option value="GR">Government Recognition (GR)</option>
                        <option value="COPC Exemption">COPC Exemption Records</option>
                        <option value="HEI List">HEI List</option>
                    </select>
                </div>

                <div class="relative">
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Region Fallback</label>
                    <input type="text" name="batch_region" placeholder="e.g. REGION-XI or CHEDRO-XI" class="w-full bg-gray-50 border-none rounded-xl py-3 px-4 text-prcnavy font-medium focus:ring-2 focus:ring-prcgold focus:outline-none transition shadow-sm">
                    <p class="mt-1 text-[10px] text-gray-400 uppercase">Used when the Excel or CSV row has no Region value</p>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-3">Batch Record Status</label>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <label class="flex cursor-pointer items-center rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 transition hover:border-prcgold">
                            <input type="radio" name="batch_status" value="NEW" class="h-4 w-4 border-gray-300 text-prcnavy focus:ring-prcgold" checked>
                            <span class="ml-3">
                                <span class="block text-sm font-bold text-prcnavy">NEW</span>
                                <span class="block text-xs text-gray-400">Save all imported rows as new records</span>
                            </span>
                        </label>
                        <label class="flex cursor-pointer items-center rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 transition hover:border-prcgold">
                            <input type="radio" name="batch_status" value="OLD" class="h-4 w-4 border-gray-300 text-prcnavy focus:ring-prcgold">
                            <span class="ml-3">
                                <span class="block text-sm font-bold text-prcnavy">OLD</span>
                                <span class="block text-xs text-gray-400">Save all imported rows as old records</span>
                            </span>
                        </label>
                    </div>
                </div>

                <div class="relative group">
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Master List File (.xlsx, .csv)</label>
                    <div id="importDropZone" class="border-2 border-dashed border-gray-200 rounded-2xl py-12 text-center hover:border-prcgold transition-colors cursor-pointer group-hover:bg-prclight/30 relative">
                        <input type="file" id="importInput" name="import_file" accept=".xlsx,.csv" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                        <i class="fa-solid fa-file-excel text-4xl text-gray-300 mb-3 group-hover:text-prcgold transition-colors"></i>
                        <p class="text-sm text-gray-500 font-bold" id="importStatus">Drop Masterlist file here</p>
                        <p class="text-[10px] text-gray-400 mt-1 uppercase">Supports Excel (XLSX) and CSV</p>
                    </div>
                </div>

                <button type="submit" class="w-full bg-prcgold hover:bg-yellow-600 text-white font-extrabold py-5 rounded-2xl shadow-md hover:shadow-lg transition-all transform hover:-translate-y-0.5 flex items-center justify-center">
                    <i class="fa-solid fa-bolt mr-2 opacity-80"></i> START BATCH INGESTION
                </button>
            </form>
        </section>

        <!-- Bulk PDF Section -->
        <section id="bulkPdfPanel" class="hidden bg-white rounded-3xl shadow-soft p-8 border border-gray-100 flex-col h-full border-t-4 border-t-prcnavy/20">
            <div class="mb-6 flex items-center">
                <div class="w-12 h-12 bg-prcnavy/10 rounded-2xl flex items-center justify-center text-prcnavy mr-4 shadow-inner">
                    <i class="fa-solid fa-file-pdf text-xl"></i>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-prcnavy">Bulk PDF Extraction</h2>
                    <p class="text-xs text-gray-400 font-medium uppercase tracking-wider">Automated Metadata Mining</p>
                </div>
            </div>

            <div class="bg-indigo-50/50 rounded-2xl p-5 mb-8 border border-indigo-100">
                <h3 class="text-sm font-bold text-indigo-900 mb-2 flex items-center">
                    <i class="fa-solid fa-wand-magic-sparkles mr-2 text-indigo-500"></i> AI-Powered Extraction
                </h3>
                <p class="text-xs text-indigo-700/80 leading-relaxed">
                    Select multiple COPC or Exemption PDF files. Our system will automatically extract <strong>School Name</strong>, 
                    <strong>Program</strong>, <strong>Region</strong>, and <strong>Date Approved</strong> using OCR and pattern recognition.
                </p>
            </div>

            <div id="ocrDiagnosticsCard" class="mb-8 rounded-2xl border border-slate-200 bg-slate-50 p-5">
                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-prcnavy flex items-center">
                            <i class="fa-solid fa-stethoscope mr-2 text-prcgold"></i> OCR Diagnostics
                        </h3>
                        <p class="mt-1 text-xs text-slate-500">Checks whether PDF OCR dependencies are available on this server.</p>
                    </div>
                    <button type="button" id="refreshOcrDiagnosticsBtn" class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-bold uppercase tracking-widest text-prcnavy transition hover:border-prcgold hover:text-prcgold">
                        <i class="fa-solid fa-rotate-right mr-2"></i> Refresh Check
                    </button>
                </div>
                <div id="ocrDiagnosticsStatus" class="mt-4 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600">
                    Checking OCR tools...
                </div>
                <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div class="rounded-2xl border border-slate-200 bg-white p-4">
                        <div class="text-[11px] font-bold uppercase tracking-widest text-slate-400">Tesseract OCR</div>
                        <div id="ocrToolTesseract" class="mt-2 text-sm text-slate-600">Checking...</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-4">
                        <div class="text-[11px] font-bold uppercase tracking-widest text-slate-400">Poppler pdftoppm</div>
                        <div id="ocrToolPdftoppm" class="mt-2 text-sm text-slate-600">Checking...</div>
                    </div>
                </div>
            </div>

            <form id="bulkPdfForm" enctype="multipart/form-data" class="space-y-6 flex-1">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">Global Region Override (Optional)</label>
                        <input type="text" name="global_region" placeholder="Assigned if extraction fails..." class="w-full bg-gray-50 border-none rounded-xl py-3 px-4 text-prcnavy focus:ring-2 focus:ring-prcgold focus:outline-none transition">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">Record Category</label>
                        <select name="category" required class="w-full bg-gray-50 border-none rounded-xl py-3 px-4 text-prcnavy focus:ring-2 focus:ring-prcgold focus:outline-none transition">
                            <option value="COPC">COPC</option>
                            <option value="GR">Government Recognition (GR)</option>
                            <option value="COPC Exemption">COPC Exemption</option>
                        </select>
                    </div>
                </div>

                <div class="relative group">
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-3">Select Files (.pdf Only)</label>
                    <div id="bulkPdfDropZone" class="border-2 border-dashed border-gray-200 rounded-2xl py-12 text-center hover:border-prcgold transition-colors cursor-pointer group-hover:bg-prclight/30 relative">
                        <input type="file" id="bulkPdfInput" name="bulk_files[]" accept=".pdf" multiple class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                        <i class="fa-solid fa-copy text-4xl text-gray-300 mb-3 group-hover:text-prcgold transition-colors"></i>
                        <p class="text-sm text-gray-500 font-bold" id="bulkPdfStatus">Drag many PDFs here or click to browse</p>
                        <p class="text-[10px] text-gray-400 mt-1 uppercase">Hold CTRL to select multiple files</p>
                    </div>
                </div>

                <button type="submit" id="startBulkProcessingBtn" class="w-full bg-prcnavy hover:bg-prcaccent text-white font-extrabold py-5 rounded-2xl shadow-md hover:shadow-lg transition-all transform hover:-translate-y-0.5 flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fa-solid fa-play mr-2 text-prcgold"></i> BEGIN PARSING & IMPORT
                </button>
            </form>

            <!-- Extraction Results List (Visible during/after processing) -->
            <div id="extractionResultsContainer" class="mt-10 hidden">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-bold text-prcnavy uppercase tracking-widest">Extraction Log</h3>
                    <span id="bulkProgressLabel" class="text-xs font-bold text-prcgold">0 / 0 Completed</span>
                </div>
                <div class="overflow-x-auto rounded-2xl border border-gray-100">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-gray-50 text-gray-500 uppercase font-bold">
                            <tr>
                                <th class="px-4 py-3">File</th>
                                <th class="px-4 py-3">Detected School</th>
                                <th class="px-4 py-3">Detected Program</th>
                                <th class="px-4 py-3">Region</th>
                                <th class="px-4 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody id="extractionLogBody" class="divide-y divide-gray-50 bg-white">
                            <!-- JS injected rows -->
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</div>

<div id="progressOverlay" class="fixed inset-0 bg-prcnavy/80 backdrop-blur-md z-[100] hidden flex items-center justify-center flex-col text-white">
    <div class="w-20 h-20 border-4 border-prcgold border-t-transparent rounded-full animate-spin mb-6 shadow-glow"></div>
    <h2 class="text-2xl font-bold tracking-tight mb-2">Processing Data...</h2>
    <p class="text-prclight/60 text-sm italic font-light">Please do not refresh the page while import is in progress.</p>
</div>

<script src="<?php echo BASE_URL; ?>/assets/js/bulk_pdf.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/upload.js"></script>
<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
