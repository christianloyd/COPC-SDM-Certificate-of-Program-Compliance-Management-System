<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

$pageTitle = "Dashboard";
require_once __DIR__ . '/../includes/admin_header.php';

try {
    $pdo = getDBConnection();
    
    // Stats
    $total = $pdo->query("SELECT COUNT(*) FROM copc_documents")->fetchColumn();
    $copcCount = $pdo->query("SELECT COUNT(*) FROM copc_documents WHERE category = 'COPC'")->fetchColumn();
    $exemptCount = $pdo->query("SELECT COUNT(*) FROM copc_documents WHERE category = 'COPC Exemption'")->fetchColumn();
    $manualCount = $pdo->query("SELECT COUNT(*) FROM copc_documents WHERE entry_type = 'manual'")->fetchColumn();
    
    // Recent Records
    $stmt = $pdo->query("SELECT id, school_name, program, category, date_approved, entry_type, created_at 
                         FROM copc_documents ORDER BY created_at DESC LIMIT 5");
    $recent = $stmt->fetchAll();
    
} catch (\Exception $e) {
    die("Database error on dashboard: " . $e->getMessage());
}
?>

<div class="mb-6 flex justify-between items-end">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
        <p class="text-sm text-gray-500 mt-1">Welcome back, <?php echo h($_SESSION['full_name'] ?? $_SESSION['username']); ?></p>
    </div>
    <div class="space-x-2 hidden md:block">
        <a href="upload.php" class="bg-chedblue hover:bg-blue-800 text-white px-4 py-2 rounded shadow text-sm font-medium transition">
            + Upload File
        </a>
        <a href="manual-entry.php" class="bg-chedteal hover:bg-teal-700 text-white px-4 py-2 rounded shadow text-sm font-medium transition">
            + Manual Entry
        </a>
    </div>
</div>

<!-- Key Metrics Base Form / Overview Form -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
    <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-100 flex items-center">
        <div class="bg-blue-100 text-blue-600 p-3 rounded-full mr-4">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
        </div>
        <div>
            <p class="text-sm text-gray-500 font-medium">Total Records</p>
            <p class="text-2xl font-bold text-gray-800"><?php echo number_format($total); ?></p>
        </div>
    </div>
    <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-100 flex items-center">
        <div class="bg-green-100 text-green-600 p-3 rounded-full mr-4">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        </div>
        <div>
            <p class="text-sm text-gray-500 font-medium">COPC</p>
            <p class="text-2xl font-bold text-gray-800"><?php echo number_format($copcCount); ?></p>
        </div>
    </div>
    <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-100 flex items-center">
        <div class="bg-purple-100 text-purple-600 p-3 rounded-full mr-4">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        </div>
        <div>
            <p class="text-sm text-gray-500 font-medium">Exemptions</p>
            <p class="text-2xl font-bold text-gray-800"><?php echo number_format($exemptCount); ?></p>
        </div>
    </div>
    <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-100 flex items-center">
        <div class="bg-amber-100 text-amber-600 p-3 rounded-full mr-4">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
        </div>
        <div>
            <p class="text-sm text-gray-500 font-medium">Manual Entries</p>
            <p class="text-2xl font-bold text-gray-800"><?php echo number_format($manualCount); ?></p>
        </div>
    </div>
</div>

<!-- Mobile action buttons -->
<div class="grid grid-cols-2 gap-4 mb-6 md:hidden">
    <a href="upload.php" class="bg-chedblue text-white text-center py-3 rounded shadow font-medium">Upload File</a>
    <a href="manual-entry.php" class="bg-chedteal text-white text-center py-3 rounded shadow font-medium">Manual Entry</a>
</div>

<!-- Recent Activity -->
<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
        <h2 class="text-sm font-bold text-gray-700 uppercase tracking-wider">Recently Added Records</h2>
        <a href="records.php" class="text-sm text-chedmid hover:underline font-medium">View All →</a>
    </div>
    
    <div class="overflow-x-auto">
        <table class="min-w-[1100px] w-full text-left border-collapse">
            <thead>
                <tr class="bg-gray-50 text-gray-500 text-xs text-left uppercase border-b">
                    <th class="px-6 py-3 font-semibold w-[34rem]">School & Program</th>
                    <th class="px-6 py-3 font-semibold w-[12rem]">Category</th>
                    <th class="px-6 py-3 font-semibold w-[12rem]">Type</th>
                    <th class="px-6 py-3 font-semibold w-[12rem]">Added</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 text-sm">
                <?php if (empty($recent)): ?>
                <tr>
                    <td colspan="4" class="px-6 py-8 text-center text-gray-500">No records found. Start by adding one.</td>
                </tr>
                <?php else: ?>
                    <?php foreach ($recent as $r): ?>
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-6 py-4 align-top">
                            <div class="font-medium text-gray-900 leading-snug min-w-[28rem]"><?php echo h($r['school_name']); ?></div>
                            <div class="text-gray-500 text-xs mt-1 leading-relaxed"><?php echo h($r['program']); ?></div>
                        </td>
                        <td class="px-6 py-4 align-top">
                            <span class="inline-flex self-start px-2 py-1 text-xs font-semibold rounded-full <?php echo $r['category'] === 'COPC' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'; ?>">
                                <?php echo h($r['category']); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 align-top">
                            <?php if ($r['entry_type'] === 'manual'): ?>
                                <span class="text-amber-600 bg-amber-50 px-2 py-1 rounded text-xs inline-flex items-center">Manual</span>
                            <?php else: ?>
                                <span class="text-gray-600 bg-gray-100 px-2 py-1 rounded text-xs items-center">Upload</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-gray-500 align-top">
                            <?php echo date('M d, Y', strtotime($r['created_at'])); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
