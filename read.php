<?php
require_once 'config/database.php';
require_once 'includes/auth.php'; // Opsional

// 1. TANGKAP DATA URL STANDAR (Query String)
// URL: read.php?slug=judul-komik&chapter=10
$comic_slug = isset($_GET['slug']) ? mysqli_real_escape_string($conn, $_GET['slug']) : '';
$chapter_num = isset($_GET['chapter']) ? mysqli_real_escape_string($conn, $_GET['chapter']) : '';

// 2. QUERY MENCARI CHAPTER
$query = "SELECT c.*, cm.title as comic_title, cm.slug as comic_slug, cm.id as comic_id, cm.cover_image as comic_cover, cm.description as comic_desc 
          FROM chapters c 
          JOIN comics cm ON c.comic_id = cm.id 
          WHERE cm.slug = '$comic_slug' AND c.chapter_number = '$chapter_num'";

$result = mysqli_query($conn, $query);
$chapter = mysqli_fetch_assoc($result);

if (!$chapter) {
    // Jika tidak ketemu, redirect ke halaman detail komik
    if($comic_slug) header("Location: comic.php?slug=$comic_slug");
    else header("Location: index.php");
    exit();
}

$chapter_id = $chapter['id'];
$comic_id = $chapter['comic_id'];

// 3. AMBIL GAMBAR
$img_query = "SELECT * FROM chapter_images WHERE chapter_id = $chapter_id ORDER BY display_order ASC";
$images = mysqli_query($conn, $img_query);

// 4. NAVIGASI NEXT/PREV
$prev_q = "SELECT chapter_number FROM chapters WHERE comic_id = $comic_id AND chapter_number < $chapter_num ORDER BY chapter_number DESC LIMIT 1";
$prev_res = mysqli_fetch_assoc(mysqli_query($conn, $prev_q));

$next_q = "SELECT chapter_number FROM chapters WHERE comic_id = $comic_id AND chapter_number > $chapter_num ORDER BY chapter_number ASC LIMIT 1";
$next_res = mysqli_fetch_assoc(mysqli_query($conn, $next_q));

// 5. LIST CHAPTER UNTUK DROPDOWN
$all_chaps = mysqli_query($conn, "SELECT chapter_number FROM chapters WHERE comic_id = $comic_id ORDER BY chapter_number DESC");
?>

<?php
require_once 'config/database.php';

$comic_slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
$chapter_num = isset($_GET['chapter']) ? trim($_GET['chapter']) : '';

// [KEAMANAN DITINGKATKAN]: Query Chapter Utama
$query = "SELECT c.*, cm.title as comic_title, cm.slug as comic_slug, cm.id as comic_id, cm.cover_image as comic_cover, cm.description as comic_desc 
          FROM chapters c JOIN comics cm ON c.comic_id = cm.id 
          WHERE cm.slug = ? AND c.chapter_number = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "ss", $comic_slug, $chapter_num);
mysqli_stmt_execute($stmt);
$chapter = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$chapter) {
    if($comic_slug) header("Location: comic.php?slug=$comic_slug");
    else header("Location: index.php");
    exit();
}

$chapter_id = $chapter['id'];
$comic_id = $chapter['comic_id'];

// [KEAMANAN DITINGKATKAN]: Ambil Gambar
$stmt_img = mysqli_prepare($conn, "SELECT * FROM chapter_images WHERE chapter_id = ? ORDER BY display_order ASC");
mysqli_stmt_bind_param($stmt_img, "i", $chapter_id);
mysqli_stmt_execute($stmt_img);
$images = mysqli_stmt_get_result($stmt_img);

// [KEAMANAN DITINGKATKAN]: Navigasi Prev
$stmt_prev = mysqli_prepare($conn, "SELECT chapter_number FROM chapters WHERE comic_id = ? AND chapter_number < ? ORDER BY chapter_number DESC LIMIT 1");
mysqli_stmt_bind_param($stmt_prev, "is", $comic_id, $chapter_num);
mysqli_stmt_execute($stmt_prev);
$prev_res = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_prev));

// [KEAMANAN DITINGKATKAN]: Navigasi Next
$stmt_next = mysqli_prepare($conn, "SELECT chapter_number FROM chapters WHERE comic_id = ? AND chapter_number > ? ORDER BY chapter_number ASC LIMIT 1");
mysqli_stmt_bind_param($stmt_next, "is", $comic_id, $chapter_num);
mysqli_stmt_execute($stmt_next);
$next_res = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_next));

// [KEAMANAN DITINGKATKAN]: List Chapter untuk Dropdown
$stmt_all = mysqli_prepare($conn, "SELECT chapter_number FROM chapters WHERE comic_id = ? ORDER BY chapter_number DESC");
mysqli_stmt_bind_param($stmt_all, "i", $comic_id);
mysqli_stmt_execute($stmt_all);
$all_chaps = mysqli_stmt_get_result($stmt_all);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <base href="<?= $base_url ?>/"> 
    
    <?php
        // Logika Cover untuk OG Image Halaman Baca
        $og_image_read = $chapter['comic_cover'];
        if (strpos($og_image_read, 'http') !== 0) {
            $og_image_read = $base_url . "/uploads/covers/" . $og_image_read;
        }
        $formatted_chapter = formatChapterNumber($chapter['chapter_number']);
    ?>
    <title>Chapter <?= $formatted_chapter ?> - <?= htmlspecialchars($chapter['comic_title']) ?></title>
    <link rel="icon" href="<?= $base_url ?>/assets/favicon.png?v=2" type="image/png">
    
    <meta property="og:type" content="article">
    <meta property="og:url" content="<?= $base_url ?>/baca/<?= $comic_slug ?>/<?= $chapter_num ?>">
    <meta property="og:title" content="Chapter <?= $formatted_chapter ?> - <?= htmlspecialchars($chapter['comic_title']) ?>">
    <meta property="og:description" content="Baca chapter terbaru dari <?= htmlspecialchars($chapter['comic_title']) ?> hanya di Readmanga.">
    <meta property="og:image" content="<?= $og_image_read ?>">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Warna default dihapus dari sini agar mudah ditimpa JS */
        body { overscroll-behavior-y: none; margin: 0; padding: 0; transition: background-color 0.3s; }
        .reader-container { max-width: 100%; margin: 0 auto; min-height: 100vh; position: relative; z-index: 1; }
        .reader-image { display: block; margin: 0 auto; max-width: 100%; height: auto; user-select: none; -webkit-user-drag: none; }
        .mode-single .reader-image { max-height: 100vh; width: auto; object-fit: contain; display: none; }
        .mode-single .reader-image.active { display: block; }
        .mode-single .reader-container { display: flex; align-items: center; justify-content: center; height: 100vh; }
        .mode-webtoon .reader-image { width: 100%; margin-bottom: 0px !important; } /* Memastikan tidak ada jarak default */
        #brightness-layer { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: black; opacity: 0; pointer-events: none; z-index: 9999; transition: opacity 0.2s; }
        .ui-header { transform: translateY(0); transition: transform 0.3s ease-in-out; }
        .ui-header.hidden-bar { transform: translateY(-100%); }
        .ui-controls { opacity: 1; transform: translateY(0); transition: opacity 0.3s ease, transform 0.3s ease; }
        .ui-controls.hidden-bar { opacity: 0; transform: translateY(20px); pointer-events: none; }
        .tap-zone { position: fixed; height: 100%; top: 0; z-index: 20; }
        .tap-left { left: 0; width: 30%; cursor: w-resize; }
        .tap-right { right: 0; width: 30%; cursor: e-resize; }
        .tap-center { left: 30%; width: 40%; cursor: pointer; }
        ::-webkit-scrollbar { width: 0px; background: transparent; }
    </style>
</head>
<body class="antialiased mode-webtoon" id="bodyReader">

    <div id="brightness-layer"></div>

    <nav class="fixed top-0 w-full bg-gradient-to-b from-black/90 to-transparent z-50 ui-header h-20 pt-2" id="topNavbar">
        <div class="max-w-7xl mx-auto px-4 flex justify-between items-start">
            <div class="flex items-center gap-3 overflow-hidden">
                <a href="comic.php?slug=<?= $comic_slug ?>" class="text-white hover:text-indigo-400 transition drop-shadow-md">
                    <i class="fas fa-arrow-left text-2xl"></i>
                </a>
                <div class="flex flex-col overflow-hidden drop-shadow-md">
                    <h1 class="text-white font-bold text-sm sm:text-base truncate w-40 sm:w-auto leading-tight">
                        <?= htmlspecialchars($chapter['comic_title']) ?>
                    </h1>
                    <p class="text-[11px] text-gray-300 font-mono">Chapter <?= formatChapterNumber($chapter['chapter_number']) ?></p>
                </div>
            </div>
            
            <div class="flex items-center gap-3 bg-black/50 backdrop-blur-sm rounded-full px-3 py-1 border border-white/10">
                <?php if ($prev_res): ?>
                    <a href="baca/<?= $comic_slug ?>/<?= $prev_res['chapter_number'] ?>" class="text-gray-300 hover:text-white"><i class="fas fa-chevron-left"></i></a>
                <?php else: ?>
                    <span class="text-gray-600"><i class="fas fa-chevron-left"></i></span>
                <?php endif; ?>
                
                <span class="text-xs font-bold text-white">Chapter <?= formatChapterNumber($chapter['chapter_number']) ?></span>
                
                <?php if ($next_res): ?>
                    <a href="baca/<?= $comic_slug ?>/<?= $next_res['chapter_number'] ?>" class="text-gray-300 hover:text-white"><i class="fas fa-chevron-right"></i></a>
                <?php else: ?>
                    <span class="text-gray-600"><i class="fas fa-chevron-right"></i></span>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="tap-zone tap-center" onclick="toggleUI()"></div>
    <div class="tap-zone tap-left hidden" id="zoneLeft" onclick="prevPage()"></div>
    <div class="tap-zone tap-right hidden" id="zoneRight" onclick="nextPage()"></div>

    <main class="min-h-screen transition-colors duration-300" id="readerArea">
        <div class="reader-container max-w-[800px]" id="imageContainer">
            <?php 
            if (mysqli_num_rows($images) > 0) {
                $count = 0;
                while ($img = mysqli_fetch_assoc($images)): 
                    $count++;
                    $imgSrc = $img['image_path'];
                    if (strpos($imgSrc, 'http') !== 0) {
                        $imgSrc = $base_url . '/' . $imgSrc;
                    }
            ?>
                <img src="<?= $imgSrc ?>" loading="lazy" class="reader-image" id="page-<?= $count ?>" alt="Page <?= $count ?>">
            <?php endwhile; } else { echo '<div class="flex h-screen items-center justify-center text-gray-500">Gambar kosong.</div>'; } ?>
        </div>

        <div class="max-w-[800px] mx-auto px-4 py-10 pb-32 hidden relative z-10" id="bottomNavWebtoon">
            <div class="grid grid-cols-2 gap-4">
                <?php if ($prev_res): ?>
                    <a href="baca/<?= $comic_slug ?>/<?= $prev_res['chapter_number'] ?>" class="bg-gray-800 text-white py-3 rounded-lg text-center font-bold border border-gray-700 hover:bg-gray-700">Prev</a>
                <?php else: ?>
                    <div></div>
                <?php endif; ?>
                
                <?php if ($next_res): ?>
                    <a href="baca/<?= $comic_slug ?>/<?= $next_res['chapter_number'] ?>" class="bg-indigo-600 text-white py-3 rounded-lg text-center font-bold hover:bg-indigo-500 shadow-lg shadow-indigo-500/30">Next Chapter</a>
                <?php else: ?>
                    <a href="comic.php?slug=<?= $comic_slug ?>" class="bg-gray-700 text-white py-3 rounded-lg text-center font-bold">Selesai</a>
                <?php endif; ?>
            </div>
            
            <div class="mt-6">
                <select onchange="if(this.value) window.location.href=this.value" class="w-full bg-black text-white border border-gray-700 rounded p-2 text-center">
                    <option value="">-- Pilih Chapter --</option>
                    <?php while($ch = mysqli_fetch_assoc($all_chaps)): ?>
                        <option value="baca/<?= $comic_slug ?>/<?= $ch['chapter_number'] ?>" <?= $ch['chapter_number'] == $chapter_num ? 'selected' : '' ?>>
                            Chapter <?= formatChapterNumber($ch['chapter_number']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>
    </main>

    <div class="fixed bottom-6 right-4 z-[60] flex flex-col gap-3 ui-controls" id="floatingControls">
        <button onclick="toggleFullScreen()" id="btnFullscreen" class="bg-black/70 backdrop-blur text-white w-10 h-10 rounded-full border border-gray-600 flex items-center justify-center transition-colors"><i class="fas fa-expand text-sm"></i></button>
        <button onclick="toggleAutoScroll()" id="btnAutoScroll" class="bg-black/70 backdrop-blur text-white w-10 h-10 rounded-full border border-gray-600 flex items-center justify-center transition-colors"><i class="fas fa-play text-sm"></i></button>
        <button onclick="scrollToTop()" class="bg-black/70 backdrop-blur text-white w-10 h-10 rounded-full border border-gray-600 flex items-center justify-center"><i class="fas fa-arrow-up text-sm"></i></button>
        <button onclick="toggleSettings()" class="bg-indigo-600 text-white w-12 h-12 rounded-full shadow-xl flex items-center justify-center animate-pulse"><i class="fas fa-cog text-lg fa-spin-hover"></i></button>
    </div>

    <div id="settingsPanel" class="fixed bottom-0 left-0 w-full bg-gray-900/95 backdrop-blur-xl border-t border-gray-700 z-[70] transform translate-y-full transition-transform duration-300 rounded-t-2xl shadow-2xl">
        <div class="max-w-2xl mx-auto p-6 pb-8">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-white font-bold text-lg"><i class="fas fa-sliders-h mr-2 text-indigo-500"></i> Pengaturan</h3>
                <button onclick="toggleSettings()" class="text-gray-400 hover:text-white p-2"><i class="fas fa-times text-xl"></i></button>
            </div>
            
            <div class="mb-6">
                <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Tampilan Mode</label>
                <div class="grid grid-cols-2 gap-2 bg-gray-800 p-1 rounded-lg border border-gray-700">
                    <button onclick="setMode('webtoon')" id="btn-webtoon" class="py-2 rounded text-sm font-bold flex items-center justify-center gap-2"><i class="fas fa-scroll"></i> Webtoon</button>
                    <button onclick="setMode('single')" id="btn-single" class="py-2 rounded text-sm font-bold flex items-center justify-center gap-2"><i class="fas fa-clone"></i> Per Page</button>
                </div>
            </div>

            <div class="mb-6">
                <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Tema Latar</label>
                <div class="flex gap-2">
                    <button onclick="setBgColor('#121212')" class="flex-1 py-2 bg-gray-800 border border-gray-700 rounded text-xs hover:bg-gray-700 text-white font-semibold">Gelap</button>
                    <button onclick="setBgColor('#FFFFFF')" class="flex-1 py-2 bg-gray-200 border border-gray-300 rounded text-xs hover:bg-gray-300 text-black font-semibold">Terang</button>
                    <button onclick="setBgColor('#F4ECD8')" class="flex-1 py-2 bg-[#F4ECD8] border border-[#e0d5ba] rounded text-xs hover:bg-[#eaddba] text-gray-800 font-semibold">Sepia</button>
                </div>
            </div>

            <div class="mb-6">
                <label class="block text-xs font-bold text-gray-500 uppercase mb-2 flex justify-between"><span>Kecerahan Layar</span><span id="brightVal" class="text-indigo-400">100%</span></label>
                <input type="range" min="20" max="100" value="100" class="w-full h-2 bg-gray-700 rounded-lg appearance-none cursor-pointer accent-indigo-500" oninput="setBrightness(this.value)">
            </div>
            
            <div class="mb-2" id="scrollControl">
                <label class="block text-xs font-bold text-gray-500 uppercase mb-2 flex justify-between"><span>Kecepatan Auto Scroll</span></label>
                <input type="range" min="1" max="50" value="15" class="w-full h-2 bg-gray-700 rounded-lg appearance-none cursor-pointer accent-indigo-500" oninput="setAutoScrollSpeed(this.value)">
            </div>
        </div>
    </div>

    <div id="pageIndicator" class="fixed bottom-8 left-1/2 transform -translate-x-1/2 bg-black/80 backdrop-blur text-white px-4 py-1.5 rounded-full text-xs font-mono border border-white/20 hidden z-40 shadow-lg"><span id="currPage">1</span> / <span id="totalPage"><?= mysqli_num_rows($images) ?></span></div>

    <script>
        const totalPages = <?= mysqli_num_rows($images) ?>;
        const nextUrl = "<?= $next_res ? "read.php?slug=$comic_slug&chapter={$next_res['chapter_number']}" : '' ?>";
        let currentPage = 1;
        let readingMode = localStorage.getItem('readingMode') || 'webtoon';
        let isUIVisible = true;
        
        // Variabel Latar Belakang & Auto Scroll
        let bgColor = localStorage.getItem('readerBgColor') || '#121212';
        let autoScrollInterval = null;
        let isAutoScrolling = false;
        let scrollSpeed = 15; // Kecepatan default

        document.addEventListener('DOMContentLoaded', () => {
            setMode(readingMode);
            setBgColor(bgColor); // Set warna latar saat dimuat
            setTimeout(() => { if(isUIVisible) toggleUI(); }, 2000);
            document.addEventListener('contextmenu', event => { event.preventDefault(); toggleSettings(); });
        });

        // --- FUNGSI UI & PENGATURAN ---
        function toggleUI() {
            isUIVisible = !isUIVisible;
            const nav = document.getElementById('topNavbar');
            const fab = document.getElementById('floatingControls');
            const indicator = document.getElementById('pageIndicator');
            const settings = document.getElementById('settingsPanel');
            if (isUIVisible) {
                nav.classList.remove('hidden-bar'); fab.classList.remove('hidden-bar');
                if(readingMode === 'single') indicator.classList.remove('hidden-bar');
            } else {
                nav.classList.add('hidden-bar'); fab.classList.add('hidden-bar'); indicator.classList.add('hidden-bar');
                settings.classList.remove('translate-y-0'); settings.classList.add('translate-y-full');
            }
        }

        function toggleSettings() {
            const panel = document.getElementById('settingsPanel');
            if (panel.classList.contains('translate-y-full')) { panel.classList.remove('translate-y-full'); panel.classList.add('translate-y-0'); } 
            else { panel.classList.add('translate-y-full'); panel.classList.remove('translate-y-0'); }
        }

        function setMode(mode) {
            readingMode = mode; localStorage.setItem('readingMode', mode);
            const body = document.body;
            const btnWebtoon = document.getElementById('btn-webtoon');
            const btnSingle = document.getElementById('btn-single');
            const images = document.querySelectorAll('.reader-image');
            const zoneLeft = document.getElementById('zoneLeft'); const zoneRight = document.getElementById('zoneRight');
            const bottomNav = document.getElementById('bottomNavWebtoon'); const pageInd = document.getElementById('pageIndicator');
            const scrollCtrl = document.getElementById('scrollControl');

            body.classList.remove('mode-webtoon', 'mode-single');
            btnWebtoon.className = 'py-2 rounded text-sm font-bold transition flex items-center justify-center gap-2 w-full bg-gray-800 text-gray-400';
            btnSingle.className = 'py-2 rounded text-sm font-bold transition flex items-center justify-center gap-2 w-full bg-gray-800 text-gray-400';

            // Hentikan auto scroll jika berganti mode
            if(isAutoScrolling) toggleAutoScroll(); 

            if (mode === 'webtoon') {
                body.classList.add('mode-webtoon');
                btnWebtoon.className = 'py-2 rounded text-sm font-bold transition flex items-center justify-center gap-2 w-full bg-indigo-600 text-white';
                images.forEach(img => img.classList.remove('active'));
                zoneLeft.classList.add('hidden'); zoneRight.classList.add('hidden');
                bottomNav.classList.remove('hidden'); pageInd.classList.add('hidden'); 
                scrollCtrl.classList.remove('hidden'); document.getElementById('btnAutoScroll').classList.remove('hidden');
            } else {
                body.classList.add('mode-single');
                btnSingle.className = 'py-2 rounded text-sm font-bold transition flex items-center justify-center gap-2 w-full bg-indigo-600 text-white';
                showPage(currentPage);
                zoneLeft.classList.remove('hidden'); zoneRight.classList.remove('hidden');
                bottomNav.classList.add('hidden'); pageInd.classList.remove('hidden'); 
                scrollCtrl.classList.add('hidden'); document.getElementById('btnAutoScroll').classList.add('hidden');
            }
        }

        // --- FUNGSI BACA (SINGLE PAGE) ---
        function showPage(num) {
            const images = document.querySelectorAll('.reader-image');
            images.forEach(img => img.classList.remove('active'));
            if (num < 1) num = 1;
            if (num > totalPages) {
                if(nextUrl) { if(confirm("Lanjut ke Chapter berikutnya?")) window.location.href = nextUrl; } 
                else { alert("Ini adalah halaman terakhir."); }
                num = totalPages;
            }
            const activeImg = document.getElementById(`page-${num}`);
            if(activeImg) { activeImg.classList.add('active'); window.scrollTo(0,0); }
            currentPage = num; document.getElementById('currPage').innerText = currentPage;
        }
        function nextPage() { showPage(currentPage + 1); }
        function prevPage() { showPage(currentPage - 1); }

        // --- FUNGSI TEMA & KECERAHAN ---
        function setBrightness(val) { 
            document.getElementById('brightness-layer').style.opacity = (100 - val) / 100; 
            document.getElementById('brightVal').innerText = val + '%'; 
        }

        function setBgColor(color) {
            bgColor = color;
            document.body.style.backgroundColor = color;
            document.getElementById('readerArea').style.backgroundColor = color;
            localStorage.setItem('readerBgColor', color);
        }

        // --- FUNGSI AUTO SCROLL ---
        function toggleAutoScroll() {
            const btn = document.getElementById('btnAutoScroll');
            if (isAutoScrolling) {
                clearInterval(autoScrollInterval);
                isAutoScrolling = false;
                btn.innerHTML = '<i class="fas fa-play text-sm"></i>';
                btn.classList.remove('bg-indigo-600', 'border-indigo-500');
                btn.classList.add('bg-black/70', 'border-gray-600');
            } else {
                if (readingMode === 'single') return; // Jaga-jaga
                isAutoScrolling = true;
                btn.innerHTML = '<i class="fas fa-pause text-sm"></i>';
                btn.classList.remove('bg-black/70', 'border-gray-600');
                btn.classList.add('bg-indigo-600', 'border-indigo-500');
                
                autoScrollInterval = setInterval(() => {
                    window.scrollBy(0, scrollSpeed / 10);
                    // Berhenti otomatis jika mencapai dasar halaman
                    if ((window.innerHeight + window.scrollY) >= document.body.offsetHeight - 20) {
                        toggleAutoScroll();
                    }
                }, 16);
            }
        }

        function setAutoScrollSpeed(val) {
            scrollSpeed = val;
        }

        // --- FUNGSI FULL SCREEN ---
        function toggleFullScreen() {
            const btn = document.getElementById('btnFullscreen');
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().catch(err => {
                    console.error(`Error full-screen: ${err.message}`);
                });
            } else {
                if (document.exitFullscreen) document.exitFullscreen();
            }
        }

        // Ganti Ikon Fullscreen saat status berubah (misal user tekan tombol ESC)
        document.addEventListener('fullscreenchange', () => {
            const btn = document.getElementById('btnFullscreen');
            if (!document.fullscreenElement) btn.innerHTML = '<i class="fas fa-expand text-sm"></i>';
            else btn.innerHTML = '<i class="fas fa-compress text-sm"></i>';
        });

        // --- LAINNYA ---
        function scrollToTop() { window.scrollTo({ top: 0, behavior: 'smooth' }); }
        document.addEventListener('keydown', (e) => { 
            if (readingMode === 'single') { 
                if (e.key === 'ArrowRight' || e.key === ' ') nextPage(); 
                if (e.key === 'ArrowLeft') prevPage(); 
            } 
        });
    </script>
</body>
</html>