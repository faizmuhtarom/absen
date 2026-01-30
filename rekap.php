<?php
session_start();
include 'koneksi.php';

// --- KEAMANAN ---
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

// --- QUERY DATA ---
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

// --- HITUNG STATISTIK ---
$total_data = 0;
$stat_hadir = 0;
$stat_telat = 0;
$stat_awal  = 0;
$stat_alpha = 0;
$stat_alpha_auto = 0;

$data_rows = [];

while($row = mysqli_fetch_assoc($result)) {
    $data_rows[] = $row;
    $total_data++;
    $status = $row['status'];
    
    // Logika Hitung Statistik (Sama dengan index.php)
    $approval = isset($row['status_approval']) ? $row['status_approval'] : '';

    if ($approval == 'Rejected' || strpos($status, 'Alpha') !== false) {
        $stat_alpha++;
        if (strpos($status, 'Otomatis') !== false) $stat_alpha_auto++;
    } elseif (strpos($status, 'Lebih Awal') !== false) {
        $stat_awal++; // Dianggap peringatan, bisa dimasukkan ke alpha atau kategori sendiri
    } elseif (strpos($status, 'Telat') !== false) {
        $stat_telat++;
    } elseif (strpos($status, 'Sakit') !== false || strpos($status, 'Izin') !== false) {
        // Izin/Sakit hanya dihitung jika Approved (Logic di atas sudah filter Alpha jika Rejected)
        // Di sini kita anggap masuk kategori "Izin/Sakit" (bisa buat variabel baru jika mau dipisah dari Hadir)
        $stat_hadir++; // Atau buat $stat_izin sendiri
    } else {
        $stat_hadir++;
    }
}

// --- AMBIL DATA KELAS UNTUK FILTER ---
$q_kelas = mysqli_query($conn, "SELECT DISTINCT kelas FROM siswa ORDER BY kelas ASC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Premium - <?php echo $nama_bulan; ?></title>
    <link rel="shortcut icon" href="gambar/logo.jpg">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts: Outfit -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #f8fafc; }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        .glass-nav {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.3);
        }

        /* Print Styles */
        @media print {
            .no-print { display: none !important; }
            body { background: white; }
            .shadow-xl, .shadow-lg, .shadow-sm { box-shadow: none !important; }
            .print-border { border: 1px solid #000; }
            #chartSection { display: block !important; break-inside: avoid; }
            .gradient-card { background: white !important; color: black !important; border: 1px solid #ccc; }
            .text-white { color: black !important; }
        }
    </style>
</head>
<body class="text-slate-800 flex flex-col min-h-screen">

    <!-- NAVBAR -->
    <nav class="glass-nav fixed w-full z-50 top-0 transition-all duration-300 no-print">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <!-- Logo & Title -->
                <div class="flex items-center gap-4">
                    <div class="bg-gradient-to-tr from-emerald-500 to-teal-500 p-2.5 rounded-xl text-white shadow-lg">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-slate-800 tracking-tight">Laporan Absensi</h1>
                        <p class="text-xs text-slate-500 font-medium">Analytics & Export</p>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex gap-3">
                    <a href="index.php" class="px-4 py-2.5 rounded-xl text-sm font-semibold text-slate-600 hover:bg-slate-100 transition-all">
                        Dashboard
                    </a>
                    <button onclick="window.print()" class="bg-slate-900 hover:bg-slate-800 text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow-lg shadow-slate-200 flex items-center gap-2 transition transform hover:scale-105 active:scale-95">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                        Export PDF
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 pt-28 pb-12 flex-grow space-y-8">

        <!-- FILTER BAR -->
        <div class="bg-white rounded-2xl p-1 shadow-sm border border-slate-200 no-print">
            <form method="GET" class="flex flex-col md:flex-row gap-2 p-2">
                
                <!-- Select Bulan -->
                <div class="relative flex-1">
                    <select name="bulan" class="w-full appearance-none bg-slate-50 border border-slate-200 text-slate-700 py-3 px-4 pr-8 rounded-xl leading-tight focus:outline-none focus:bg-white focus:border-emerald-500 focus:ring-2 focus:ring-emerald-50 font-medium cursor-pointer">
                        <?php
                        $bulan_array = [1=>'Januari', 2=>'Februari', 3=>'Maret', 4=>'April', 5=>'Mei', 6=>'Juni', 7=>'Juli', 8=>'Agustus', 9=>'September', 10=>'Oktober', 11=>'November', 12=>'Desember'];
                        foreach ($bulan_array as $k => $v) {
                            $sel = ($k == $bulan_pilih) ? 'selected' : '';
                            echo "<option value='$k' $sel>$v</option>";
                        }
                        ?>
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-slate-500"><svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/></svg></div>
                </div>

                <!-- Select Tahun -->
                <div class="relative flex-1">
                    <select name="tahun" class="w-full appearance-none bg-slate-50 border border-slate-200 text-slate-700 py-3 px-4 pr-8 rounded-xl leading-tight focus:outline-none focus:bg-white focus:border-emerald-500 focus:ring-2 focus:ring-emerald-50 font-medium cursor-pointer">
                        <?php
                        for ($t = 2023; $t <= date('Y'); $t++) {
                            $sel = ($t == $tahun_pilih) ? 'selected' : '';
                            echo "<option value='$t' $sel>$t</option>";
                        }
                        ?>
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-slate-500"><svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/></svg></div>
                </div>

                <!-- Select Kelas -->
                <div class="relative flex-1">
                    <select name="kelas" class="w-full appearance-none bg-slate-50 border border-slate-200 text-slate-700 py-3 px-4 pr-8 rounded-xl leading-tight focus:outline-none focus:bg-white focus:border-emerald-500 focus:ring-2 focus:ring-emerald-50 font-medium cursor-pointer">
                        <option value="">-- Semua Kelas --</option>
                        <?php 
                        mysqli_data_seek($q_kelas, 0);
                        while($row_kelas = mysqli_fetch_assoc($q_kelas)) {
                            $k = $row_kelas['kelas'];
                            $sel = ($k == $kelas_pilih) ? 'selected' : '';
                            echo "<option value='$k' $sel>$k</option>";
                        }
                        ?>
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-slate-500"><svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/></svg></div>
                </div>

                <!-- Tombol Filter -->
                <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white px-8 py-3 rounded-xl font-bold shadow-lg shadow-emerald-200 transition flex-none flex items-center justify-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
                    Filter
                </button>
            </form>
        </div>

        <!-- JUDUL PRINT (Hanya muncul saat print) -->
        <div class="hidden print:block text-center mb-8 border-b-2 border-black pb-6">
            <h2 class="text-4xl font-bold text-black mb-2">LAPORAN ABSENSI SISWA</h2>
            <p class="text-xl font-medium text-gray-600">SMK DIGITAL NUSANTARA</p>
            <div class="mt-4 text-sm flex justify-center gap-6">
                <p><strong>Periode:</strong> <?php echo $nama_bulan . " " . $tahun_pilih; ?></p>
                <?php if(!empty($kelas_pilih)): ?><p><strong>Kelas:</strong> <?php echo $kelas_pilih; ?></p><?php endif; ?>
            </div>
        </div>

        <!-- STATS GRID (Gradient Cards) -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-6">
            <!-- Total Activity -->
            <div class="gradient-card bg-gradient-to-br from-slate-700 to-slate-800 rounded-2xl p-6 text-white shadow-xl relative overflow-hidden group">
                <div class="absolute top-0 right-0 -mt-4 -mr-4 w-24 h-24 bg-white/10 rounded-full blur-xl group-hover:scale-150 transition duration-500"></div>
                <p class="text-slate-300 text-xs font-bold uppercase tracking-wider mb-1">Total Aktivitas</p>
                <h3 class="text-3xl font-bold"><?php echo $total_data; ?></h3>
            </div>

            <!-- Hadir (Green) -->
            <div class="gradient-card bg-gradient-to-br from-emerald-500 to-teal-600 rounded-2xl p-6 text-white shadow-xl shadow-emerald-200 relative overflow-hidden group">
                <div class="absolute top-0 right-0 -mt-4 -mr-4 w-24 h-24 bg-white/20 rounded-full blur-xl group-hover:scale-150 transition duration-500"></div>
                <p class="text-emerald-100 text-xs font-bold uppercase tracking-wider mb-1">Hadir / Tepat</p>
                <h3 class="text-3xl font-bold"><?php echo $stat_hadir; ?></h3>
            </div>

            <!-- Telat (Orange) -->
            <div class="gradient-card bg-gradient-to-br from-orange-400 to-amber-500 rounded-2xl p-6 text-white shadow-xl shadow-orange-200 relative overflow-hidden group">
                <div class="absolute top-0 right-0 -mt-4 -mr-4 w-24 h-24 bg-white/20 rounded-full blur-xl group-hover:scale-150 transition duration-500"></div>
                <p class="text-orange-100 text-xs font-bold uppercase tracking-wider mb-1">Terlambat</p>
                <h3 class="text-3xl font-bold"><?php echo $stat_telat; ?></h3>
            </div>

            <!-- Alpha Auto (Purple) -->
            <div class="gradient-card bg-gradient-to-br from-violet-500 to-purple-600 rounded-2xl p-6 text-white shadow-xl shadow-violet-200 relative overflow-hidden group">
                <div class="absolute top-0 right-0 -mt-4 -mr-4 w-24 h-24 bg-white/20 rounded-full blur-xl group-hover:scale-150 transition duration-500"></div>
                <p class="text-violet-100 text-xs font-bold uppercase tracking-wider mb-1">Alpha Otomatis</p>
                <h3 class="text-3xl font-bold"><?php echo $stat_alpha_auto; ?></h3>
            </div>

            <!-- Alpha Lain (Red) -->
            <div class="gradient-card bg-gradient-to-br from-rose-500 to-pink-600 rounded-2xl p-6 text-white shadow-xl shadow-rose-200 relative overflow-hidden group">
                <div class="absolute top-0 right-0 -mt-4 -mr-4 w-24 h-24 bg-white/20 rounded-full blur-xl group-hover:scale-150 transition duration-500"></div>
                <p class="text-rose-100 text-xs font-bold uppercase tracking-wider mb-1">Alpha Lain / Pulang Awal</p>
                <h3 class="text-3xl font-bold"><?php echo ($stat_alpha - $stat_alpha_auto) + $stat_awal; ?></h3>
            </div>
        </div>

        <!-- DATA TABLE -->
        <div class="bg-white rounded-2xl shadow-lg border border-slate-100 overflow-hidden">
            <div class="px-8 py-6 border-b border-slate-100 bg-white flex flex-col md:flex-row justify-between items-center gap-4">
                <h3 class="text-xl font-bold text-slate-800">Detail Log Absensi</h3>
                <span class="bg-slate-100 text-slate-600 px-4 py-1.5 rounded-full text-sm font-bold border border-slate-200">
                    <?php echo count($data_rows); ?> Data Ditemukan
                </span>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-50/50 text-slate-500 uppercase text-xs font-bold tracking-wider">
                        <tr>
                            <th class="px-8 py-4">Siswa</th>
                            <th class="px-6 py-4">Tanggal</th>
                            <th class="px-6 py-4">Jam</th>
                            <th class="px-6 py-4">Kelas</th>
                            <th class="px-6 py-4">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm text-slate-600">
                        <?php if(count($data_rows) > 0): ?>
                            <?php foreach($data_rows as $row): 
                                $tgl = date('d M Y', strtotime($row['waktu']));
                                $jam = date('H:i', strtotime($row['waktu']));
                                $status = $row['status'];
                                $approval = isset($row['status_approval']) ? $row['status_approval'] : '';
                                
                                // Badge Style Generator
                                $badgeColor = "bg-slate-100 text-slate-600 border-slate-200";
                                $dotColor = "bg-slate-400";

                                if ($approval == 'Rejected' || strpos($status, 'Alpha') !== false) {
                                    $badgeColor = "bg-rose-50 text-rose-700 border-rose-100"; $dotColor = "bg-rose-500";
                                    if(strpos($status, 'Izin') !== false || strpos($status, 'Sakit') !== false) { $status .= " (Ditolak)"; }
                                }
                                elseif (strpos($status, 'Lebih Awal') !== false) {
                                    $badgeColor = "bg-amber-50 text-amber-700 border-amber-100"; $dotColor = "bg-amber-500";
                                } 
                                elseif (strpos($status, 'Telat') !== false) {
                                    $badgeColor = "bg-orange-50 text-orange-700 border-orange-100"; $dotColor = "bg-orange-500";
                                } 
                                elseif (strpos($status, 'Datang') !== false) {
                                    $badgeColor = "bg-blue-50 text-blue-700 border-blue-100"; $dotColor = "bg-blue-500";
                                }
                                elseif (strpos($status, 'Pulang') !== false) {
                                    $badgeColor = "bg-emerald-50 text-emerald-700 border-emerald-100"; $dotColor = "bg-emerald-500";
                                }
                                elseif (strpos($status, 'Sholat') !== false) {
                                    $badgeClass = "bg-purple-50 text-purple-700 border-purple-100"; $dotColor = "bg-purple-500";
                                }
                            ?>
                            <tr class="hover:bg-slate-50/80 transition-colors duration-200">
                                <td class="px-8 py-4">
                                    <div class="flex items-center gap-3">
                                        <!-- Avatar Inisial -->
                                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-slate-200 to-slate-300 flex items-center justify-center text-slate-600 font-bold text-xs shadow-inner">
                                            <?php 
                                                $words = explode(" ", $row['nama']);
                                                $initials = "";
                                                foreach ($words as $w) { $initials .= $w[0]; }
                                                echo substr($initials, 0, 2);
                                            ?>
                                        </div>
                                        <div>
                                            <p class="font-bold text-slate-800"><?php echo $row['nama']; ?></p>
                                            <p class="text-xs text-slate-400 font-mono"><?php echo $row['nis']; ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 font-medium"><?php echo $tgl; ?></td>
                                <td class="px-6 py-4 font-mono text-indigo-600 font-bold"><?php echo $jam; ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-2.5 py-1 rounded-lg bg-white border border-slate-200 text-xs font-bold text-slate-600 shadow-sm">
                                        <?php echo $row['kelas']; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold border <?php echo $badgeColor; ?>">
                                        <span class="w-1.5 h-1.5 rounded-full <?php echo $dotColor; ?>"></span>
                                        <?php echo $status; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="px-6 py-24 text-center">
                                    <div class="flex flex-col items-center justify-center text-slate-400">
                                        <div class="bg-slate-50 p-4 rounded-full mb-3">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        </div>
                                        <p class="text-sm font-medium">Tidak ada data absensi untuk filter ini.</p>
                                        <p class="text-xs mt-1 opacity-75">Coba ubah filter bulan atau kelas.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- FOOTER HAK CIPTA -->
    <footer class="bg-white border-t border-slate-200 mt-auto py-6 no-print">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <p class="text-slate-500 text-xs sm:text-sm font-medium">
                &copy; <?php echo date('Y'); ?> <span class="font-bold text-emerald-600">SMK Ma'arif 4-5 Tambakboyo</span>. All rights reserved.
            </p>
        </div>
    </footer>

</body>
</html>