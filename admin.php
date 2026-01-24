<?php
session_start();
include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

// --- KEAMANAN ---
if(!isset($_SESSION['status']) || $_SESSION['status'] != "login"){
    header("location:login.php?pesan=belum_login");
    exit;
}

// --- KONFIGURASI KELAS ---
$pilihan_kelas = [
    "X RPL", "XI RPL", "XII RPL", "X AK", "XI AK", "XII AK",
];

// --- INITIALIZE VARS ---
$nis_val = ""; $nama_val = ""; $kelas_val = ""; $rfid_val = ""; $id_val = ""; $foto_val = ""; 
$is_edit = false;

// Variabel untuk SweetAlert
$swal_type = "";
$swal_msg = "";

// --- FUNGSI UPLOAD FOTO ---
function uploadFoto($file, $nis) {
    $target_dir = "uploads/siswa/";
    if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); } // Buat folder jika belum ada

    $file_extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $new_filename = "siswa_" . $nis . "_" . time() . "." . $file_extension;
    $target_file = $target_dir . $new_filename;
    
    $allowed = ['jpg', 'jpeg', 'png'];

    if (in_array($file_extension, $allowed)) {
        if (move_uploaded_file($file["tmp_name"], $target_file)) {
            return $new_filename;
        }
    }
    return false;
}

// --- LOGIC: GENERATE ALPHA OTOMATIS ---
if (isset($_POST['generate_alpha'])) {
    $today = date('Y-m-d');
    $query_alpha = "SELECT * FROM siswa WHERE rfid_uid NOT IN (SELECT rfid_uid FROM log_absensi WHERE DATE(waktu) = '$today')";
    $result_alpha = mysqli_query($conn, $query_alpha);
    $jumlah_alpha = mysqli_num_rows($result_alpha);

    if ($jumlah_alpha > 0) {
        while ($row = mysqli_fetch_assoc($result_alpha)) {
            $uid = $row['rfid_uid'];
            mysqli_query($conn, "INSERT INTO log_absensi (rfid_uid, waktu, status) VALUES ('$uid', '$today 08:00:00', 'Alpha (Tidak Hadir)')");
        }
        $swal_type = "success"; $swal_msg = "Berhasil! $jumlah_alpha siswa ditandai Alpha.";
    } else {
        $swal_type = "info"; $swal_msg = "Semua siswa sudah absen. Tidak ada data baru.";
    }
}

// --- LOGIC: PERSIAPAN EDIT ---
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $q_edit = mysqli_query($conn, "SELECT * FROM siswa WHERE id='$id'");
    if(mysqli_num_rows($q_edit) > 0){
        $d = mysqli_fetch_assoc($q_edit);
        $nis_val = $d['nis']; $nama_val = $d['nama']; $kelas_val = $d['kelas']; 
        $rfid_val = $d['rfid_uid']; $id_val = $d['id']; $foto_val = $d['foto'];
        $is_edit = true;
    }
}

// --- LOGIC: TAMBAH SISWA ---
if (isset($_POST['tambah'])) {
    $nis = mysqli_real_escape_string($conn, $_POST['nis']);
    $nama = mysqli_real_escape_string($conn, $_POST['nama']);
    $kelas = mysqli_real_escape_string($conn, $_POST['kelas']);
    $rfid = mysqli_real_escape_string($conn, $_POST['rfid']);
    
    $cek = mysqli_query($conn, "SELECT * FROM siswa WHERE rfid_uid='$rfid'");
    if(mysqli_num_rows($cek) > 0){ 
        $swal_type = "error"; $swal_msg = "RFID sudah terdaftar!";
    } else {
        // Handle Upload
        $foto_nama = "";
        if (!empty($_FILES['foto']['name'])) {
            $foto_nama = uploadFoto($_FILES['foto'], $nis);
        }

        $query = "INSERT INTO siswa (nis, nama, kelas, rfid_uid, foto) VALUES ('$nis', '$nama', '$kelas', '$rfid', '$foto_nama')";
        if(mysqli_query($conn, $query)){
            $swal_type = "success"; $swal_msg = "Siswa berhasil ditambahkan!";
            $nis_val=""; $nama_val=""; $kelas_val=""; $rfid_val=""; // Clear form
        } else {
            $swal_type = "error"; $swal_msg = "Database Error: " . mysqli_error($conn);
        }
    }
}

// --- LOGIC: UPDATE SISWA ---
if (isset($_POST['update'])) {
    $id = $_POST['id_siswa'];
    $nis = mysqli_real_escape_string($conn, $_POST['nis']);
    $nama = mysqli_real_escape_string($conn, $_POST['nama']);
    $kelas = mysqli_real_escape_string($conn, $_POST['kelas']);
    $rfid = mysqli_real_escape_string($conn, $_POST['rfid']);
    
    $cek = mysqli_query($conn, "SELECT * FROM siswa WHERE rfid_uid='$rfid' AND id != '$id'");
    if(mysqli_num_rows($cek) > 0){ 
        $swal_type = "error"; $swal_msg = "RFID sudah dipakai siswa lain!";
    } else { 
        // Handle Foto Update
        $query_update = "UPDATE siswa SET nis='$nis', nama='$nama', kelas='$kelas', rfid_uid='$rfid'";
        
        if (!empty($_FILES['foto']['name'])) {
            // Upload foto baru
            $foto_baru = uploadFoto($_FILES['foto'], $nis);
            if($foto_baru){
                $query_update .= ", foto='$foto_baru'";
                
                // Hapus foto lama
                $q_old = mysqli_query($conn, "SELECT foto FROM siswa WHERE id='$id'");
                $d_old = mysqli_fetch_assoc($q_old);
                if(!empty($d_old['foto']) && file_exists("uploads/siswa/".$d_old['foto'])){
                    unlink("uploads/siswa/".$d_old['foto']);
                }
            }
        }

        $query_update .= " WHERE id='$id'";
        
        if(mysqli_query($conn, $query_update)){
            echo "<script>alert('Data berhasil diupdate!'); window.location='admin.php';</script>";
        } else {
            $swal_type = "error"; $swal_msg = "Gagal Update: " . mysqli_error($conn);
        }
    }
}

// --- LOGIC: HAPUS SISWA ---
if (isset($_GET['hapus'])) {
    $id = $_GET['hapus'];
    
    // Hapus file foto dulu
    $q_cek = mysqli_query($conn, "SELECT foto FROM siswa WHERE id='$id'");
    $d_cek = mysqli_fetch_assoc($q_cek);
    if(!empty($d_cek['foto']) && file_exists("uploads/siswa/".$d_cek['foto'])){
        unlink("uploads/siswa/".$d_cek['foto']);
    }

    mysqli_query($conn, "DELETE FROM siswa WHERE id='$id'");
    echo "<script>alert('Data dihapus!'); window.location='admin.php';</script>";
}

// --- DATA DASHBOARD KECIL ---
$today = date('Y-m-d');
$q_total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM siswa"));
$q_sudah = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT rfid_uid) as t FROM log_absensi WHERE DATE(waktu) = '$today'"));
$belum_hadir = $q_total['t'] - $q_sudah['t'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - E-Absensi</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts: Outfit -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #f8fafc; }
        .glass-nav {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(226, 232, 240, 0.8);
        }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #f1f5f9; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        .table-row-hover:hover td { background-color: #f8fafc; }
    </style>
</head>
<body class="text-slate-800">

    <!-- NAVBAR MODERN -->
    <nav class="glass-nav fixed w-full z-50 top-0 transition-all duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <div class="flex items-center gap-3">
                    <div class="bg-gradient-to-tr from-blue-600 to-indigo-600 p-2.5 rounded-xl text-white shadow-lg shadow-indigo-500/20">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-slate-800 tracking-tight">Admin Panel</h1>
                        <p class="text-xs text-slate-500 font-medium">Manajemen Data Siswa</p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <a href="index.php" class="px-4 py-2 rounded-lg text-sm font-medium text-slate-600 hover:text-blue-600 hover:bg-blue-50 transition-all">Dashboard</a>
                    <a href="logout.php" class="px-4 py-2 rounded-lg text-sm font-medium text-rose-600 hover:bg-rose-50 transition-all flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
                        Logout
                    </a>
                    <a href="admin_libur.php" class="bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 rounded-xl font-bold shadow-lg flex items-center gap-2 transition">
                      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                      Atur Hari Libur
                    </a>

                </div>
            </div>
        </div>
    </nav>

    <!-- MAIN CONTENT -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-28 pb-12">

        <!-- STATISTIK HEADER -->
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100 mb-8 flex flex-col md:flex-row justify-between items-center gap-6">
            <div class="flex items-center gap-4 w-full md:w-auto">
                <div class="p-4 bg-blue-50 text-blue-600 rounded-xl">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-slate-800">Status Hari Ini</h2>
                    <div class="flex gap-4 text-sm mt-1">
                        <span class="text-slate-500">Total: <strong class="text-slate-800"><?php echo $q_total['t']; ?></strong></span>
                        <span class="text-slate-500">|</span>
                        <span class="text-slate-500">Belum Hadir: <strong class="text-rose-500"><?php echo $belum_hadir; ?></strong></span>
                    </div>
                </div>
            </div>
            
            <form method="POST" class="w-full md:w-auto" id="formAlpha">
                <button type="button" onclick="confirmAlpha()" class="w-full md:w-auto bg-rose-500 hover:bg-rose-600 text-white px-6 py-3 rounded-xl font-bold shadow-lg shadow-rose-200 flex items-center justify-center gap-2 transition transform hover:scale-105">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="23" y1="11" x2="17" y2="11"></line></svg>
                    Tandai Sisa sbg Alpha
                </button>
                <input type="hidden" name="generate_alpha" value="1">
            </form>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- FORM SECTION (Kiri) -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-2xl shadow-lg border border-slate-200 overflow-hidden sticky top-28">
                    <div class="bg-slate-50 px-6 py-4 border-b border-slate-200 flex justify-between items-center">
                        <h3 class="font-bold text-slate-800 flex items-center gap-2">
                            <?php if($is_edit): ?>
                                <span class="w-2 h-2 rounded-full bg-amber-500"></span> Edit Data
                            <?php else: ?>
                                <span class="w-2 h-2 rounded-full bg-blue-500"></span> Tambah Siswa
                            <?php endif; ?>
                        </h3>
                        <?php if($is_edit): ?> <span class="text-xs bg-amber-100 text-amber-700 px-2 py-1 rounded font-bold">Mode Edit</span> <?php endif; ?>
                    </div>
                    
                    <!-- Tambahkan enctype="multipart/form-data" untuk upload -->
                    <form method="POST" enctype="multipart/form-data" class="p-6 space-y-5">
                        <input type="hidden" name="id_siswa" value="<?php echo $id_val; ?>">

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1.5 ml-1">NIS</label>
                            <input type="text" name="nis" value="<?php echo $nis_val; ?>" required class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition font-mono text-sm" placeholder="1001">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1.5 ml-1">Nama Lengkap</label>
                            <input type="text" name="nama" value="<?php echo $nama_val; ?>" required class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition text-sm" placeholder="Nama Siswa">
                        </div>
                        
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1.5 ml-1">Kelas</label>
                            <div class="relative">
                                <select name="kelas" required class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition appearance-none text-sm cursor-pointer">
                                    <option value="">-- Pilih Kelas --</option>
                                    <?php foreach($pilihan_kelas as $opsi): ?>
                                        <option value="<?php echo $opsi; ?>" <?php echo ($kelas_val == $opsi) ? 'selected' : ''; ?>><?php echo $opsi; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="absolute inset-y-0 right-0 flex items-center px-3 pointer-events-none text-slate-500">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1.5 ml-1">UID Kartu RFID</label>
                            <div class="relative group">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7V5a2 2 0 0 1 2-2h2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/><path d="M21 17v2a2 2 0 0 1-2 2h-2"/><path d="M7 21H5a2 2 0 0 1-2-2v-2"/></svg>
                                </div>
                                <input type="text" name="rfid" value="<?php echo $rfid_val; ?>" required class="w-full pl-10 pr-4 py-3 rounded-xl bg-yellow-50 border border-yellow-200 focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 outline-none transition font-mono text-sm text-slate-700" placeholder="Tap Kartu RFID...">
                            </div>
                        </div>

                        <!-- INPUT FOTO BARU -->
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1.5 ml-1">Foto Siswa</label>
                            <?php if($is_edit && !empty($foto_val)): ?>
                                <div class="flex items-center gap-3 mb-2">
                                    <img src="uploads/siswa/<?php echo $foto_val; ?>" class="w-12 h-12 rounded-full object-cover border">
                                    <span class="text-xs text-slate-400 italic">Foto saat ini</span>
                                </div>
                            <?php endif; ?>
                            <input type="file" name="foto" accept="image/*" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"/>
                        </div>

                        <div class="pt-2">
                            <?php if($is_edit): ?>
                                <div class="flex gap-3">
                                    <button type="submit" name="update" class="flex-1 bg-amber-500 hover:bg-amber-600 text-white py-3 rounded-xl font-bold shadow-lg shadow-amber-500/30 transition transform active:scale-95">Update Data</button>
                                    <a href="admin.php" class="flex-none px-5 py-3 bg-slate-200 hover:bg-slate-300 text-slate-600 rounded-xl font-bold transition text-center">Batal</a>
                                </div>
                            <?php else: ?>
                                <button type="submit" name="tambah" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-3 rounded-xl font-bold shadow-lg shadow-indigo-500/30 transition transform active:scale-95 flex justify-center items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                                    Simpan Siswa
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- TABLE SECTION (Kanan) -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden flex flex-col h-full max-h-[800px]">
                    <div class="px-6 py-5 border-b border-slate-200 bg-white sticky top-0 z-20">
                        <h3 class="font-bold text-lg text-slate-800">Database Siswa</h3>
                    </div>

                    <div class="overflow-x-auto custom-scrollbar flex-grow">
                        <table class="w-full text-left text-sm border-collapse">
                            <thead class="bg-slate-50 text-slate-500 uppercase font-bold text-xs sticky top-0 z-10 shadow-sm">
                                <tr>
                                    <th class="px-6 py-4">Siswa</th>
                                    <th class="px-6 py-4">NIS</th>
                                    <th class="px-6 py-4">Kelas</th>
                                    <th class="px-6 py-4">RFID UID</th>
                                    <th class="px-6 py-4 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php 
                                $data = mysqli_query($conn, "SELECT * FROM siswa ORDER BY id DESC");
                                if(mysqli_num_rows($data) > 0){
                                    while($d = mysqli_fetch_array($data)){
                                        $foto_show = !empty($d['foto']) ? "uploads/siswa/".$d['foto'] : "https://ui-avatars.com/api/?name=".urlencode($d['nama'])."&background=random";
                                ?>
                                    <tr class="table-row-hover transition-colors duration-150 group">
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-3">
                                                <img src="<?php echo $foto_show; ?>" class="w-8 h-8 rounded-full object-cover border border-slate-200">
                                                <span class="font-bold text-slate-700"><?php echo htmlspecialchars($d['nama']); ?></span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 font-mono text-slate-500 font-medium"><?php echo htmlspecialchars($d['nis']); ?></td>
                                        <td class="px-6 py-4">
                                            <span class="bg-indigo-50 text-indigo-600 text-[11px] px-2.5 py-1 rounded-md font-bold border border-indigo-100 uppercase tracking-wide">
                                                <?php echo htmlspecialchars($d['kelas']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 font-mono text-xs text-slate-400 bg-slate-50/50 rounded-lg px-2 py-1 w-fit">
                                            <?php echo htmlspecialchars($d['rfid_uid']); ?>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <div class="flex justify-center gap-2 opacity-80 group-hover:opacity-100 transition-opacity">
                                                <a href="admin.php?edit=<?php echo $d['id']; ?>" class="p-2 bg-amber-50 text-amber-600 rounded-lg hover:bg-amber-500 hover:text-white transition border border-amber-100 shadow-sm" title="Edit">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                                </a>
                                                <button onclick="confirmDelete(<?php echo $d['id']; ?>)" class="p-2 bg-rose-50 text-rose-600 rounded-lg hover:bg-rose-500 hover:text-white transition border border-rose-100 shadow-sm" title="Hapus">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php 
                                    }
                                } else {
                                    echo "<tr><td colspan='5' class='px-6 py-16 text-center text-slate-400 italic'>Belum ada data siswa.</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script>
        <?php if($swal_type && $swal_msg): ?>
            Swal.fire({ icon: '<?php echo $swal_type; ?>', title: '<?php echo $swal_type == "success" ? "Berhasil!" : "Info"; ?>', text: '<?php echo $swal_msg; ?>', confirmButtonColor: '#4f46e5', timer: 3000, timerProgressBar: true }).then(() => { window.history.replaceState(null, null, window.location.pathname); });
        <?php endif; ?>

        function confirmDelete(id) {
            Swal.fire({ title: 'Hapus Data Siswa?', text: "Data siswa dan riwayat absensi akan hilang!", icon: 'warning', showCancelButton: true, confirmButtonColor: '#e11d48', cancelButtonColor: '#64748b', confirmButtonText: 'Ya, Hapus!' }).then((result) => { if (result.isConfirmed) { window.location.href = 'admin.php?hapus=' + id; } })
        }
        
        function confirmAlpha() {
            Swal.fire({ title: 'Proses Alpha Otomatis?', text: "Menandai <?php echo $belum_hadir; ?> siswa belum hadir sebagai ALPHA.", icon: 'question', showCancelButton: true, confirmButtonColor: '#e11d48', cancelButtonColor: '#64748b', confirmButtonText: 'Proses' }).then((result) => { if (result.isConfirmed) { document.getElementById('formAlpha').submit(); } })
        }
    </script>
</body>
</html>