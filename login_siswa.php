<?php
session_start();
include 'koneksi.php';

// Jika sudah login, langsung ke portal
if(isset($_SESSION['status_siswa']) && $_SESSION['status_siswa'] == "login_siswa"){
    header("location:portal_siswa.php");
    exit;
}

$alert = "";

if(isset($_POST['login'])){
    $nis = mysqli_real_escape_string($conn, $_POST['nis']);
    $rfid = mysqli_real_escape_string($conn, $_POST['rfid']);

    // Cek kombinasi NIS dan RFID
    $cek = mysqli_query($conn, "SELECT * FROM siswa WHERE nis='$nis' AND rfid_uid='$rfid'");
    
    if(mysqli_num_rows($cek) > 0){
        $data = mysqli_fetch_assoc($cek);
        $_SESSION['nis_siswa'] = $nis;
        $_SESSION['nama_siswa'] = $data['nama'];
        $_SESSION['rfid_siswa'] = $data['rfid_uid'];
        $_SESSION['status_siswa'] = "login_siswa";
        header("location:portal_siswa.php");
    } else {
        $alert = "NIS atau ID Kartu salah!";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Siswa - E-Absensi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; }
        .bg-glass {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.5);
        }
        .anim-blob {
            animation: blob 7s infinite;
        }
        @keyframes blob {
            0% { transform: translate(0px, 0px) scale(1); }
            33% { transform: translate(30px, -50px) scale(1.1); }
            66% { transform: translate(-20px, 20px) scale(0.9); }
            100% { transform: translate(0px, 0px) scale(1); }
        }
    </style>
</head>
<body class="bg-slate-900 min-h-screen flex items-center justify-center p-4 relative overflow-hidden">

    <!-- Background Animation -->
    <div class="absolute top-0 -left-4 w-72 h-72 bg-purple-500 rounded-full mix-blend-multiply filter blur-xl opacity-70 anim-blob"></div>
    <div class="absolute top-0 -right-4 w-72 h-72 bg-blue-500 rounded-full mix-blend-multiply filter blur-xl opacity-70 anim-blob" style="animation-delay: 2s"></div>
    <div class="absolute -bottom-8 left-20 w-72 h-72 bg-pink-500 rounded-full mix-blend-multiply filter blur-xl opacity-70 anim-blob" style="animation-delay: 4s"></div>

    <!-- Login Card -->
    <div class="bg-glass p-8 rounded-3xl shadow-2xl w-full max-w-md relative z-10">
        <div class="text-center mb-8">
            <div class="bg-gradient-to-r from-blue-600 to-purple-600 w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
            </div>
            <h2 class="text-3xl font-bold text-slate-800">Portal Siswa</h2>
            <p class="text-slate-500 text-sm mt-2">Silakan login untuk akses layanan akademik</p>
        </div>

        <?php if($alert): ?>
            <div class="bg-red-100 border border-red-200 text-red-600 px-4 py-3 rounded-xl mb-6 text-sm text-center flex items-center justify-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
                <?php echo $alert; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-5">
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1 ml-1">Nomor Induk Siswa (NIS)</label>
                <input type="text" name="nis" required class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition font-mono" placeholder="Contoh: 1001">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1 ml-1">ID Kartu (Password)</label>
                <input type="password" name="rfid" required class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition font-mono" placeholder="Scan Kartu / Input ID">
                <p class="text-xs text-slate-400 mt-1 ml-1">*Gunakan UID Kartu RFID Anda sebagai password</p>
            </div>
            
            <button type="submit" name="login" class="w-full bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 text-white py-3.5 rounded-xl font-bold shadow-lg shadow-blue-500/30 transition transform active:scale-95">
                Masuk Portal
            </button>
        </form>

        <div class="mt-8 text-center">
            <a href="index.php" class="text-sm text-slate-400 hover:text-slate-600 transition">Kembali ke Dashboard Utama</a>
        </div>
    </div>

</body>
</html>