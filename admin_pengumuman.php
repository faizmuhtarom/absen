<?php
session_start();
include 'koneksi.php';

// --- KEAMANAN ---
if(!isset($_SESSION['status']) || $_SESSION['status'] != "login"){
    header("location:login.php?pesan=belum_login");
    exit;
}

// --- LOGIC: UPDATE PENGUMUMAN ---
if(isset($_POST['update_pengumuman'])){
    $teks = mysqli_real_escape_string($conn, $_POST['teks']);
    $aktif = isset($_POST['aktif']) ? 1 : 0;
    
    // Kita hanya pakai 1 baris data untuk simpelnya (ID 1)
    // Cek dulu ada data atau tidak
    $cek = mysqli_query($conn, "SELECT id FROM pengumuman LIMIT 1");
    if(mysqli_num_rows($cek) > 0){
        mysqli_query($conn, "UPDATE pengumuman SET isi_teks='$teks', aktif='$aktif'");
    } else {
        mysqli_query($conn, "INSERT INTO pengumuman (isi_teks, aktif) VALUES ('$teks', '$aktif')");
    }
    echo "<script>alert('Pengumuman diperbarui!'); window.location='admin_pengumuman.php';</script>";
}

// Ambil Data
$q_info = mysqli_query($conn, "SELECT * FROM pengumuman LIMIT 1");
$d_info = mysqli_fetch_assoc($q_info);
$teks_sekarang = $d_info ? $d_info['isi_teks'] : "Selamat Datang";
$status_aktif = ($d_info && $d_info['aktif'] == 1) ? "checked" : "";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Atur Pengumuman - Admin</title>
    <link rel="shortcut icon" href="gambar/logo.jpg">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Outfit', sans-serif; background-color: #f8fafc; }</style>
</head>
<body class="text-slate-800">

    <!-- Navbar -->
    <nav class="bg-white border-b border-slate-200 p-4 fixed w-full top-0 z-50">
        <div class="max-w-3xl mx-auto flex justify-between items-center">
            <div class="flex items-center gap-3">
                <a href="admin.php" class="p-2 bg-slate-100 rounded-lg hover:bg-slate-200 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                </a>
                <h1 class="font-bold text-lg text-slate-800">Setting Running Text</h1>
            </div>
            <div class="text-sm text-slate-500">Admin Panel</div>
        </div>
    </nav>

    <main class="max-w-3xl mx-auto px-4 pt-24 pb-12">
        
        <div class="bg-white p-8 rounded-2xl shadow-lg border border-slate-100">
            <form method="POST" class="space-y-6">
                
                <!-- Preview -->
                <div class="bg-slate-900 text-white p-4 rounded-xl mb-6">
                    <p class="text-xs text-slate-400 mb-2 uppercase tracking-widest font-bold">Preview Tampilan Kiosk</p>
                    <div class="overflow-hidden whitespace-nowrap">
                        <p class="animate-marquee inline-block text-lg font-mono text-yellow-400">
                            📢 <?php echo $teks_sekarang; ?>
                        </p>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Isi Pengumuman</label>
                    <textarea name="teks" rows="4" class="w-full p-4 rounded-xl bg-slate-50 border border-slate-200 focus:ring-2 focus:ring-indigo-500 outline-none text-slate-800" placeholder="Tulis pengumuman di sini..."><?php echo $teks_sekarang; ?></textarea>
                    <p class="text-xs text-slate-500 mt-2">Teks ini akan berjalan di bagian bawah halaman Scan.</p>
                </div>

                <div class="flex items-center gap-3">
                    <input type="checkbox" name="aktif" id="aktif" class="w-5 h-5 text-indigo-600 rounded focus:ring-indigo-500" <?php echo $status_aktif; ?>>
                    <label for="aktif" class="text-sm font-medium text-slate-700">Tampilkan di Layar Scan</label>
                </div>

                <button type="submit" name="update_pengumuman" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-3 rounded-xl font-bold shadow-lg shadow-indigo-200 transition">
                    Simpan Perubahan
                </button>
            </form>
        </div>

    </main>

    <style>
        .animate-marquee { animation: marquee 10s linear infinite; }
        @keyframes marquee {
            0% { transform: translateX(100%); }
            100% { transform: translateX(-100%); }
        }
    </style>
</body>
</html>