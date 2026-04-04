<?php
// 1. AKTIFKAN ERROR REPORTING
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. INCLUDE CONFIG & AUTH
require_once 'config/database.php';

// PENTING: Baris ini WAJIB ada agar header.php tidak error (isLoggedIn)
require_once 'includes/auth.php'; 

require_once 'includes/header.php'; 

// Cek koneksi database
if (!$conn) {
    die("<div style='color:red; text-align:center; padding:20px;'>Koneksi Database Gagal: " . mysqli_connect_error() . "</div>");
}

// 3. QUERY DATA
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

// Menggunakan ORDER BY c.id DESC agar lebih stabil
$query = "SELECT c.*, 
          (SELECT chapter_number FROM chapters WHERE comic_id = c.id ORDER BY id DESC LIMIT 1) as latest_chap 
          FROM comics c 
          ORDER BY c.id DESC 
          LIMIT $limit OFFSET $offset";

$result = mysqli_query($conn, $query);

// Cek Error Query
if (!$result) {
    echo "<div class='max-w-7xl mx-auto px-4 py-8 text-center'>";
    echo "<div class='bg-red-900 text-white p-4 rounded mb-4'>Error Database SQL: " . mysqli_error($conn) . "</div>";
    echo "</div>";
    require_once 'includes/footer.php';
    exit();
}

$total_q = mysqli_query($conn, "SELECT COUNT(*) as total FROM comics");
$total_data = ($total_q) ? mysqli_fetch_assoc($total_q)['total'] : 0;
$total_pages = ceil($total_data / $limit);
?>

<main class="flex-grow max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full min-h-screen">
    
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-xl md:text-2xl font-bold text-white border-l-4 border-indigo-500 pl-3">Update Terbaru</h2>
        <a href="search.php" class="text-sm text-indigo-400 hover:text-white transition">Lihat Semua <i class="fas fa-arrow-right ml-1"></i></a>
    </div>

    <?php if (mysqli_num_rows($result) > 0): ?>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4 md:gap-6">
            <?php while ($comic = mysqli_fetch_assoc($result)): ?>
                
                <?php 
                    // Logika Gambar (Lokal vs URL)
                    $coverUrl = $comic['cover_image'];
                    // Jika tidak mengandung http, anggap file lokal
                    if (strpos($coverUrl, 'http') === false) {
                        $coverUrl = "uploads/covers/" . $coverUrl;
                    }
                ?>

                <a href="comic.php?slug=<?= $comic['slug'] ?>" class="group block bg-gray-800 rounded-xl overflow-hidden hover:ring-2 hover:ring-indigo-500 transition relative shadow-lg hover:shadow-indigo-500/20 transform hover:-translate-y-1 duration-300">
                    
                    <div class="aspect-[3/4] w-full overflow-hidden bg-gray-700 relative">
                        <span class="absolute top-2 left-2 bg-black/70 backdrop-blur-md text-[10px] px-2 py-1 rounded text-white font-bold z-10 border border-white/10 uppercase tracking-wider">
                            <?= htmlspecialchars($comic['status']) ?>
                        </span>
                        
                        <img src="<?= $coverUrl ?>" 
                             alt="<?= htmlspecialchars($comic['title']) ?>" 
                             class="w-full h-full object-cover group-hover:scale-110 transition duration-500"
                             loading="lazy"
                             onerror="this.src='https://via.placeholder.com/300x450?text=No+Cover'">
                        
                        <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/20 to-transparent opacity-80"></div>

                        <div class="absolute bottom-0 left-0 right-0 p-3">
                            <span class="text-[10px] text-yellow-400 font-bold uppercase mb-1 block"><?= htmlspecialchars($comic['type']) ?></span>
                            <h3 class="text-white font-bold text-sm leading-tight line-clamp-2 group-hover:text-indigo-300 transition">
                                <?= htmlspecialchars($comic['title']) ?>
                            </h3>
                        </div>
                    </div>

                    <div class="p-3 bg-gray-800 border-t border-gray-700/50">
                        <div class="flex justify-between items-center text-xs">
                            <span class="text-gray-400">Chapter Baru</span>
                            <?php if ($comic['latest_chap']): ?>
                                <span class="bg-indigo-600 text-white px-2 py-0.5 rounded font-bold">Chapter <?= formatChapterNumber($comic['latest_chap']) ?></span>
                            <?php else: ?>
                                <span class="text-gray-600">-</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>

            <?php endwhile; ?>
        </div>

        <div class="mt-10 flex justify-center gap-2">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>" class="px-4 py-2 bg-gray-800 text-white rounded hover:bg-indigo-600 transition border border-gray-700">Prev</a>
            <?php endif; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?= $page + 1 ?>" class="px-4 py-2 bg-gray-800 text-white rounded hover:bg-indigo-600 transition border border-gray-700">Next</a>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <div class="text-center py-20 bg-gray-800/50 rounded-2xl border border-gray-800 border-dashed">
            <i class="fas fa-ghost text-4xl text-gray-600 mb-4"></i>
            <h3 class="text-xl font-medium text-white">Belum ada komik</h3>
            <p class="text-gray-400 mt-2">Admin belum mengupload komik apapun.</p>
        </div>
    <?php endif; ?>

</main>

<?php require_once 'includes/footer.php'; ?>