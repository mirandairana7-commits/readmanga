<?php
// BUFFERING: Tangkap output tidak diinginkan
ob_start();
session_start();
require_once '../config/database.php';

// Cek Admin (Gunakan Base URL untuk redirect)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    if (isset($_POST['ajax_action'])) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'msg' => 'Sesi habis, silakan login ulang.']);
        exit;
    }
    header("Location: " . $base_url . "/login.php");
    exit();
}

// ==========================================
// BAGIAN 1: API AJAX HANDLER
// ==========================================
if (isset($_POST['ajax_action'])) {
    ob_clean(); // Bersihkan buffer sebelum kirim JSON
    header('Content-Type: application/json');

    try {
        // A. Hapus Gambar
        if ($_POST['ajax_action'] === 'delete_image') {
            $img_id = intval($_POST['image_id']);
            // Hapus dari database saja (ImgBB tidak menyediakan API delete via API key standar dengan mudah)
            if (mysqli_query($conn, "DELETE FROM chapter_images WHERE id = $img_id")) {
                echo json_encode(['success' => true]);
            } else {
                throw new Exception("Gagal menghapus data dari database.");
            }
            exit;
        }

        // B. Update Info Chapter
        if ($_POST['ajax_action'] === 'update_info') {
            $id = intval($_POST['chapter_id']);
            $num = mysqli_real_escape_string($conn, $_POST['number']);
            $title = mysqli_real_escape_string($conn, $_POST['title']);
            
            if (mysqli_query($conn, "UPDATE chapters SET chapter_number='$num', title='$title' WHERE id=$id")) {
                echo json_encode(['success' => true]);
            } else {
                throw new Exception("Gagal mengupdate informasi chapter.");
            }
            exit;
        }

        // C. Upload Gambar Baru ke ImgBB
        if ($_POST['ajax_action'] === 'upload_image') {
            $chapter_id = intval($_POST['chapter_id']);
            
            // Ambil urutan gambar terakhir
            $res = mysqli_query($conn, "SELECT MAX(display_order) as max_o FROM chapter_images WHERE chapter_id=$chapter_id");
            $row = mysqli_fetch_assoc($res);
            $order = ($row['max_o'] ?? 0) + 1;

            $image_data = null;
            if (!empty($_FILES['file']['tmp_name'])) {
                if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) throw new Exception("Error Upload File: " . $_FILES['file']['error']);
                $image_data = base64_encode(file_get_contents($_FILES['file']['tmp_name']));
            } elseif (!empty($_POST['url'])) {
                $image_data = $_POST['url'];
            }

            if (!$image_data) throw new Exception("Data gambar kosong/tidak valid.");

            // Ambil API Key
            $q_set = mysqli_query($conn, "SELECT setting_value FROM settings WHERE setting_key = 'imgbb_api_keys'");
            $row_set = mysqli_fetch_assoc($q_set);
            $keys = $row_set ? json_decode($row_set['setting_value'], true) : [];
            
            if (empty($keys)) throw new Exception("API Key ImgBB belum diatur di Settings.");
            
            $apiKey = $keys[array_rand($keys)]; // Pilih key acak

            // CURL Request
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.imgbb.com/1/upload');
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, ['key' => $apiKey, 'image' => $image_data]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Penting untuk hosting gratis
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            
            $result = curl_exec($ch);
            
            if(curl_errno($ch)){
                throw new Exception("CURL Error: " . curl_error($ch));
            }
            curl_close($ch);

            $json = json_decode($result, true);

            if (isset($json['data']['url'])) {
                $url = $json['data']['url'];
                mysqli_query($conn, "INSERT INTO chapter_images (chapter_id, image_path, display_order) VALUES ($chapter_id, '$url', $order)");
                echo json_encode(['success' => true, 'url' => $url, 'id' => mysqli_insert_id($conn), 'order' => $order]);
            } else {
                throw new Exception($json['error']['message'] ?? "Gagal upload ke ImgBB.");
            }
            exit;
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'msg' => $e->getMessage()]);
        exit;
    }
}

// ==========================================
// BAGIAN 2: TAMPILAN HALAMAN
// ==========================================
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$query = "SELECT c.*, cm.title as comic_title, cm.id as comic_id FROM chapters c JOIN comics cm ON c.comic_id = cm.id WHERE c.id = $id";
$chapter = mysqli_fetch_assoc(mysqli_query($conn, $query));

if (!$chapter) die("Chapter tidak ditemukan.");

$images = mysqli_query($conn, "SELECT * FROM chapter_images WHERE chapter_id = $id ORDER BY display_order ASC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Chapter - <?= htmlspecialchars($chapter['comic_title']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>const BASE_URL = "<?= $base_url ?>";</script>
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
                <a href="<?= $base_url ?>/admin/index.php" class="flex items-center px-4 py-3 text-white bg-indigo-600 rounded-md shadow-lg transition"><i class="fas fa-book mr-3 w-5 text-center"></i> Daftar Komik</a>
                <a href="<?= $base_url ?>/admin/settings.php" class="flex items-center px-4 py-3 text-gray-400 hover:bg-gray-700 hover:text-white rounded-md transition"><i class="fas fa-cogs mr-3 w-5 text-center"></i> Pengaturan API</a>
                <a href="<?= $base_url ?>/index.php" class="flex items-center px-4 py-3 text-gray-400 hover:bg-gray-700 hover:text-white rounded-md transition"><i class="fas fa-home mr-3 w-5 text-center"></i> Lihat Website</a>
            </nav>
        </aside>

        <div class="flex-1 flex flex-col overflow-hidden relative w-full">
            <header class="md:hidden flex items-center justify-between p-4 bg-gray-800 border-b border-gray-700 h-16 shrink-0">
                <div class="flex items-center gap-3">
                    <button onclick="toggleSidebar()" class="text-gray-300 hover:text-white focus:outline-none p-2 rounded hover:bg-gray-700"><i class="fas fa-bars text-xl"></i></button>
                    <span class="font-bold text-lg">Edit Chapter</span>
                </div>
            </header>

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-900 p-4 md:p-8">
                <div class="max-w-6xl mx-auto">
                    
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h1 class="text-2xl font-bold text-white">Edit Chapter</h1>
                            <p class="text-gray-400 text-sm mt-1">Komik: <span class="text-indigo-400 font-bold"><?= htmlspecialchars($chapter['comic_title']) ?></span></p>
                        </div>
                        <a href="<?= $base_url ?>/admin/edit_comic.php?id=<?= $chapter['comic_id'] ?>" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded transition flex items-center gap-2">
                            <i class="fas fa-arrow-left"></i> Kembali
                        </a>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        
                        <div class="space-y-6">
                            <div class="bg-gray-800 rounded-xl p-6 border border-gray-700 shadow-lg">
                                <h3 class="font-bold text-lg mb-4 border-b border-gray-700 pb-2 text-white">Info Dasar</h3>
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Nomor</label>
                                        <input type="number" id="editNum" step="0.1" value="<?= $chapter['chapter_number'] ?>" class="w-full bg-gray-900 border border-gray-600 rounded px-3 py-2 text-white focus:border-indigo-500 outline-none">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Judul</label>
                                        <input type="text" id="editTitle" value="<?= htmlspecialchars($chapter['title']) ?>" class="w-full bg-gray-900 border border-gray-600 rounded px-3 py-2 text-white focus:border-indigo-500 outline-none">
                                    </div>
                                    <button type="button" onclick="updateInfo()" id="btnSaveInfo" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-2 rounded font-bold transition flex items-center justify-center gap-2">
                                        <i class="fas fa-save"></i> Simpan Info
                                    </button>
                                </div>
                            </div>
                            
                            <div class="bg-gray-800 rounded-xl p-6 border border-gray-700 shadow-lg">
                                <h3 class="font-bold text-lg mb-4 border-b border-gray-700 pb-2 text-white">Tambah Gambar</h3>
                                <div class="flex gap-2 mb-3">
                                    <button type="button" onclick="switchTab('file')" id="btn-tab-file" class="text-xs bg-indigo-600 text-white px-3 py-1 rounded">File</button>
                                    <button type="button" onclick="switchTab('url')" id="btn-tab-url" class="text-xs bg-gray-700 text-gray-300 px-3 py-1 rounded hover:bg-gray-600">Link</button>
                                </div>
                                <div id="add-file" class="block">
                                    <input type="file" id="newFile" multiple accept="image/*" class="w-full text-xs text-gray-400 file:mr-2 file:py-1 file:px-2 file:rounded file:bg-gray-700 file:text-white file:border-0 hover:file:bg-gray-600 cursor-pointer">
                                </div>
                                <div id="add-url" class="hidden">
                                    <textarea id="newUrl" rows="3" class="w-full bg-gray-900 border border-gray-600 rounded p-2 text-xs text-white focus:border-indigo-500 outline-none" placeholder="Paste URL..."></textarea>
                                </div>
                                <div id="uploadStatus" class="mt-3 text-xs text-indigo-400 italic hidden font-bold animate-pulse">Sedang mengupload...</div>
                                <button type="button" onclick="uploadImages()" id="btnAddImg" class="w-full bg-green-600 hover:bg-green-700 text-white py-2 rounded font-bold transition text-sm mt-4 flex items-center justify-center gap-2">
                                    <i class="fas fa-cloud-upload-alt"></i> Upload
                                </button>
                            </div>
                        </div>

                        <div class="lg:col-span-2">
                            <div class="bg-gray-800 rounded-xl p-6 border border-gray-700 shadow-lg">
                                <h3 class="font-bold text-lg mb-4 border-b border-gray-700 pb-2 flex justify-between items-center text-white">
                                    <span>Galeri Chapter</span>
                                    <span class="text-xs bg-gray-700 px-2 py-1 rounded text-gray-300" id="imgCount"><?= mysqli_num_rows($images) ?> Gambar</span>
                                </h3>
                                
                                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 max-h-[600px] overflow-y-auto custom-scrollbar p-1" id="imageGrid">
                                    <?php while ($img = mysqli_fetch_assoc($images)): ?>
                                        <div class="relative group bg-gray-900 border border-gray-600 rounded-lg overflow-hidden shadow-sm hover:shadow-md transition" id="img-<?= $img['id'] ?>">
                                            <div class="aspect-[2/3] w-full bg-gray-800">
                                                <img src="<?= $img['image_path'] ?>" class="w-full h-full object-cover">
                                            </div>
                                            <div class="absolute inset-0 bg-black/80 flex items-center justify-center opacity-0 group-hover:opacity-100 transition duration-200">
                                                <button type="button" onclick="deleteImage(<?= $img['id'] ?>)" class="bg-red-600 hover:bg-red-700 text-white p-2 rounded-full shadow-lg transform hover:scale-110 transition">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </div>
                                            <div class="absolute top-0 left-0 bg-black/60 px-2 py-0.5 text-[10px] text-white font-mono rounded-br">#<?= $img['display_order'] ?></div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                                <p id="emptyMsg" class="text-gray-500 text-center py-8 text-sm <?= mysqli_num_rows($images) > 0 ? 'hidden' : '' ?>">
                                    Belum ada gambar. Silakan upload.
                                </p>
                            </div>
                        </div>

                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        const chapterId = <?= $id ?>;
        // Gunakan BASE_URL untuk fetch agar path selalu benar
        const ajaxEndpoint = BASE_URL + "/admin/edit_chapter.php";

        function toggleSidebar() {
            const sb = document.getElementById('adminSidebar');
            const ov = document.getElementById('sidebarOverlay');
            sb.classList.toggle('-translate-x-full');
            ov.classList.toggle('hidden');
        }

        function switchTab(mode) {
            document.getElementById('add-file').className = mode === 'file' ? 'block' : 'hidden';
            document.getElementById('add-url').className = mode === 'url' ? 'block' : 'hidden';
            
            const btnFile = document.getElementById('btn-tab-file');
            const btnUrl = document.getElementById('btn-tab-url');
            
            if(mode === 'file') {
                btnFile.className = "text-xs bg-indigo-600 text-white px-3 py-1 rounded shadow";
                btnUrl.className = "text-xs bg-gray-700 text-gray-300 px-3 py-1 rounded hover:bg-gray-600";
            } else {
                btnFile.className = "text-xs bg-gray-700 text-gray-300 px-3 py-1 rounded hover:bg-gray-600";
                btnUrl.className = "text-xs bg-indigo-600 text-white px-3 py-1 rounded shadow";
            }
        }

        async function updateInfo() {
            const btn = document.getElementById('btnSaveInfo');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
            btn.disabled = true;
            
            const fd = new FormData();
            fd.append('ajax_action', 'update_info');
            fd.append('chapter_id', chapterId);
            fd.append('number', document.getElementById('editNum').value);
            fd.append('title', document.getElementById('editTitle').value);
            
            try {
                const res = await fetch(ajaxEndpoint, {method:'POST', body:fd});
                const json = await res.json();
                if(json.success) alert("Info tersimpan!"); else alert("Gagal: " + json.msg);
            } catch(e) { alert("Error koneksi!"); }
            
            btn.innerHTML = '<i class="fas fa-save"></i> Simpan Info';
            btn.disabled = false;
        }

        async function deleteImage(id) {
            if(!confirm("Hapus gambar ini?")) return;
            
            // UI Feedback instan
            const el = document.getElementById('img-' + id);
            el.style.opacity = '0.5';

            const fd = new FormData();
            fd.append('ajax_action', 'delete_image');
            fd.append('image_id', id);
            
            try {
                const res = await fetch(ajaxEndpoint, {method:'POST', body:fd});
                const json = await res.json();
                if(json.success) {
                    el.remove();
                    updateCount(-1);
                } else {
                    alert("Gagal hapus: " + json.msg);
                    el.style.opacity = '1';
                }
            } catch(e) { 
                alert("Error koneksi!"); 
                el.style.opacity = '1';
            }
        }

        async function uploadImages() {
            const fileIn = document.getElementById('newFile');
            const urlIn = document.getElementById('newUrl');
            const status = document.getElementById('uploadStatus');
            const btn = document.getElementById('btnAddImg');
            
            let queue = [];
            if(!document.getElementById('add-file').classList.contains('hidden')) {
                if(fileIn.files.length > 0) for(let f of fileIn.files) queue.push({type:'file', data:f});
            } else {
                if(urlIn.value.trim()) {
                    const urls = urlIn.value.split('\n');
                    for(let u of urls) if(u.trim()) queue.push({type:'url', data:u.trim()});
                }
            }

            if(queue.length === 0) return alert("Pilih gambar dulu");

            btn.disabled = true;
            btn.classList.add('opacity-50', 'cursor-not-allowed');
            status.classList.remove('hidden');
            
            for(let i=0; i<queue.length; i++) {
                status.innerText = `Mengupload ${i+1} dari ${queue.length}...`;
                const item = queue[i];
                const fd = new FormData();
                fd.append('ajax_action', 'upload_image');
                fd.append('chapter_id', chapterId);
                
                if(item.type === 'file') fd.append('file', item.data); else fd.append('url', item.data);

                try {
                    const res = await fetch(ajaxEndpoint, {method:'POST', body:fd});
                    const json = await res.json();
                    if(json.success) {
                        appendImageToGrid(json.id, json.url, json.order);
                        updateCount(1);
                    } else {
                        console.error("Gagal: " + json.msg);
                    }
                } catch(e) { console.error("Error Fetch"); }
            }
            status.innerText = "Selesai!";
            setTimeout(() => { 
                status.classList.add('hidden'); 
                btn.disabled = false; 
                btn.classList.remove('opacity-50', 'cursor-not-allowed');
                fileIn.value=''; urlIn.value=''; 
            }, 1000);
        }

        function appendImageToGrid(id, url, order) {
            document.getElementById('emptyMsg').classList.add('hidden');
            const div = document.createElement('div');
            div.className = 'relative group bg-gray-900 border border-gray-600 rounded-lg overflow-hidden shadow-sm hover:shadow-md transition';
            div.id = 'img-' + id;
            div.innerHTML = `
                <div class="aspect-[2/3] w-full bg-gray-800"><img src="${url}" class="w-full h-full object-cover"></div>
                <div class="absolute inset-0 bg-black/80 flex items-center justify-center opacity-0 group-hover:opacity-100 transition duration-200">
                    <button type="button" onclick="deleteImage(${id})" class="bg-red-600 hover:bg-red-700 text-white p-2 rounded-full shadow-lg transform hover:scale-110 transition"><i class="fas fa-trash-alt"></i></button>
                </div>
                <div class="absolute top-0 left-0 bg-black/60 px-2 py-0.5 text-[10px] text-white font-mono rounded-br">#${order}</div>
            `;
            document.getElementById('imageGrid').appendChild(div);
        }

        function updateCount(change) {
            const el = document.getElementById('imgCount');
            let count = parseInt(el.innerText) + change;
            el.innerText = count + " Gambar";
            if(count === 0) document.getElementById('emptyMsg').classList.remove('hidden');
        }
    </script>
</body>
</html>