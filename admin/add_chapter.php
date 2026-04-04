<?php
// admin/add_chapter.php
ob_start();
session_start();
require_once '../config/database.php';

// Supaya proses upload banyak gambar tidak putus
set_time_limit(0); 

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// ==========================================
// 1. API AJAX HANDLER (Backend)
// ==========================================
if (isset($_POST['ajax_action'])) {
    ob_clean();
    header('Content-Type: application/json');

    try {
        // A. Create Chapter Database (DIUPGRADE KE PREPARED STATEMENT)
        if ($_POST['ajax_action'] === 'create_chapter') {
            $comic_id = intval($_POST['comic_id']);
            $chap_num = trim($_POST['chapter_number']);
            $title    = trim($_POST['title']);

            if (empty($chap_num)) throw new Exception('Nomor chapter wajib diisi!');

            // Cek Duplikat menggunakan Prepared Statement
            $stmt_cek = mysqli_prepare($conn, "SELECT id FROM chapters WHERE comic_id = ? AND chapter_number = ?");
            mysqli_stmt_bind_param($stmt_cek, "is", $comic_id, $chap_num);
            mysqli_stmt_execute($stmt_cek);
            mysqli_stmt_store_result($stmt_cek);
            if (mysqli_stmt_num_rows($stmt_cek) > 0) throw new Exception('Nomor chapter ini sudah ada!');
            mysqli_stmt_close($stmt_cek);

            // Insert Chapter menggunakan Prepared Statement
            $stmt_ins = mysqli_prepare($conn, "INSERT INTO chapters (comic_id, chapter_number, title) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($stmt_ins, "iss", $comic_id, $chap_num, $title);
            
            if (mysqli_stmt_execute($stmt_ins)) {
                echo json_encode(['success' => true, 'chapter_id' => mysqli_insert_id($conn)]);
            } else {
                throw new Exception('Database Error');
            }
            mysqli_stmt_close($stmt_ins);
            exit;
        }

        // B. Upload ke ImgBB (Gambar Satuan atau URL)
        if ($_POST['ajax_action'] === 'upload_image') {
            $chapter_id = intval($_POST['chapter_id']);
            $order      = intval($_POST['order']);
            $image_data = null; 

            if (!empty($_FILES['file']['tmp_name'])) {
                if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) throw new Exception('Upload Error');
                $image_data = base64_encode(file_get_contents($_FILES['file']['tmp_name']));
            } elseif (!empty($_POST['url'])) {
                $image_data = $_POST['url'];
            }

            if (!$image_data) throw new Exception('Data gambar kosong');

            // Ambil API Key (Rotasi)
            $q_set = mysqli_query($conn, "SELECT setting_value FROM settings WHERE setting_key = 'imgbb_api_keys'");
            $row = mysqli_fetch_assoc($q_set);
            $api_keys = $row ? json_decode($row['setting_value'], true) : [];
            if (empty($api_keys)) throw new Exception('API Key ImgBB belum diatur!');
            
            $apiKey = $api_keys[array_rand($api_keys)];

            // CURL ke ImgBB
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
                $final_url = $json['data']['url'];
                
                // Insert Gambar menggunakan Prepared Statement
                $stmt_img = mysqli_prepare($conn, "INSERT INTO chapter_images (chapter_id, image_path, display_order) VALUES (?, ?, ?)");
                mysqli_stmt_bind_param($stmt_img, "isi", $chapter_id, $final_url, $order);
                
                if (mysqli_stmt_execute($stmt_img)) {
                    echo json_encode(['success' => true, 'url' => $final_url]);
                } else {
                    throw new Exception('DB Insert Gagal');
                }
                mysqli_stmt_close($stmt_img);
            } else {
                throw new Exception($json['error']['message'] ?? 'Gagal upload ke ImgBB');
            }
            exit;
        }

        // C. PROSES UPLOAD FILE ZIP
        if ($_POST['ajax_action'] === 'process_zip') {
            $chapter_id = intval($_POST['chapter_id']);
            
            if (empty($_FILES['zip_file']['tmp_name'])) throw new Exception('File ZIP tidak ditemukan');
            
            $zip_path = $_FILES['zip_file']['tmp_name'];
            $zip = new ZipArchive;
            
            if ($zip->open($zip_path) === TRUE) {
                $extract_path = '../uploads/temp/' . uniqid();
                if (!is_dir($extract_path)) mkdir($extract_path, 0777, true);
                
                $zip->extractTo($extract_path);
                $zip->close();
                
                // Ambil API Keys
                $q_set = mysqli_query($conn, "SELECT setting_value FROM settings WHERE setting_key = 'imgbb_api_keys'");
                $row = mysqli_fetch_assoc($q_set);
                $api_keys = $row ? json_decode($row['setting_value'], true) : [];
                if (empty($api_keys)) throw new Exception('API Key ImgBB belum diatur!');
                
                // Urutkan gambar
                $files = scandir($extract_path);
                $images = [];
                foreach ($files as $file) {
                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                        $images[] = $file;
                    }
                }
                natsort($images); // Urutkan 1, 2, 10 secara benar
                
                $success_count = 0;
                $display_order = 1;
                $stmt_img = mysqli_prepare($conn, "INSERT INTO chapter_images (chapter_id, image_path, display_order) VALUES (?, ?, ?)");
                
                foreach ($images as $img_file) {
                    $img_path = $extract_path . '/' . $img_file;
                    $image_data = base64_encode(file_get_contents($img_path));
                    $apiKey = $api_keys[array_rand($api_keys)];
                    
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
                        $final_url = $json['data']['url'];
                        mysqli_stmt_bind_param($stmt_img, "isi", $chapter_id, $final_url, $display_order);
                        mysqli_stmt_execute($stmt_img);
                        $success_count++;
                        $display_order++;
                    }
                    unlink($img_path);
                }
                rmdir($extract_path);
                echo json_encode(['success' => true, 'count' => $success_count]);
            } else {
                throw new Exception('Gagal membuka file ZIP');
            }
            exit;
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'msg' => $e->getMessage()]);
        exit;
    }
}

// ==========================================
// 2. TAMPILAN HALAMAN
// ==========================================
$slug = isset($_GET['slug']) ? mysqli_real_escape_string($conn, $_GET['slug']) : '';
$comic = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM comics WHERE slug = '$slug'"));

if (!$comic) {
    echo "<div style='padding:20px; color:white; background:#111; text-align:center;'>Komik tidak ditemukan. Pastikan URL benar: ?slug=judul-komik</div>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Chapter - <?= htmlspecialchars($comic['title']) ?></title>
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
                <a href="index.php" class="flex items-center px-4 py-3 text-gray-400 hover:bg-gray-700 hover:text-white rounded-md transition"><i class="fas fa-book mr-3 w-5 text-center"></i> Daftar Komik</a>
                <a href="settings.php" class="flex items-center px-4 py-3 text-gray-400 hover:bg-gray-700 hover:text-white rounded-md transition"><i class="fas fa-cogs mr-3 w-5 text-center"></i> Pengaturan API</a>
                <a href="../index.php" class="flex items-center px-4 py-3 text-gray-400 hover:bg-gray-700 hover:text-white rounded-md transition"><i class="fas fa-home mr-3 w-5 text-center"></i> Lihat Website</a>
            </nav>
        </aside>

        <div class="flex-1 flex flex-col overflow-hidden relative w-full">
            <header class="md:hidden flex items-center justify-between p-4 bg-gray-800 border-b border-gray-700 h-16 shrink-0">
                <div class="flex items-center gap-3">
                    <button onclick="toggleSidebar()" class="text-gray-300 hover:text-white focus:outline-none p-2 rounded hover:bg-gray-700"><i class="fas fa-bars text-xl"></i></button>
                    <span class="font-bold text-lg">Upload Chapter</span>
                </div>
            </header>

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-900 p-4 md:p-8">
                <div class="max-w-4xl mx-auto">
                    
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h1 class="text-2xl font-bold text-white">Upload Chapter Baru</h1>
                            <p class="text-gray-400 text-sm mt-1">Komik: <span class="text-indigo-400 font-bold"><?= htmlspecialchars($comic['title']) ?></span></p>
                        </div>
                        <a href="index.php" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded transition flex items-center gap-2">
                            <i class="fas fa-arrow-left"></i> Kembali
                        </a>
                    </div>

                    <form id="uploadForm" onsubmit="return false;">
                        
                        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700 shadow-lg mb-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Nomor Chapter</label>
                                    <input type="number" id="chapNum" step="0.1" class="w-full bg-gray-900 border border-gray-600 rounded px-3 py-2 focus:border-indigo-500 outline-none text-white" placeholder="Contoh: 10">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Judul Chapter (Opsional)</label>
                                    <input type="text" id="chapTitle" class="w-full bg-gray-900 border border-gray-600 rounded px-3 py-2 focus:border-indigo-500 outline-none text-white" placeholder="Contoh: The Beginning">
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700 shadow-lg mb-6">
                            <h3 class="font-bold text-lg mb-4 border-b border-gray-700 pb-2 text-white">Pilih Gambar</h3>
                            
                            <div class="flex gap-4 mb-4 border-b border-gray-700">
                                <button type="button" onclick="switchTab('file')" id="btn-tab-file" class="text-sm font-bold text-indigo-400 border-b-2 border-indigo-400 pb-2 focus:outline-none">Upload File</button>
                                <button type="button" onclick="switchTab('url')" id="btn-tab-url" class="text-sm font-bold text-gray-500 hover:text-white pb-2 focus:outline-none">Paste URL</button>
                                <button type="button" onclick="switchTab('zip')" id="btn-tab-zip" class="text-sm font-bold text-gray-500 hover:text-white pb-2 focus:outline-none">Upload ZIP</button>
                            </div>

                            <div id="tab-file" class="block">
                                <div class="border-2 border-dashed border-gray-600 rounded-xl p-8 text-center hover:border-indigo-500 hover:bg-gray-700/30 transition cursor-pointer relative group">
                                    <input type="file" id="fileInput" multiple accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" onchange="updateFileCount(this, 'file-label')">
                                    <div class="text-gray-400 group-hover:text-indigo-400 transition">
                                        <i class="fas fa-cloud-upload-alt text-4xl mb-3"></i>
                                        <p id="file-label" class="text-sm font-medium">Klik untuk memilih gambar</p>
                                        <p class="text-xs text-gray-500 mt-1">Support JPG, PNG, WEBP</p>
                                    </div>
                                </div>
                            </div>

                            <div id="tab-url" class="hidden">
                                <textarea id="urlInput" rows="5" class="w-full bg-gray-900 border border-gray-600 rounded p-3 text-sm text-indigo-300 font-mono focus:border-indigo-500 outline-none" placeholder="https://site.com/img1.jpg&#10;https://site.com/img2.jpg"></textarea>
                                <p class="text-xs text-gray-500 mt-2">Masukkan satu link per baris.</p>
                            </div>

                            <div id="tab-zip" class="hidden">
                                <div class="border-2 border-dashed border-gray-600 rounded-xl p-8 text-center hover:border-indigo-500 hover:bg-gray-700/30 transition cursor-pointer relative group">
                                    <input type="file" id="zipInput" accept=".zip" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" onchange="updateFileCount(this, 'zip-label', true)">
                                    <div class="text-gray-400 group-hover:text-indigo-400 transition">
                                        <i class="fas fa-file-archive text-4xl mb-3 text-yellow-500"></i>
                                        <p id="zip-label" class="text-sm font-medium">Klik untuk memilih file .ZIP</p>
                                        <p class="text-xs text-gray-500 mt-1">Pastikan gambar langsung ada di dalam ZIP, bukan di dalam folder</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="progressArea" class="hidden bg-gray-800 rounded-xl p-6 border border-gray-700 shadow-lg mb-6">
                            <h3 class="font-bold text-white mb-2" id="progressTitle">Sedang Mengupload...</h3>
                            <div class="w-full bg-gray-700 rounded-full h-4 mb-2 overflow-hidden" id="barContainer">
                                <div id="progressBar" class="bg-indigo-600 h-4 rounded-full transition-all duration-300" style="width: 0%"></div>
                            </div>
                            <p class="text-xs text-gray-400" id="progressText">Memulai...</p>
                            <div id="errorLog" class="mt-4 hidden p-3 bg-red-900/20 border border-red-500/50 rounded text-xs text-red-300 max-h-32 overflow-y-auto"></div>
                        </div>

                        <button type="button" onclick="startUpload()" id="btnUpload" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 rounded-lg shadow-lg transition transform hover:-translate-y-1 flex items-center justify-center gap-2">
                            <i class="fas fa-rocket"></i> Mulai Proses Upload
                        </button>
                    </form>

                </div>
            </main>
        </div>
    </div>

    <script>
        const comicId = <?= $comic['id'] ?>;
        const redirectUrl = "../comic.php?slug=<?= $comic['slug'] ?>"; 

        let filesQueue = [];
        let totalFiles = 0;
        let processed = 0;
        let successCount = 0;
        let currentTab = 'file';

        function toggleSidebar() {
            const sb = document.getElementById('adminSidebar');
            const ov = document.getElementById('sidebarOverlay');
            sb.classList.toggle('-translate-x-full');
            ov.classList.toggle('hidden');
        }

        function switchTab(tab) {
            currentTab = tab;
            document.getElementById('tab-file').className = tab === 'file' ? 'block' : 'hidden';
            document.getElementById('tab-url').className = tab === 'url' ? 'block' : 'hidden';
            document.getElementById('tab-zip').className = tab === 'zip' ? 'block' : 'hidden';
            
            const btnFile = document.getElementById('btn-tab-file');
            const btnUrl = document.getElementById('btn-tab-url');
            const btnZip = document.getElementById('btn-tab-zip');
            
            // Reset style
            [btnFile, btnUrl, btnZip].forEach(btn => {
                btn.className = 'text-sm font-bold text-gray-500 hover:text-white pb-2 focus:outline-none';
            });

            // Aktifkan style
            document.getElementById(`btn-tab-${tab}`).className = 'text-sm font-bold text-indigo-400 border-b-2 border-indigo-400 pb-2 focus:outline-none';
        }

        function updateFileCount(input, labelId, isZip = false) {
            const label = document.getElementById(labelId);
            if (input.files.length > 0) {
                if (isZip) {
                    label.innerHTML = `<span class="text-yellow-400 font-bold">${input.files[0].name} Terpilih</span>`;
                } else {
                    label.innerHTML = `<span class="text-green-400 font-bold">${input.files.length} Gambar Dipilih</span>`;
                }
            } else {
                label.innerText = isZip ? "Klik untuk memilih file .ZIP" : "Klik untuk memilih gambar";
            }
        }

        async function startUpload() {
            const chapNum = document.getElementById('chapNum').value;
            const chapTitle = document.getElementById('chapTitle').value;

            if (!chapNum) return alert("Nomor Chapter wajib diisi!");

            // Validasi input berdasarkan tab aktif
            if (currentTab === 'file' && document.getElementById('fileInput').files.length === 0) return alert("Pilih minimal satu gambar!");
            if (currentTab === 'url' && !document.getElementById('urlInput').value.trim()) return alert("Masukkan minimal satu URL!");
            if (currentTab === 'zip' && document.getElementById('zipInput').files.length === 0) return alert("Pilih file ZIP!");

            const btn = document.getElementById('btnUpload');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
            btn.classList.add('opacity-50', 'cursor-not-allowed');
            
            document.getElementById('progressArea').classList.remove('hidden');
            document.getElementById('errorLog').classList.add('hidden');
            document.getElementById('errorLog').innerHTML = '';

            try {
                // 1. Buat Chapter
                const formData = new FormData();
                formData.append('ajax_action', 'create_chapter');
                formData.append('comic_id', comicId);
                formData.append('chapter_number', chapNum);
                formData.append('title', chapTitle);

                const res = await fetch('add_chapter.php', { method: 'POST', body: formData });
                const text = await res.text();
                let json;
                try { json = JSON.parse(text); } 
                catch(e) { throw new Error("Server Error (HTML): " + text.substring(0, 50)); }

                if (!json.success) throw new Error(json.msg);
                
                const chapterId = json.chapter_id;
                
                // 2. Upload Gambar / ZIP
                if (currentTab === 'zip') {
                    // LOGIKA UPLOAD ZIP
                    document.getElementById('barContainer').classList.add('hidden'); // Sembunyikan progress bar
                    document.getElementById('progressText').innerHTML = `<i class="fas fa-cog fa-spin text-indigo-400 mr-2"></i> Server sedang mengekstrak dan mengirim gambar ke ImgBB. Mohon tunggu...`;
                    
                    const zipData = new FormData();
                    zipData.append('ajax_action', 'process_zip');
                    zipData.append('chapter_id', chapterId);
                    zipData.append('zip_file', document.getElementById('zipInput').files[0]);

                    const zipRes = await fetch('add_chapter.php', { method: 'POST', body: zipData });
                    const zipText = await zipRes.text();
                    let zipJson = JSON.parse(zipText);

                    if (zipJson.success) {
                        successCount = zipJson.count;
                    } else {
                        throw new Error(zipJson.msg);
                    }

                } else {
                    // LOGIKA UPLOAD SATUAN (FILE / URL)
                    filesQueue = [];
                    if (currentTab === 'file') {
                        for (let f of document.getElementById('fileInput').files) filesQueue.push({type: 'file', data: f});
                    } else {
                        const urls = document.getElementById('urlInput').value.split('\n').map(u => u.trim()).filter(u => u);
                        for (let u of urls) filesQueue.push({type: 'url', data: u});
                    }

                    totalFiles = filesQueue.length;
                    processed = 0;
                    successCount = 0;
                    document.getElementById('barContainer').classList.remove('hidden');
                    updateProgress();

                    for (let i = 0; i < totalFiles; i++) {
                        await uploadSingleImage(chapterId, filesQueue[i], i + 1);
                    }
                }

                btn.innerHTML = '<i class="fas fa-check"></i> Selesai!';
                alert("Upload Selesai! " + successCount + " gambar berhasil disimpan.");
                window.location.href = redirectUrl;

            } catch (err) {
                alert("Gagal: " + err.message);
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-rocket"></i> Coba Lagi';
                btn.classList.remove('opacity-50', 'cursor-not-allowed');
            }
        }

        async function uploadSingleImage(chapterId, item, order) {
            const formData = new FormData();
            formData.append('ajax_action', 'upload_image');
            formData.append('chapter_id', chapterId);
            formData.append('order', order);

            if (item.type === 'file') formData.append('file', item.data);
            else formData.append('url', item.data);

            try {
                const res = await fetch('add_chapter.php', { method: 'POST', body: formData });
                const text = await res.text();
                let json;
                try { json = JSON.parse(text); } catch(e) { throw new Error("Invalid JSON"); }

                if (json.success) {
                    successCount++;
                } else {
                    logError(`Gambar #${order} Gagal: ${json.msg}`);
                }
            } catch (e) {
                logError(`Gambar #${order} Error: ${e.message}`);
            } finally {
                processed++;
                updateProgress();
            }
        }

        function updateProgress() {
            const pct = Math.round((processed / totalFiles) * 100);
            document.getElementById('progressBar').style.width = pct + '%';
            document.getElementById('progressText').innerText = `${successCount} / ${totalFiles} Berhasil diupload (${pct}%)`;
        }

        function logError(msg) {
            const div = document.getElementById('errorLog');
            div.classList.remove('hidden');
            div.innerHTML += `<div class="mb-1 border-b border-red-500/20 pb-1 last:border-0">${msg}</div>`;
        }
    </script>
</body>
</html>