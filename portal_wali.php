<?php
session_start();
include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

// --- KEAMANAN ---
if(!isset($_SESSION['status_wali']) || $_SESSION['status_wali'] != "login_wali"){
    header("location:login_wali.php");
    exit;
}

$id_wali = $_SESSION['id_wali'];
$nama_wali = $_SESSION['nama_wali'];
$kelas_binaan = $_SESSION['kelas_binaan'];

// --- LOGIC: PROSES APPROVAL ---
if(isset($_POST['aksi_approval'])){
    $id_log = $_POST['id_log'];
    $status_aksi = $_POST['status_aksi']; 
    
    $query_update = "UPDATE log_absensi SET status_approval='$status_aksi' WHERE id='$id_log'";
    $update = mysqli_query($conn, $query_update);
    
    if($update){
        if($status_aksi == 'Rejected'){
             mysqli_query($conn, "UPDATE log_absensi SET status=CONCAT(status, ' [Ditolak]') WHERE id='$id_log' AND status NOT LIKE '%[Ditolak]%'");
        }
        echo "<script>window.location='portal_wali.php';</script>";
    }
}

// --- DATA: PENGAJUAN PENDING ---
$query_pending = "SELECT log.id as log_id, log.waktu, log.status, log.bukti, 
                         s.nama AS nama_siswa, s.nis AS nis_siswa 
                  FROM log_absensi log 
                  JOIN siswa s ON log.rfid_uid = s.rfid_uid 
                  WHERE s.kelas = '$kelas_binaan' 
                  AND (log.status LIKE '%Izin%' OR log.status LIKE '%Sakit%') 
                  AND (log.status_approval = 'Pending' OR log.status_approval IS NULL OR log.status_approval = '')
                  ORDER BY log.waktu DESC";

$q_pending = mysqli_query($conn, $query_pending);

// --- DATA: REKAP KELAS ---
$q_siswa_kelas = mysqli_query($conn, "SELECT * FROM siswa WHERE kelas='$kelas_binaan' ORDER BY nama ASC");
$total_siswa = mysqli_num_rows($q_siswa_kelas);

// Hitung Ringkasan Hari Ini
$today = date('Y-m-d');
$q_hadir_today = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT log.rfid_uid) as c FROM log_absensi log JOIN siswa s ON log.rfid_uid = s.rfid_uid WHERE s.kelas='$kelas_binaan' AND DATE(log.waktu)='$today' AND log.status NOT LIKE '%Alpha%' AND (log.status_approval IS NULL OR log.status_approval != 'Rejected')"));
$q_alpha_today = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT log.rfid_uid) as c FROM log_absensi log JOIN siswa s ON log.rfid_uid = s.rfid_uid WHERE s.kelas='$kelas_binaan' AND DATE(log.waktu)='$today' AND (log.status LIKE '%Alpha%' OR log.status_approval = 'Rejected')"));

// Filter Bulan/Tahun untuk Tabel Rekap
$bulan_pilih = isset($_GET['bulan']) ? $_GET['bulan'] : date('m');
$tahun_pilih = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');
$nama_bulan_str = date('F', mktime(0, 0, 0, $bulan_pilih, 10));
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Wali Kelas - <?php echo $kelas_binaan; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f0fdfa; }
        
        /* CSS KHUSUS CETAK */
        @media print {
            .no-print { display: none !important; }
            .print-only { display: block !important; }
            body { background-color: white; }
            .shadow-md, .shadow-lg { box-shadow: none !important; }
            .border { border: 1px solid #000 !important; }
            /* Pastikan warna background tercetak */
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            /* Sembunyikan scrollbar di tabel */
            .overflow-x-auto { overflow: visible !important; }
        }
        .print-only { display: none; }
    </style>
</head>
<body class="text-slate-800">

    <!-- NAVBAR (Disembunyikan saat print) -->
    <nav class="bg-teal-600 text-white p-4 shadow-lg sticky top-0 z-50 no-print">
        <div class="container mx-auto flex flex-col md:flex-row justify-between items-center gap-4">
            <div class="flex items-center space-x-3">
                <div class="bg-white text-teal-600 p-2 rounded-full">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg>
                </div>
                <div>
                    <h1 class="text-xl font-bold">Portal Wali Kelas</h1>
                    <p class="text-xs text-teal-100"><?php echo $nama_wali; ?> - Kelas <?php echo $kelas_binaan; ?></p>
                </div>
            </div>
            
            <div class="flex items-center gap-4">
                <a href="logout.php" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-bold transition shadow-sm flex items-center gap-2">
                    Logout
                </a>
            </div>
        </div>
    </nav>

    <main class="container mx-auto p-6">

        <!-- KOP LAPORAN (Hanya Muncul Saat Print) -->
        <div class="print-only mb-8 text-center border-b-2 border-black pb-4">
            <h2 class="text-2xl font-bold uppercase">Laporan Absensi Siswa</h2>
            <h3 class="text-xl font-semibold">SMK Ma'arif 4-5 Tambakboyo</h3>
            <div class="flex justify-between mt-6 text-sm">
                <p>Kelas: <strong><?php echo $kelas_binaan; ?></strong></p>
                <p>Wali Kelas: <strong><?php echo $nama_wali; ?></strong></p>
                <p>Bulan: <strong><?php echo $nama_bulan_str . " " . $tahun_pilih; ?></strong></p>
            </div>
        </div>

        <!-- 1. STATISTIK KELAS HARI INI (Disembunyikan saat print) -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8 no-print">
            <div class="bg-white p-6 rounded-xl shadow-md border-l-4 border-teal-500 flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-xs font-bold uppercase tracking-wider">Total Siswa</p>
                    <h2 class="text-3xl font-bold text-gray-800"><?php echo $total_siswa; ?></h2>
                </div>
                <div class="p-3 bg-teal-100 text-teal-600 rounded-full">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-md border-l-4 border-green-500 flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-xs font-bold uppercase tracking-wider">Hadir Hari Ini</p>
                    <h2 class="text-3xl font-bold text-green-600"><?php echo $q_hadir_today['c']; ?></h2>
                </div>
                <div class="p-3 bg-green-100 text-green-600 rounded-full">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-md border-l-4 border-red-500 flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-xs font-bold uppercase tracking-wider">Alpha / Bolos</p>
                    <h2 class="text-3xl font-bold text-red-600"><?php echo $q_alpha_today['c']; ?></h2>
                </div>
                <div class="p-3 bg-red-100 text-red-600 rounded-full">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
                </div>
            </div>
        </div>

        <!-- 2. PERMOHONAN IZIN (Disembunyikan saat print) -->
        <?php if(mysqli_num_rows($q_pending) > 0): ?>
        <div class="mb-8 no-print">
            <div class="flex items-center gap-2 mb-4">
                <div class="w-1 h-6 bg-yellow-500 rounded-full"></div>
                <h2 class="text-xl font-bold text-gray-800">Permohonan Izin Masuk (Butuh Persetujuan)</h2>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php while($row = mysqli_fetch_assoc($q_pending)): 
                    $nama_siswa = !empty($row['nama_siswa']) ? $row['nama_siswa'] : "Nama Tidak Ditemukan";
                    $nis_siswa = !empty($row['nis_siswa']) ? $row['nis_siswa'] : "-";
                    $log_id = $row['log_id'];
                ?>
                    <div class="bg-white rounded-xl shadow-md border border-gray-200 p-5 relative overflow-hidden">
                        <div class="absolute top-0 left-0 w-1 h-full bg-yellow-400"></div>
                        <div class="flex justify-between items-start mb-3 pl-2">
                            <div>
                                <h3 class="font-bold text-gray-800 text-lg"><?php echo $nama_siswa; ?></h3>
                                <p class="text-xs text-gray-500">NIS: <?php echo $nis_siswa; ?></p>
                            </div>
                            <span class="bg-yellow-100 text-yellow-800 text-xs font-bold px-2 py-1 rounded border border-yellow-200">PENDING</span>
                        </div>
                        <div class="bg-gray-50 p-3 rounded-lg mb-4 text-sm text-gray-700 border border-gray-100">
                            <p class="font-semibold mb-1"><?php echo date('d F Y', strtotime($row['waktu'])); ?></p>
                            <p class="italic">"<?php echo $row['status']; ?>"</p>
                            <?php if(!empty($row['bukti'])): ?>
                                <a href="uploads/<?php echo $row['bukti']; ?>" target="_blank" class="inline-flex items-center gap-1 mt-2 text-blue-600 hover:underline font-bold text-xs">Lihat Bukti Foto</a>
                            <?php endif; ?>
                        </div>
                        <div class="flex gap-2 pl-2">
                            <form method="POST" class="flex-1">
                                <input type="hidden" name="id_log" value="<?php echo $log_id; ?>">
                                <input type="hidden" name="status_aksi" value="Approved">
                                <input type="hidden" name="aksi_approval" value="true"> 
                                <button type="submit" onclick="return confirm('Setujui izin ini?')" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2 rounded transition text-sm">Setujui</button>
                            </form>
                            <form method="POST" class="flex-1">
                                <input type="hidden" name="id_log" value="<?php echo $log_id; ?>">
                                <input type="hidden" name="status_aksi" value="Rejected">
                                <input type="hidden" name="aksi_approval" value="true"> 
                                <button type="submit" onclick="return confirm('Tolak izin ini? Status akan menjadi Alpha.')" class="w-full bg-red-100 hover:bg-red-200 text-red-700 font-bold py-2 rounded transition text-sm border border-red-200">Tolak</button>
                            </form>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- 3. TABEL REKAP BULANAN KELAS -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden border border-gray-200 print:shadow-none print:border-0">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200 flex flex-col md:flex-row justify-between items-center gap-3 no-print">
                <h3 class="font-bold text-gray-800 text-lg">Rekap Absensi Kelas</h3>
                
                <div class="flex items-center gap-2">
                    <!-- Form Filter -->
                    <form method="GET" class="flex items-center gap-2">
                        <select name="bulan" class="text-sm border border-gray-300 rounded px-2 py-1">
                            <?php 
                            $bln_arr = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'Mei',6=>'Jun',7=>'Jul',8=>'Ags',9=>'Sep',10=>'Okt',11=>'Nov',12=>'Des'];
                            foreach($bln_arr as $k=>$v){
                                $sel = ($k == $bulan_pilih) ? 'selected' : '';
                                echo "<option value='$k' $sel>$v</option>";
                            }
                            ?>
                        </select>
                        <select name="tahun" class="text-sm border border-gray-300 rounded px-2 py-1">
                            <?php 
                            for($t=2024; $t<=date('Y'); $t++){
                                $sel = ($t == $tahun_pilih) ? 'selected' : '';
                                echo "<option value='$t' $sel>$t</option>";
                            }
                            ?>
                        </select>
                        <button type="submit" class="bg-teal-600 text-white px-3 py-1 rounded text-sm">Lihat</button>
                    </form>

                    <!-- TOMBOL CETAK -->
                    <button onclick="window.print()" class="bg-slate-700 hover:bg-slate-800 text-white px-4 py-1.5 rounded text-sm font-bold flex items-center gap-1 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                        Cetak Laporan
                    </button>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-600">
                    <thead class="bg-gray-100 text-gray-700 uppercase text-xs font-bold print:bg-gray-200 print:text-black">
                        <tr>
                            <th class="px-6 py-3 border-b border-black">Nama Siswa</th>
                            <th class="px-6 py-3 border-b border-black text-center">Hadir</th>
                            <th class="px-6 py-3 border-b border-black text-center">Sakit</th>
                            <th class="px-6 py-3 border-b border-black text-center">Izin</th>
                            <th class="px-6 py-3 border-b border-black text-center">Alpha</th>
                            <th class="px-6 py-3 border-b border-black text-center">Persentase</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 print:divide-black">
                        <?php 
                        // Hitung Hari Efektif
                        $total_hari_bulan = cal_days_in_month(CAL_GREGORIAN, $bulan_pilih, $tahun_pilih);
                        $hari_efektif = 0;
                        for($i=1; $i<=$total_hari_bulan; $i++){
                            if(date('N', strtotime("$tahun_pilih-$bulan_pilih-$i")) <= 5) $hari_efektif++;
                        }
                        // Jika bulan yang dipilih = bulan ini, batasi sampai hari ini
                        if($bulan_pilih == date('m') && $tahun_pilih == date('Y')){
                            $hari_berjalan = 0;
                            for($i=1; $i<=date('d'); $i++){
                                if(date('N', strtotime("$tahun_pilih-$bulan_pilih-$i")) <= 5) $hari_berjalan++;
                            }
                            $pembagi_persen = ($hari_berjalan > 0) ? $hari_berjalan : 1;
                        } else {
                            $pembagi_persen = $hari_efektif;
                        }

                        mysqli_data_seek($q_siswa_kelas, 0); 
                        while($siswa = mysqli_fetch_assoc($q_siswa_kelas)): 
                            $uid_s = $siswa['rfid_uid'];
                            
                            // Hitung Statistik (Sama dengan Index Admin)
                            $q_h = mysqli_query($conn, "SELECT COUNT(*) as c FROM log_absensi WHERE rfid_uid='$uid_s' AND MONTH(waktu)='$bulan_pilih' AND YEAR(waktu)='$tahun_pilih' AND (status LIKE '%Datang%' OR status LIKE '%Tadarus%' OR status LIKE '%Sholat%' OR status LIKE '%Pulang%') AND status NOT LIKE '%Alpha%' AND status NOT LIKE '%Lebih Awal%' AND (status_approval IS NULL OR status_approval != 'Rejected')");
                            $h = mysqli_fetch_assoc($q_h);

                            $q_s = mysqli_query($conn, "SELECT COUNT(*) as c FROM log_absensi WHERE rfid_uid='$uid_s' AND MONTH(waktu)='$bulan_pilih' AND YEAR(waktu)='$tahun_pilih' AND status LIKE '%Sakit%' AND status_approval='Approved'");
                            $s = mysqli_fetch_assoc($q_s);

                            $q_i = mysqli_query($conn, "SELECT COUNT(*) as c FROM log_absensi WHERE rfid_uid='$uid_s' AND MONTH(waktu)='$bulan_pilih' AND YEAR(waktu)='$tahun_pilih' AND status LIKE '%Izin%' AND status_approval='Approved'");
                            $i = mysqli_fetch_assoc($q_i);

                            $q_a = mysqli_query($conn, "SELECT COUNT(*) as c FROM log_absensi WHERE rfid_uid='$uid_s' AND MONTH(waktu)='$bulan_pilih' AND YEAR(waktu)='$tahun_pilih' AND (status LIKE '%Alpha%' OR status LIKE '%Lebih Awal%' OR status_approval='Rejected')");
                            $a = mysqli_fetch_assoc($q_a);
                            
                            $total_masuk = $h['c'] + $s['c'] + $i['c'];
                            $persen = ($total_masuk / $pembagi_persen) * 100;
                            if($persen > 100) $persen = 100;
                            
                            $text_color = "text-green-600";
                            $progress_color = "bg-green-500";
                            if($persen < 80) { $text_color = "text-orange-500"; $progress_color = "bg-orange-500"; }
                            if($persen < 50) { $text_color = "text-red-600"; $progress_color = "bg-red-500"; }
                        ?>
                        <tr class="hover:bg-gray-50 transition print:bg-white">
                            <td class="px-6 py-4 font-bold text-gray-800 border-b border-gray-200 print:border-black"><?php echo $siswa['nama']; ?></td>
                            <td class="px-6 py-4 text-center text-green-600 font-bold bg-green-50/30 print:bg-white print:text-black border-b border-gray-200 print:border-black"><?php echo $h['c']; ?></td>
                            <td class="px-6 py-4 text-center text-blue-600 font-bold bg-blue-50/30 print:bg-white print:text-black border-b border-gray-200 print:border-black"><?php echo $s['c']; ?></td>
                            <td class="px-6 py-4 text-center text-purple-600 font-bold bg-purple-50/30 print:bg-white print:text-black border-b border-gray-200 print:border-black"><?php echo $i['c']; ?></td>
                            <td class="px-6 py-4 text-center text-red-600 font-bold bg-red-50/30 print:bg-white print:text-black border-b border-gray-200 print:border-black"><?php echo $a['c']; ?></td>
                            <td class="px-6 py-4 w-32 border-b border-gray-200 print:border-black">
                                <div class="flex items-center gap-2">
                                    <div class="w-full bg-gray-200 rounded-full h-1.5 no-print">
                                        <div class="h-1.5 rounded-full <?php echo $progress_color; ?>" style="width: <?php echo $persen; ?>%"></div>
                                    </div>
                                    <span class="text-xs font-bold <?php echo $text_color; ?> print:text-black"><?php echo round($persen); ?>%</span>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="p-4 bg-gray-50 text-xs text-gray-500 text-center border-t border-gray-200 no-print">
                Persentase dihitung berdasarkan <b><?php echo $pembagi_persen; ?> hari efektif</b> bulan ini.
            </div>
        </div>

        <!-- Tanda Tangan (Hanya muncul saat print) -->
        <div class="print-only mt-16 flex justify-end">
            <div class="text-center w-64">
                <p>Tambakboyo, <?php echo date('d F Y'); ?></p>
                <p class="mb-20">Wali Kelas,</p>
                <p class="font-bold underline"><?php echo $nama_wali; ?></p>
            </div>
        </div>

    </main>

</body>
</html>