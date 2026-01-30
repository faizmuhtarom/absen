<?php
session_start();
include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

// --- KEAMANAN ---
if(!isset($_SESSION['status']) || $_SESSION['status'] != "login"){
    header("location:login.php?pesan=belum_login");
    exit;
}

$status_msg = "";
$pesan = "";
$siswa_nama = "";
$siswa_foto = ""; 
$rfid_debug = ""; 
$today = date('Y-m-d');

// =========================================================================
// 1. AMBIL PENGUMUMAN (RUNNING TEXT)
// =========================================================================
$running_text = "Selamat Datang di Sistem Absensi SMK Digital Nusantara."; 
$q_info = mysqli_query($conn, "SELECT isi_teks FROM pengumuman WHERE aktif=1 ORDER BY id DESC LIMIT 1");
if(mysqli_num_rows($q_info) > 0){
    $d_info = mysqli_fetch_assoc($q_info);
    $running_text = $d_info['isi_teks'];
}

// =========================================================================
// 2. CEK STATUS HARI LIBUR & KONFIGURASI HARI
// =========================================================================
$is_libur = false;
$ket_libur = "";
$hari_inggris = date('l');
$arr_hari = [ 
    'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 
    'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu' 
];
$hari_ini = $arr_hari[$hari_inggris];
$tgl_indo = date('d') . ' ' . [
    '01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni',
    '07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'
][date('m')] . ' ' . date('Y');

// A. Cek Hari Minggu
if ($hari_inggris == 'Sunday') {
    $is_libur = true;
    $ket_libur = "Hari Minggu (Libur Rutin)";
}

// B. Cek Database Hari Libur
if (!$is_libur) {
    $cek_libur_db = mysqli_query($conn, "SELECT * FROM hari_libur WHERE tanggal = '$today'");
    if (mysqli_num_rows($cek_libur_db) > 0) {
        $data_libur = mysqli_fetch_assoc($cek_libur_db);
        $is_libur = true;
        $ket_libur = $data_libur['keterangan'];
    }
}

// =========================================================================
// 3. KONFIGURASI JADWAL DINAMIS
// =========================================================================
$jadwal = [];
if (!$is_libur) {
    if ($hari_ini == 'Jumat') {
        $jadwal = ['Datang' => '06:40', 'Pulang' => '10:20'];
    } elseif ($hari_ini == 'Sabtu') {
        $jadwal = ['Datang' => '06:40', 'Pulang' => '11:20'];
    } else {
        $jadwal = ['Datang' => '06:40', 'Sholat Jamaah' => '12:00', 'Pulang' => '13:50'];
    }
}

// =========================================================================
// 4. OTOMATIS ALPHA (BACKGROUND PROCESS)
// =========================================================================
function cekOtomatisAlpha($conn, $jam_datang) {
    global $today; 
    $batas_waktu_alpha = strtotime($today . ' ' . $jam_datang . ' +45 minutes'); 
    $waktu_sekarang = time();
    $file_log = 'last_auto_alpha.txt'; 
    $last_run = file_exists($file_log) ? file_get_contents($file_log) : '';

    if ($waktu_sekarang > $batas_waktu_alpha && $last_run != $today) {
        $query_alpha = "SELECT rfid_uid FROM siswa WHERE rfid_uid NOT IN (SELECT rfid_uid FROM log_absensi WHERE DATE(waktu) = '$today')";
        $result = mysqli_query($conn, $query_alpha);
        if (mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $uid = $row['rfid_uid'];
                $waktu_catat = date('Y-m-d H:i:s', $batas_waktu_alpha);
                mysqli_query($conn, "INSERT INTO log_absensi (rfid_uid, waktu, status) VALUES ('$uid', '$waktu_catat', 'Alpha (Datang - Otomatis)')");
            }
        }
        file_put_contents($file_log, $today);
    }
}

if (!$is_libur) {
    $jam_datang_trigger = isset($jadwal['Datang']) ? $jadwal['Datang'] : '07:00';
    cekOtomatisAlpha($conn, $jam_datang_trigger);
}

// =========================================================================
// 5. PROSES SCAN (POST)
// =========================================================================
$jenis_absen_terpilih = "Datang"; 
if (isset($_POST['jenis_absen'])) $jenis_absen_terpilih = $_POST['jenis_absen'];

if (!$is_libur && !array_key_exists($jenis_absen_terpilih, $jadwal)) {
    $jenis_absen_terpilih = array_key_first($jadwal) ? array_key_first($jadwal) : 'Datang';
}

if (isset($_POST['rfid']) && !$is_libur) {
    $rfid = trim(mysqli_real_escape_string($conn, $_POST['rfid']));
    $rfid_debug = $rfid;
    $jenis = mysqli_real_escape_string($conn, $_POST['jenis_absen']); 
    
    if (!array_key_exists($jenis, $jadwal)) $jenis = 'Datang'; 

    $waktu_sekarang_str = date('Y-m-d H:i:s');
    $jam_target_str = $today . ' ' . $jadwal[$jenis];
    $timestamp_target = strtotime($jam_target_str);
    $timestamp_sekarang = time();
    $selisih_menit = ($timestamp_sekarang - $timestamp_target) / 60;

    $status_final = "";
    $boleh_absen = true; 

    // --- CEK SISWA ---
    $cek_siswa = mysqli_query($conn, "SELECT * FROM siswa WHERE rfid_uid = '$rfid'");
    
    if (mysqli_num_rows($cek_siswa) > 0) {
        $data_siswa = mysqli_fetch_assoc($cek_siswa);
        $siswa_nama = $data_siswa['nama'];
        
        // Ambil Foto Profil untuk notifikasi (Tanpa Webcam)
        $file_foto = isset($data_siswa['foto']) ? $data_siswa['foto'] : '';
        if (!empty($file_foto) && file_exists("uploads/siswa/" . $file_foto)) {
            $siswa_foto = "uploads/siswa/" . $file_foto;
        } else {
            $siswa_foto = "https://ui-avatars.com/api/?name=" . urlencode($siswa_nama) . "&background=random&size=200&bold=true";
        }

        // --- CEK KELENGKAPAN ABSENSI ---
        $jadwal_wajib = array_keys($jadwal); 
        $jumlah_wajib = count($jadwal_wajib);
        
        $q_cek_lengkap = mysqli_query($conn, "SELECT status FROM log_absensi WHERE rfid_uid = '$rfid' AND DATE(waktu) = '$today'");
        $status_tercatat = [];
        while($row_stat = mysqli_fetch_assoc($q_cek_lengkap)){
            foreach($jadwal_wajib as $tipe){
                if(strpos($row_stat['status'], $tipe) !== false){
                    if(!in_array($tipe, $status_tercatat)) $status_tercatat[] = $tipe;
                }
            }
        }
        $absen_selesai = count($status_tercatat);

        if ($absen_selesai >= $jumlah_wajib && !in_array($jenis, $status_tercatat)) {
            $status_msg = "warning"; 
            $pesan = "$siswa_nama, Absensi hari ini sudah lengkap!";
            $boleh_absen = false; 
        } 

        if ($boleh_absen) {
            // --- LOGIKA STATUS ---
            if ($jenis == 'Pulang') {
                $cek_izin = mysqli_query($conn, "SELECT alasan FROM izin_pulang WHERE rfid_uid='$rfid' AND tanggal='$today'");
                $data_izin = mysqli_fetch_assoc($cek_izin);

                if ($selisih_menit < 0) {
                    if ($data_izin) {
                        $status_final = "Pulang (Izin: " . $data_izin['alasan'] . ")";
                        $status_msg = "success"; $pesan = "Izin Pulang Diterima. Hati-hati!";
                    } else {
                        $status_final = "Pulang Lebih Awal";
                        $status_msg = "warning_early"; $pesan = "Anda pulang lebih awal (Tanpa Izin).";
                    }
                } else {
                    $status_final = "Pulang";
                    $status_msg = "success"; $pesan = "Hati-hati di jalan!";
                }
            } else {
                if ($selisih_menit <= 5) {
                    $status_final = $jenis; 
                    $status_msg = "success"; $pesan = "Terima kasih, Anda tepat waktu.";
                } elseif ($selisih_menit <= 10) {
                    $status_final = $jenis . " - Telat";
                    $status_msg = "warning_late"; $pesan = "Anda Terlambat " . ceil($selisih_menit) . " Menit!";
                } else {
                    $status_final = "Alpha ($jenis - Terlambat)";
                    $status_msg = "error"; $pesan = "Terlambat > 10 Menit! Dianggap Alpha.";
                }
            }

            if(in_array($jenis, $status_tercatat)) {
                $status_msg = "warning";
                $pesan = "$siswa_nama sudah absen $jenis hari ini.";
            } else {
                // INSERT TANPA FOTO BUKTI WEBCAM
                $insert = mysqli_query($conn, "INSERT INTO log_absensi (rfid_uid, waktu, status) VALUES ('$rfid', '$waktu_sekarang_str', '$status_final')");
                if ($insert) {
                    if(strpos($pesan, $siswa_nama) === false) $pesan .= " ($siswa_nama)"; 
                } else {
                    $status_msg = "error"; $pesan = "Database Error.";
                }
            }
        }
    } else {
        $status_msg = "error"; $pesan = "Kartu Tidak Dikenal!"; $siswa_foto = "";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link rel="shortcut icon" href="gambar/logo.jpg">
    <title>Mode Scan Absensi</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #022c22; }
        .font-mono { font-family: 'Space Mono', monospace; }
        .bg-animate { background: linear-gradient(-45deg, #022c22, #064e3b, #065f46, #022c22); background-size: 400% 400%; animation: gradient 15s ease infinite; }
        @keyframes gradient { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
        .glass-panel { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); }
        .btn-mode { transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); }
        .btn-mode:active { transform: scale(0.98); }
        #rfidInput { caret-color: transparent; }
        .swal2-image { border-radius: 50%; border: 4px solid #f0fdf4; object-fit: cover; box-shadow: 0 10px 20px -5px rgba(0,0,0,0.2); }
        .holiday-bg { background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); border: 2px dashed #ef4444; }
        .marquee-container { overflow: hidden; white-space: nowrap; position: relative; }
        .marquee-text { display: inline-block; padding-left: 100%; animation: marquee 20s linear infinite; }
        @keyframes marquee { 0% { transform: translateX(0); } 100% { transform: translateX(-100%); } }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4 bg-animate relative overflow-y-auto pb-16">

    <a href="index.php" class="absolute top-4 left-4 z-50 flex items-center gap-2 text-white/60 hover:text-white transition-colors bg-black/20 px-3 py-1.5 rounded-full backdrop-blur-sm">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        <span class="text-xs font-bold">Dashboard</span>
    </a>

    <!-- Container Utama -->
    <div class="w-full max-w-6xl min-h-[600px] h-auto md:h-[85vh] bg-white rounded-3xl md:rounded-[2.5rem] shadow-2xl overflow-hidden flex flex-col md:flex-row relative z-10 my-10 md:my-0">
        
        <!-- SISI KIRI (Visual, Waktu) -->
        <div class="w-full md:w-5/12 bg-gradient-to-br from-emerald-600 to-green-800 p-6 sm:p-10 text-white flex flex-col justify-between relative overflow-hidden shrink-0">
            <div class="absolute top-0 right-0 -mt-10 -mr-10 w-64 h-64 bg-white/10 rounded-full blur-3xl"></div>
            <div class="absolute bottom-0 left-0 -mb-10 -ml-10 w-40 h-40 bg-teal-500/20 rounded-full blur-2xl"></div>

            <div class="relative z-10 mb-4 text-center md:text-left">
                <h2 class="text-sm sm:text-lg font-medium text-emerald-100 mb-1">Sistem Absensi Rfid</h2>
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight leading-tight">SMK Ma'arif 4-5<br>Tambakboyo</h1>
                <div class="h-1 w-20 bg-emerald-400 mt-4 rounded-full hidden md:block"></div>
            </div>

            <!-- ILUSTRASI / LOGO (PENGGANTI WEBCAM) -->
            <div class="relative z-10 flex flex-col items-center justify-center my-6">
                <div class="relative bg-white/10 p-6 rounded-full backdrop-blur-sm border border-white/20 shadow-inner animate-pulse">
                    <!-- Icon RFID -->
                    <svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-white/80"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M12 12v.01"/></svg>
                </div>
                <p class="text-xs text-emerald-100 mt-4 tracking-widest uppercase font-bold">Tempelkan Kartu</p>
            </div>

            <div class="relative z-10 my-auto text-center py-4">
                <div id="liveClock" class="font-mono text-5xl sm:text-7xl font-bold tracking-tighter drop-shadow-lg leading-none"><?php echo date('H:i'); ?></div>
                <div id="liveSeconds" class="font-mono text-xl sm:text-2xl text-emerald-200 font-light mt-1 mb-2"><?php echo date('s'); ?></div>
                <div class="inline-block px-4 py-1 rounded-full bg-white/10 backdrop-blur-md border border-white/10 text-sm md:text-base font-medium opacity-90">
                    <?php echo $hari_ini . ', ' . $tgl_indo; ?>
                </div>
            </div>
        </div>

        <!-- SISI KANAN: Interaksi -->
        <div class="w-full md:w-7/12 p-6 sm:p-8 md:p-12 bg-white flex flex-col relative h-full justify-center">
            
            <?php if ($is_libur): ?>
                <!-- MODE LIBUR -->
                <div class="flex flex-col items-center justify-center text-center holiday-bg rounded-3xl p-8 py-12 h-full">
                    <div class="w-20 h-20 sm:w-28 sm:h-28 bg-red-100 text-red-500 rounded-full flex items-center justify-center mb-6 shadow-xl animate-bounce">
                        <svg xmlns="http://www.w3.org/2000/svg" width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M16 16s-1.5-2-4-2-4 2-4 2"></path><line x1="9" y1="9" x2="9.01" y2="9"></line><line x1="15" y1="9" x2="15.01" y2="9"></line></svg>
                    </div>
                    <h2 class="text-2xl sm:text-4xl font-extrabold text-slate-800 mb-2">SEKOLAH LIBUR</h2>
                    <div class="bg-white px-4 sm:px-6 py-2 rounded-full shadow-sm border border-red-100 mt-2">
                        <p class="text-sm sm:text-lg text-red-500 font-bold"><?php echo $ket_libur; ?></p>
                    </div>
                    <p class="mt-8 text-xs sm:text-sm text-slate-500">Mesin absensi dinonaktifkan sementara.</p>
                </div>

            <?php else: ?>
                <!-- HEADER KANAN -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end mb-6 sm:mb-8 gap-4">
                    <div>
                        <h2 class="text-2xl sm:text-3xl font-bold text-slate-800 mb-1">Scan Kartu</h2>
                        <p class="text-sm sm:text-base text-slate-500">Pilih jenis absen & tempel kartu</p>
                    </div>
                    <div class="w-full sm:w-auto text-right bg-emerald-50 px-3 py-1.5 sm:px-4 sm:py-2 rounded-lg border border-emerald-100 flex justify-between sm:block items-center">
                        <span class="text-[10px] sm:text-xs font-bold text-slate-400 uppercase tracking-wider block sm:mb-1">Batas Waktu</span>
                        <div class="text-lg sm:text-2xl font-mono font-bold text-emerald-600 leading-none"><?php echo $jadwal[$jenis_absen_terpilih]; ?></div>
                    </div>
                </div>

                <!-- GRID TOMBOL -->
                <div class="grid grid-cols-2 gap-3 sm:gap-4 mb-auto">
                    <?php 
                    $icons = [ 'Datang' => '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/><path d="M19 5L4 20"/>', 'Sholat Jamaah' => '<path d="M12 2l4 10h6l-5 4 2 9-7-5-7 5 2-9-5-4h6z"/>', 'Pulang' => '<path d="M9 21v-6a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v6"/><path d="M5 12V7c0-1.1.9-2 2-2h10a2 2 0 0 1 2 2v5"/><path d="M3 10l9-7 9 7"/>' ];
                    foreach($jadwal as $key => $jam): 
                        $isActive = ($key == $jenis_absen_terpilih);
                        $btnClass = $isActive ? "bg-emerald-600 text-white shadow-xl shadow-emerald-200 ring-2 ring-emerald-600 ring-offset-2 scale-[1.02]" : "bg-slate-50 text-slate-600 border border-slate-200 hover:border-emerald-300 hover:bg-emerald-50";
                        $iconClass = $isActive ? "text-white" : "text-emerald-500";
                    ?>
                    <button type="button" onclick="setMode('<?php echo $key; ?>')" class="btn-mode relative group flex flex-col sm:flex-row items-center sm:items-start gap-3 p-3 sm:p-4 rounded-xl sm:rounded-2xl text-center sm:text-left w-full <?php echo $btnClass; ?>">
                        <div class="p-2 sm:p-3 rounded-lg sm:rounded-xl bg-white/20 backdrop-blur-sm <?php echo $isActive ? 'bg-white/20' : 'bg-white shadow-sm'; ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" sm:width="24" sm:height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="<?php echo $iconClass; ?>"><?php echo isset($icons[$key]) ? $icons[$key] : '<circle cx="12" cy="12" r="10"/>'; ?></svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <span class="block text-[10px] sm:text-xs opacity-80 font-medium uppercase tracking-wider mb-0.5">Mode</span>
                            <span class="block font-bold text-sm sm:text-lg leading-tight truncate"><?php echo $key; ?></span>
                        </div>
                        <?php if($isActive): ?><div class="absolute top-2 right-2 sm:top-3 sm:right-3 hidden sm:block"><span class="relative flex h-2 w-2 sm:h-3 sm:w-3"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-white opacity-75"></span><span class="relative inline-flex rounded-full h-2 w-2 sm:h-3 sm:w-3 bg-emerald-400"></span></span></div><?php endif; ?>
                    </button>
                    <?php endforeach; ?>
                </div>

                <!-- FORM INPUT -->
                <form method="POST" action="" id="scanForm" class="relative mt-6 sm:mt-8">
                    <input type="hidden" name="jenis_absen" id="jenis_absen_input" value="<?php echo $jenis_absen_terpilih; ?>">
                    <div class="relative group w-full">
                        <div class="absolute -inset-1 bg-gradient-to-r from-emerald-500 to-teal-500 rounded-2xl blur opacity-20 group-hover:opacity-40 transition duration-500"></div>
                        <input type="text" name="rfid" id="rfidInput" class="relative w-full bg-white border-2 border-slate-200 text-slate-800 text-center font-mono text-lg sm:text-xl p-4 sm:p-5 rounded-xl focus:outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 transition-all placeholder:text-slate-300" placeholder="Klik di sini & Scan Kartu..." autocomplete="off" required>
                    </div>
                </form>
                
                <div class="mt-6 text-center">
                    <p class="text-[10px] text-slate-400 font-medium bg-slate-50 px-3 py-1 rounded-full inline-block border border-slate-100">Status: Siap Menerima Data</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Running Text -->
    <div class="fixed bottom-0 w-full bg-emerald-900 text-emerald-100 py-3 z-50 border-t border-emerald-800 shadow-lg">
        <div class="max-w-7xl mx-auto flex items-center px-4">
            <div class="bg-emerald-700 px-3 py-1 text-[10px] sm:text-xs font-bold uppercase tracking-wider rounded mr-4 shrink-0 shadow-sm border border-emerald-600">PENGUMUMAN</div>
            <div class="marquee-container flex-1">
                <div class="marquee-text text-sm font-mono tracking-wide font-medium">
                    <?php echo isset($running_text) ? $running_text : "Selamat Datang di Sistem Absensi SMK Ma'arif 4-5 Tambakboyo."; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function updateClock() {
            const now = new Date();
            const h = String(now.getHours()).padStart(2, '0');
            const m = String(now.getMinutes()).padStart(2, '0');
            const s = String(now.getSeconds()).padStart(2, '0');
            document.getElementById('liveClock').innerText = `${h}:${m}`;
            document.getElementById('liveSeconds').innerText = s;
        }
        setInterval(updateClock, 1000);
        updateClock(); 

        <?php if(!$is_libur): ?>
        const serverStatus = "<?php echo $status_msg; ?>";
        const serverMessage = "<?php echo $pesan; ?>";
        const serverPhoto = "<?php echo $siswa_foto; ?>";

        if (serverStatus) {
            let iconType = 'info'; let titleText = 'Info'; let popupColor = '#10b981'; 

            if (serverStatus === 'success') { iconType = 'success'; titleText = 'Berhasil!'; popupColor = '#10b981'; }
            else if (serverStatus === 'warning_late') { iconType = 'warning'; titleText = 'Terlambat!'; popupColor = '#f97316'; }
            else if (serverStatus === 'warning_early') { iconType = 'warning'; titleText = 'Pulang Awal'; popupColor = '#eab308'; }
            else if (serverStatus === 'warning') { iconType = 'info'; titleText = 'Sudah Absen'; popupColor = '#3b82f6'; }
            else if (serverStatus === 'error') { iconType = 'error'; titleText = 'Gagal!'; popupColor = '#ef4444'; }

            if ('speechSynthesis' in window) {
                const utterance = new SpeechSynthesisUtterance(serverMessage);
                utterance.lang = 'id-ID'; utterance.rate = 0.9; window.speechSynthesis.speak(utterance);
            }

            let swalConfig = {
                title: titleText, text: serverMessage, timer: 3000, showConfirmButton: false,
                timerProgressBar: true, background: '#fff', color: '#1e293b', iconColor: popupColor,
                backdrop: `rgba(0,0,0,0.5)`,
                didClose: () => { document.getElementById("rfidInput").focus(); }
            };
            if (serverPhoto && serverStatus !== 'error') {
                swalConfig.imageUrl = serverPhoto; swalConfig.imageWidth = 150; swalConfig.imageHeight = 150; swalConfig.imageAlt = 'Foto Siswa';
            } else { swalConfig.icon = iconType; }
            Swal.fire(swalConfig);
        }

        function setMode(mode) {
            document.getElementById('jenis_absen_input').value = mode;
            document.getElementById('rfidInput').removeAttribute('required');
            document.getElementById('scanForm').submit();
        }

        document.addEventListener("DOMContentLoaded", function() {
            const input = document.getElementById("rfidInput");
            if(input) {
                input.focus();
                setInterval(() => { if (document.activeElement !== input && !Swal.isVisible()) { input.focus(); } }, 1000);
                document.addEventListener("click", function(e) { 
                    if(!e.target.closest('button') && !e.target.closest('a') && !Swal.isVisible()) { input.focus(); }
                });
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>