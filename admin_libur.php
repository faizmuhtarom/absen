<?php
session_start();
include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

// --- KEAMANAN ---
if(!isset($_SESSION['status']) || $_SESSION['status'] != "login"){
    header("location:login.php?pesan=belum_login");
    exit;
}

// --- LOGIC: TAMBAH LIBUR ---
if(isset($_POST['tambah_libur'])){
    $tanggal = $_POST['tanggal'];
    $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan']);
    
    $cek = mysqli_query($conn, "SELECT * FROM hari_libur WHERE tanggal='$tanggal'");
    if(mysqli_num_rows($cek) > 0){
        echo "<script>alert('Tanggal tersebut sudah ada di daftar libur!');</script>";
    } else {
        mysqli_query($conn, "INSERT INTO hari_libur (tanggal, keterangan) VALUES ('$tanggal', '$keterangan')");
        echo "<script>window.location='admin_libur.php';</script>";
    }
}

// --- LOGIC: HAPUS LIBUR ---
if(isset($_GET['hapus'])){
    $id = $_GET['hapus'];
    mysqli_query($conn, "DELETE FROM hari_libur WHERE id='$id'");
    echo "<script>window.location='admin_libur.php';</script>";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Atur Hari Libur - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Outfit', sans-serif; background-color: #f8fafc; }</style>
</head>
<body class="text-slate-800">

    <!-- Navbar -->
    <nav class="bg-white border-b border-slate-200 p-4 fixed w-full top-0 z-50">
        <div class="max-w-5xl mx-auto flex justify-between items-center">
            <div class="flex items-center gap-3">
                <a href="admin.php" class="p-2 bg-slate-100 rounded-lg hover:bg-slate-200 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                </a>
                <h1 class="font-bold text-lg text-slate-800">Pengaturan Hari Libur</h1>
            </div>
            <div class="text-sm text-slate-500">Admin Panel</div>
        </div>
    </nav>

    <main class="max-w-5xl mx-auto px-4 pt-24 pb-12">
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <!-- FORM TAMBAH -->
            <div class="md:col-span-1">
                <div class="bg-white p-6 rounded-2xl shadow-lg border border-slate-100 sticky top-24">
                    <h2 class="font-bold text-lg mb-4 flex items-center gap-2">
                        <span class="w-2 h-6 bg-red-500 rounded-full"></span> Tambah Libur
                    </h2>
                    <form method="POST" class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Tanggal</label>
                            <input type="date" name="tanggal" required class="w-full p-3 rounded-xl bg-slate-50 border border-slate-200 focus:ring-2 focus:ring-red-200 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Keterangan</label>
                            <textarea name="keterangan" rows="3" required class="w-full p-3 rounded-xl bg-slate-50 border border-slate-200 focus:ring-2 focus:ring-red-200 outline-none" placeholder="Contoh: Hari Raya Idul Fitri"></textarea>
                        </div>
                        <button type="submit" name="tambah_libur" class="w-full bg-red-500 hover:bg-red-600 text-white py-3 rounded-xl font-bold shadow-lg shadow-red-200 transition">
                            Simpan Jadwal Libur
                        </button>
                    </form>
                </div>
            </div>

            <!-- DAFTAR LIBUR -->
            <div class="md:col-span-2">
                <div class="bg-white rounded-2xl shadow-lg border border-slate-100 overflow-hidden">
                    <div class="p-6 border-b border-slate-100">
                        <h3 class="font-bold text-lg text-slate-800">Daftar Hari Libur</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 text-slate-500 uppercase text-xs font-bold">
                                <tr>
                                    <th class="px-6 py-4">Tanggal</th>
                                    <th class="px-6 py-4">Hari</th>
                                    <th class="px-6 py-4">Keterangan</th>
                                    <th class="px-6 py-4 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php 
                                // Tampilkan libur yang akan datang & bulan ini
                                $q_libur = mysqli_query($conn, "SELECT * FROM hari_libur ORDER BY tanggal DESC");
                                if(mysqli_num_rows($q_libur) > 0){
                                    while($row = mysqli_fetch_assoc($q_libur)){
                                        $date = strtotime($row['tanggal']);
                                        $hari_indo = ['Sunday'=>'Minggu','Monday'=>'Senin','Tuesday'=>'Selasa','Wednesday'=>'Rabu','Thursday'=>'Kamis','Friday'=>'Jumat','Saturday'=>'Sabtu'];
                                        $hari = $hari_indo[date('l', $date)];
                                ?>
                                <tr class="hover:bg-slate-50">
                                    <td class="px-6 py-4 font-mono text-red-500 font-bold"><?php echo date('d-m-Y', $date); ?></td>
                                    <td class="px-6 py-4 font-bold"><?php echo $hari; ?></td>
                                    <td class="px-6 py-4"><?php echo $row['keterangan']; ?></td>
                                    <td class="px-6 py-4 text-center">
                                        <a href="admin_libur.php?hapus=<?php echo $row['id']; ?>" onclick="return confirm('Hapus jadwal ini?')" class="text-red-500 hover:text-red-700 p-2 rounded hover:bg-red-50 transition">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                        </a>
                                    </td>
                                </tr>
                                <?php }} else { ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-12 text-center text-slate-400">Belum ada data hari libur.</td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>