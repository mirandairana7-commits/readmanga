<?php
require_once 'config/database.php';

// === PENGAMANAN ===
// Ganti die() menjadi komentar jika ingin mengizinkan registrasi
die("<div style='color:white;background:#111;padding:20px;text-align:center;font-family:sans-serif;'>Registrasi Publik Dinonaktifkan oleh Administrator.</div>");
// ==================

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($username) || empty($password)) {
        $error = "Semua kolom wajib diisi!";
    } elseif ($password !== $confirm_password) {
        $error = "Konfirmasi password tidak cocok!";
    } else {
        // [UPGRADE KEAMANAN]: Cek duplikat dengan Prepared Statement
        $stmt_check = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ?");
        mysqli_stmt_bind_param($stmt_check, "s", $username);
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_store_result($stmt_check);
        
        if (mysqli_stmt_num_rows($stmt_check) > 0) {
            $error = "Username sudah dipakai!";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $role = 'admin'; // Role otomatis admin
            
            // [UPGRADE KEAMANAN]: Insert user dengan Prepared Statement
            $stmt_ins = mysqli_prepare($conn, "INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($stmt_ins, "sss", $username, $hashed_password, $role);
            
            if (mysqli_stmt_execute($stmt_ins)) {
                $success = "Admin berhasil dibuat! Silakan <a href='login.php' class='underline'>login di sini</a>.";
            } else {
                $error = "Terjadi kesalahan sistem.";
            }
            mysqli_stmt_close($stmt_ins);
        }
        mysqli_stmt_close($stmt_check);
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat Akun Admin - Readmanga</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-gray-800 p-8 rounded-lg shadow-xl border border-gray-700">
        <h2 class="text-2xl font-bold text-center mb-6">Registrasi Admin</h2>
        
        <?php if ($error): ?>
            <div class="bg-red-600/20 border border-red-500 text-red-500 p-3 rounded mb-4 text-sm"><?= $error ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="bg-green-600/20 border border-green-500 text-green-500 p-3 rounded mb-4 text-sm"><?= $success ?></div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-400 mb-1">Username</label>
                <input type="text" name="username" required 
                       class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded focus:outline-none focus:border-indigo-500 transition-colors">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-400 mb-1">Password</label>
                <input type="password" name="password" required 
                       class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded focus:outline-none focus:border-indigo-500 transition-colors">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-400 mb-1">Konfirmasi Password</label>
                <input type="password" name="confirm_password" required 
                       class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded focus:outline-none focus:border-indigo-500 transition-colors">
            </div>
            <button type="submit" 
                    class="w-full py-2 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded transition duration-200">
                Buat Akun Admin
            </button>
        </form>

        <p class="text-center text-sm text-gray-400 mt-6">
            Sudah punya akun? <a href="login.php" class="text-indigo-400 hover:text-indigo-300">Login di sini</a>
        </p>
    </div>
</body>
</html>