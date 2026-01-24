<?php
session_start();
include 'koneksi.php';

// --- KEAMANAN: HANYA ADMIN ---
if(!isset($_SESSION['status']) || $_SESSION['status'] != "login"){
    header("location:login.php?pesan=belum_login");
    exit;
}

// --- LOGIKA FILTER ---
$bulan_ini = date('m');
$tahun_ini = date('Y');

$bulan_pilih = isset($_GET['bulan']) ? $_GET['bulan'] : $bulan_ini;
$tahun_pilih = isset($_GET['tahun']) ? $_GET['tahun'] : $tahun_ini;
$kelas_pilih = isset($_GET['kelas']) ? $_GET['kelas'] : ''; 

$nama_bulan = date('F', mktime(0, 0, 0, $bulan_pilih, 10));

// --- QUERY UTAMA ---
$query = "SELECT log.*, s.nama, s.kelas, s.nis 
          FROM log_absensi log 
          JOIN siswa s ON log.rfid_uid = s.rfid_uid 
          WHERE MONTH(log.waktu) = '$bulan_pilih' 
          AND YEAR(log.waktu) = '$tahun_pilih'";

if (!empty($kelas_pilih)) {
    $query .= " AND s.kelas = '$kelas_pilih'";
}
$query .= " ORDER BY log.waktu DESC";
$result = mysqli_query($conn, $query);

// --- DATA CHART & STATISTIK ---
$total_data = 0;
$stat_hadir = 0;
$stat_telat = 0;
$stat_awal  = 0;
$stat_alpha = 0;
$stat_alpha_auto = 0; // Variabel baru untuk Alpha Otomatis

// Array untuk Chart Batang (Per Tanggal)
$daily_stats = [];

$data_rows = []; // Simpan data agar tidak perlu fetch ulang
while($row = mysqli_fetch_assoc($result)) {
    $data_rows[] = $row;
    $total_data++;
    $status = $row['status'];
    $tgl = date('j', strtotime($row['waktu'])); // Tanggal saja (1-31)

    // Hitung Kategori
    if (strpos($status, 'Telat') !== false || strpos($status, '10m') !== false) {
        $stat_telat++;
    } elseif (strpos($status, 'Pulang Lebih Awal') !== false) {
        $stat_awal++;
    } elseif (strpos($status, 'Alpha') !== false) {
        $stat_alpha++; // Menghitung SEMUA Alpha (termasuk otomatis & terlambat parah)
        
        // Cek spesifik jika ini Alpha Otomatis (dari scan.php)
        if (strpos($status, 'Otomatis') !== false) {
            $stat_alpha_auto++;
        }
    } else {
        $stat_hadir++;
    }

    // Data Harian
    if (!isset($daily_stats[$tgl])) $daily_stats[$tgl] = 0;
    $daily_stats[$tgl]++;
}

// Sortir data harian berdasarkan tanggal
ksort($daily_stats);
$chart_labels_daily = json_encode(array_keys($daily_stats));
$chart_data_daily = json_encode(array_values($daily_stats));

// --- AMBIL KELAS ---
$q_kelas = mysqli_query($conn, "SELECT DISTINCT kelas FROM siswa ORDER BY kelas ASC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Kekinian - <?php echo $nama_bulan; ?></title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        body { font-family: 'Outfit', sans-serif; }
        .glass-effect {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
        }
        @media print {
            .no-print { display: none !important; }
            body { background: white; font-size: 12px; }
            .shadow-xl, .shadow-lg { box-shadow: none !important; }
            .print-border { border: 1px solid #ddd; }
            /* Sembunyikan chart saat print agar hemat tinta, atau biarkan jika ingin */
            #chartSection { display: none; } 
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-800">

    <!-- Navbar Modern -->
    <nav class="bg-gradient-to-r from-blue-800 to-indigo-900 text-white p-4 shadow-xl sticky top-0 z-50 no-print">
        <div class="container mx-auto flex justify-between items-center">
            <div class="flex items-center gap-3">
                <div class="bg-white/20 p-2 rounded-lg">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                </div>
                <div>
                    <h1 class="font-bold text-xl tracking-tight">Analytics Absensi</h1>
                    <p class="text-xs text-blue-200">Monitoring & Pelaporan</p>
                </div>
            </div>
            <div class="flex gap-3">
                <a href="index.php" class="px-4 py-2 rounded-lg text-sm font-semibold text-blue-100 hover:bg-white/10 transition">Dashboard</a>
                <button onclick="window.print()" class="bg-white text-blue-900 hover:bg-blue-50 px-5 py-2 rounded-lg text-sm font-bold shadow-lg flex items-center gap-2 transition transform hover:scale-105">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                    Print Laporan
                </button>
            </div>
        </div>
    </nav>

    <div class="container mx-auto p-6 space-y-8">

        <!-- FILTER & CONTROL PANEL -->
        <div class="glass-effect p-6 rounded-2xl shadow-lg border border-slate-200 no-print">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1 tracking-wider">Bulan</label>
                    <select name="bulan" class="w-full p-2.5 rounded-lg border-slate-300 bg-slate-50 focus:ring-2 focus:ring-blue-500 outline-none border">
                        <?php
                        $bulan_array = [1=>'Januari', 2=>'Februari', 3=>'Maret', 4=>'April', 5=>'Mei', 6=>'Juni', 7=>'Juli', 8=>'Agustus', 9=>'September', 10=>'Oktober', 11=>'November', 12=>'Desember'];
                        foreach ($bulan_array as $k => $v) {
                            $sel = ($k == $bulan_pilih) ? 'selected' : '';
                            echo "<option value='$k' $sel>$v</option>";
                        }
                        ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1 tracking-wider">Tahun</label>
                    <select name="tahun" class="w-full p-2.5 rounded-lg border-slate-300 bg-slate-50 focus:ring-2 focus:ring-blue-500 outline-none border">
                        <?php
                        for ($t = 2023; $t <= date('Y'); $t++) {
                            $sel = ($t == $tahun_pilih) ? 'selected' : '';
                            echo "<option value='$t' $sel>$t</option>";
                        }
                        ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1 tracking-wider">Kelas</label>
                    <select name="kelas" class="w-full p-2.5 rounded-lg border-slate-300 bg-slate-50 focus:ring-2 focus:ring-blue-500 outline-none border">
                        <option value="">Semua Kelas</option>
                        <?php 
                        mysqli_data_seek($q_kelas, 0);
                        while($row_kelas = mysqli_fetch_assoc($q_kelas)) {
                            $k = $row_kelas['kelas'];
                            $sel = ($k == $kelas_pilih) ? 'selected' : '';
                            echo "<option value='$k' $sel>$k</option>";
                        }
                        ?>
                    </select>
                </div>
                <button type="submit" class="bg-indigo-600 text-white p-2.5 rounded-lg font-bold hover:bg-indigo-700 transition shadow-md">
                    Terapkan Filter
                </button>
            </form>
        </div>

        <!-- HEADER CETAK -->
        <div class="hidden print:block text-center mb-6 border-b-2 border-black pb-4">
            <h2 class="text-3xl font-bold text-slate-900">LAPORAN ABSENSI SISWA</h2>
            <p class="text-lg font-semibold text-slate-600">SMK DIGITAL NUSANTARA</p>
            <p class="text-sm mt-2">Periode: <?php echo $nama_bulan . " " . $tahun_pilih; ?> <?php if(!empty($kelas_pilih)) echo " | Kelas: " . $kelas_pilih; ?></p>
        </div>

        <!-- STATISTIK CARDS (KEKINIAN) - DIUPDATE MENJADI 5 KOLOM -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <!-- Card Total -->
            <div class="bg-white p-6 rounded-2xl shadow-lg border-l-4 border-blue-500 flex items-center justify-between hover:-translate-y-1 transition duration-300">
                <div>
                    <p class="text-slate-400 text-xs font-bold uppercase tracking-wider">Total</p>
                    <h3 class="text-3xl font-bold text-slate-800 mt-1"><?php echo $total_data; ?></h3>
                </div>
                <div class="bg-blue-100 p-3 rounded-full text-blue-600">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                </div>
            </div>

            <!-- Card Hadir -->
            <div class="bg-white p-6 rounded-2xl shadow-lg border-l-4 border-green-500 flex items-center justify-between hover:-translate-y-1 transition duration-300">
                <div>
                    <p class="text-slate-400 text-xs font-bold uppercase tracking-wider">Hadir</p>
                    <h3 class="text-3xl font-bold text-green-600 mt-1"><?php echo $stat_hadir; ?></h3>
                </div>
                <div class="bg-green-100 p-3 rounded-full text-green-600">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                </div>
            </div>

            <!-- Card Telat -->
            <div class="bg-white p-6 rounded-2xl shadow-lg border-l-4 border-orange-500 flex items-center justify-between hover:-translate-y-1 transition duration-300">
                <div>
                    <p class="text-slate-400 text-xs font-bold uppercase tracking-wider">Telat</p>
                    <h3 class="text-3xl font-bold text-orange-600 mt-1"><?php echo $stat_telat; ?></h3>
                </div>
                <div class="bg-orange-100 p-3 rounded-full text-orange-600">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                </div>
            </div>

            

            <!-- Card Alpha Manual / Plg Awal -->
            <div class="bg-white p-6 rounded-2xl shadow-lg border-l-4 border-red-500 flex items-center justify-between hover:-translate-y-1 transition duration-300">
                <div>
                    <p class="text-slate-400 text-xs font-bold uppercase tracking-wider">Alpha Lain</p>
                    <!-- Menampilkan sisa alpha (manual) + pulang awal -->
                    <h3 class="text-3xl font-bold text-red-600 mt-1"><?php echo ($stat_alpha - $stat_alpha_auto) + $stat_awal; ?></h3>
                </div>
                <div class="bg-red-100 p-3 rounded-full text-red-600">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                </div>
            </div>
        </div>


        <!-- DATA TABLE -->
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden border border-slate-200 print-border">
            <div class="p-6 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                <h3 class="text-lg font-bold text-slate-700">Rincian Data Absensi</h3>
                <span class="text-xs bg-slate-200 px-2 py-1 rounded text-slate-600 font-mono"><?php echo count($data_rows); ?> Rows</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600">
                    <thead class="bg-slate-100 text-slate-500 uppercase font-bold text-xs tracking-wider">
                        <tr>
                            <th class="px-6 py-4">Tanggal</th>
                            <th class="px-6 py-4">Jam</th>
                            <th class="px-6 py-4">NIS</th>
                            <th class="px-6 py-4">Nama Siswa</th>
                            <th class="px-6 py-4">Kelas</th>
                            <th class="px-6 py-4">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if(count($data_rows) > 0): ?>
                            <?php foreach($data_rows as $row): 
                                $tgl = date('d M Y', strtotime($row['waktu']));
                                $jam = date('H:i', strtotime($row['waktu']));
                                $status = $row['status'];
                                
                                // Styling Status Kekinian
                                $badge = "bg-slate-100 text-slate-600";
                                if(strpos($status, 'Telat') !== false) $badge = "bg-orange-100 text-orange-600 border border-orange-200";
                                elseif(strpos($status, 'Pulang Lebih Awal') !== false) $badge = "bg-yellow-100 text-yellow-700 border border-yellow-200";
                                elseif(strpos($status, 'Otomatis') !== false) $badge = "bg-purple-100 text-purple-600 border border-purple-200";
                                elseif(strpos($status, 'Alpha') !== false) $badge = "bg-red-100 text-red-600 border border-red-200";
                                elseif(strpos($status, 'Datang') !== false) $badge = "bg-blue-100 text-blue-600 border border-blue-200";
                                elseif(strpos($status, 'Pulang') !== false) $badge = "bg-green-100 text-green-600 border border-green-200";
                            ?>
                            <tr class="hover:bg-slate-50 transition duration-150">
                                <td class="px-6 py-4 font-medium"><?php echo $tgl; ?></td>
                                <td class="px-6 py-4 font-mono text-indigo-600"><?php echo $jam; ?></td>
                                <td class="px-6 py-4"><?php echo $row['nis']; ?></td>
                                <td class="px-6 py-4 font-bold text-slate-800"><?php echo $row['nama']; ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 rounded bg-slate-200 text-xs font-bold text-slate-600"><?php echo $row['kelas']; ?></span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wide <?php echo $badge; ?>">
                                        <?php echo $status; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-slate-400">
                                    <div class="flex flex-col items-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" class="mb-2 text-slate-300"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                        <span>Tidak ada data ditemukan.</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

  </body>
</html>