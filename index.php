<?php
session_start();
include 'koneksi.php'; 

date_default_timezone_set('Asia/Jakarta');
$today = date('Y-m-d');
$jam_sekarang = date('H:i:s');

// =================================================================================
// [LOGIKA OTOMATIS] PROSES ALPHA (KEMARIN & HARI INI)
// =================================================================================
function prosesAlphaOtomatis($conn) {
    $tgl_hari_ini = date('Y-m-d');
    $tgl_kemarin = date('Y-m-d', strtotime("-1 days"));
    $jam_skrg = date('H:i:s');
    
    // Batas waktu: Jika lewat jam ini siswa belum datang, dianggap Alpha
    // Diset ke jam pulang paling lambat (13:50) atau lebih (misal 14:00)
    $batas_jam_alpha_hari_ini = "14:00:00"; 

    // 1. PROSES ALPHA UNTUK KEMARIN (Menutup data bolong)
    // Cari siswa yang tidak ada log sama sekali di tanggal kemarin
    $cek_kemarin = mysqli_query($conn, "SELECT id, rfid_uid FROM siswa WHERE rfid_uid NOT IN (SELECT rfid_uid FROM log_absensi WHERE DATE(waktu) = '$tgl_kemarin')");
    while($row = mysqli_fetch_assoc($cek_kemarin)) {
        $uid = $row['rfid_uid'];
        // Pastikan tidak duplikat
        mysqli_query($conn, "INSERT INTO log_absensi (rfid_uid, waktu, status) VALUES ('$uid', '$tgl_kemarin 13:50:00', 'Alpha (Otomatis)')");
    }

    // 2. PROSES ALPHA UNTUK HARI INI
    if ($jam_skrg >= $batas_jam_alpha_hari_ini) {
        $cek_hari_ini = mysqli_query($conn, "SELECT id, rfid_uid FROM siswa WHERE rfid_uid NOT IN (SELECT rfid_uid FROM log_absensi WHERE DATE(waktu) = '$tgl_hari_ini')");
        while($row = mysqli_fetch_assoc($cek_hari_ini)) {
            $uid = $row['rfid_uid'];
            mysqli_query($conn, "INSERT INTO log_absensi (rfid_uid, waktu, status) VALUES ('$uid', '$tgl_hari_ini $jam_skrg', 'Alpha (Otomatis)')");
        }
    }
}
// Jalankan fungsi otomatisasi
prosesAlphaOtomatis($conn);
// =================================================================================

// --- QUERY DATA DASHBOARD (UPDATED LOGIC) ---

// 1. Total Siswa
$q_siswa = mysqli_query($conn, "SELECT COUNT(*) as total FROM siswa");
$d_siswa = mysqli_fetch_assoc($q_siswa);
$total_siswa = $d_siswa['total'];

// 2. Siswa Hadir 
// Syarat: Status BUKAN Alpha DAN Approval TIDAK Ditolak
$q_hadir = mysqli_query($conn, "SELECT COUNT(DISTINCT rfid_uid) as total FROM log_absensi 
                                WHERE DATE(waktu) = '$today' 
                                AND status NOT LIKE '%Alpha%' 
                                AND (status_approval IS NULL OR status_approval != 'Rejected')");
$d_hadir = mysqli_fetch_assoc($q_hadir);
$jumlah_hadir = $d_hadir['total'];

// 3. Siswa Alpha
// Syarat: Status MENGANDUNG Alpha ATAU Approval DITOLAK (Rejected)
$q_alpha = mysqli_query($conn, "SELECT COUNT(DISTINCT rfid_uid) as total FROM log_absensi 
                                WHERE DATE(waktu) = '$today' 
                                AND (status LIKE '%Alpha%' OR status_approval = 'Rejected')");
$d_alpha = mysqli_fetch_assoc($q_alpha);
$jumlah_alpha = $d_alpha['total'];

// 4. Belum Hadir
$belum_hadir = $total_siswa - ($jumlah_hadir + $jumlah_alpha);
if($belum_hadir < 0) $belum_hadir = 0;

// 5. Ambil Data Riwayat Absensi Hari Ini
$query_log = "SELECT log_absensi.*, siswa.nama, siswa.kelas 
              FROM log_absensi 
              JOIN siswa ON log_absensi.rfid_uid = siswa.rfid_uid 
              WHERE DATE(log_absensi.waktu) = '$today' 
              ORDER BY log_absensi.waktu DESC";
$result_log = mysqli_query($conn, $query_log);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link rel="shortcut icon" href="gambar/logo.jpg">
    <title>Dashboard Modern - E-Absensi</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Google Fonts: Outfit (Modern Sans Serif) -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Auto Refresh -->
    <meta http-equiv="refresh" content="5">

    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #f8fafc; }
        .glass-nav {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.3);
        }
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        }
        /* Custom scrollbar untuk tabel */
        .no-scrollbar::-webkit-scrollbar { height: 4px; }
        .no-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    </style>
</head>
<body class="text-slate-800 flex flex-col min-h-screen">

    <!-- NAVBAR MODERN (Glassmorphism & Responsive) -->
    <nav class="glass-nav fixed w-full z-50 top-0 transition-all duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row justify-between items-center py-3 md:h-20 gap-4">
                <!-- Logo Area -->
                <div class="flex items-center gap-4 self-start md:self-center">
                    <div class="relative group flex-shrink-0">
                        <div class="absolute -inset-0.5 bg-gradient-to-r from-emerald-500 to-teal-500 rounded-full blur opacity-50 group-hover:opacity-75 transition duration-200"></div>
                        <img src="gambar/logo.jpg" alt="Logo" class="relative h-10 w-10 sm:h-12 sm:w-12 rounded-full object-cover border-2 border-white" onerror="this.src='https://ui-avatars.com/api/?name=SMK&background=10b981&color=fff'">
                    </div>
                    <div>
                        <h1 class="text-lg sm:text-xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-emerald-600 to-teal-600 leading-tight">
                            E-Absensi RFID
                        </h1>
                        <p class="text-[10px] sm:text-xs text-slate-500 font-medium tracking-wide">SMK Ma'arif 4-5 Tambakboyo</p>
                        
                        <!-- BREADCRUMB -->
                        <nav class="flex mt-1" aria-label="Breadcrumb">
                          <ol class="inline-flex items-center space-x-1 md:space-x-2">
                            <li class="inline-flex items-center">
                              <span class="inline-flex items-center text-[10px] font-medium text-slate-400">
                                Home
                              </span>
                            </li>
                            <li>
                              <div class="flex items-center">
                                <svg class="w-3 h-3 text-slate-300 mx-0.5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path></svg>
                                <span class="text-[10px] font-medium text-emerald-500">Dashboard</span>
                              </div>
                            </li>
                          </ol>
                        </nav>
                        <!-- END BREADCRUMB -->
                    </div>
                </div>

                <!-- Menu Navigasi (Responsive) -->
                <div class="flex flex-wrap justify-center md:justify-end gap-2 w-full md:w-auto">
                        <?php if(isset($_SESSION['status']) && $_SESSION['status'] == "login"): ?>
                        <div class="hidden md:block h-6 w-px bg-slate-200 mx-2"></div>
                        
                        <a href="scan.php" class="flex items-center gap-2 px-3 py-2 rounded-lg text-xs sm:text-sm font-medium text-white bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-600 hover:to-teal-600 shadow-md shadow-emerald-200 transition-all">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" /></svg>
                            <span class="hidden sm:inline">Mode</span> Scan
                        </a>
                        
                        <a href="admin.php" class="px-3 py-2 rounded-lg text-xs sm:text-sm font-medium text-slate-600 hover:text-emerald-600 hover:bg-emerald-50 transition-all border border-transparent hover:border-emerald-100">
                            Admin <span class="hidden sm:inline"></span>
                        </a>
                        
                        <a href="rekap.php" class="px-3 py-2 rounded-lg text-xs sm:text-sm font-medium text-slate-600 hover:text-emerald-600 hover:bg-emerald-50 transition-all border border-transparent hover:border-emerald-100">
                            Rekap
                        </a>

                        <a href="logout.php" class="ml-1 p-2 rounded-lg text-slate-400 hover:text-red-500 hover:bg-red-50 transition-all" title="Logout">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
                        </a>
                    <?php else: ?>

                        <a href="scan.php" class="flex items-center gap-2 px-3 py-2 rounded-lg text-xs sm:text-sm font-medium text-white bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-600 hover:to-teal-600 shadow-md shadow-emerald-200 transition-all">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" /></svg>
                            <span class="hidden sm:inline">Mode</span> Scan
                        </a>

                        <a href="login.php" class="px-3 py-2 rounded-lg text-xs sm:text-sm font-bold text-emerald-600 bg-emerald-50 hover:bg-emerald-100 transition-all border border-emerald-100">
                            Login Admin
                        </a>
                         <a href="login_siswa.php" class="px-3 py-2 rounded-lg text-xs sm:text-sm font-medium text-slate-600 hover:text-emerald-600 hover:bg-emerald-50 transition-all">
                            Login Siswa
                        </a>
                         <a href="login_wali.php" class="px-3 py-2 rounded-lg text-xs sm:text-sm font-medium text-slate-600 hover:text-emerald-600 hover:bg-emerald-50 transition-all">
                            Login Wali
                        </a>
                         <a href="login_bk.php" class="px-3 py-2 rounded-lg text-xs sm:text-sm font-medium text-rose-600 hover:text-rose-700 hover:bg-rose-50 transition-all">
                            Login BK
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- KONTEN UTAMA -->
    <main class="max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 pt-40 md:pt-28 pb-12 flex-grow">
        
        <!-- Welcome Banner -->
        <div class="mb-8 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
                <h2 class="text-2xl sm:text-3xl font-bold text-slate-800">Dashboard Monitoring</h2>
                <p class="text-slate-500 text-sm sm:text-base mt-1 flex items-center gap-2">
                    <span class="inline-block w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    Update Realtime: <?php echo date('d F Y'); ?>
                </p>
            </div>
        </div>

        <!-- GRID KARTU STATISTIK (Responsive Grid) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-10">
            
            <!-- 1. Total Siswa -->
            <div class="bg-white p-5 sm:p-6 rounded-2xl shadow-sm border border-slate-100 card-hover transition-all duration-300 group">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-slate-400 text-[10px] sm:text-xs font-bold uppercase tracking-wider mb-1">Total Siswa</p>
                        <h3 class="text-2xl sm:text-3xl font-bold text-slate-800"><?php echo $total_siswa; ?></h3>
                    </div>
                    <div class="p-2 sm:p-3 bg-blue-50 text-blue-600 rounded-xl group-hover:bg-blue-600 group-hover:text-white transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 sm:h-6 sm:w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                    </div>
                </div>
                <div class="mt-4 h-1 w-full bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full bg-blue-500 w-full rounded-full"></div>
                </div>
            </div>

            <!-- 2. Siswa Hadir -->
            <div class="bg-white p-5 sm:p-6 rounded-2xl shadow-sm border border-slate-100 card-hover transition-all duration-300 group">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-slate-400 text-[10px] sm:text-xs font-bold uppercase tracking-wider mb-1">Hadir Hari Ini</p>
                        <h3 class="text-2xl sm:text-3xl font-bold text-emerald-600"><?php echo $jumlah_hadir; ?></h3>
                    </div>
                    <div class="p-2 sm:p-3 bg-emerald-50 text-emerald-600 rounded-xl group-hover:bg-emerald-600 group-hover:text-white transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 sm:h-6 sm:w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </div>
                </div>
                <div class="mt-4 h-1 w-full bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full bg-emerald-500 rounded-full" style="width: <?php echo ($total_siswa > 0) ? ($jumlah_hadir/$total_siswa)*100 : 0; ?>%"></div>
                </div>
            </div>

            <!-- 3. Siswa Alpha -->
            <div class="bg-white p-5 sm:p-6 rounded-2xl shadow-sm border border-slate-100 card-hover transition-all duration-300 group">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-slate-400 text-[10px] sm:text-xs font-bold uppercase tracking-wider mb-1">Alpha / Bolos</p>
                        <h3 class="text-2xl sm:text-3xl font-bold text-purple-600"><?php echo $jumlah_alpha; ?></h3>
                    </div>
                    <div class="p-2 sm:p-3 bg-purple-50 text-purple-600 rounded-xl group-hover:bg-purple-600 group-hover:text-white transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 sm:h-6 sm:w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </div>
                </div>
                <div class="mt-4 h-1 w-full bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full bg-purple-500 rounded-full" style="width: <?php echo ($total_siswa > 0) ? ($jumlah_alpha/$total_siswa)*100 : 0; ?>%"></div>
                </div>
            </div>

            <!-- 4. Belum Hadir -->
            <div class="bg-white p-5 sm:p-6 rounded-2xl shadow-sm border border-slate-100 card-hover transition-all duration-300 group">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-slate-400 text-[10px] sm:text-xs font-bold uppercase tracking-wider mb-1">Belum Hadir</p>
                        <h3 class="text-2xl sm:text-3xl font-bold text-rose-600"><?php echo $belum_hadir; ?></h3>
                    </div>
                    <div class="p-2 sm:p-3 bg-rose-50 text-rose-600 rounded-xl group-hover:bg-rose-600 group-hover:text-white transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 sm:h-6 sm:w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </div>
                </div>
                <div class="mt-4 h-1 w-full bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full bg-rose-500 rounded-full" style="width: <?php echo ($total_siswa > 0) ? ($belum_hadir/$total_siswa)*100 : 0; ?>%"></div>
                </div>
            </div>
        </div>

        <!-- TABEL AKTIVITAS -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center bg-white">
                <h3 class="font-bold text-base sm:text-lg text-slate-800 flex items-center gap-2">
                    <span class="relative flex h-3 w-3">
                      <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                      <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                    </span>
                    Log Aktivitas Terbaru
                </h3>
                <span class="text-[10px] sm:text-xs font-medium text-slate-400 bg-slate-50 px-3 py-1 rounded-full border border-slate-100">
                    Live Update
                </span>
            </div>
            
            <div class="overflow-x-auto no-scrollbar">
                <table class="w-full text-left border-collapse min-w-[600px]">
                    <thead>
                        <tr class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider border-b border-slate-100">
                            <th class="px-6 py-4 font-semibold whitespace-nowrap">Waktu Scan</th>
                            <th class="px-6 py-4 font-semibold whitespace-nowrap">Nama Siswa</th>
                            <th class="px-6 py-4 font-semibold whitespace-nowrap">Kelas</th>
                            <th class="px-6 py-4 font-semibold whitespace-nowrap">ID Kartu</th>
                            <th class="px-6 py-4 font-semibold whitespace-nowrap">Status</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm divide-y divide-slate-100">
                        <?php 
                        if (mysqli_num_rows($result_log) > 0) {
                            while($row = mysqli_fetch_assoc($result_log)) {
                                $waktu = date('H:i', strtotime($row['waktu']));
                                $status = htmlspecialchars($row['status']);
                                $approval = isset($row['status_approval']) ? $row['status_approval'] : '';
                                
                                // --- LOGIKA WARNA BADGE MODERN ---
                                $badgeClass = "bg-slate-100 text-slate-600 border-slate-200"; 
                                $dotColor = "bg-slate-400";

                                if ($approval == 'Rejected' || strpos($status, 'Alpha') !== false) {
                                    $badgeClass = "bg-rose-50 text-rose-700 border-rose-100"; $dotColor = "bg-rose-500";
                                    if(strpos($status, 'Izin') !== false || strpos($status, 'Sakit') !== false) { $status .= " (Ditolak)"; }
                                }
                                elseif (strpos($status, 'Lebih Awal') !== false) {
                                    $badgeClass = "bg-amber-50 text-amber-700 border-amber-100"; $dotColor = "bg-amber-500";
                                } 
                                elseif (strpos($status, 'Telat') !== false) {
                                    $badgeClass = "bg-orange-50 text-orange-700 border-orange-100"; $dotColor = "bg-orange-500";
                                } 
                                elseif (strpos($status, 'Datang') !== false) {
                                    $badgeClass = "bg-blue-50 text-blue-700 border-blue-100"; $dotColor = "bg-blue-500";
                                }
                                elseif (strpos($status, 'Pulang') !== false) {
                                    $badgeClass = "bg-emerald-50 text-emerald-700 border-emerald-100"; $dotColor = "bg-emerald-500";
                                }
                                elseif (strpos($status, 'Sholat') !== false) {
                                    $badgeClass = "bg-purple-50 text-purple-700 border-purple-100"; $dotColor = "bg-purple-500";
                                }
                        ?>
                            <tr class="hover:bg-slate-50 transition-colors duration-150">
                                <td class="px-6 py-4 font-mono text-slate-500 whitespace-nowrap">
                                    <?php echo $waktu; ?> WIB
                                </td>
                                <td class="px-6 py-4">
                                    <div class="font-bold text-slate-700 whitespace-nowrap"><?php echo htmlspecialchars($row['nama']); ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2.5 py-0.5 rounded-md text-xs font-bold bg-slate-100 text-slate-600 border border-slate-200 whitespace-nowrap">
                                        <?php echo htmlspecialchars($row['kelas']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 font-mono text-xs text-slate-400 whitespace-nowrap">
                                    <?php echo htmlspecialchars($row['rfid_uid']); ?>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold border whitespace-nowrap <?php echo $badgeClass; ?>">
                                        <span class="w-1.5 h-1.5 rounded-full <?php echo $dotColor; ?>"></span>
                                        <?php echo $status; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php 
                            } 
                        } else { 
                        ?>
                            <tr>
                                <td colspan="5" class="px-6 py-16 text-center">
                                    <div class="flex flex-col items-center justify-center text-slate-400">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mb-3 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        <p class="text-sm font-medium">Belum ada aktivitas absen hari ini.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- FOOTER HAK CIPTA -->
    <footer class="bg-white border-t border-slate-200 py-6 mt-auto">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <p class="text-slate-500 text-xs sm:text-sm font-medium">
                &copy; <?php echo date('Y'); ?> <span class="font-bold text-emerald-600">SMK Ma'arif 4-5 Tambakboyo</span>. All rights reserved.
            </p>
            <p class="text-slate-400 text-[10px] sm:text-xs mt-1">
                Sistem Informasi Absensi RFID Berbasis Web
            </p>
        </div>
    </footer>

</body>
</html>