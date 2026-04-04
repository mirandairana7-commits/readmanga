<?php
session_start();
require_once '../config/database.php';

// Cek Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$query = "SELECT * FROM comics ORDER BY created_at DESC";
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Readmanga</title>
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
                <a href="index.php" class="flex items-center px-4 py-3 text-white bg-indigo-600 rounded-md shadow-lg shadow-indigo-500/20 transition">
                    <i class="fas fa-book mr-3 w-5 text-center"></i> Daftar Komik
                </a>
                
                <a href="settings.php" class="flex items-center px-4 py-3 text-gray-400 hover:bg-gray-700 hover:text-white rounded-md transition">
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
                    <span class="font-bold text-lg">Dashboard</span>
                </div>

                <div class="flex items-center gap-4">
                    <a href="../index.php" class="text-indigo-400 hover:text-white p-2" title="Lihat Website">
                        <i class="fas fa-home text-lg"></i>
                    </a>
                    <a href="../logout.php" class="text-red-400 hover:text-red-300 p-2">
                        <i class="fas fa-sign-out-alt text-lg"></i>
                    </a>
                </div>
            </header>

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-900 p-4 md:p-6">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                    <div>
                        <h1 class="text-2xl font-bold hidden md:block">Manajemen Komik</h1>
                        <p class="text-gray-400 text-sm hidden md:block">Kelola semua komik yang ada di Readmanga.</p>
                    </div>
                    <a href="add_comic.php" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg shadow-lg flex items-center gap-2 transition w-full md:w-auto justify-center">
                        <i class="fas fa-plus"></i> <span class="font-bold">Tambah Komik</span>
                    </a>
                </div>

                <div class="bg-gray-800 rounded-xl shadow-xl overflow-hidden border border-gray-700">
                    <div class="overflow-x-auto">
                        <table class="w-full whitespace-nowrap">
                            <thead class="bg-gray-700/50">
                                <tr class="text-left text-xs font-semibold tracking-wide text-gray-400 uppercase border-b border-gray-700">
                                    <th class="px-4 py-3">Cover</th>
                                    <th class="px-4 py-3">Judul Komik</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-700">
                                <?php if (mysqli_num_rows($result) > 0): ?>
                                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                        
                                        <?php 
                                            $coverUrl = $row['cover_image'];
                                            if (strpos($coverUrl, 'http') !== 0) {
                                                $coverUrl = "../uploads/covers/" . $coverUrl;
                                            }
                                        ?>

                                        <tr class="text-gray-300 hover:bg-gray-700/30 transition group">
                                            <td class="px-4 py-3 w-16">
                                                <div class="w-10 h-14 bg-gray-600 rounded overflow-hidden shadow-sm relative">
                                                    <img src="<?= $coverUrl ?>" alt="Cover" class="w-full h-full object-cover">
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 font-medium text-white">
                                                <a href="../comic.php?slug=<?= $row['slug'] ?>" class="hover:text-indigo-400 transition block truncate max-w-[150px] md:max-w-xs" target="_blank">
                                                    <?= htmlspecialchars($row['title']) ?>
                                                </a>
                                                <div class="text-[10px] text-gray-500 mt-0.5 font-mono">/<?= substr($row['slug'], 0, 20) ?>...</div>
                                            </td>
                                            <td class="px-4 py-3">
                                                <span class="px-2 py-1 text-[10px] uppercase font-bold rounded-full 
                                                    <?= $row['status'] == 'Ongoing' ? 'bg-green-500/10 text-green-500 border border-green-500/20' : 
                                                       ($row['status'] == 'Completed' ? 'bg-blue-500/10 text-blue-500 border border-blue-500/20' : 'bg-yellow-500/10 text-yellow-500 border border-yellow-500/20') ?>">
                                                    <?= $row['status'] ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-right">
                                                <div class="flex items-center justify-end gap-2">
                                                    
                                                    <a href="add_chapter.php?slug=<?= $row['slug'] ?>" class="bg-gray-700 hover:bg-indigo-600 text-white w-8 h-8 flex items-center justify-center rounded transition" title="Upload Chapter">
                                                        <i class="fas fa-upload text-xs"></i>
                                                    </a>
                                                    
                                                    <a href="edit_comic.php?id=<?= $row['id'] ?>" class="bg-gray-700 hover:bg-blue-600 text-white w-8 h-8 flex items-center justify-center rounded transition" title="Edit Komik">
                                                        <i class="fas fa-edit text-xs"></i>
                                                    </a>

                                                    <a href="delete_comic.php?id=<?= $row['id'] ?>" 
                                                       onclick="return confirm('Yakin ingin menghapus <?= htmlspecialchars($row['title']) ?>? Semua chapter dan gambar akan ikut terhapus permanen!');"
                                                       class="bg-gray-700 hover:bg-red-600 text-white w-8 h-8 flex items-center justify-center rounded transition" title="Hapus Komik">
                                                        <i class="fas fa-trash text-xs"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="px-4 py-12 text-center text-gray-500">
                                            <i class="fas fa-box-open text-4xl mb-3 opacity-50"></i>
                                            <p>Belum ada komik. Yuk tambah baru!</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
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
                // Buka Sidebar
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('hidden');
            } else {
                // Tutup Sidebar
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('hidden');
            }
        }
    </script>
</body>
</html>