<?php
require_once 'config/database.php';

// === PENGAMANAN ===
// Hapus tanda // di bawah ini jika ingin MENGAKTIFKAN registrasi sementara
die("<div style='color:white;background:#111;padding:20px;text-align:center;'>Registrasi Publik Dinonaktifkan oleh Administrator.</div>");
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
        $safe_user = mysqli_real_escape_string($conn, $username);
        $check = mysqli_query($conn, "SELECT id FROM users WHERE username = '$safe_user'");
        
        if (mysqli_num_rows($check) > 0) {
            $error = "Username sudah dipakai!";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // ROLE OTOMATIS JADI 'ADMIN' (Karena ini private server)
            $query = "INSERT INTO users (username, password, role) VALUES ('$safe_user', '$hashed_password', 'admin')";
            
            if (mysqli_query($conn, $query)) {
                $success = "Admin berhasil dibuat! Silakan <a href='login.php' class='text-indigo-400 font-bold'>Login disini</a>.";
            } else {
                $error = "Gagal mendaftar: " . mysqli_error($conn);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat Admin - Readmanga</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white flex items-center justify-center min-h-screen">
    <div class="w-full max-w-md p-8 space-y-6 bg-gray-800 rounded-xl shadow-2xl border border-gray-700">
        <div class="text-center">
            <h1 class="text-2xl font-bold text-indigo-500">Registrasi Admin</h1>
            <p class="text-gray-400 text-sm mt-1">Buat akun administrator baru</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-500/10 border border-red-500 text-red-500 p-3 rounded text-sm text-center">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="bg-green-500/10 border border-green-500 text-green-500 p-3 rounded text-sm text-center">
                <?= $success ?>
            </div>
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

        <p class="text-center text-sm text-gray-400">
            Sudah punya akun? <a href="login.php" class="text-indigo-400 hover:underline">Login</a>
        </p>
    </div>
</body>
</html>