<?php
session_start();
require_once '../config/database.php';

// Cek Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$message = '';

// Proses Simpan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Simpan MangaDex & MAL (Kode Lama Tetap Ada)
    $md_client = mysqli_real_escape_string($conn, $_POST['mangadex_client_id']);
    $mal_client = mysqli_real_escape_string($conn, $_POST['mal_client_id']);

    // Update Mangadex Key
    mysqli_query($conn, "INSERT INTO settings (setting_key, setting_value) VALUES ('mangadex_client_id', '$md_client') ON DUPLICATE KEY UPDATE setting_value = '$md_client'");
    
    // Update MAL Key
    mysqli_query($conn, "INSERT INTO settings (setting_key, setting_value) VALUES ('mal_client_id', '$mal_client') ON DUPLICATE KEY UPDATE setting_value = '$mal_client'");

    // 2. Simpan ImgBB Keys (Kode Baru)
    // Ambil input textarea, pecah per baris menjadi array
    $raw_keys = explode("\n", $_POST['imgbb_api_keys']);
    // Bersihkan spasi dan hapus baris kosong
    $clean_keys = array_filter(array_map('trim', $raw_keys));
    // Ubah jadi JSON agar bisa disimpan di satu kolom database
    $json_keys = mysqli_real_escape_string($conn, json_encode(array_values($clean_keys)));

    mysqli_query($conn, "INSERT INTO settings (setting_key, setting_value) VALUES ('imgbb_api_keys', '$json_keys') ON DUPLICATE KEY UPDATE setting_value = '$json_keys'");

    $message = "<div class='bg-green-600 p-3 rounded mb-6 text-sm font-bold flex items-center'><i class='fas fa-check-circle mr-2'></i> Pengaturan berhasil disimpan!</div>";
}

// Ambil Data Saat Ini
function getSetting($conn, $key) {
    $q = mysqli_query($conn, "SELECT setting_value FROM settings WHERE setting_key = '$key'");
    $row = mysqli_fetch_assoc($q);
    return $row ? $row['setting_value'] : '';
}

$current_md = getSetting($conn, 'mangadex_client_id');
$current_mal = getSetting($conn, 'mal_client_id');

// Ambil Keys ImgBB dan decode kembali ke bentuk text (satu per baris) untuk ditampilkan di textarea
$current_imgbb_json = getSetting($conn, 'imgbb_api_keys');
$current_imgbb_array = json_decode($current_imgbb_json, true);
$current_imgbb_text = is_array($current_imgbb_array) ? implode("\n", $current_imgbb_array) : '';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan API - Readmanga</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-900 text-white font-sans antialiased">

    <div class="flex h-screen overflow-hidden">
        
        <div id="sidebarOverlay" onclick="toggleSidebar()" class="fixed inset-0 bg-black/50 z-20 hidden md:hidden backdrop-blur-sm transition-opacity"></div>

        <aside id="adminSidebar" class="fixed md:static inset-y-0 left-0 z-30 w-64 bg-gray-800 border-r border-gray-700 transform -translate-x-full md:translate-x-0 transition-transform duration-300 flex flex-col h-full shadow-2xl md:shadow-none">
            
            <div class="h-16 flex items-center justify-between px-6 border-b border-gray-700 bg-gray-800">
                <span class="text-xl font-bold text-indigo-500">Admin Panel</span>
                <button onclick="toggleSidebar()" class="md:hidden text-gray-400 hover:text-white">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <nav class="flex-1 px-4 py-4 space-y-2 overflow-y-auto">
                <a href="index.php" class="flex items-center px-4 py-3 text-gray-400 hover:bg-gray-700 hover:text-white rounded-md transition">
                    <i class="fas fa-book mr-3 w-5 text-center"></i> Daftar Komik
                </a>
                
                <a href="settings.php" class="flex items-center px-4 py-3 text-white bg-indigo-600 rounded-md shadow-lg shadow-indigo-500/20 transition">
                    <i class="fas fa-cogs mr-3 w-5 text-center"></i> Pengaturan API
                </a>

                <a href="../index.php" class="flex items-center px-4 py-3 text-gray-400 hover:bg-gray-700 hover:text-white rounded-md transition">
                    <i class="fas fa-home mr-3 w-5 text-center"></i> Lihat Website
                </a>
            </nav>

            <div class="p-4 border-t border-gray-700 bg-gray-800">
                <a href="../logout.php" class="flex items-center px-4 py-3 text-red-400 hover:bg-red-500/10 rounded-md transition">
                    <i class="fas fa-sign-out-alt mr-3 w-5 text-center"></i> Logout
                </a>
            </div>
        </aside>

        <div class="flex-1 flex flex-col overflow-hidden relative w-full">
            
            <header class="md:hidden flex items-center justify-between p-4 bg-gray-800 border-b border-gray-700 h-16 shrink-0">
                <div class="flex items-center gap-3">
                    <button onclick="toggleSidebar()" class="text-gray-300 hover:text-white focus:outline-none p-2 rounded hover:bg-gray-700">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <span class="font-bold text-lg">Pengaturan</span>
                </div>
                <div class="flex items-center gap-4">
                    <a href="../index.php" class="text-indigo-400 hover:text-white p-2">
                        <i class="fas fa-home text-lg"></i>
                    </a>
                    <a href="../logout.php" class="text-red-400 hover:text-red-300 p-2">
                        <i class="fas fa-sign-out-alt text-lg"></i>
                    </a>
                </div>
            </header>

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-900 p-4 md:p-8">
                
                <div class="max-w-3xl mx-auto">
                    <div class="flex justify-between items-center mb-8">
                        <div>
                            <h1 class="text-2xl font-bold text-white">Pengaturan Website</h1>
                            <p class="text-gray-400 text-sm mt-1">Kelola kunci API untuk integrasi eksternal.</p>
                        </div>
                    </div>

                    <?= $message ?>

                    <div class="bg-gray-800 rounded-xl p-6 md:p-8 border border-gray-700 shadow-xl">
                        <form method="POST" class="space-y-8">
                            
                            <div class="border-b border-gray-700 pb-6">
                                <div class="flex items-center gap-3 mb-4">
                                    <i class="fas fa-images text-indigo-400 text-2xl"></i>
                                    <h2 class="text-lg font-bold text-white">ImgBB Storage API</h2>
                                </div>
                                <p class="text-xs text-gray-400 mb-4 bg-gray-900 p-3 rounded border border-gray-700">
                                    <i class="fas fa-info-circle mr-1"></i> Masukkan API Key ImgBB di sini. <strong>Satu key per baris</strong> (Enter untuk baris baru). Sistem akan menggunakannya secara acak saat upload gambar.
                                </p>
                                <div>
                                    <label class="block text-sm font-bold text-gray-300 mb-2">Daftar API Key</label>
                                    <textarea name="imgbb_api_keys" rows="5" 
                                           class="w-full bg-gray-900 border border-gray-600 rounded-lg px-4 py-3 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none font-mono text-sm text-indigo-300 transition"
                                           placeholder="Contoh Key 1&#10;Contoh Key 2&#10;Contoh Key 3..."><?= htmlspecialchars($current_imgbb_text) ?></textarea>
                                </div>
                            </div>

                            <div class="border-b border-gray-700 pb-6">
                                <div class="flex items-center gap-3 mb-4">
                                    <i class="fas fa-book-open text-orange-400 text-2xl"></i>
                                    <h2 class="text-lg font-bold text-white">MangaDex API</h2>
                                </div>
                                <p class="text-xs text-gray-400 mb-4 bg-gray-900 p-3 rounded border border-gray-700">
                                    <i class="fas fa-info-circle mr-1"></i> Opsional. Digunakan untuk menembus rate-limit jika traffic tinggi. Biarkan kosong jika tidak punya.
                                </p>
                                <div>
                                    <label class="block text-sm font-bold text-gray-300 mb-2">Personal Client ID / Key</label>
                                    <input type="text" name="mangadex_client_id" value="<?= htmlspecialchars($current_md) ?>" 
                                           class="w-full bg-gray-900 border border-gray-600 rounded-lg px-4 py-3 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none font-mono text-sm text-indigo-300 transition"
                                           placeholder="Contoh: personal-client-xxxx-xxxx...">
                                </div>
                            </div>

                            <div class="border-b border-gray-700 pb-6">
                                <div class="flex items-center gap-3 mb-4">
                                    <i class="fas fa-list-alt text-blue-400 text-2xl"></i>
                                    <h2 class="text-lg font-bold text-white">MyAnimeList API</h2>
                                </div>
                                <p class="text-xs text-gray-400 mb-4 bg-gray-900 p-3 rounded border border-gray-700">
                                    <i class="fas fa-info-circle mr-1"></i> Jika menggunakan Jikan API (Gratis), field ini tidak wajib diisi. Hanya isi jika menggunakan API Resmi MAL.
                                </p>
                                <div>
                                    <label class="block text-sm font-bold text-gray-300 mb-2">Client ID</label>
                                    <input type="text" name="mal_client_id" value="<?= htmlspecialchars($current_mal) ?>" 
                                           class="w-full bg-gray-900 border border-gray-600 rounded-lg px-4 py-3 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none font-mono text-sm text-indigo-300 transition"
                                           placeholder="Masukkan Client ID MAL...">
                                </div>
                            </div>

                            <div class="flex justify-end pt-2">
                                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-8 rounded-lg shadow-lg hover:shadow-indigo-500/30 transition transform hover:-translate-y-1">
                                    <i class="fas fa-save mr-2"></i> Simpan Pengaturan
                                </button>
                            </div>

                        </form>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('adminSidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            if (sidebar.classList.contains('-translate-x-full')) {
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('hidden');
            } else {
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('hidden');
            }
        }
    </script>
</body>
</html>