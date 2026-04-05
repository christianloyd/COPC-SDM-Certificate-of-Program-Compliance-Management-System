<?php 
require_once __DIR__ . '/config.php'; 
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
requireAdmin(); // Enforce auth
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - <?php echo h($pageTitle ?? APP_NAME); ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>/assets/img/prclogo.png">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <!-- Tailwind CSS (compiled) -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/app.css">
</head>
<body class="bg-prclight text-gray-800 font-sans min-h-screen antialiased selection:bg-prcgold selection:text-white">
    <?php $currentPage = basename($_SERVER['PHP_SELF']); ?>
    <div class="min-h-screen md:flex">

        <!-- Desktop Sidebar -->
        <aside class="hidden md:block md:w-72 md:shrink-0">
            <div class="sticky top-0 flex h-screen flex-col border-r border-gray-100 bg-white shadow-sm">
                <div class="flex h-24 shrink-0 items-center justify-center border-b border-gray-100 px-6">
                    <img src="<?php echo BASE_URL; ?>/assets/img/prclogo.png" alt="PRC Logo" class="mr-3 h-10 w-10 object-contain drop-shadow-sm">
                    <div class="flex flex-col">
                        <span class="text-sm font-bold uppercase tracking-widest text-prcnavy">PRC Admin</span>
                        <span class="text-xs font-medium text-gray-400">COPC Matrix Portal</span>
                    </div>
                </div>

                <nav class="custom-scrollbar flex-1 space-y-1.5 overflow-y-auto px-4 py-8 font-medium">
                    <a href="<?php echo BASE_URL; ?>/admin/dashboard.php" class="flex items-center rounded-xl px-4 py-3 transition-all duration-200 <?php echo ($currentPage == 'dashboard.php') ? 'border border-gray-100 bg-prclight text-prcnavy shadow-sm' : 'text-gray-500 hover:bg-prclight hover:text-prcnavy'; ?>">
                        <i class="fa-solid fa-chart-line w-6 text-center <?php echo ($currentPage == 'dashboard.php') ? 'text-prcgold' : 'text-gray-400'; ?>"></i>
                        <span class="ml-3">Dashboard</span>
                    </a>

                    <a href="<?php echo BASE_URL; ?>/admin/records.php" class="flex items-center rounded-xl px-4 py-3 transition-all duration-200 <?php echo ($currentPage == 'records.php') ? 'border border-gray-100 bg-prclight text-prcnavy shadow-sm' : 'text-gray-500 hover:bg-prclight hover:text-prcnavy'; ?>">
                        <i class="fa-solid fa-layer-group w-6 text-center <?php echo ($currentPage == 'records.php') ? 'text-prcgold' : 'text-gray-400'; ?>"></i>
                        <span class="ml-3">Record Vault</span>
                    </a>

                    <a href="<?php echo BASE_URL; ?>/admin/upload.php" class="flex items-center rounded-xl px-4 py-3 transition-all duration-200 <?php echo ($currentPage == 'upload.php') ? 'border border-gray-100 bg-prclight text-prcnavy shadow-sm' : 'text-gray-500 hover:bg-prclight hover:text-prcnavy'; ?>">
                        <i class="fa-solid fa-cloud-arrow-up w-6 text-center <?php echo ($currentPage == 'upload.php') ? 'text-prcgold' : 'text-gray-400'; ?>"></i>
                        <span class="ml-3">Secure Upload</span>
                    </a>

                    <a href="<?php echo BASE_URL; ?>/admin/manual-entry.php" class="group flex items-center rounded-xl px-4 py-3 transition-all duration-200 <?php echo ($currentPage == 'manual-entry.php') ? 'border border-gray-100 bg-prclight text-prcnavy shadow-sm' : 'text-gray-500 hover:bg-prclight hover:text-prcnavy'; ?>">
                        <i class="fa-solid fa-keyboard w-6 text-center <?php echo ($currentPage == 'manual-entry.php') ? 'text-prcgold' : 'text-gray-400'; ?>"></i>
                        <div class="ml-3 flex flex-col">
                            <span>Manual Log</span>
                            <span class="mt-0.5 text-[10px] font-normal uppercase tracking-wider text-gray-400">No Physical File</span>
                        </div>
                    </a>

                    <a href="<?php echo BASE_URL; ?>/admin/migrate_exemptions.php" class="group flex items-center rounded-xl px-4 py-3 transition-all duration-200 <?php echo ($currentPage == 'migrate_exemptions.php') ? 'border border-gray-100 bg-prclight text-prcnavy shadow-sm' : 'text-gray-500 hover:bg-prclight hover:text-prcnavy'; ?>">
                        <i class="fa-solid fa-shuffle w-6 text-center <?php echo ($currentPage == 'migrate_exemptions.php') ? 'text-prcgold' : 'text-gray-400'; ?>"></i>
                        <div class="ml-3 flex flex-col">
                            <span>Exemption Migration</span>
                            <span class="mt-0.5 text-[10px] font-normal uppercase tracking-wider text-gray-400">Split Existing Rows</span>
                        </div>
                    </a>

                    <div class="mt-8 border-t border-gray-100 pt-8">
                        <a href="<?php echo BASE_URL; ?>/index.php" target="_blank" class="flex items-center rounded-xl px-4 py-2.5 text-sm text-gray-400 transition-colors hover:bg-gray-50 hover:text-prcnavy">
                            <i class="fa-solid fa-arrow-up-right-from-square w-6 text-center"></i>
                            <span class="ml-3">Launch Public Portal</span>
                        </a>

                        <a href="<?php echo BASE_URL; ?>/logout.php" class="group mt-2 flex items-center rounded-xl px-4 py-2.5 text-sm text-red-300 transition-colors hover:bg-red-50 hover:text-red-700">
                            <i class="fa-solid fa-power-off w-6 text-center group-hover:animate-pulse"></i>
                            <span class="ml-3">End Session</span>
                        </a>
                    </div>
                </nav>
            </div>
        </aside>

        <!-- Content column -->
        <div class="relative flex min-h-screen min-w-0 flex-1 flex-col">

            <div class="pointer-events-none absolute inset-x-0 top-0 z-30 hidden h-1 bg-gradient-to-r from-prcnavy via-prcgold to-transparent opacity-30 md:block"></div>

            <!-- Mobile Header -->
            <header class="sticky top-0 z-40 flex h-20 shrink-0 items-center justify-between border-b border-gray-100 bg-white/95 px-6 shadow-sm backdrop-blur-sm md:hidden">
                <div class="flex items-center">
                    <img src="<?php echo BASE_URL; ?>/assets/img/prclogo.png" alt="PRC Logo" class="mr-2 h-8 w-8 object-contain">
                    <span class="text-sm font-bold uppercase tracking-widest text-prcnavy">PRC Admin</span>
                </div>
                <button id="mobile-menu-toggle" type="button" class="text-prcnavy transition hover:text-prcgold focus:outline-none" aria-controls="mobile-menu" aria-expanded="false">
                    <i class="fa-solid fa-bars text-2xl"></i>
                </button>
            </header>

            <!-- Mobile Menu -->
            <div id="mobile-menu" class="fixed inset-x-0 top-20 z-40 hidden border-b border-gray-100 bg-white/95 shadow-soft-lg backdrop-blur-md md:hidden">
                <nav class="space-y-2 px-4 py-6 text-center text-sm font-medium">
                    <a href="<?php echo BASE_URL; ?>/admin/dashboard.php" class="block rounded-xl px-4 py-3 <?php echo ($currentPage == 'dashboard.php') ? 'bg-gray-50 text-prcnavy' : 'hover:bg-gray-50'; ?>"><i class="fa-solid fa-chart-line mr-2 <?php echo ($currentPage == 'dashboard.php') ? 'text-prcgold' : 'text-gray-400'; ?>"></i> Dashboard</a>
                    <a href="<?php echo BASE_URL; ?>/admin/records.php" class="block rounded-xl px-4 py-3 <?php echo ($currentPage == 'records.php') ? 'bg-gray-50 text-prcnavy' : 'hover:bg-gray-50'; ?>"><i class="fa-solid fa-layer-group mr-2 <?php echo ($currentPage == 'records.php') ? 'text-prcgold' : 'text-gray-400'; ?>"></i> Records</a>
                    <a href="<?php echo BASE_URL; ?>/admin/upload.php" class="block rounded-xl px-4 py-3 <?php echo ($currentPage == 'upload.php') ? 'bg-gray-50 text-prcnavy' : 'hover:bg-gray-50'; ?>"><i class="fa-solid fa-cloud-arrow-up mr-2 <?php echo ($currentPage == 'upload.php') ? 'text-prcgold' : 'text-gray-400'; ?>"></i> Upload</a>
                    <a href="<?php echo BASE_URL; ?>/admin/manual-entry.php" class="block rounded-xl px-4 py-3 <?php echo ($currentPage == 'manual-entry.php') ? 'bg-gray-50 text-prcnavy' : 'hover:bg-gray-50'; ?>"><i class="fa-solid fa-keyboard mr-2 <?php echo ($currentPage == 'manual-entry.php') ? 'text-prcgold' : 'text-gray-400'; ?>"></i> Manual Entry</a>
                    <a href="<?php echo BASE_URL; ?>/admin/migrate_exemptions.php" class="block rounded-xl px-4 py-3 <?php echo ($currentPage == 'migrate_exemptions.php') ? 'bg-gray-50 text-prcnavy' : 'hover:bg-gray-50'; ?>"><i class="fa-solid fa-shuffle mr-2 <?php echo ($currentPage == 'migrate_exemptions.php') ? 'text-prcgold' : 'text-gray-400'; ?>"></i> Exemption Migration</a>
                    <a href="<?php echo BASE_URL; ?>/logout.php" class="mt-4 block rounded-xl px-4 py-3 text-red-500 transition hover:bg-red-50"><i class="fa-solid fa-power-off mr-2"></i> Logout</a>
                </nav>
            </div>

            <main class="min-w-0 flex-1 p-6 lg:p-10">
