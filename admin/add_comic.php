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
        if (empty($keys)) throw new Exception("API Key ImgBB belum diatur di Pengaturan.");
        
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

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax_action'])) {
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $alt_titles = mysqli_real_escape_string($conn, $_POST['alternative_titles']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $external_link = mysqli_real_escape_string($conn, $_POST['external_link']); 
    $link_label = mysqli_real_escape_string($conn, $_POST['link_label']); 
    $author = mysqli_real_escape_string($conn, $_POST['author']); 
    $genres = mysqli_real_escape_string($conn, $_POST['genres']);
    $release_year = mysqli_real_escape_string($conn, $_POST['release_year']);
    $status = $_POST['status'];
    $type = $_POST['type'];
    
    $artist = '';          
    $serialization = '';   
    $score = 0;            
    
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));

    // Cover langsung ambil dari URL hasil AJAX ImgBB atau Auto-Fill (Lebih Hemat Server)
    $cover_name = 'default.jpg';
    if (!empty($_POST['fetched_cover_url'])) {
        $cover_name = mysqli_real_escape_string($conn, $_POST['fetched_cover_url']);
    }

    $query = "INSERT INTO comics (title, slug, alternative_titles, description, author, artist, genres, serialization, release_year, status, type, score, cover_image, external_link, link_label) 
              VALUES ('$title', '$slug', '$alt_titles', '$description', '$author', '$artist', '$genres', '$serialization', '$release_year', '$status', '$type', '$score', '$cover_name', '$external_link', '$link_label')";
    
    if (mysqli_query($conn, $query)) {
        header("Location: " . $base_url . "/admin/index.php");
        exit();
    } else {
        $message = "<div class='bg-red-600 text-white p-3 rounded mb-4'>Database Error: " . mysqli_error($conn) . "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Komik - Readmanga</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-900 text-white font-sans antialiased min-h-screen p-4 md:p-8">

    <div class="max-w-6xl mx-auto">
        <div class="flex justify-between items-center mb-8 border-b border-gray-700 pb-4">
            <h1 class="text-3xl font-bold text-indigo-400">Tambah Komik</h1>
            <a href="index.php" class="text-gray-400 hover:text-white"><i class="fas fa-times"></i> Batal</a>
        </div>

        <?= $message ?>

        <div class="bg-gray-800 rounded-xl p-6 border border-indigo-500/30 mb-8 shadow-lg">
            <h2 class="text-xl font-bold mb-4 flex items-center gap-2 text-white">
                <i class="fas fa-bolt text-yellow-400"></i> Auto-Fill Data
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
                <div class="md:col-span-3">
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Sumber</label>
                    <select id="fetchSource" class="w-full bg-gray-900 border border-gray-600 rounded px-4 py-2 focus:border-indigo-500 outline-none cursor-pointer text-white">
                        <option value="mangadex">MangaDex</option>
                        <option value="mal">MyAnimeList</option>
                    </select>
                </div>
                <div class="md:col-span-7">
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Link / ID</label>
                    <input type="text" id="fetchId" placeholder="Paste Link atau ID Manga disini..." class="w-full bg-gray-900 border border-gray-600 rounded px-4 py-2 text-indigo-300 focus:border-indigo-500 outline-none">
                </div>
                <div class="md:col-span-2">
                    <button type="button" onclick="fetchData()" id="btnFetch" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 rounded transition shadow-lg flex justify-center items-center gap-2">
                        <i class="fas fa-search"></i> Ambil Data
                    </button>
                </div>
            </div>
        </div>

        <form method="POST" id="mainComicForm" class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <input type="hidden" name="fetched_cover_url" id="fetchedCoverUrl">

            <div class="space-y-6">
                <div class="bg-gray-800 rounded-xl p-6 border border-gray-700 flex flex-col items-center shadow-lg">
                    <label class="mb-4 font-bold text-gray-300">Cover Preview</label>
                    <div class="w-48 h-72 bg-gray-900 rounded-lg shadow-lg overflow-hidden border border-gray-600 flex items-center justify-center">
                        <img id="previewImg" src="https://via.placeholder.com/300x450?text=No+Cover" class="w-full h-full object-cover">
                    </div>
                    <input type="file" id="coverInput" onchange="uploadCoverAJAX(this)" accept="image/*" class="mt-6 text-sm text-gray-400 w-full bg-gray-900 p-2 rounded border border-gray-600 cursor-pointer">
                    <p class="text-xs text-gray-500 mt-2 text-center">Pilih file untuk upload otomatis ke ImgBB, atau gunakan fitur Auto-Fill.</p>
                </div>
            </div>

            <div class="lg:col-span-2 space-y-6">
                <div class="bg-gray-800 rounded-xl p-6 border border-gray-700 shadow-lg">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Judul Komik</label>
                            <input type="text" name="title" id="inputTitle" required class="w-full bg-gray-900 border border-gray-600 rounded px-4 py-2 focus:border-indigo-500 outline-none text-white">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Judul Alternatif</label>
                            <input type="text" name="alternative_titles" id="inputAlt" class="w-full bg-gray-900 border border-gray-600 rounded px-4 py-2 focus:border-indigo-500 outline-none text-sm text-gray-300">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Author / Artist</label>
                            <input type="text" name="author" id="inputAuthor" class="w-full bg-gray-900 border border-gray-600 rounded px-4 py-2 focus:border-indigo-500 outline-none text-white">
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Link Eksternal (URL)</label>
                                <input type="url" name="external_link" placeholder="https://..." class="w-full bg-gray-900 border border-gray-600 rounded px-4 py-2 text-indigo-400 focus:border-indigo-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Nama Platform</label>
                                <input type="text" name="link_label" placeholder="Cth: MangaDex" class="w-full bg-gray-900 border border-gray-600 rounded px-4 py-2 text-white focus:border-indigo-500 outline-none">
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Status</label>
                                <select name="status" id="inputStatus" class="w-full bg-gray-900 border border-gray-600 rounded px-3 py-2 focus:border-indigo-500 outline-none text-white">
                                    <option value="Ongoing">Ongoing</option>
                                    <option value="Completed">Completed</option>
                                    <option value="Hiatus">Hiatus</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Tipe</label>
                                <select name="type" id="inputType" class="w-full bg-gray-900 border border-gray-600 rounded px-3 py-2 focus:border-indigo-500 outline-none text-white">
                                    <option value="Manga">Manga (JP)</option>
                                    <option value="Manhwa">Manhwa (KR)</option>
                                    <option value="Manhua">Manhua (CN)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Tahun Rilis</label>
                                <input type="number" name="release_year" id="inputYear" class="w-full bg-gray-900 border border-gray-600 rounded px-3 py-2 focus:border-indigo-500 outline-none text-white">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Genre</label>
                            <input type="text" name="genres" id="inputGenres" class="w-full bg-gray-900 border border-gray-600 rounded px-4 py-2 focus:border-indigo-500 outline-none text-white">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Sinopsis</label>
                            <textarea name="description" id="inputDesc" rows="6" class="w-full bg-gray-900 border border-gray-600 rounded px-4 py-2 focus:border-indigo-500 outline-none text-gray-300"></textarea>
                        </div>

                        <div class="pt-4 flex justify-end">
                            <button type="submit" id="btnSubmitForm" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-8 rounded shadow-lg transition transform hover:-translate-y-1 flex items-center gap-2">
                                <i class="fas fa-save"></i> Simpan Komik
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
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
                // Fetch ke halaman ini sendiri
                const res = await fetch('', {method: 'POST', body: fd}); 
                const json = await res.json();
                
                if(json.success) {
                    // Update Value ke Hidden Input dan rubah Preview Image
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
                // Kembalikan Tombol Simpan
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = originalBtnText;
                btnSubmit.classList.remove('opacity-50', 'cursor-not-allowed');
            }
        }

        // --- MANGADEX / MAL AUTO-FILL LOGIC (Sama persis tidak ada yang dirubah) ---
        async function fetchData() {
            const source = document.getElementById('fetchSource').value;
            let inputId = document.getElementById('fetchId').value.trim();
            const btn = document.getElementById('btnFetch');
            
            if (!inputId) return alert("Masukkan ID/Link dulu!");
            
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...'; 
            btn.disabled = true;
            btn.classList.add('opacity-70', 'cursor-not-allowed');

            try {
                if(inputId.endsWith('/')) inputId = inputId.slice(0, -1);
                if (inputId.includes('http')) {
                    if(source === 'mangadex' && inputId.includes('mangadex.org')) {
                        const match = inputId.match(/[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}/);
                        if(match) inputId = match[0];
                    } 
                    else if (source === 'mal' && inputId.includes('myanimelist.net')) {
                         const match = inputId.match(/\/manga\/(\d+)/);
                         if(match) { inputId = match[1]; } else { const nums = inputId.match(/\d+/g); if(nums) inputId = nums[nums.length-1]; }
                    }
                }

                const proxyUrl = `fetch_helper.php?source=${source}&id=${inputId}`;
                const response = await fetch(proxyUrl);
                const json = await response.json();

                if (json.error) throw new Error(json.error);

                if (source === 'mangadex') processMangaDexData(json.data);
                else processMalData(json.data);

                alert("Data berhasil diambil!");

            } catch (error) {
                alert("Gagal: " + error.message);
            } finally {
                btn.innerHTML = originalText; 
                btn.disabled = false;
                btn.classList.remove('opacity-70', 'cursor-not-allowed');
            }
        }

        function processMangaDexData(data) {
            const attr = data.attributes;
            const rels = data.relationships;
            document.getElementById('inputTitle').value = attr.title.en || attr.title.ja || Object.values(attr.title)[0];
            document.getElementById('inputDesc').value = (attr.description.en || '').replace(/\[.*?\]/g, '');
            document.getElementById('inputStatus').value = attr.status === 'completed' ? 'Completed' : (attr.status === 'hiatus' ? 'Hiatus' : 'Ongoing');
            document.getElementById('inputYear').value = attr.year || new Date().getFullYear();
            
            let type = 'Manga';
            if(attr.originalLanguage === 'ko') type = 'Manhwa';
            if(attr.originalLanguage === 'zh') type = 'Manhua';
            document.getElementById('inputType').value = type;
            document.getElementById('inputGenres').value = attr.tags.filter(t => t.attributes.group === 'genre').map(t => t.attributes.name.en).join(', ');
            document.getElementById('inputAlt').value = attr.altTitles.map(t => Object.values(t)[0]).slice(0, 5).join(', ');

            const authors = rels.filter(r => r.type === 'author').map(r => r.attributes.name);
            const artists = rels.filter(r => r.type === 'artist').map(r => r.attributes.name);
            const combined = [...new Set([...authors, ...artists])].join(', ');
            document.getElementById('inputAuthor').value = combined;

            const coverRel = rels.find(r => r.type === 'cover_art');
            if(coverRel) {
                const url = `https://uploads.mangadex.org/covers/${data.id}/${coverRel.attributes.fileName}`;
                document.getElementById('fetchedCoverUrl').value = url;
                document.getElementById('previewImg').src = url;
            }
        }

        function processMalData(data) {
            document.getElementById('inputTitle').value = data.title;
            document.getElementById('inputDesc').value = data.synopsis || '';
            document.getElementById('inputStatus').value = data.status === 'Finished' ? 'Completed' : (data.status === 'On Hiatus' ? 'Hiatus' : 'Ongoing');
            if(data.published?.from) document.getElementById('inputYear').value = new Date(data.published.from).getFullYear();
            
            let type = 'Manga';
            if(data.type === 'Manhwa') type = 'Manhwa';
            if(data.type === 'Manhua') type = 'Manhua';
            document.getElementById('inputType').value = type;

            if(data.genres) document.getElementById('inputGenres').value = data.genres.map(g => g.name).join(', ');
            if(data.titles) document.getElementById('inputAlt').value = data.titles.filter(t => t.type !== 'Default').map(t => t.title).join(', ');
            if(data.authors) document.getElementById('inputAuthor').value = data.authors.map(a => a.name.replace(',', '')).join(', ');

            if(data.images?.jpg?.large_image_url) {
                const url = data.images.jpg.large_image_url;
                document.getElementById('fetchedCoverUrl').value = url;
                document.getElementById('previewImg').src = url;
            }
        }
    </script>
</body>
</html>