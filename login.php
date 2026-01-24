<?php
session_start();

// Cek jika sudah login, langsung ke index
if(isset($_SESSION['status']) && $_SESSION['status'] == "login"){
    header("location:index.php");
    exit;
}

$pesan = "";

// Logic Login Sederhana (Hardcoded untuk kemudahan)
// Anda bisa mengubahnya menggunakan Database jika perlu
if(isset($_POST['login'])){
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Username: admin, Password: admin123
    if($username == "admin" && $password == "admin123"){
        $_SESSION['username'] = $username;
        $_SESSION['status'] = "login";
        header("location:index.php");
    } else {
        $pesan = "Username atau Password salah!";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Administrator</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-200 h-screen flex items-center justify-center font-sans">

    <div class="bg-white p-8 rounded-xl shadow-2xl w-full max-w-sm border-t-4 border-blue-600">
        <div class="text-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Login Admin</h1>
            <p class="text-sm text-gray-500">Sistem Absensi RFID</p>
        </div>

        <?php if($pesan): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded mb-4 text-sm text-center">
                <?php echo $pesan; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Username</label>
                <input type="text" name="username" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="admin" required autofocus>
            </div>
            <div class="mb-6">
                <label class="block text-gray-700 text-sm font-bold mb-2">Password</label>
                <input type="password" name="password" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="admin123" required>
            </div>
            <button type="submit" name="login" class="w-full bg-blue-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-blue-700 transition duration-300 shadow-lg">
                Masuk
            </button>
        </form>

        <div class="mt-6 text-center">
            <a href="index.php" class="text-sm text-blue-600 hover:underline">Kembali ke Dashboard</a>
        </div>
    </div>

</body>
</html>