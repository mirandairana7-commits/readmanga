<?php
// config/database.php

// 1. KONFIGURASI DATABASE
$host = 'localhost';
$user = 'root';  
$pass = '';
$db   = 'readmanga';

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Koneksi Database Gagal: " . mysqli_connect_error());
}

// 2. KONFIGURASI BASE URL (HARDCODE)
// Kita kunci URL ini agar stabil. 
// Jangan tambahkan tanda miring (slash) di akhir link.
$base_url = "http://localhost/readmanga";

// 3. GLOBAL SESSION START
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 4. FUNGSI FORMAT CHAPTER (1.0 -> 01, 1.5 -> 01.5, 10 -> 10)
function formatChapterNumber($num) {
    if ($num === null || $num === '') return '-';
    
    // Ubah jadi float untuk menghilangkan .0 otomatis (contoh: 1.0 jadi 1)
    $floatNum = floatval($num); 
    
    // Cek apakah angkanya bulat (tanpa koma/desimal)
    if (floor($floatNum) == $floatNum) {
        return sprintf("%02d", $floatNum); // Tambah angka 0 di depan jika < 10
    } else {
        // Jika ada desimal (contoh: 1.5), pisahkan angka utama dan desimalnya
        $parts = explode('.', (string)$floatNum);
        return sprintf("%02d", $parts[0]) . '.' . $parts[1];
    }
}
?>