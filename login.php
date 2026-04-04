<?php
// login.php
session_start();
require_once 'config/database.php';

// Jika sudah login, cek role
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: " . $base_url . "/admin/index.php");
    } else {
        session_destroy();
        header("Location: " . $base_url . "/login.php");
    }
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // [UPGRADE KEAMANAN]: Menggunakan Prepared Statement
    $stmt = mysqli_prepare($conn, "SELECT id, username, password, role FROM users WHERE username = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $username); // "s" = string
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);

    if ($user && password_verify($password, $user['password'])) {
        if ($user['role'] !== 'admin') {
            $error = "Akses Ditolak! Website ini khusus Administrator.";
        } else {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            
            header("Location: " . $base_url . "/admin/index.php");
            exit();
        }
    } else {
        $error = "Username atau Password salah!";
    }
    mysqli_stmt_close($stmt);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Readmanga</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-[#0f0f0f] text-white flex items-center justify-center min-h-screen p-4 font-sans">
    <div class="w-full max-w-md">
        <div class="bg-gray-900/50 backdrop-blur-xl border border-gray-800 rounded-2xl shadow-2xl p-8">
            <div class="text-center mb-8">
                <div class="bg-indigo-600 w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg shadow-indigo-500/20 rotate-3 transition hover:rotate-0 duration-300">
                    <i class="fas fa-user-shield text-3xl"></i>
                </div>
                <h2 class="text-2xl font-bold tracking-tight">Selamat Datang</h2>
                <p class="text-gray-500 text-sm mt-1">Silakan masuk ke panel kontrol</p>
            </div>

            <?php if ($error): ?>
                <div class="bg-red-500/10 border border-red-500/50 text-red-500 p-4 rounded-xl mb-6 text-sm flex items-center gap-3">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-5">
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2 ml-1">Username</label>
                    <div class="relative">
                        <span class="absolute left-4 top-3.5 text-gray-500"><i class="fas fa-user"></i></span>
                        <input type="text" name="username" required placeholder="Admin Username"
                               class="w-full bg-gray-800 border border-gray-700 text-white rounded-xl py-3 pl-12 pr-4 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition placeholder-gray-600">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2 ml-1">Password</label>
                    <div class="relative">
                        <span class="absolute left-4 top-3.5 text-gray-500"><i class="fas fa-lock"></i></span>
                        <input type="password" name="password" required placeholder="••••••••"
                               class="w-full bg-gray-800 border border-gray-700 text-white rounded-xl py-3 pl-12 pr-4 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition placeholder-gray-600">
                    </div>
                </div>

                <button type="submit" class="w-full bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-500 hover:to-indigo-600 text-white font-bold py-3.5 rounded-xl shadow-lg transition transform hover:-translate-y-0.5">
                    Masuk Panel Admin
                </button>
            </form>
        </div>
        
        <div class="text-center mt-8">
            <a href="index.php" class="text-gray-500 hover:text-indigo-400 text-sm transition font-medium">
                <i class="fas fa-arrow-left mr-2"></i> Kembali ke Beranda
            </a>
        </div>
    </div>
</body>
</html>