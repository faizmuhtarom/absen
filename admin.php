<?php
session_start();
include 'koneksi.php';

// Include SimpleXLSX for Excel Import if available
if (file_exists('SimpleXLSX.php')) {
    include 'SimpleXLSX.php';
}
date_default_timezone_set('Asia/Jakarta');

// --- SECURITY CHECK ---
if(!isset($_SESSION['status']) || $_SESSION['status'] != "login"){
    header("location:login.php?pesan=belum_login");
    exit;
}

// --- CLASS CONFIGURATION ---
$pilihan_kelas = [
    "X RPL 1", "X RPL 2", "X TKJ 1", "X TKJ 2", "X MM 1", "X MM 2",
    "XI RPL 1", "XI RPL 2", "XI TKJ 1", "XI TKJ 2", "XI MM 1", "XI MM 2",
    "XII RPL 1", "XII RPL 2", "XII TKJ 1", "XII TKJ 2", "XII MM 1", "XII MM 2"
];

$page = isset($_GET['page']) ? $_GET['page'] : 'siswa'; 
$swal_type = ""; $swal_msg = "";
$is_edit = false;

// Vars Siswa
$nis_val = ""; $nama_val = ""; $kelas_val = ""; $rfid_val = ""; $id_val = ""; $foto_val = ""; 

// Vars Wali
$id_wali = ""; $user_wali = ""; $pass_wali = ""; $nama_wali = ""; $kelas_wali = "";

// Vars BK
$id_bk = ""; $user_bk = ""; $pass_bk = ""; $nama_bk = "";

// =================================================================================
// LOGIC: MANAJEMEN SISWA
// =================================================================================
if ($page == 'siswa') {
    if (!function_exists('uploadFoto')) {
        function uploadFoto($file, $nis) {
            $target_dir = "uploads/siswa/";
            if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }
            $ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
            $new_name = "siswa_" . $nis . "_" . time() . "." . $ext;
            $allowed = ['jpg', 'jpeg', 'png'];
            if (in_array($ext, $allowed) && move_uploaded_file($file["tmp_name"], $target_dir . $new_name)) return $new_name;
            return false;
        }
    }

    // --- IMPORT EXCEL FEATURE ---
    if (isset($_POST['import_excel'])) {
        if (isset($_FILES['file_excel']) && $_FILES['file_excel']['error'] == 0) {
            if (class_exists('SimpleXLSX')) {
                if ($xlsx = SimpleXLSX::parse($_FILES['file_excel']['tmp_name'])) {
                    $rows = $xlsx->rows();
                    $count_success = 0;
                    $count_fail = 0;
                    
                    // Skip header row (index 0), start from 1
                    for ($i = 1; $i < count($rows); $i++) {
                        // Assuming Excel columns: A=NIS, B=Nama, C=Kelas, D=RFID
                        $nis = isset($rows[$i][0]) ? mysqli_real_escape_string($conn, $rows[$i][0]) : '';
                        $nama = isset($rows[$i][1]) ? mysqli_real_escape_string($conn, $rows[$i][1]) : '';
                        $kelas = isset($rows[$i][2]) ? mysqli_real_escape_string($conn, $rows[$i][2]) : '';
                        $rfid = isset($rows[$i][3]) ? mysqli_real_escape_string($conn, $rows[$i][3]) : '';

                        if (!empty($nis) && !empty($nama)) {
                            // Check Duplicate
                            $cek = mysqli_query($conn, "SELECT id FROM siswa WHERE nis='$nis' OR rfid_uid='$rfid'");
                            if (mysqli_num_rows($cek) > 0) {
                                $count_fail++;
                            } else {
                                $insert = mysqli_query($conn, "INSERT INTO siswa (nis, nama, kelas, rfid_uid) VALUES ('$nis', '$nama', '$kelas', '$rfid')");
                                if ($insert) $count_success++;
                                else $count_fail++;
                            }
                        }
                    }
                    $swal_type = "success";
                    $swal_msg = "Import Selesai! Berhasil: $count_success, Gagal/Duplikat: $count_fail";
                } else {
                    $swal_type = "error"; $swal_msg = "Gagal membaca file Excel.";
                }
            } else {
                $swal_type = "error"; $swal_msg = "Library SimpleXLSX belum terpasang.";
            }
        } else {
            $swal_type = "error"; $swal_msg = "Pilih file Excel (.xlsx) terlebih dahulu!";
        }
    }

    // Generate Alpha
    if (isset($_POST['generate_alpha'])) {
        $today = date('Y-m-d');
        // Find students who haven't tapped today
        $q = mysqli_query($conn, "SELECT * FROM siswa WHERE rfid_uid NOT IN (SELECT rfid_uid FROM log_absensi WHERE DATE(waktu) = '$today')");
        if (mysqli_num_rows($q) > 0) {
            $count = 0;
            while ($row = mysqli_fetch_assoc($q)) {
                $uid = $row['rfid_uid'];
                mysqli_query($conn, "INSERT INTO log_absensi (rfid_uid, waktu, status) VALUES ('$uid', '$today 08:00:00', 'Alpha (Tidak Hadir)')");
                $count++;
            }
            $swal_type = "success"; $swal_msg = "Berhasil! $count siswa ditandai Alpha.";
        } else {
            $swal_type = "info"; $swal_msg = "Semua siswa sudah tercatat kehadirannya hari ini.";
        }
    }

    // Prepare Edit Student
    if (isset($_GET['edit'])) {
        $id = $_GET['edit'];
        $d = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM siswa WHERE id='$id'"));
        if($d) {
            $nis_val = $d['nis']; $nama_val = $d['nama']; $kelas_val = $d['kelas']; 
            $rfid_val = $d['rfid_uid']; $id_val = $d['id']; $foto_val = $d['foto'];
            $is_edit = true;
        }
    }

    // Add Student
    if (isset($_POST['tambah_siswa'])) {
        $nis = $_POST['nis']; $nama = $_POST['nama']; $kelas = $_POST['kelas']; $rfid = $_POST['rfid'];
        $cek = mysqli_query($conn, "SELECT * FROM siswa WHERE rfid_uid='$rfid' OR nis='$nis'");
        if(mysqli_num_rows($cek) > 0){ $swal_type = "error"; $swal_msg = "NIS atau RFID sudah terdaftar!"; } 
        else {
            $foto = !empty($_FILES['foto']['name']) ? uploadFoto($_FILES['foto'], $nis) : "";
            mysqli_query($conn, "INSERT INTO siswa (nis, nama, kelas, rfid_uid, foto) VALUES ('$nis', '$nama', '$kelas', '$rfid', '$foto')");
            $swal_type = "success"; $swal_msg = "Siswa berhasil ditambahkan!";
        }
    }

    // Update Student
    if (isset($_POST['update_siswa'])) {
        $id = $_POST['id_siswa']; $nis = $_POST['nis']; $nama = $_POST['nama']; $kelas = $_POST['kelas']; $rfid = $_POST['rfid'];
        $foto_sql = "";
        if (!empty($_FILES['foto']['name'])) {
            $foto_baru = uploadFoto($_FILES['foto'], $nis);
            if($foto_baru) $foto_sql = ", foto='$foto_baru'";
        }
        $cek = mysqli_query($conn, "SELECT * FROM siswa WHERE (rfid_uid='$rfid' OR nis='$nis') AND id != '$id'");
        if(mysqli_num_rows($cek) > 0){
             $swal_type = "error"; $swal_msg = "NIS atau RFID sudah dipakai siswa lain!";
        } else {
            mysqli_query($conn, "UPDATE siswa SET nis='$nis', nama='$nama', kelas='$kelas', rfid_uid='$rfid' $foto_sql WHERE id='$id'");
            echo "<script>window.location='admin.php?page=siswa';</script>";
        }
    }

    // Delete Student
    if (isset($_GET['hapus'])) {
        $q_foto = mysqli_fetch_assoc(mysqli_query($conn, "SELECT foto FROM siswa WHERE id='$_GET[hapus]'"));
        if(!empty($q_foto['foto']) && file_exists("uploads/siswa/".$q_foto['foto'])){
            unlink("uploads/siswa/".$q_foto['foto']);
        }
        mysqli_query($conn, "DELETE FROM siswa WHERE id='$_GET[hapus]'");
        echo "<script>window.location='admin.php?page=siswa';</script>";
    }
}

// =================================================================================
// LOGIC: TEACHER MANAGEMENT (WALI KELAS)
// =================================================================================
if ($page == 'wali') {
    // Prepare Edit Teacher
    if (isset($_GET['edit'])) {
        $id = $_GET['edit'];
        $d = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM wali_kelas WHERE id='$id'"));
        if($d) {
            $id_wali = $d['id']; $user_wali = $d['username']; $nama_wali = $d['nama_lengkap']; $kelas_wali = $d['kelas_binaan'];
            $is_edit = true;
        }
    }

    // Add Teacher
    if (isset($_POST['tambah_wali'])) {
        $user = $_POST['username']; $pass = $_POST['password']; $nama = $_POST['nama_lengkap']; $kelas = $_POST['kelas_binaan'];
        $cek = mysqli_query($conn, "SELECT * FROM wali_kelas WHERE username='$user'");
        if(mysqli_num_rows($cek) > 0){ 
            $swal_type = "error"; $swal_msg = "Username sudah dipakai!"; 
        } else {
            $cek_kelas = mysqli_query($conn, "SELECT * FROM wali_kelas WHERE kelas_binaan='$kelas'");
            if(mysqli_num_rows($cek_kelas) > 0){
                $swal_type = "warning"; $swal_msg = "Kelas $kelas sudah memiliki Wali Kelas!";
            } else {
                mysqli_query($conn, "INSERT INTO wali_kelas (username, password, nama_lengkap, kelas_binaan) VALUES ('$user', '$pass', '$nama', '$kelas')");
                $swal_type = "success"; $swal_msg = "Wali Kelas berhasil ditambahkan!";
            }
        }
    }

    // Update Teacher
    if (isset($_POST['update_wali'])) {
        $id = $_POST['id_wali']; $user = $_POST['username']; $pass = $_POST['password']; $nama = $_POST['nama_lengkap']; $kelas = $_POST['kelas_binaan'];
        
        $sql_pass = "";
        if(!empty($pass)) { $sql_pass = ", password='$pass'"; } 

        mysqli_query($conn, "UPDATE wali_kelas SET username='$user', nama_lengkap='$nama', kelas_binaan='$kelas' $sql_pass WHERE id='$id'");
        echo "<script>window.location='admin.php?page=wali';</script>";
    }

    // Delete Teacher
    if (isset($_GET['hapus'])) {
        mysqli_query($conn, "DELETE FROM wali_kelas WHERE id='$_GET[hapus]'");
        echo "<script>window.location='admin.php?page=wali';</script>";
    }
}

// =================================================================================
// LOGIC: BK MANAGEMENT (GURU BK)
// =================================================================================
if ($page == 'bk') {
    // Prepare Edit BK
    if (isset($_GET['edit'])) {
        $id = $_GET['edit'];
        $d = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM user_bk WHERE id='$id'"));
        if($d) {
            $id_bk = $d['id']; $user_bk = $d['username']; $nama_bk = $d['nama_lengkap'];
            $is_edit = true;
        }
    }

    // Add BK
    if (isset($_POST['tambah_bk'])) {
        $user = $_POST['username']; $pass = $_POST['password']; $nama = $_POST['nama_lengkap'];
        $cek = mysqli_query($conn, "SELECT * FROM user_bk WHERE username='$user'");
        if(mysqli_num_rows($cek) > 0){ 
            $swal_type = "error"; $swal_msg = "Username sudah dipakai!"; 
        } else {
            mysqli_query($conn, "INSERT INTO user_bk (username, password, nama_lengkap) VALUES ('$user', '$pass', '$nama')");
            $swal_type = "success"; $swal_msg = "Guru BK berhasil ditambahkan!";
        }
    }

    // Update BK
    if (isset($_POST['update_bk'])) {
        $id = $_POST['id_bk']; $user = $_POST['username']; $pass = $_POST['password']; $nama = $_POST['nama_lengkap'];
        
        $sql_pass = "";
        if(!empty($pass)) { $sql_pass = ", password='$pass'"; } 

        mysqli_query($conn, "UPDATE user_bk SET username='$user', nama_lengkap='$nama' $sql_pass WHERE id='$id'");
        echo "<script>window.location='admin.php?page=bk';</script>";
    }

    // Delete BK
    if (isset($_GET['hapus'])) {
        mysqli_query($conn, "DELETE FROM user_bk WHERE id='$_GET[hapus]'");
        echo "<script>window.location='admin.php?page=bk';</script>";
    }
}


// --- DASHBOARD DATA COUNTS ---
$q_total_siswa = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM siswa"));
$q_total_wali = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM wali_kelas"));
$q_total_bk = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM user_bk"));
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - E-Absensi</title>
    <link rel="shortcut icon" href="gambar/logo.jpg">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>body { font-family: 'Outfit', sans-serif; background-color: #f1f5f9; }</style>
</head>
<body class="text-slate-800 flex flex-col min-h-screen">

    <!-- NAVBAR -->
    <nav class="bg-white/90 backdrop-blur border-b border-slate-200 fixed w-full z-50 top-0">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <div class="flex items-center gap-3">
                    <div class="bg-gradient-to-tr from-blue-600 to-indigo-600 p-2.5 rounded-xl text-white shadow-lg">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-slate-800">Admin Panel</h1>
                        <p class="text-xs text-slate-500">Manajemen Data Sekolah</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <!-- Mode Scan Button -->
                    <a href="scan.php" class="hidden md:flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-bold text-white bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-600 hover:to-teal-600 shadow-md shadow-emerald-200 transition-all">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7V5a2 2 0 0 1 2-2h2"></path><path d="M17 3h2a2 2 0 0 1 2 2v2"></path><path d="M21 17v2a2 2 0 0 1-2 2h-2"></path><path d="M7 21H5a2 2 0 0 1-2-2v-2"></path></svg>
                        Mode Scan
                    </a>
                    
                    <a href="index.php" class="px-4 py-2 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-100">Dashboard</a>
                    <a href="logout.php" class="px-4 py-2 rounded-lg text-sm font-medium text-red-600 hover:bg-red-50">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- MAIN CONTENT -->
    <main class="max-w-7xl mx-auto px-4 pt-28 pb-12 flex-grow">
        
        <!-- Mobile Scan Button -->
        <div class="md:hidden mb-6">
            <a href="scan.php" class="w-full flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-sm font-bold text-white bg-gradient-to-r from-emerald-500 to-teal-500 shadow-lg">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7V5a2 2 0 0 1 2-2h2"></path><path d="M17 3h2a2 2 0 0 1 2 2v2"></path><path d="M21 17v2a2 2 0 0 1-2 2h-2"></path><path d="M7 21H5a2 2 0 0 1-2-2v-2"></path></svg>
                Buka Mode Scan Kiosk
            </a>
        </div>

        <!-- TAB NAVIGATION -->
        <div class="flex space-x-2 mb-8 border-b border-slate-200 pb-1 overflow-x-auto">
            <a href="admin.php?page=siswa" class="px-6 py-2 rounded-t-lg font-bold transition whitespace-nowrap <?php echo ($page=='siswa') ? 'bg-blue-600 text-white shadow-lg' : 'bg-white text-slate-500 hover:bg-slate-100'; ?>">
                Data Siswa (<?php echo $q_total_siswa['t']; ?>)
            </a>
            <a href="admin.php?page=wali" class="px-6 py-2 rounded-t-lg font-bold transition whitespace-nowrap <?php echo ($page=='wali') ? 'bg-teal-600 text-white shadow-lg' : 'bg-white text-slate-500 hover:bg-slate-100'; ?>">
                Data Wali Kelas (<?php echo $q_total_wali['t']; ?>)
            </a>
            <a href="admin.php?page=bk" class="px-6 py-2 rounded-t-lg font-bold transition whitespace-nowrap <?php echo ($page=='bk') ? 'bg-rose-600 text-white shadow-lg' : 'bg-white text-slate-500 hover:bg-slate-100'; ?>">
                Data Guru BK (<?php echo $q_total_bk['t']; ?>)
            </a>
            <a href="admin_libur.php" class="px-6 py-2 rounded-t-lg font-bold bg-white text-slate-500 hover:bg-slate-100 transition whitespace-nowrap">
                Hari Libur
            </a>
            <a href="gate_pass.php" class="px-6 py-2 rounded-t-lg font-bold bg-white text-slate-500 hover:bg-slate-100 transition whitespace-nowrap">
                Gate Pass (Izin)
            </a>
            <a href="admin_sp.php" class="px-6 py-2 rounded-t-lg font-bold bg-white text-slate-500 hover:bg-slate-100 transition whitespace-nowrap">
                Monitoring SP
            </a>
             <a href="admin_pengumuman.php" class="px-6 py-2 rounded-t-lg font-bold bg-white text-slate-500 hover:bg-slate-100 transition whitespace-nowrap">
                Info (Running Text)
            </a>
        </div>

        <!-- ========================== PAGE: SISWA ========================== -->
        <?php if($page == 'siswa'): ?>
        
        <div class="flex flex-col md:flex-row justify-between mb-6 gap-4">
            <!-- PANEL IMPORT EXCEL -->
            <div class="bg-emerald-50 border border-emerald-200 p-4 rounded-xl flex-1 flex flex-col md:flex-row items-center gap-4">
                <div class="p-3 bg-emerald-100 rounded-full text-emerald-600">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
                </div>
                <div class="flex-grow w-full">
                    <h4 class="font-bold text-emerald-800">Import Data dari Excel</h4>
                    <p class="text-xs text-emerald-600 mb-2">Upload file .xlsx (Urutan Kolom: NIS, Nama, Kelas, RFID)</p>
                    <form method="POST" enctype="multipart/form-data" class="flex gap-2">
                        <input type="file" name="file_excel" accept=".xlsx" class="block w-full text-xs text-slate-500 file:mr-2 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-emerald-100 file:text-emerald-700 hover:file:bg-emerald-200" required>
                        <button type="submit" name="import_excel" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-xs font-bold transition">Upload</button>
                    </form>
                </div>
            </div>

            <!-- Tombol Alpha -->
            <div class="flex items-center">
                <form method="POST" onsubmit="return confirm('Tandai semua siswa yang BELUM scan hari ini sebagai ALPHA?')">
                    <button type="submit" name="generate_alpha" class="bg-red-500 hover:bg-red-600 text-white px-4 py-3 rounded-xl text-sm font-bold shadow flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>
                        Generate Alpha
                    </button>
                </form>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Form Siswa -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-2xl shadow-lg border border-slate-200 p-6 sticky top-28">
                    <h3 class="font-bold text-slate-800 mb-4 flex items-center gap-2">
                        <?php if($is_edit): ?><span class="w-2 h-6 bg-amber-500 rounded-full"></span> Edit Siswa<?php else: ?><span class="w-2 h-6 bg-blue-500 rounded-full"></span> Tambah Siswa<?php endif; ?>
                    </h3>
                    <form method="POST" enctype="multipart/form-data" class="space-y-4">
                        <input type="hidden" name="id_siswa" value="<?php echo $id_val; ?>">
                        <input type="text" name="nis" value="<?php echo $nis_val; ?>" required class="w-full p-3 rounded-xl bg-slate-50 border focus:border-blue-500 outline-none text-sm" placeholder="NIS">
                        <input type="text" name="nama" value="<?php echo $nama_val; ?>" required class="w-full p-3 rounded-xl bg-slate-50 border focus:border-blue-500 outline-none text-sm" placeholder="Nama Lengkap">
                        <select name="kelas" required class="w-full p-3 rounded-xl bg-slate-50 border focus:border-blue-500 outline-none text-sm">
                            <option value="">-- Pilih Kelas --</option>
                            <?php foreach($pilihan_kelas as $k): ?>
                                <option value="<?php echo $k; ?>" <?php echo ($kelas_val==$k)?'selected':''; ?>><?php echo $k; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="rfid" value="<?php echo $rfid_val; ?>" required class="w-full p-3 rounded-xl bg-yellow-50 border border-yellow-200 focus:border-yellow-500 outline-none text-sm font-mono" placeholder="Tap Kartu RFID...">
                        <div class="border-t pt-2">
                            <label class="text-xs font-bold text-slate-500">Foto Siswa (Opsional)</label>
                            <input type="file" name="foto" accept="image/*" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 mt-1"/>
                        </div>

                        <?php if($is_edit): ?>
                            <div class="flex gap-2">
                                <button type="submit" name="update_siswa" class="flex-1 bg-amber-500 hover:bg-amber-600 text-white py-2 rounded-xl font-bold">Update</button>
                                <a href="admin.php?page=siswa" class="flex-1 bg-slate-200 text-slate-600 py-2 rounded-xl font-bold text-center">Batal</a>
                            </div>
                        <?php else: ?>
                            <button type="submit" name="tambah_siswa" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-xl font-bold">Simpan</button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Tabel Siswa -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="overflow-x-auto max-h-[600px]">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 text-slate-500 uppercase text-xs font-bold sticky top-0 z-10">
                                <tr>
                                    <th class="px-6 py-4">Siswa</th>
                                    <th class="px-6 py-4">Kelas</th>
                                    <th class="px-6 py-4">RFID</th>
                                    <th class="px-6 py-4 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php 
                                $data = mysqli_query($conn, "SELECT * FROM siswa ORDER BY id DESC");
                                while($d = mysqli_fetch_array($data)){
                                    $foto = !empty($d['foto']) ? "uploads/siswa/".$d['foto'] : "https://ui-avatars.com/api/?name=".urlencode($d['nama']);
                                ?>
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="px-6 py-4 flex items-center gap-3">
                                        <img src="<?php echo $foto; ?>" class="w-8 h-8 rounded-full object-cover border">
                                        <div>
                                            <p class="font-bold text-slate-700"><?php echo $d['nama']; ?></p>
                                            <p class="text-xs text-slate-400 font-mono"><?php echo $d['nis']; ?></p>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4"><span class="bg-blue-50 text-blue-600 text-xs px-2 py-1 rounded border border-blue-100"><?php echo $d['kelas']; ?></span></td>
                                    <td class="px-6 py-4 font-mono text-xs text-slate-400"><?php echo $d['rfid_uid']; ?></td>
                                    <td class="px-6 py-4 text-center flex justify-center gap-2">
                                        <a href="admin.php?page=siswa&edit=<?php echo $d['id']; ?>" class="text-amber-500 hover:text-amber-600"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></a>
                                        <a href="admin.php?page=siswa&hapus=<?php echo $d['id']; ?>" onclick="return confirm('Hapus?')" class="text-red-500 hover:text-red-600"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></a>
                                    </td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================== PAGE: WALI KELAS ========================== -->
        <?php elseif($page == 'wali'): ?>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Form Wali -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-2xl shadow-lg border border-slate-200 p-6 sticky top-28">
                    <h3 class="font-bold text-slate-800 mb-4 flex items-center gap-2">
                        <?php if($is_edit): ?><span class="w-2 h-6 bg-amber-500 rounded-full"></span> Edit Wali Kelas<?php else: ?><span class="w-2 h-6 bg-teal-500 rounded-full"></span> Tambah Wali Kelas<?php endif; ?>
                    </h3>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="id_wali" value="<?php echo $id_wali; ?>">
                        
                        <div>
                            <label class="text-xs font-bold text-slate-500">Nama Lengkap</label>
                            <input type="text" name="nama_lengkap" value="<?php echo $nama_wali; ?>" required class="w-full p-3 rounded-xl bg-slate-50 border focus:border-teal-500 outline-none text-sm" placeholder="Nama Guru">
                        </div>
                        
                        <div>
                            <label class="text-xs font-bold text-slate-500">Kelas Binaan</label>
                            <select name="kelas_binaan" required class="w-full p-3 rounded-xl bg-slate-50 border focus:border-teal-500 outline-none text-sm">
                                <option value="">-- Pilih Kelas --</option>
                                <?php foreach($pilihan_kelas as $k): ?>
                                    <option value="<?php echo $k; ?>" <?php echo ($kelas_wali==$k)?'selected':''; ?>><?php echo $k; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="text-xs font-bold text-slate-500">Username</label>
                                <input type="text" name="username" value="<?php echo $user_wali; ?>" required class="w-full p-3 rounded-xl bg-slate-50 border focus:border-teal-500 outline-none text-sm">
                            </div>
                            <div>
                                <label class="text-xs font-bold text-slate-500">Password</label>
                                <input type="text" name="password" class="w-full p-3 rounded-xl bg-slate-50 border focus:border-teal-500 outline-none text-sm" placeholder="<?php echo $is_edit ? '(Biarkan kosong)' : 'Password'; ?>" <?php echo $is_edit ? '' : 'required'; ?>>
                            </div>
                        </div>

                        <?php if($is_edit): ?>
                            <div class="flex gap-2">
                                <button type="submit" name="update_wali" class="flex-1 bg-amber-500 hover:bg-amber-600 text-white py-2 rounded-xl font-bold">Update</button>
                                <a href="admin.php?page=wali" class="flex-1 bg-slate-200 text-slate-600 py-2 rounded-xl font-bold text-center">Batal</a>
                            </div>
                        <?php else: ?>
                            <button type="submit" name="tambah_wali" class="w-full bg-teal-600 hover:bg-teal-700 text-white py-2 rounded-xl font-bold">Simpan Wali Kelas</button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Tabel Wali -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 text-slate-500 uppercase text-xs font-bold">
                                <tr>
                                    <th class="px-6 py-4">Nama Wali Kelas</th>
                                    <th class="px-6 py-4">Username</th>
                                    <th class="px-6 py-4">Kelas Binaan</th>
                                    <th class="px-6 py-4 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php 
                                $data = mysqli_query($conn, "SELECT * FROM wali_kelas ORDER BY kelas_binaan ASC");
                                if(mysqli_num_rows($data) > 0) {
                                    while($d = mysqli_fetch_array($data)){
                                ?>
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="px-6 py-4 font-bold text-slate-700"><?php echo $d['nama_lengkap']; ?></td>
                                    <td class="px-6 py-4 font-mono text-slate-500"><?php echo $d['username']; ?></td>
                                    <td class="px-6 py-4"><span class="bg-teal-50 text-teal-600 text-xs px-2 py-1 rounded border border-teal-100"><?php echo $d['kelas_binaan']; ?></span></td>
                                    <td class="px-6 py-4 text-center flex justify-center gap-2">
                                        <a href="admin.php?page=wali&edit=<?php echo $d['id']; ?>" class="text-amber-500 hover:text-amber-600"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></a>
                                        <a href="admin.php?page=wali&hapus=<?php echo $d['id']; ?>" onclick="return confirm('Hapus Wali Kelas ini?')" class="text-red-500 hover:text-red-600"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></a>
                                    </td>
                                </tr>
                                <?php }
                                } else { echo "<tr><td colspan='4' class='px-6 py-12 text-center text-slate-400'>Belum ada data wali kelas.</td></tr>"; } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================== PAGE: GURU BK ========================== -->
        <?php elseif($page == 'bk'): ?>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Form BK -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-2xl shadow-lg border border-slate-200 p-6 sticky top-28">
                    <h3 class="font-bold text-slate-800 mb-4 flex items-center gap-2">
                        <?php if($is_edit): ?><span class="w-2 h-6 bg-amber-500 rounded-full"></span> Edit Guru BK<?php else: ?><span class="w-2 h-6 bg-rose-500 rounded-full"></span> Tambah Guru BK<?php endif; ?>
                    </h3>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="id_bk" value="<?php echo $id_bk; ?>">
                        
                        <div>
                            <label class="text-xs font-bold text-slate-500">Nama Lengkap</label>
                            <input type="text" name="nama_lengkap" value="<?php echo $nama_bk; ?>" required class="w-full p-3 rounded-xl bg-slate-50 border focus:border-rose-500 outline-none text-sm" placeholder="Nama Guru BK">
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="text-xs font-bold text-slate-500">Username</label>
                                <input type="text" name="username" value="<?php echo $user_bk; ?>" required class="w-full p-3 rounded-xl bg-slate-50 border focus:border-rose-500 outline-none text-sm">
                            </div>
                            <div>
                                <label class="text-xs font-bold text-slate-500">Password</label>
                                <input type="text" name="password" class="w-full p-3 rounded-xl bg-slate-50 border focus:border-rose-500 outline-none text-sm" placeholder="<?php echo $is_edit ? '(Biarkan kosong)' : 'Password'; ?>" <?php echo $is_edit ? '' : 'required'; ?>>
                            </div>
                        </div>

                        <?php if($is_edit): ?>
                            <div class="flex gap-2">
                                <button type="submit" name="update_bk" class="flex-1 bg-amber-500 hover:bg-amber-600 text-white py-2 rounded-xl font-bold">Update</button>
                                <a href="admin.php?page=bk" class="flex-1 bg-slate-200 text-slate-600 py-2 rounded-xl font-bold text-center">Batal</a>
                            </div>
                        <?php else: ?>
                            <button type="submit" name="tambah_bk" class="w-full bg-rose-600 hover:bg-rose-700 text-white py-2 rounded-xl font-bold">Simpan Guru BK</button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Tabel BK -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 text-slate-500 uppercase text-xs font-bold">
                                <tr>
                                    <th class="px-6 py-4">Nama Guru BK</th>
                                    <th class="px-6 py-4">Username</th>
                                    <th class="px-6 py-4 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php 
                                $data = mysqli_query($conn, "SELECT * FROM user_bk ORDER BY nama_lengkap ASC");
                                if(mysqli_num_rows($data) > 0) {
                                    while($d = mysqli_fetch_array($data)){
                                ?>
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="px-6 py-4 font-bold text-slate-700"><?php echo $d['nama_lengkap']; ?></td>
                                    <td class="px-6 py-4 font-mono text-slate-500"><?php echo $d['username']; ?></td>
                                    <td class="px-6 py-4 text-center flex justify-center gap-2">
                                        <a href="admin.php?page=bk&edit=<?php echo $d['id']; ?>" class="text-amber-500 hover:text-amber-600"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></a>
                                        <a href="admin.php?page=bk&hapus=<?php echo $d['id']; ?>" onclick="return confirm('Hapus Guru BK ini?')" class="text-red-500 hover:text-red-600"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></a>
                                    </td>
                                </tr>
                                <?php }
                                } else { echo "<tr><td colspan='3' class='px-6 py-12 text-center text-slate-400'>Belum ada data guru BK.</td></tr>"; } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <?php endif; ?>

    </main>

    <!-- FOOTER HAK CIPTA -->
    <footer class="bg-white border-t border-slate-200 mt-auto py-6">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <p class="text-slate-500 text-xs sm:text-sm font-medium">
                &copy; <?php echo date('Y'); ?> <span class="font-bold text-blue-600">SMK Ma'arif 4-5 Tambakboyo</span>. All rights reserved.
            </p>
        </div>
    </footer>

    <script>
        <?php if($swal_type && $swal_msg): ?>
            Swal.fire({ icon: '<?php echo $swal_type; ?>', title: 'Info', text: '<?php echo $swal_msg; ?>', confirmButtonColor: '#4f46e5' });
        <?php endif; ?>
    </script>
</body>
</html>