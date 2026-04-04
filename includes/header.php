<?php
// Pastikan konfigurasi database dan base_url termuat
if (!isset($base_url)) {
    // Coba load dari path relative standard
    $config_path = __DIR__ . '/../config/database.php';
    if (file_exists($config_path)) {
        require_once $config_path;
    } else {
        // Fallback jika path beda
        $base_url = 'http://' . $_SERVER['HTTP_HOST']; 
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <base href="<?= $base_url ?>"> 
    
    <title>Readmanga - Baca Manga Online</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #1f2937; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #4f46e5; border-radius: 10px; }
    </style>
</head>
<body class="bg-[#0a0a0a] text-gray-200 font-sans antialiased min-h-screen flex flex-col">

<nav id="mainNav" class="fixed w-full top-0 z-50 bg-gray-900/95 backdrop-blur-md border-b border-gray-800 transition-transform duration-300">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            
            <div class="flex-shrink-0 flex items-center gap-2">
                <a href="index.php" class="text-2xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-indigo-400 to-cyan-400 hover:opacity-80 transition">
                    Readmanga
                </a>
            </div>

            <div class="hidden md:flex items-center space-x-4">
                
                <div class="relative w-64 group" id="desktopSearchContainer">
                    <form action="search.php" method="GET" class="relative">
                        <input type="text" name="q" placeholder="Cari komik..." autocomplete="off"
                            class="w-full bg-gray-800 text-sm text-gray-200 rounded-full pl-4 pr-10 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500 border border-gray-700 transition-all"
                            onkeyup="doLiveSearch(this.value, 'desktopSearchResults')">
                        <button type="submit" class="absolute right-0 top-0 mt-2 mr-3 text-gray-400 hover:text-white">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                    <div id="desktopSearchResults" class="hidden absolute top-full left-0 right-0 mt-2 bg-gray-800 rounded-xl shadow-2xl border border-gray-700 overflow-hidden z-50 w-full max-h-96 overflow-y-auto custom-scrollbar"></div>
                </div>

                <a href="search.php?filter=open" class="text-gray-300 hover:text-white px-3 py-2 rounded-md transition" title="Filter"><i class="fas fa-sliders-h"></i></a>

                <?php if (isLoggedIn()): ?>
                    <div class="flex items-center gap-3 border-l border-gray-700 pl-4">
                        <span class="text-sm font-bold text-white"><?= htmlspecialchars($_SESSION['username']) ?></span>
                        <?php if (isAdmin()): ?>
                            <a href="admin/index.php" class="text-indigo-400 hover:text-indigo-300" title="Admin Panel"><i class="fas fa-cogs"></i></a>
                        <?php endif; ?>
                        <a href="logout.php" class="text-red-400 hover:text-red-300" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
                    </div>
                <?php else: ?>
                    <a href="login.php" class="text-gray-300 hover:text-white font-medium">Masuk</a>
                    <a href="register.php" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md font-bold text-sm shadow-lg shadow-indigo-500/20">Daftar</a>
                <?php endif; ?>
            </div>

            <div class="md:hidden flex items-center gap-2">
                <button onclick="toggleMobileSearch()" class="text-gray-300 hover:text-white p-2 rounded-full hover:bg-gray-800 focus:outline-none transition">
                    <i class="fas fa-search text-xl"></i>
                </button>

                <button onclick="toggleMobileMenu()" class="text-gray-300 hover:text-white p-2 rounded-full hover:bg-gray-800 focus:outline-none transition">
                    <i class="fas fa-bars text-xl"></i>
                </button>
            </div>
        </div>
    </div>

    <div id="mobileSearchBar" class="hidden md:hidden bg-gray-800 border-b border-gray-700 p-4 absolute w-full top-16 left-0 shadow-xl transition-all duration-300 z-40">
        <form action="search.php" method="GET" class="relative">
            <input type="text" name="q" id="mobileSearchInput" placeholder="Ketik judul komik..." autocomplete="off"
                   class="w-full bg-gray-900 text-white rounded-lg pl-4 pr-10 py-3 border border-gray-600 focus:border-indigo-500 outline-none"
                   onkeyup="doLiveSearch(this.value, 'mobileSearchResults')">
            <button type="submit" class="absolute right-3 top-3 text-gray-400"><i class="fas fa-search"></i></button>
        </form>
        <div id="mobileSearchResults" class="hidden mt-2 bg-gray-900 rounded-lg border border-gray-700 overflow-hidden w-full max-h-60 overflow-y-auto custom-scrollbar"></div>
    </div>
</nav>

<div id="mobileMenuOverlay" onclick="toggleMobileMenu()" class="fixed inset-0 bg-black/50 z-[60] hidden backdrop-blur-sm transition-opacity"></div>
<div id="mobileMenuSidebar" class="fixed top-0 right-0 h-full w-64 bg-gray-900 border-l border-gray-800 z-[70] transform translate-x-full transition-transform duration-300 shadow-2xl">
    <div class="p-5 flex flex-col h-full">
        <div class="flex justify-between items-center mb-6 border-b border-gray-800 pb-4">
            <span class="text-xl font-bold text-white">Menu</span>
            <button onclick="toggleMobileMenu()" class="text-gray-400 hover:text-white">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <div class="flex flex-col space-y-4">
            <a href="index.php" class="flex items-center text-gray-300 hover:text-indigo-400 transition"><i class="fas fa-home w-6 mr-2"></i> Beranda</a>
            <a href="search.php?filter=open" class="flex items-center text-gray-300 hover:text-indigo-400 transition"><i class="fas fa-sliders-h w-6 mr-2"></i> Filter Pencarian</a>
            
            <div class="border-t border-gray-800 my-2 pt-2"></div>

            <?php if (isLoggedIn()): ?>
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-8 h-8 rounded-full bg-indigo-600 flex items-center justify-center text-white font-bold text-sm">
                        <?= strtoupper(substr($_SESSION['username'], 0, 1)) ?>
                    </div>
                    <div>
                        <p class="text-white font-bold text-sm"><?= htmlspecialchars($_SESSION['username']) ?></p>
                        <p class="text-xs text-green-400">Online</p>
                    </div>
                </div>
                <?php if (isAdmin()): ?>
                    <a href="admin/index.php" class="flex items-center text-indigo-400 hover:text-white transition"><i class="fas fa-cogs w-6 mr-2"></i> Admin Panel</a>
                <?php endif; ?>
                <a href="logout.php" class="flex items-center text-red-400 hover:text-red-300 transition"><i class="fas fa-sign-out-alt w-6 mr-2"></i> Logout</a>
            <?php else: ?>
                <a href="login.php" class="block w-full text-center py-2 border border-gray-600 rounded-lg text-white hover:bg-gray-800">Masuk</a>
                <a href="register.php" class="block w-full text-center py-2 bg-indigo-600 rounded-lg text-white hover:bg-indigo-700 font-bold">Daftar</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="h-16"></div>