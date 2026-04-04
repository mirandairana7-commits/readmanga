<?php
// BUFFERING: Mencegah error header redirect
ob_start();
session_start();
require_once '../config/database.php';

// Cek Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: " . $base_url . "/login.php");
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$message = '';

// ==========================================
// API AJAX HANDLER UNTUK UPLOAD COVER KE IMGBB
// ==========================================
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'upload_cover') {
    ob_clean();
    header('Content-Type: application/json');
    try {
        if (empty($_FILES['cover_file']['tmp_name'])) throw new Exception("Pilih gambar terlebih dahulu.");
        $image_data = base64_encode(file_get_contents($_FILES['cover_file']['tmp_name']));
        
        $q_set = mysqli_query($conn, "SELECT setting_value FROM settings WHERE setting_key = 'imgbb_api_keys'");
        $row_set = mysqli_fetch_assoc($q_set);
        $keys = $row_set ? json_decode($row_set['setting_value'], true) : [];
        if (empty($keys)) throw new Exception("API Key ImgBB belum diatur.");
        
        $apiKey = $keys[array_rand($keys)];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.imgbb.com/1/upload');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, ['key' => $apiKey, 'image' => $image_data]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($ch);
        curl_close($ch);
        
        $json = json_decode($result, true);
        if (isset($json['data']['url'])) {
            echo json_encode(['success' => true, 'url' => $json['data']['url']]);
        } else {
            throw new Exception($json['error']['message'] ?? "Gagal upload ke ImgBB.");
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}
// ==========================================

// [KEAMANAN DITINGKATKAN]: Ambil Data Komik
$stmt_get = mysqli_prepare($conn, "SELECT * FROM comics WHERE id = ?");
mysqli_stmt_bind_param($stmt_get, "i", $id);
mysqli_stmt_execute($stmt_get);
$comic = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_get));

if (!$comic) die("Komik tidak ditemukan.");

// Proses Update Komik
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax_action'])) {
    $title = trim($_POST['title']);
    $alt_titles = trim($_POST['alternative_titles']);
    $description = trim($_POST['description']);
    $author = trim($_POST['author']);
    $genres = trim($_POST['genres']);
    $status = $_POST['status'];
    $type = $_POST['type'];
    $release_year = $_POST['release_year'];
    $external_link = trim($_POST['external_link']); 
    $link_label = trim($_POST['link_label']); 
    
    // [KEAMANAN DITINGKATKAN]: UPDATE Data Komik
    $update_query = "UPDATE comics SET 
                        title = ?, alternative_titles = ?, description = ?, 
                        author = ?, genres = ?, status = ?, type = ?, 
                        release_year = ?, external_link = ?, link_label = ? 
                      WHERE id = ?";
                      
    $stmt_upd = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($stmt_upd, "ssssssssssi", $title, $alt_titles, $description, $author, $genres, $status, $type, $release_year, $external_link, $link_label, $id);
    
    if (mysqli_stmt_execute($stmt_upd)) {
        $message = "<div class='bg-green-600 p-3 rounded mb-4 font-bold text-white'><i class='fas fa-check-circle'></i> Data berhasil diperbarui!</div>";
        
        // Hapus file cover lama di lokal jika Admin mengubah Cover
        if (!empty($_POST['fetched_cover_url'])) {
            $new_cover = trim($_POST['fetched_cover_url']);
            if ($comic['cover_image'] != 'default.jpg' && strpos($comic['cover_image'], 'http') === false && file_exists('../uploads/covers/' . $comic['cover_image'])) {
                unlink('../uploads/covers/' . $comic['cover_image']);
            }
            // Update nama cover baru di DB
            $stmt_cov = mysqli_prepare($conn, "UPDATE comics SET cover_image = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt_cov, "si", $new_cover, $id);
            mysqli_stmt_execute($stmt_cov);
        }
        
        // Segarkan data komik setelah update
        mysqli_stmt_execute($stmt_get);
        $comic = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_get));
    } else {
        $message = "<div class='bg-red-600 p-3 rounded mb-4 text-white'>Gagal update data.</div>";
    }
}

// [KEAMANAN DITINGKATKAN]: Ambil Daftar Chapter
$stmt_chap = mysqli_prepare($conn, "SELECT * FROM chapters WHERE comic_id = ? ORDER BY chapter_number DESC");
mysqli_stmt_bind_param($stmt_chap, "i", $id);
mysqli_stmt_execute($stmt_chap);
$chapters_q = mysqli_stmt_get_result($stmt_chap);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Komik - Readmanga</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-900 text-white font-sans antialiased min-h-screen">

    <div class="flex h-screen overflow-hidden">
        
        <div id="sidebarOverlay" onclick="toggleSidebar()" class="fixed inset-0 bg-black/50 z-20 hidden md:hidden backdrop-blur-sm transition-opacity"></div>

        <aside id="adminSidebar" class="fixed md:static inset-y-0 left-0 z-30 w-64 bg-gray-800 border-r border-gray-700 transform -translate-x-full md:translate-x-0 transition-transform duration-300 flex flex-col h-full shadow-2xl md:shadow-none">
            <div class="h-16 flex items-center justify-between px-6 border-b border-gray-700 bg-gray-800">
                <span class="text-xl font-bold text-indigo-500">Admin Panel</span>
                <button onclick="toggleSidebar()" class="md:hidden text-gray-400 hover:text-white"><i class="fas fa-times"></i></button>
            </div>
            <nav class="flex-1 px-4 py-4 space-y-2 overflow-y-auto">
                <a href="index.php" class="flex items-center px-4 py-3 text-white bg-indigo-600 rounded-md shadow-lg transition"><i class="fas fa-book mr-3 w-5 text-center"></i> Daftar Komik</a>
                <a href="settings.php" class="flex items-center px-4 py-3 text-gray-400 hover:bg-gray-700 hover:text-white rounded-md transition"><i class="fas fa-cogs mr-3 w-5 text-center"></i> Pengaturan API</a>
                <a href="../index.php" class="flex items-center px-4 py-3 text-gray-400 hover:bg-gray-700 hover:text-white rounded-md transition"><i class="fas fa-home mr-3 w-5 text-center"></i> Lihat Website</a>
            </nav>
        </aside>

        <div class="flex-1 flex flex-col overflow-hidden relative w-full">
            <header class="md:hidden flex items-center justify-between p-4 bg-gray-800 border-b border-gray-700 h-16 shrink-0">
                <div class="flex items-center gap-3">
                    <button onclick="toggleSidebar()" class="text-gray-300 hover:text-white focus:outline-none p-2 rounded hover:bg-gray-700"><i class="fas fa-bars text-xl"></i></button>
                    <span class="font-bold text-lg">Edit Komik</span>
                </div>
            </header>

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-900 p-4 md:p-8">
                <div class="max-w-5xl mx-auto">
                    
                    <div class="flex justify-between items-center mb-6">
                        <h1 class="text-2xl font-bold text-indigo-400 truncate max-w-lg">Edit: <?= htmlspecialchars($comic['title']) ?></h1>
                        <a href="index.php" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded transition flex items-center gap-2">
                            <i class="fas fa-arrow-left"></i> Kembali
                        </a>
                    </div>

                    <?= $message ?>

                    <form method="POST" id="mainComicForm" class="bg-gray-800 rounded-xl shadow-lg border border-gray-700 p-6 mb-8">
                        <input type="hidden" name="fetched_cover_url" id="fetchedCoverUrl">
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                            
                            <div class="flex flex-col items-center">
                                <?php 
                                    $coverPath = $comic['cover_image'];
                                    if(strpos($coverPath, 'http') !== 0) $coverPath = "../uploads/covers/" . $coverPath;
                                ?>
                                <img src="<?= $coverPath ?>" id="previewImg" class="w-48 h-72 object-cover rounded shadow-lg mb-4 bg-gray-900 border border-gray-600">
                                <label class="block text-gray-400 text-xs uppercase font-bold mb-2 w-full text-left">Ganti Cover</label>
                                <input type="file" id="coverInput" onchange="uploadCoverAJAX(this)" accept="image/*" class="text-sm text-gray-400 w-full bg-gray-900 p-2 rounded border border-gray-600 cursor-pointer">
                            </div>

                            <div class="md:col-span-2 space-y-4">
                                <div>
                                    <label class="text-gray-400 text-xs uppercase font-bold block mb-1">Judul Utama</label>
                                    <input type="text" name="title" required value="<?= htmlspecialchars($comic['title']) ?>" class="w-full bg-gray-900 border border-gray-600 rounded px-4 py-2 focus:border-indigo-500 outline-none text-white">
                                </div>

                                <div>
                                    <label class="text-gray-400 text-xs uppercase font-bold block mb-1">Judul Alternatif (Jepang/Korea/Lainnya)</label>
                                    <input type="text" name="alternative_titles" value="<?= htmlspecialchars($comic['alternative_titles'] ?? '') ?>" class="w-full bg-gray-900 border border-gray-600 rounded px-4 py-2 focus:border-indigo-500 outline-none text-sm text-gray-300">
                                </div>

                                <div>
                                    <label class="text-gray-400 text-xs uppercase font-bold block mb-1">Author / Artist</label>
                                    <input type="text" name="author" value="<?= htmlspecialchars($comic['author']) ?>" class="w-full bg-gray-900 border border-gray-600 rounded px-4 py-2 focus:border-indigo-500 outline-none text-white">
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="text-gray-400 text-xs uppercase font-bold block mb-1">Link Eksternal</label>
                                        <input type="url" name="external_link" value="<?= htmlspecialchars($comic['external_link'] ?? '') ?>" class="w-full bg-gray-900 border border-gray-600 rounded px-4 py-2 text-indigo-400 focus:border-indigo-500 outline-none">
                                    </div>
                                    <div>
                                        <label class="text-gray-400 text-xs uppercase font-bold block mb-1">Nama Platform</label>
                                        <input type="text" name="link_label" value="<?= htmlspecialchars($comic['link_label'] ?? '') ?>" class="w-full bg-gray-900 border border-gray-600 rounded px-4 py-2 text-white focus:border-indigo-500 outline-none">
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-3 gap-4">
                                    <div>
                                        <label class="text-gray-400 text-xs uppercase font-bold block mb-1">Status</label>
                                        <select name="status" class="w-full bg-gray-900 border border-gray-600 rounded px-3 py-2 text-white">
                                            <option value="Ongoing" <?= $comic['status']=='Ongoing'?'selected':'' ?>>Ongoing</option>
                                            <option value="Completed" <?= $comic['status']=='Completed'?'selected':'' ?>>Completed</option>
                                            <option value="Hiatus" <?= $comic['status']=='Hiatus'?'selected':'' ?>>Hiatus</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="text-gray-400 text-xs uppercase font-bold block mb-1">Tipe</label>
                                        <select name="type" class="w-full bg-gray-900 border border-gray-600 rounded px-3 py-2 text-white">
                                            <option value="Manga" <?= $comic['type']=='Manga'?'selected':'' ?>>Manga</option>
                                            <option value="Manhwa" <?= $comic['type']=='Manhwa'?'selected':'' ?>>Manhwa</option>
                                            <option value="Manhua" <?= $comic['type']=='Manhua'?'selected':'' ?>>Manhua</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="text-gray-400 text-xs uppercase font-bold block mb-1">Tahun</label>
                                        <input type="number" name="release_year" value="<?= $comic['release_year'] ?>" class="w-full bg-gray-900 border border-gray-600 rounded px-3 py-2 text-white">
                                    </div>
                                </div>
                                <div>
                                    <label class="text-gray-400 text-xs uppercase font-bold block mb-1">Genre</label>
                                    <input type="text" name="genres" value="<?= htmlspecialchars($comic['genres']) ?>" class="w-full bg-gray-900 border border-gray-600 rounded px-4 py-2 text-white focus:border-indigo-500 outline-none">
                                </div>
                                <div>
                                    <label class="text-gray-400 text-xs uppercase font-bold block mb-1">Sinopsis</label>
                                    <textarea name="description" rows="5" class="w-full bg-gray-900 border border-gray-600 rounded px-4 py-2 text-white focus:border-indigo-500 outline-none"><?= htmlspecialchars($comic['description']) ?></textarea>
                                </div>
                                <div class="flex justify-end pt-4">
                                    <button type="submit" id="btnSubmitForm" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-8 rounded shadow-lg transition transform hover:-translate-y-1">Simpan Info</button>
                                </div>
                            </div>
                        </div>
                    </form>

                    <div class="bg-gray-800 rounded-xl shadow-lg border border-gray-700 overflow-hidden">
                        <div class="flex justify-between items-center p-6 border-b border-gray-700 bg-gray-800/50">
                            <h2 class="text-xl font-bold text-white"><i class="fas fa-list-ol mr-2 text-indigo-500"></i> Daftar Chapter</h2>
                            <a href="add_chapter.php?slug=<?= $comic['slug'] ?>" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded text-sm font-bold flex items-center gap-2 shadow transition hover:shadow-lg">
                                <i class="fas fa-plus"></i> Tambah Chapter
                            </a>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="w-full whitespace-nowrap text-left">
                                <thead class="bg-gray-700/50 text-xs uppercase font-bold text-gray-400">
                                    <tr>
                                        <th class="px-6 py-3">No</th>
                                        <th class="px-6 py-3">Judul Chapter</th>
                                        <th class="px-6 py-3 text-right">Tanggal</th>
                                        <th class="px-6 py-3 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-700">
                                    <?php if(mysqli_num_rows($chapters_q) > 0): ?>
                                        <?php while($chap = mysqli_fetch_assoc($chapters_q)): ?>
                                            <tr class="hover:bg-gray-700/30 transition">
                                                <td class="px-6 py-4 font-mono text-indigo-300">Chapter <?= formatChapterNumber($chap['chapter_number']) ?></td>
                                                <td class="px-6 py-4 font-bold text-white"><?= $chap['title'] ? htmlspecialchars($chap['title']) : '-' ?></td>
                                                <td class="px-6 py-4 text-right text-gray-500 text-sm"><?= date('d M Y', strtotime($chap['created_at'])) ?></td>
                                                <td class="px-6 py-4 text-right">
                                                    <a href="edit_chapter.php?id=<?= $chap['id'] ?>" class="bg-blue-600 hover:bg-blue-700 text-white p-2 rounded mr-2 transition" title="Edit Chapter"><i class="fas fa-edit"></i></a>
                                                    <a href="delete_chapter.php?id=<?= $chap['id'] ?>" onclick="return confirm('Hapus Chapter <?= formatChapterNumber($chap['chapter_number']) ?>?')" class="bg-red-600 hover:bg-red-700 text-white p-2 rounded transition" title="Hapus"><i class="fas fa-trash"></i></a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="px-6 py-10 text-center text-gray-500">Belum ada chapter yang diupload.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
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

        // --- JAVASCRIPT AJAX UPLOAD KE IMGBB ---
        async function uploadCoverAJAX(input) {
            if(!input.files || input.files.length === 0) return;
            
            const btnSubmit = document.getElementById('btnSubmitForm');
            const originalBtnText = btnSubmit.innerHTML;
            
            // Tampilan Loading
            btnSubmit.disabled = true;
            btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading Cover...';
            btnSubmit.classList.add('opacity-50', 'cursor-not-allowed');
            
            const fd = new FormData();
            fd.append('ajax_action', 'upload_cover');
            fd.append('cover_file', input.files[0]);
            
            try {
                const res = await fetch('', {method: 'POST', body: fd}); 
                const json = await res.json();
                
                if(json.success) {
                    document.getElementById('fetchedCoverUrl').value = json.url;
                    document.getElementById('previewImg').src = json.url;
                } else {
                    alert("Gagal Upload Cover: " + json.msg);
                    input.value = ''; 
                }
            } catch(e) {
                alert("Error jaringan saat upload cover.");
                input.value = '';
            } finally {
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = originalBtnText;
                btnSubmit.classList.remove('opacity-50', 'cursor-not-allowed');
            }
        }
    </script>
</body>
</html>