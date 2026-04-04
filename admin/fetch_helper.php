<?php
// admin/fetch_helper.php
// 1. TANGKAP OUTPUT YANG TIDAK DIINGINKAN
ob_start();
session_start();
require_once '../config/database.php';

// 2. BERSIHKAN BUFFER & SET HEADER JSON
ob_clean();
header('Content-Type: application/json');

// Keamanan: Hanya admin yang boleh akses
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$source = isset($_GET['source']) ? $_GET['source'] : '';
$id = isset($_GET['id']) ? trim($_GET['id']) : '';

if (empty($source) || empty($id)) {
    echo json_encode(['error' => 'Parameter kurang lengkap']);
    exit();
}

// Fungsi Helper untuk Ambil Setting dari DB
function getApiKey($conn, $key) {
    $safe_key = mysqli_real_escape_string($conn, $key);
    $q = mysqli_query($conn, "SELECT setting_value FROM settings WHERE setting_key = '$safe_key'");
    if(mysqli_num_rows($q) > 0){
        $row = mysqli_fetch_assoc($q);
        return $row['setting_value'];
    }
    return '';
}

// --- LOGIKA MANGADEX ---
if ($source === 'mangadex') {
    $client_id = getApiKey($conn, 'mangadex_client_id');
    
    // Gunakan urlencode untuk keamanan ID
    $safe_id = urlencode($id);
    $url = "https://api.mangadex.org/manga/$safe_id?includes[]=author&includes[]=artist&includes[]=cover_art";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    
    // PENTING UNTUK HOSTING GRATIS: Matikan verifikasi SSL agar tidak error koneksi
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Timeout 30 detik
    
    // Header Wajib MangaDex
    $headers = [
        'User-Agent: Readmanga/1.0 (Linux; Android 10)'
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        echo json_encode(['error' => 'CURL Error: ' . curl_error($ch)]);
    } else {
        curl_close($ch);
        if ($httpCode === 200) {
            echo $result;
        } else {
            // Coba ambil pesan error dari JSON MangaDex
            $json = json_decode($result, true);
            $msg = $json['errors'][0]['detail'] ?? "Gagal mengambil data MangaDex (HTTP $httpCode)";
            echo json_encode(['error' => $msg]);
        }
    }
}

// --- LOGIKA MAL (VIA JIKAN) ---
else if ($source === 'mal') {
    $safe_id = urlencode($id);
    $url = "https://api.jikan.moe/v4/manga/$safe_id/full";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Matikan SSL check
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        echo json_encode(['error' => 'CURL Error: ' . curl_error($ch)]);
    } else {
        curl_close($ch);
        if ($httpCode === 200) {
            echo $result;
        } else {
            echo json_encode(['error' => "Gagal mengambil data Jikan/MAL (HTTP $httpCode). Mungkin ID salah atau Rate Limit."]);
        }
    }
}
?>