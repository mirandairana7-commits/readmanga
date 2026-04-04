<?php
// login.php
session_start();
require_once 'config/database.php';

// Jika sudah login, cek role
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: " . $base_url . "/admin/index.php");
    } else {
        // Jika user biasa terlanjur login, logoutkan paksa (karena ini web khusus admin)
        session_destroy();
        header("Location: " . $base_url . "/login.php");
    }
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];

    $query = "SELECT * FROM users WHERE username = '$username' LIMIT 1";
    $result = mysqli_query($conn, $query);
    $user = mysqli_fetch_assoc($result);

    if ($user && password_verify($password, $user['password'])) {
        // --- LOGIKA KHUSUS ADMIN ---
        if ($user['role'] !== 'admin') {
            $error = "Akses Ditolak! Website ini khusus Administrator.";
        } else {
            // Set Session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            
            // Redirect ke Panel Admin
            header("Location: " . $base_url . "/admin/index.php");
            exit();
        }
    } else {
        $error = "Username atau password salah!";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - Readmanga</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-[#0a0a0a] text-gray-200 font-sans antialiased min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-black text-transparent bg-clip-text bg-gradient-to-r from-indigo-400 to-cyan-400 mb-2">Readmanga</h1>
            <p class="text-gray-500 text-sm">Restricted Area: Administrator Only</p>
        </div>

        <div class="bg-gray-900 border border-gray-800 rounded-2xl p-8 shadow-2xl shadow-black">
            
            <?php if ($error): ?>
                <div class="bg-red-500/10 border border-red-500/50 text-red-400 p-3 rounded-lg mb-6 text-sm text-center font-bold">
                    <i class="fas fa-exclamation-circle mr-2"></i> <?= $error ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
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
        
        <div class="text-center mt-8 text-xs text-gray-600">
            &copy; 2026 Readmanga System.
        </div>
    </div>

</body>
</html>