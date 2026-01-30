<?php
session_start();
include 'koneksi.php';

// Jika sudah login, langsung ke portal
if(isset($_SESSION['status_wali']) && $_SESSION['status_wali'] == "login_wali"){
    header("location:portal_wali.php");
    exit;
}

$alert = "";

if(isset($_POST['login'])){
    $user = mysqli_real_escape_string($conn, $_POST['username']);
    $pass = mysqli_real_escape_string($conn, $_POST['password']);

    // Cek di tabel wali_kelas
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Login Wali Kelas - E-Absensi</title>
    <link rel="shortcut icon" href="gambar/logo.jpg">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #022c22; }
        
        /* Background Animasi Hijau */
        .bg-animate {
            background: linear-gradient(-45deg, #022c22, #064e3b, #065f46, #022c22);
            background-size: 400% 400%;
            animation: gradient 15s ease infinite;
        }
        @keyframes gradient {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        .glass-panel {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
    </style>
</head>
<body class="min-h-screen flex flex-col items-center justify-between p-4 bg-animate relative overflow-hidden">

    <!-- Dekorasi Lingkaran (Background) -->
    <div class="absolute top-0 left-0 w-full h-full overflow-hidden pointer-events-none z-0">
        <div class="absolute top-10 left-10 w-48 h-48 sm:w-72 sm:h-72 bg-emerald-500 rounded-full mix-blend-overlay filter blur-3xl opacity-20 animate-pulse"></div>
        <div class="absolute bottom-10 right-10 w-48 h-48 sm:w-72 sm:h-72 bg-green-500 rounded-full mix-blend-overlay filter blur-3xl opacity-20 animate-pulse" style="animation-delay: 2s"></div>
    </div>

    <!-- Wrapper Konten Utama (Tengah) -->
    <div class="flex-grow flex flex-col justify-center w-full max-w-md mx-auto relative z-10 py-6">
        
        <!-- Login Card -->
        <div class="glass-panel p-6 sm:p-8 rounded-2xl sm:rounded-3xl shadow-2xl w-full transition-all duration-300">
            <div class="text-center mb-6 sm:mb-8">
                <div class="bg-gradient-to-tr from-emerald-500 to-green-600 w-14 h-14 sm:w-16 sm:h-16 rounded-xl sm:rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg shadow-emerald-500/30">
                    <!-- Icon Wali Kelas -->
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 sm:h-8 sm:w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                </div>
                <h2 class="text-2xl sm:text-3xl font-bold text-slate-800">Portal Wali Kelas</h2>
                <p class="text-slate-500 text-xs sm:text-sm mt-1">Kelola perizinan & pantau absensi siswa</p>
            </div>

            <?php if($alert): ?>
                <div class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-xl mb-6 text-xs sm:text-sm text-center flex items-center justify-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
                    <?php echo $alert; ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4 sm:space-y-5">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1 ml-1">Username</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                        </div>
                        <input type="text" name="username" required class="w-full pl-10 pr-4 py-2.5 sm:py-3 rounded-xl bg-slate-50 border border-slate-200 focus:bg-white focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 outline-none transition font-mono text-slate-700 text-sm sm:text-base" placeholder="Username Wali">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1 ml-1">Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                        </div>
                        <input type="password" name="password" required class="w-full pl-10 pr-4 py-2.5 sm:py-3 rounded-xl bg-slate-50 border border-slate-200 focus:bg-white focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 outline-none transition font-mono text-slate-700 text-sm sm:text-base" placeholder="••••••••">
                    </div>
                </div>
                
                <button type="submit" name="login" class="w-full bg-gradient-to-r from-emerald-600 to-green-600 hover:from-emerald-700 hover:to-green-700 text-white py-3 sm:py-3.5 rounded-xl font-bold shadow-lg shadow-emerald-500/30 transition transform active:scale-95 text-sm sm:text-base">
                    Masuk Portal
                </button>
            </form>

            <div class="mt-6 sm:mt-8 text-center border-t border-slate-100 pt-4">
                <a href="index.php" class="text-xs font-semibold text-slate-400 hover:text-emerald-600 transition flex items-center justify-center gap-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                    Kembali ke Halaman Utama
                </a>
            </div>
        </div>
    </div>

    <!-- FOOTER HAK CIPTA (Sticky Bottom) -->
    <footer class="w-full text-center py-4 relative z-20 mt-auto">
        <div class="text-emerald-100/60 text-[10px] sm:text-xs font-medium">
            <p>&copy; <?php echo date('Y'); ?> <span class="font-bold text-emerald-400">SMK Ma'arif 4-5 Tambakboyo</span>.</p>
            <p class="mt-0.5">All rights reserved.</p>
        </div>
    </footer>

</body>
</html>