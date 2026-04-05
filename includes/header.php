<?php 
require_once __DIR__ . '/config.php'; 
require_once __DIR__ . '/functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($pageTitle ?? APP_NAME); ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>/assets/img/prclogo.png">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <!-- Tailwind CSS (compiled) -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/app.css">
</head>
<body class="bg-prclight text-gray-800 font-sans min-h-screen flex flex-col antialiased selection:bg-prcgold selection:text-white">

    <!-- Minimalist Premium Header -->
    <header class="bg-white border-b border-gray-100 sticky top-0 z-50 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-20 items-center">
                <div class="flex-shrink-0 flex items-center group">
                    <img src="<?php echo BASE_URL; ?>/assets/img/prclogo.png" alt="PRC Logo" class="w-10 h-10 object-contain mr-4 group-hover:scale-105 transition-transform duration-300 drop-shadow-sm">
                    <a href="<?php echo BASE_URL; ?>/index.php" class="text-xl font-bold tracking-tight text-prcnavy group-hover:text-prcaccent transition-colors">
                        PRC <span class="font-normal text-gray-400 mx-1">|</span> COPC Matrix
                    </a>
                </div>
                <div class="flex items-center space-x-6">
                    <a href="<?php echo BASE_URL; ?>/index.php" class="text-gray-500 hover:text-prcgold font-medium transition-colors text-sm flex items-center">
                        <i class="fa-solid fa-magnifying-glass mr-2"></i> Search
                    </a>
                    
                    <a href="<?php echo BASE_URL; ?>/login.php" class="bg-prcnavy hover:bg-prcaccent text-white px-5 py-2.5 rounded-xl text-sm font-medium shadow-md hover:shadow-lg transition-all transform hover:-translate-y-0.5 flex items-center">
                        <i class="fa-solid fa-user-shield mr-2 text-prcgold"></i> Admin Sign In
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main class="flex-grow w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
