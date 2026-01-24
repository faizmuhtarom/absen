<?php
include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

$student_data = null;
$history_logs = [];
$stat_hadir = 0;
$stat_telat = 0;
$stat_izin = 0;
$stat_alpha = 0;
$search_nis = "";

if (isset($_GET['nis'])) {
    $search_nis = mysqli_real_escape_string($conn, $_GET['nis']);
    
    // 1. Cari Data Siswa
    $q_siswa = mysqli_query($conn, "SELECT * FROM siswa WHERE nis = '$search_nis'");
    
    if (mysqli_num_rows($q_siswa) > 0) {
        $student_data = mysqli_fetch_assoc($q_siswa);
        $uid = $student_data['rfid_uid'];

        // 2. Ambil Riwayat Absensi
        $q_logs = mysqli_query($conn, "SELECT * FROM log_absensi WHERE rfid_uid = '$uid' ORDER BY waktu DESC LIMIT 50");
        
        while ($row = mysqli_fetch_assoc($q_logs)) {
            $history_logs[] = $row;
            
            $status = $row['status'];
            $approval = isset($row['status_approval']) ? $row['status_approval'] : '';

            // Hitung Statistik (Sesuai Logika Baru)
            if ($approval == 'Rejected' || strpos($status, 'Alpha') !== false) {
                $stat_alpha++;
            } 
            elseif (strpos($status, 'Lebih Awal') !== false) {
                $stat_alpha++; // Pulang awal dianggap Alpha/Warning
            }
            elseif (strpos($status, 'Telat') !== false) {
                $stat_telat++;
            } 
            elseif (strpos($status, 'Sakit') !== false || strpos($status, 'Izin') !== false) {
                $stat_izin++;
            } 
            elseif (strpos($status, 'Datang') !== false || strpos($status, 'Pulang') !== false || strpos($status, 'Tadarus') !== false || strpos($status, 'Sholat') !== false) {
                $stat_hadir++;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cek Kehadiran Siswa</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts: Outfit -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #f8fafc; }
        .glass-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.5);
        }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none;  scrollbar-width: none; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col">

    <!-- NAVBAR SEDERHANA -->
    <nav class="bg-white/80 backdrop-blur-md border-b border-slate-200 fixed w-full z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="index.php" class="group flex items-center gap-2 text-slate-500 hover:text-emerald-600 transition-colors">
                    <div class="p-1.5 rounded-lg bg-slate-100 group-hover:bg-emerald-50">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                    </div>
                    <span class="font-semibold text-sm">Kembali ke Dashboard</span>
                </a>
            </div>
            <div class="text-sm font-bold text-slate-800 tracking-wide flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                PORTAL SISWA
            </div>
        </div>
    </nav>

    <!-- MAIN CONTENT -->
    <main class="flex-grow pt-28 pb-12 px-4 sm:px-6">
        <div class="max-w-3xl mx-auto">

            <!-- HERO SEARCH SECTION -->
            <div class="text-center mb-10 space-y-4">
                <div class="inline-flex p-4 rounded-3xl bg-gradient-to-tr from-emerald-100 to-teal-100 text-emerald-600 mb-2 shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                </div>
                <h1 class="text-4xl font-extrabold text-slate-900 tracking-tight">Cek Riwayat Kehadiran</h1>
                <p class="text-slate-500 text-lg">Masukkan Nomor Induk Siswa (NIS) untuk melihat data presensi Anda.</p>
            </div>

            <!-- SEARCH FORM -->
            <div class="bg-white p-2 rounded-2xl shadow-xl shadow-slate-200/60 border border-slate-100 max-w-xl mx-auto transform transition hover:-translate-y-1 duration-300 relative z-10">
                <form method="GET" class="flex items-center gap-2">
                    <div class="relative flex-grow">
                        <div class="absolute inset-y-0 left-0 pl-5 flex items-center pointer-events-none">
                            <svg class="h-6 w-6 text-slate-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                        </div>
                        <input type="text" name="nis" value="<?php echo htmlspecialchars($search_nis); ?>" 
                               class="w-full pl-14 pr-4 py-4 bg-transparent text-lg font-medium placeholder-slate-300 focus:outline-none text-slate-700 font-mono tracking-wide" 
                               placeholder="Ketik NIS Siswa..." required autofocus autocomplete="off">
                    </div>
                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white px-8 py-3 rounded-xl font-bold text-lg transition-all shadow-md hover:shadow-lg shadow-emerald-200">
                        Cari
                    </button>
                </form>
            </div>

            <!-- HASIL PENCARIAN -->
            <?php if (isset($_GET['nis'])): ?>
                <div class="mt-16 animate-fade-in-up">
                    
                    <?php if ($student_data): ?>
                        <!-- 1. KARTU PROFIL SISWA -->
                        <div class="bg-white rounded-3xl shadow-lg overflow-hidden border border-slate-100 mb-8 relative group">
                            <div class="absolute top-0 left-0 w-full h-32 bg-gradient-to-r from-emerald-500 to-teal-500 group-hover:scale-105 transition-transform duration-700"></div>
                            <div class="px-8 pt-20 pb-8 relative flex flex-col md:flex-row items-end gap-6">
                                <!-- Avatar -->
                                <div class="w-32 h-32 rounded-2xl bg-white p-2 shadow-2xl -mt-16 relative z-10">
                                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($student_data['nama']); ?>&background=0f172a&color=fff&size=128&bold=true" 
                                         alt="Avatar" class="w-full h-full object-cover rounded-xl bg-slate-100">
                                </div>
                                <!-- Info -->
                                <div class="flex-grow w-full">
                                    <h2 class="text-3xl font-bold text-slate-800 mb-1"><?php echo $student_data['nama']; ?></h2>
                                    <div class="flex flex-wrap gap-3 mt-3">
                                        <div class="flex flex-col">
                                            <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">NIS</span>
                                            <span class="font-mono text-sm font-semibold text-slate-600"><?php echo $student_data['nis']; ?></span>
                                        </div>
                                        <div class="w-px h-8 bg-slate-200"></div>
                                        <div class="flex flex-col">
                                            <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Kelas</span>
                                            <span class="text-sm font-bold text-blue-600"><?php echo $student_data['kelas']; ?></span>
                                        </div>
                                        <div class="w-px h-8 bg-slate-200"></div>
                                        <div class="flex flex-col">
                                            <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">RFID ID</span>
                                            <span class="font-mono text-sm text-slate-500"><?php echo $student_data['rfid_uid']; ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 2. STATISTIK RINGKAS -->
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                            <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 text-center hover:-translate-y-1 transition duration-300">
                                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Hadir</p>
                                <p class="text-3xl font-bold text-emerald-500"><?php echo $stat_hadir; ?></p>
                            </div>
                            <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 text-center hover:-translate-y-1 transition duration-300">
                                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Telat</p>
                                <p class="text-3xl font-bold text-orange-500"><?php echo $stat_telat; ?></p>
                            </div>
                            <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 text-center hover:-translate-y-1 transition duration-300">
                                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Izin/Sakit</p>
                                <p class="text-3xl font-bold text-blue-500"><?php echo $stat_izin; ?></p>
                            </div>
                            <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 text-center hover:-translate-y-1 transition duration-300">
                                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Alpha</p>
                                <p class="text-3xl font-bold text-red-500"><?php echo $stat_alpha; ?></p>
                            </div>
                        </div>

                        <!-- 3. RIWAYAT ABSENSI (TABLE) -->
                        <div class="bg-white rounded-2xl shadow-lg border border-slate-200 overflow-hidden">
                            <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                                <h3 class="font-bold text-slate-700 text-lg">Riwayat Aktivitas</h3>
                                <span class="text-xs text-slate-400 font-medium bg-slate-100 px-2 py-1 rounded border border-slate-200">50 Data Terakhir</span>
                            </div>
                            
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-sm">
                                    <thead class="bg-slate-50 text-slate-500 font-bold text-xs uppercase tracking-wider">
                                        <tr>
                                            <th class="px-6 py-4">Hari, Tanggal</th>
                                            <th class="px-6 py-4">Jam</th>
                                            <th class="px-6 py-4">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 text-slate-600">
                                        <?php if (count($history_logs) > 0): ?>
                                            <?php foreach ($history_logs as $log): 
                                                $waktu = strtotime($log['waktu']);
                                                $hari = date('l', $waktu);
                                                $tgl = date('d M Y', $waktu);
                                                $jam = date('H:i', $waktu);
                                                $st = $log['status'];
                                                $approval = isset($log['status_approval']) ? $log['status_approval'] : '';

                                                // Translate Hari
                                                $hari_indo = [
                                                    'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
                                                    'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
                                                ];
                                                $nama_hari = $hari_indo[$hari];

                                                // Warna Badge
                                                $badge = "bg-slate-100 text-slate-600 border-slate-200"; // Default
                                                $dot = "bg-slate-400";

                                                // Logika Warna & Status
                                                if ($approval == 'Rejected') {
                                                    $badge = "bg-red-50 text-red-700 border-red-200";
                                                    $dot = "bg-red-500";
                                                    $st = "Izin Ditolak (Alpha)";
                                                }
                                                elseif ($approval == 'Pending' && (strpos($st, 'Izin') !== false || strpos($st, 'Sakit') !== false)) {
                                                    $badge = "bg-yellow-50 text-yellow-700 border-yellow-200";
                                                    $dot = "bg-yellow-500";
                                                    $st .= " (Menunggu)";
                                                }
                                                elseif (strpos($st, 'Telat') !== false) {
                                                    $badge = "bg-orange-50 text-orange-700 border-orange-200";
                                                    $dot = "bg-orange-500";
                                                }
                                                elseif (strpos($st, 'Alpha') !== false || strpos($st, 'Lebih Awal') !== false) {
                                                    $badge = "bg-red-50 text-red-700 border-red-200";
                                                    $dot = "bg-red-500";
                                                }
                                                elseif (strpos($st, 'Datang') !== false) {
                                                    $badge = "bg-blue-50 text-blue-700 border-blue-200";
                                                    $dot = "bg-blue-500";
                                                }
                                                elseif (strpos($st, 'Pulang') !== false) {
                                                    $badge = "bg-emerald-50 text-emerald-700 border-emerald-200";
                                                    $dot = "bg-emerald-500";
                                                }
                                            ?>
                                            <tr class="hover:bg-slate-50 transition duration-150">
                                                <td class="px-6 py-4">
                                                    <span class="block font-bold text-slate-700"><?php echo $nama_hari; ?></span>
                                                    <span class="text-xs text-slate-400"><?php echo $tgl; ?></span>
                                                </td>
                                                <td class="px-6 py-4 font-mono text-indigo-600 font-bold">
                                                    <?php echo $jam; ?>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold border uppercase tracking-wide <?php echo $badge; ?>">
                                                        <span class="w-1.5 h-1.5 rounded-full <?php echo $dot; ?>"></span>
                                                        <?php echo $st; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="3" class="px-6 py-16 text-center text-slate-400 italic flex flex-col items-center">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" class="mb-2 opacity-50"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                                    Belum ada riwayat absensi.
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    <?php else: ?>
                        <!-- JIKA SISWA TIDAK DITEMUKAN -->
                        <div class="max-w-md mx-auto text-center py-16 bg-white rounded-3xl shadow-xl border border-slate-100">
                            <div class="bg-red-50 text-red-500 w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-6 shadow-inner">
                                <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
                            </div>
                            <h3 class="text-2xl font-bold text-slate-800 mb-2">Data Tidak Ditemukan</h3>
                            <p class="text-slate-500 px-8">Nomor Induk Siswa <span class="font-mono font-bold text-slate-800 bg-slate-100 px-1 rounded"><?php echo htmlspecialchars($search_nis); ?></span> tidak terdaftar di sistem kami.</p>
                            <a href="siswa.php" class="inline-block mt-6 text-emerald-600 font-bold hover:underline">Coba Cari Lagi</a>
                        </div>
                    <?php endif; ?>

                </div>
            <?php endif; ?>

        </div>
    </main>

    <footer class="py-8 text-center text-slate-400 text-xs font-medium border-t border-slate-200 bg-white/50">
        &copy; <?php echo date('Y'); ?> SMK Digital Nusantara. All rights reserved.
    </footer>

</body>
</html>