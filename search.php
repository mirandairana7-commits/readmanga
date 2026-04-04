<?php
// 1. AKTIFKAN DEBUGGING
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/database.php';

// PENTING: Jangan dikomentari agar header.php tidak error!
require_once 'includes/auth.php'; 

// 2. AMBIL PARAMETER PENCARIAN
$keyword = isset($_GET['q']) ? $_GET['q'] : ''; 
$author  = isset($_GET['author']) ? $_GET['author'] : '';
$year    = isset($_GET['year']) ? $_GET['year'] : '';
$status  = isset($_GET['status']) ? $_GET['status'] : '';
$type    = isset($_GET['type']) ? $_GET['type'] : '';
$genre   = isset($_GET['genre']) ? $_GET['genre'] : '';

// Cek apakah sedang memfilter
$is_filtering = !empty($author) || !empty($year) || !empty($status) || !empty($type) || !empty($genre);
$show_filter_panel = (isset($_GET['filter']) && $_GET['filter'] == 'open') || $is_filtering;

$result = null;

// 3. LOGIKA QUERY
if (!empty($keyword) || $is_filtering) {
    $conditions = [];
    
    // Escape string
    $safe_keyword = mysqli_real_escape_string($conn, $keyword);
    $safe_author  = mysqli_real_escape_string($conn, $author);
    $safe_year    = mysqli_real_escape_string($conn, $year);
    $safe_status  = mysqli_real_escape_string($conn, $status);
    $safe_type    = mysqli_real_escape_string($conn, $type);
    $safe_genre   = mysqli_real_escape_string($conn, $genre);
    
    // Pencarian Keyword
    if (!empty($safe_keyword)) {
        $conditions[] = "(title LIKE '%$safe_keyword%' 
                          OR alternative_titles LIKE '%$safe_keyword%' 
                          OR description LIKE '%$safe_keyword%'
                          OR genres LIKE '%$safe_keyword%'
                          OR author LIKE '%$safe_keyword%')";
    }
    
    // Filter Spesifik
    if (!empty($safe_author)) $conditions[] = "(author LIKE '%$safe_author%' OR artist LIKE '%$safe_author%')";
    if (!empty($safe_year))   $conditions[] = "release_year = '$safe_year'";
    if (!empty($safe_status)) $conditions[] = "status = '$safe_status'";
    if (!empty($safe_type))   $conditions[] = "type = '$safe_type'";
    if (!empty($safe_genre))  $conditions[] = "genres LIKE '%$safe_genre%'";

    $sql = "SELECT * FROM comics";
    if (count($conditions) > 0) {
        $sql .= " WHERE " . implode(' AND ', $conditions);
    }
    // Urutkan berdasarkan ID DESC (Paling Aman)
    $sql .= " ORDER BY id DESC"; 
    
    $result = mysqli_query($conn, $sql);

    // Cek Error Query
    if (!$result) {
        die("<div class='text-white p-4 text-center'>Error SQL: " . mysqli_error($conn) . "</div>");
    }
}

require_once 'includes/header.php';
?>

<main class="flex-grow max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 w-full mt-16 min-h-screen">

    <div class="max-w-4xl mx-auto mb-10">
        <h1 class="text-3xl font-bold mb-6 text-center text-white">Cari Manga Favoritmu</h1>

        <div class="relative w-full z-10" id="mainSearchContainer">
            <form action="search.php" method="GET" class="relative group">
                
                <input type="text" name="q" value="<?= htmlspecialchars($keyword) ?>" 
                       placeholder="Ketik judul, author, atau genre..." 
                       class="w-full bg-gray-800 border-2 border-gray-700 group-hover:border-indigo-500 text-white px-6 py-4 rounded-full focus:outline-none focus:border-indigo-500 text-lg transition-all shadow-xl pl-14"
                       autocomplete="off" autofocus>

                <i class="fas fa-search absolute left-5 top-1/2 transform -translate-y-1/2 text-gray-500 text-xl group-hover:text-indigo-400 transition-colors"></i>

                <button type="submit" class="absolute right-2 top-2 bottom-2 bg-indigo-600 hover:bg-indigo-700 text-white px-6 rounded-full font-bold transition-all shadow-lg">
                    Cari
                </button>
            </form>
        </div>

        <div class="flex justify-center mt-6 mb-4">
            <button type="button" onclick="toggleFilter()" 
                    class="text-sm text-gray-300 hover:text-white flex items-center gap-2 transition px-4 py-2 rounded-lg bg-gray-800 border border-gray-700 hover:border-indigo-500 shadow-md">
                <i class="fas fa-sliders-h"></i> 
                <span id="filterText"><?= $show_filter_panel ? 'Sembunyikan Filter' : 'Filter Lanjutan' ?></span>
            </button>
        </div>

        <div id="filterPanel" class="<?= $show_filter_panel ? '' : 'hidden' ?> bg-gray-800 p-6 rounded-xl border border-gray-700 shadow-2xl relative z-0 transition-all duration-300">
            <form action="search.php" method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <input type="hidden" name="q" value="<?= htmlspecialchars($keyword) ?>">
                <input type="hidden" name="filter" value="open">

                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Mangaka</label>
                    <input type="text" name="author" value="<?= htmlspecialchars($author) ?>" placeholder="Nama..." class="w-full bg-gray-900 border border-gray-600 rounded px-3 py-2 text-sm focus:border-indigo-500 outline-none text-white">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Genre</label>
                    <input type="text" name="genre" value="<?= htmlspecialchars($genre) ?>" placeholder="Action, Isekai..." class="w-full bg-gray-900 border border-gray-600 rounded px-3 py-2 text-sm focus:border-indigo-500 outline-none text-white">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Status</label>
                    <select name="status" class="w-full bg-gray-900 border border-gray-600 rounded px-3 py-2 text-sm focus:border-indigo-500 outline-none text-white">
                        <option value="">Semua Status</option>
                        <option value="Ongoing" <?= $status == 'Ongoing' ? 'selected' : '' ?>>Ongoing</option>
                        <option value="Completed" <?= $status == 'Completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="Hiatus" <?= $status == 'Hiatus' ? 'selected' : '' ?>>Hiatus</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Tipe</label>
                    <select name="type" class="w-full bg-gray-900 border border-gray-600 rounded px-3 py-2 text-sm focus:border-indigo-500 outline-none text-white">
                        <option value="">Semua Tipe</option>
                        <option value="Manga" <?= $type == 'Manga' ? 'selected' : '' ?>>Manga (Jepang)</option>
                        <option value="Manhwa" <?= $type == 'Manhwa' ? 'selected' : '' ?>>Manhwa (Korea)</option>
                        <option value="Manhua" <?= $type == 'Manhua' ? 'selected' : '' ?>>Manhua (China)</option>
                    </select>
                </div>

                <div class="lg:col-span-4 flex justify-end gap-2 mt-2">
                    <a href="search.php" class="px-4 py-2 text-sm text-red-400 hover:bg-gray-700 rounded transition">Reset</a>
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded text-sm font-bold transition shadow-lg">Terapkan Filter</button>
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($keyword) || $is_filtering): ?>
        <div class="mb-6 border-b border-gray-800 pb-2">
            <h2 class="text-xl font-bold text-white">Hasil Pencarian</h2>
            <div class="flex flex-wrap gap-2 mt-2">
                <?php if($keyword): ?><span class="badge">Keyword: <?= htmlspecialchars($keyword) ?></span><?php endif; ?>
                <?php if($status): ?><span class="badge">Status: <?= htmlspecialchars($status) ?></span><?php endif; ?>
                <?php if($type): ?><span class="badge">Type: <?= htmlspecialchars($type) ?></span><?php endif; ?>
                <?php if($genre): ?><span class="badge">Genre: <?= htmlspecialchars($genre) ?></span><?php endif; ?>
                <?php if($author): ?><span class="badge">Author: <?= htmlspecialchars($author) ?></span><?php endif; ?>
            </div>
        </div>

        <?php if ($result && mysqli_num_rows($result) > 0): ?>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-6">
                <?php while ($comic = mysqli_fetch_assoc($result)): ?>
                    
                    <?php 
                        // LOGIKA GAMBAR
                        $coverUrl = $comic['cover_image'];
                        if (strpos($coverUrl, 'http') !== 0) {
                            $coverUrl = "uploads/covers/" . $coverUrl;
                        }
                    ?>

                    <a href="comic.php?slug=<?= $comic['slug'] ?>" class="group block bg-gray-800 rounded-xl overflow-hidden hover:ring-2 hover:ring-indigo-500 transition-all duration-300 relative hover:-translate-y-1 shadow-lg h-full flex flex-col">
                        
                        <div class="aspect-[2/3] w-full overflow-hidden bg-gray-700 relative">
                            <span class="absolute top-2 right-2 bg-indigo-600 text-[10px] font-bold text-white px-1.5 py-0.5 rounded shadow z-20">
                                <?= $comic['type'] ?? 'Manga' ?>
                            </span>
                            
                            <span class="absolute top-2 left-2 bg-black/70 backdrop-blur-md text-[10px] uppercase tracking-wider px-2 py-1 rounded text-white font-bold z-10">
                                <?= $comic['status'] ?>
                            </span>

                            <img src="<?= $coverUrl ?>" 
                                    alt="<?= htmlspecialchars($comic['title']) ?>" 
                                    class="w-full h-full object-cover group-hover:scale-110 transition duration-500"
                                    onerror="this.src='https://via.placeholder.com/300x450?text=No+Cover'">
                        </div>

                        <div class="p-4 flex-grow flex flex-col justify-between">
                            <div>
                                <h3 class="text-white font-bold text-sm leading-tight group-hover:text-indigo-400 transition-colors line-clamp-2 mb-1">
                                    <?= htmlspecialchars($comic['title']) ?>
                                </h3>
                                
                                <?php if ($comic['score'] > 0): ?>
                                    <div class="text-xs text-gray-500 mb-2">
                                        <i class="fas fa-star text-yellow-500 mr-1"></i><?= $comic['score'] ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if(!empty($comic['genres'])): ?>
                                <div class="text-[10px] text-gray-400 line-clamp-1">
                                    <?= htmlspecialchars($comic['genres']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-16 bg-gray-800/30 rounded-2xl border border-gray-800 border-dashed">
                <i class="fas fa-search text-5xl text-gray-600 mb-4"></i>
                <h3 class="text-xl font-bold text-white">Tidak Ditemukan</h3>
                <p class="text-gray-400 mt-2">Coba kurangi filter atau gunakan kata kunci lain.</p>
                <a href="search.php" class="inline-block mt-4 text-indigo-400 hover:text-white underline">Reset Filter</a>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="text-center text-gray-500 mt-12">
            <i class="fas fa-filter text-4xl mb-4 opacity-30"></i>
            <p>Gunakan pencarian di atas untuk menemukan manga.</p>
        </div>
    <?php endif; ?>

</main>

<style>
    .badge {
        font-size: 0.75rem;
        background-color: #374151;
        color: #d1d5db;
        padding: 0.1rem 0.5rem;
        border-radius: 0.25rem;
        border: 1px solid #4b5563;
    }
</style>

<script>
    function toggleFilter() {
        const panel = document.getElementById('filterPanel');
        const text = document.getElementById('filterText');
        if (panel.classList.contains('hidden')) {
            panel.classList.remove('hidden');
            text.innerText = 'Sembunyikan Filter';
        } else {
            panel.classList.add('hidden');
            text.innerText = 'Filter Lanjutan';
        }
    }
</script>

<?php require_once 'includes/footer.php'; ?>