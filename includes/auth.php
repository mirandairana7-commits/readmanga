<?php
// includes/auth.php

// Mulai session hanya jika belum dimulai
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Fungsi Cek Login (Hanya mengembalikan True/False)
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Fungsi Cek Admin (Hanya mengembalikan True/False)
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Fungsi Paksa Login (Hanya dipanggil di halaman khusus user/admin)
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

// Fungsi Paksa Admin (Hanya dipanggil di halaman admin)
function requireAdmin() {
    if (!isAdmin()) {
        header("Location: index.php");
        exit();
    }
}
?>