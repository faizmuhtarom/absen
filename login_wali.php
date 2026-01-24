<?php
session_start();
include 'koneksi.php';

if(isset($_SESSION['status_wali']) && $_SESSION['status_wali'] == "login_wali"){
    header("location:portal_wali.php");
    exit;
}

$alert = "";

if(isset($_POST['login'])){
    $user = mysqli_real_escape_string($conn, $_POST['username']);
    $pass = mysqli_real_escape_string($conn, $_POST['password']);

    $cek = mysqli_query($conn, "SELECT * FROM wali_kelas WHERE username='$user' AND password='$pass'");
    
    if(mysqli_num_rows($cek) > 0){
        $data = mysqli_fetch_assoc($cek);
        $_SESSION['id_wali'] = $data['id'];
        $_SESSION['nama_wali'] = $data['nama_lengkap'];
        $_SESSION['kelas_binaan'] = $data['kelas_binaan'];
        $_SESSION['status_wali'] = "login_wali";
        header("location:portal_wali.php");
    } else {
        $alert = "Username atau Password salah!";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Wali Kelas</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .glass-panel {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.5);
        }
    </style>
</head>
<body class="bg-slate-900 min-h-screen flex items-center justify-center p-4 relative overflow-hidden">

    <!-- Decorative Blobs -->
    <div class="absolute top-0 left-0 w-full h-full overflow-hidden z-0">
        <div class="absolute top-10 left-10 w-72 h-72 bg-teal-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-pulse"></div>
        <div class="absolute bottom-10 right-10 w-72 h-72 bg-cyan-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-pulse" style="animation-delay: 2s"></div>
    </div>

    <div class="glass-panel p-8 rounded-3xl shadow-2xl w-full max-w-md relative z-10">
        <div class="text-center mb-8">
            <div class="bg-gradient-to-tr from-teal-500 to-cyan-500 w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg shadow-teal-500/30">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
            </div>
            <h2 class="text-2xl font-bold text-slate-800">Portal Wali Kelas</h2>
            <p class="text-slate-500 text-sm mt-1">Monitoring Absensi & Approval Izin</p>
        </div>

        <?php if($alert): ?>
            <div class="bg-red-50 text-red-600 px-4 py-3 rounded-xl mb-6 text-sm text-center border border-red-100 font-semibold">
                <?php echo $alert; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-5">
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1 ml-1">Username</label>
                <input type="text" name="username" required class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-200 focus:border-teal-500 focus:ring-2 focus:ring-teal-200 outline-none transition" placeholder="Username Wali Kelas">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1 ml-1">Password</label>
                <input type="password" name="password" required class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-200 focus:border-teal-500 focus:ring-2 focus:ring-teal-200 outline-none transition" placeholder="••••••••">
            </div>
            
            <button type="submit" name="login" class="w-full bg-gradient-to-r from-teal-600 to-cyan-600 hover:from-teal-700 hover:to-cyan-700 text-white py-3.5 rounded-xl font-bold shadow-lg shadow-teal-500/30 transition transform active:scale-95">
                Masuk Dashboard
            </button>
        </form>

        <div class="mt-8 text-center border-t border-slate-100 pt-4">
            <a href="index.php" class="text-xs font-semibold text-slate-400 hover:text-slate-600 transition">Kembali ke Halaman Utama</a>
        </div>
    </div>

</body>
</html>