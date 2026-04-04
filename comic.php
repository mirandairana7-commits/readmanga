<?php
require_once 'config/database.php';
require_once 'includes/auth.php'; // Opsional

// Ambil Slug DULU sebelum memanggil header
$slug = isset($_GET['slug']) ? mysqli_real_escape_string($conn, $_GET['slug']) : '';
$query = "SELECT * FROM comics WHERE slug = '$slug' LIMIT 1";
$result = mysqli_query($conn, $query);
$comic = mysqli_fetch_assoc($result);

if (!$comic) {
    require_once 'includes/header.php';
    echo "<div class='text-white text-center py-20'>Komik tidak ditemukan.</div>";
    require_once 'includes/footer.php';
    exit();
}

// Logika Path Cover untuk Thumbnail WhatsApp
$coverUrl = $comic['cover_image'];
if (strpos($coverUrl, 'http') !== 0) {
    $coverUrl = $base_url . "/uploads/covers/" . $coverUrl;
}

// Set Variabel untuk OG Meta Tags (Dibaca oleh header.php)
$og_title = htmlspecialchars($comic['title']) . " - Readmanga";
$og_desc = htmlspecialchars(substr($comic['description'], 0, 150)) . "..."; 
$og_image = $coverUrl;
$og_url = $base_url . "/komik/" . $comic['slug'];

// BARU PANGGIL HEADER DI SINI
require_once 'includes/header.php';

$chapter_query = "SELECT * FROM chapters WHERE comic_id = {$comic['id']} ORDER BY chapter_number DESC";
$chapters = mysqli_query($conn, $chapter_query);
$total_chapters = mysqli_num_rows($chapters);

function getStatusColor($status) {
    if ($status == 'Ongoing') return 'text-green-400 bg-green-400/10 border-green-400/20';
    if ($status == 'Completed') return 'text-blue-400 bg-blue-400/10 border-blue-400/20';
    return 'text-yellow-400 bg-yellow-400/10 border-yellow-400/20';
}

// Logika Path Cover (Lokal vs Eksternal)
$coverUrl = $comic['cover_image'];
if (strpos($coverUrl, 'http') !== 0) {
    $coverUrl = "uploads/covers/" . $coverUrl;
}
?>

<div class="relative w-full h-[300px] md:h-[500px] overflow-hidden -mt-16 z-0">
    <img src="<?= $coverUrl ?>" class="w-full h-full object-cover blur-2xl opacity-40 scale-110">
    <div class="absolute inset-0 bg-gradient-to-t from-[#0a0a0a] via-[#0a0a0a]/60 to-transparent"></div>
</div>

<main class="flex-grow max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 -mt-[220px] md:-mt-[400px] relative z-10 w-full mb-20">
    <div class="flex flex-col md:flex-row gap-8 items-start">
        
        <div class="w-full md:w-[280px] flex-shrink-0">
            <div class="sticky top-24">
                <div class="rounded-xl overflow-hidden shadow-2xl shadow-black border border-gray-700 aspect-[2/3] mb-6 relative group bg-gray-800">
                    <img src="<?= $coverUrl ?>" 
                            alt="<?= htmlspecialchars($comic['title']) ?>" 
                            class="w-full h-full object-cover transition transform group-hover:scale-105 duration-500">
                </div>

                <?php if (isAdmin()): ?>
                    <div class="grid grid-cols-2 gap-2 mb-4">
                        <a href="admin/add_chapter.php?slug=<?= $comic['slug'] ?>" class="bg-indigo-600 hover:bg-indigo-700 text-white py-2 rounded-lg text-center font-bold shadow-lg transition text-xs flex items-center justify-center gap-1">
                            <i class="fas fa-plus"></i> Add Vol.
                        </a>
                        <a href="admin/edit_comic.php?id=<?= $comic['id'] ?>" class="bg-gray-700 hover:bg-gray-600 text-white py-2 rounded-lg text-center font-bold transition text-xs flex items-center justify-center gap-1">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                    </div>
                <?php endif; ?>

                <div class="md:hidden mb-6 text-center">
                    <h1 class="text-2xl font-black text-white mb-2 leading-tight tracking-tight drop-shadow-2xl shadow-black">
                        <?= htmlspecialchars($comic['title']) ?>
                    </h1>
                    <?php if($comic['alternative_titles']): ?>
                        <p class="text-gray-300 text-xs opacity-90 line-clamp-2 mb-3 font-medium drop-shadow-md">
                            <?= htmlspecialchars($comic['alternative_titles']) ?>
                        </p>
                    <?php endif; ?>
                    
                    <?php if ($comic['genres']): ?>
                        <div class="flex flex-wrap justify-center gap-2 mt-3">
                            <?php foreach(explode(',', $comic['genres']) as $genre): ?>
                                <a href="search.php?q=<?= urlencode(trim($genre)) ?>" class="px-2 py-1 bg-black/60 backdrop-blur hover:bg-indigo-600 text-white rounded text-[10px] font-bold border border-white/10 shadow-sm">
                                    <?= trim($genre) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="bg-gray-900/95 backdrop-blur-md rounded-xl border border-gray-800 p-5 shadow-xl grid grid-cols-2 gap-4">
                    <div>
                        <span class="text-[10px] text-gray-500 uppercase font-bold tracking-wider block mb-1">Format</span>
                        <div class="text-white font-medium text-sm"><?= $comic['type'] ?? 'Manga' ?></div>
                    </div>
                    <div>
                        <span class="text-[10px] text-gray-500 uppercase font-bold tracking-wider block mb-1">Status</span>
                        <div class="flex">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold border <?= getStatusColor($comic['status']) ?>">
                                <?= $comic['status'] ?>
                            </span>
                        </div>
                    </div>
                    <div>
                        <span class="text-[10px] text-gray-500 uppercase font-bold tracking-wider block mb-1">Tahun Rilis</span>
                        <div class="text-white font-medium text-sm"><?= $comic['release_year'] ?? '-' ?></div>
                    </div>
                    <div>
                        <span class="text-[10px] text-gray-500 uppercase font-bold tracking-wider block mb-1">Author</span>
                        <div class="text-indigo-400 text-sm font-medium truncate" title="<?= htmlspecialchars($comic['author']) ?>">
                            <?= htmlspecialchars($comic['author'] ?? '-') ?>
                        </div>
                    </div>
                    
                    <div>
                        <span class="text-[10px] text-gray-500 uppercase font-bold tracking-wider block mb-1">Total Volume</span>
                        <div class="text-white font-bold text-sm"><?= $total_chapters ?> Chapter</div>
                    </div>

                    <?php if (!empty($comic['external_link'])): ?>
                    <div>
                        <span class="text-[10px] text-gray-500 uppercase font-bold tracking-wider block mb-1">Sumber</span>
                        <a href="<?= $comic['external_link'] ?>" target="_blank" rel="nofollow" class="text-indigo-400 hover:text-white font-bold text-sm flex items-center gap-1 transition truncate">
                            <i class="fas fa-external-link-alt text-[10px]"></i> <?= !empty($comic['link_label']) ? htmlspecialchars($comic['link_label']) : 'Official' ?>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="w-full flex-grow pt-0 md:pt-10"> 
            
            <div class="hidden md:flex flex-col justify-start mb-8"> 
                <h1 class="text-4xl lg:text-5xl font-black text-white mb-3 leading-tight tracking-tight drop-shadow-2xl shadow-black">
                    <?= htmlspecialchars($comic['title']) ?>
                </h1>
                <?php if($comic['alternative_titles']): ?>
                    <p class="text-gray-300 text-base opacity-90 line-clamp-2 mb-4 font-medium drop-shadow-md">
                        <?= htmlspecialchars($comic['alternative_titles']) ?>
                    </p>
                <?php endif; ?>
                
                <?php if ($comic['genres']): ?>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach(explode(',', $comic['genres']) as $genre): ?>
                            <a href="search.php?q=<?= urlencode(trim($genre)) ?>" class="px-3 py-1.5 bg-black/60 backdrop-blur hover:bg-indigo-600 hover:text-white rounded text-xs text-gray-200 font-bold transition cursor-pointer border border-white/10 shadow-sm">
                                <?= trim($genre) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="mb-10">
                <h3 class="text-xl font-bold text-white mb-3 flex items-center gap-2 border-b border-gray-800 pb-2">
                    <i class="fas fa-book-open text-indigo-500"></i> Sinopsis
                </h3>
                <div class="text-gray-300 leading-relaxed text-sm md:text-base bg-gray-900/60 p-6 rounded-xl border border-gray-800 shadow-inner">
                    <?= nl2br(htmlspecialchars($comic['description'])) ?>
                </div>
            </div>

            <div>
                <div class="flex flex-row justify-between items-center mb-4 border-b border-gray-800 pb-4">
                    <h3 class="text-xl font-bold text-white flex items-center gap-2">
                        <i class="fas fa-list-ol text-indigo-500"></i> Daftar Chapter
                    </h3>
                    
                    <div class="relative">
                        <select id="sortChapters" onchange="sortChapterList(this.value)" class="bg-gray-800 border border-gray-700 text-white text-xs font-bold rounded-lg px-3 py-2 focus:outline-none focus:border-indigo-500 appearance-none cursor-pointer pr-8">
                            <option value="desc">Terbaru</option>
                            <option value="asc">Terlama</option>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-400">
                            <i class="fas fa-chevron-down text-xs"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-900 rounded-xl border border-gray-800 overflow-hidden shadow-lg">
                    
                    <div class="max-h-[450px] overflow-y-auto custom-scrollbar" id="chapterScrollArea">
                        
                        <div id="chapterListContainer" class="divide-y divide-gray-800">
                            <?php if (mysqli_num_rows($chapters) > 0): ?>
                                <?php while ($chap = mysqli_fetch_assoc($chapters)): ?>
                                    
                                    <div class="chapter-item group flex items-center justify-between p-3 hover:bg-gray-800 transition cursor-pointer" data-number="<?= $chap['chapter_number'] ?>">
                                        
                                        <a href="baca/<?= $comic['slug'] ?>/<?= $chap['chapter_number'] ?>" class="flex-grow flex items-center gap-3">
                                            
                                            <div class="bg-gray-800 group-hover:bg-indigo-600 border border-gray-700 group-hover:border-indigo-500 text-gray-400 group-hover:text-white px-3 py-2 rounded-md font-bold text-xs sm:text-sm transition duration-300 w-30 text-center flex-shrink-0">
                                                <?= $chap['title'] ? htmlspecialchars($chap['title']) : 'Chapter ' . formatChapterNumber($chap['chapter_number']) ?>
                                            </div>
                                            
                                            <div class="flex flex-col min-w-0">
                                                <h4 class="text-gray-300 group-hover:text-white font-medium text-sm transition line-clamp-1 truncate">
                                                    <?= $chap['title'] ? htmlspecialchars($chap['title']) : 'Chapter ' . formatChapterNumber($chap['chapter_number']) ?>
                                                </h4>
                                                <span class="text-[10px] text-gray-600 mt-0.5 flex items-center gap-1">
                                                    <i class="far fa-clock"></i> <?= date('d M Y', strtotime($chap['created_at'])) ?>
                                                </span>
                                            </div>
                                        </a>

                                        <?php if (isAdmin()): ?>
                                            <div class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition pl-3 border-l border-gray-700 ml-2">
                                                <a href="admin/edit_chapter.php?id=<?= $chap['id'] ?>" class="text-blue-400 hover:text-white p-1" title="Edit Chapter"><i class="fas fa-edit"></i></a>
                                                <a href="admin/delete_chapter.php?id=<?= $chap['id'] ?>" onclick="return confirm('Hapus Chapter <?= formatChapterNumber($chap['chapter_number']) ?>?')"')" class="text-gray-500 hover:text-red-500 p-1" title="Hapus"><i class="fas fa-trash"></i></a>
                                            </div>
                                        <?php endif; ?>

                                    </div>

                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="text-center py-16 text-gray-600">
                                    <i class="fas fa-folder-open text-4xl mb-3 opacity-30"></i>
                                    <p>Belum ada chapter yang diupload.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>
            </div>

        </div>
    </div>
</main>

<script>
    function sortChapterList(order) {
        const container = document.getElementById("chapterListContainer");
        const items = Array.from(container.getElementsByClassName("chapter-item"));
        
        items.sort((a, b) => {
            const numA = parseFloat(a.getAttribute("data-number"));
            const numB = parseFloat(b.getAttribute("data-number"));
            return order === 'asc' ? numA - numB : numB - numA;
        });
        
        container.innerHTML = "";
        items.forEach(item => container.appendChild(item));
    }
</script>

<?php require_once 'includes/footer.php'; ?>