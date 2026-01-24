<?php
session_start();
include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

$status_msg = "";
$pesan = "";
$siswa_nama = "";
$siswa_foto = "";
$rfid_debug = ""; 
$today = date('Y-m-d');

// =========================================================================
// 1. CEK STATUS HARI LIBUR
// =========================================================================
$is_libur = false;
$ket_libur = "";

// A. Cek Hari Minggu
if (date('l') == 'Sunday') {
    $is_libur = true;
    $ket_libur = "Hari Minggu (Libur Rutin)";
}

// B. Cek Database Hari Libur (Jika bukan Minggu)
if (!$is_libur) {
    $cek_libur_db = mysqli_query($conn, "SELECT * FROM hari_libur WHERE tanggal = '$today'");
    if (mysqli_num_rows($cek_libur_db) > 0) {
        $data_libur = mysqli_fetch_assoc($cek_libur_db);
        $is_libur = true;
        $ket_libur = $data_libur['keterangan'];
    }
}

// =========================================================================
// 2. KONFIGURASI JADWAL (Hanya jika TIDAK LIBUR)
// =========================================================================
$jadwal = [];
$hari_inggris = date('l');
$arr_hari = [ 'Sunday'=>'Minggu', 'Monday'=>'Senin', 'Tuesday'=>'Selasa', 'Wednesday'=>'Rabu', 'Thursday'=>'Kamis', 'Friday'=>'Jumat', 'Saturday'=>'Sabtu' ];
$hari_ini = $arr_hari[$hari_inggris];

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
// 3. OTOMATIS ALPHA (Hanya jika TIDAK LIBUR)
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
                mysqli_query($conn, "INSERT INTO log_absensi (rfid_uid, waktu, status) VALUES ('$uid', '$waktu_catat', 'Alpha (Otomatis)')");
            }
        }
        file_put_contents($file_log, $today);
    }
}

if (!$is_libur) {
    cekOtomatisAlpha($conn, $jadwal['Datang']);
}

// =========================================================================
// 4. PROSES SCAN (Hanya jika TIDAK LIBUR)
// =========================================================================

// Handle Mode Absen
$jenis_absen_terpilih = "Datang"; 
if (isset($_POST['jenis_absen'])) $jenis_absen_terpilih = $_POST['jenis_absen'];
if (!$is_libur && !array_key_exists($jenis_absen_terpilih, $jadwal)) $jenis_absen_terpilih = 'Datang';

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

    // LOGIKA WAKTU
    if ($jenis == 'Pulang') {
        if ($selisih_menit < 0) {
            $status_final = "Pulang Lebih Awal";
            $status_msg = "warning_early"; 
            $pesan = "Anda pulang lebih awal.";
        } else {
            $status_final = "Pulang";
            $status_msg = "success"; 
            $pesan = "Hati-hati di jalan!";
        }
    } else {
        if ($selisih_menit <= 5) {
            $status_final = $jenis; 
            $status_msg = "success"; 
            $pesan = "Terima kasih, Anda tepat waktu.";
        } elseif ($selisih_menit <= 10) {
            $status_final = $jenis . " - Telat";
            $status_msg = "warning_late"; 
            $pesan = "Anda Terlambat " . ceil($selisih_menit) . " Menit!";
        } else {
            $status_final = "Alpha (Terlambat)";
            $status_msg = "error"; 
            $pesan = "Terlambat > 10 Menit! Dianggap Alpha.";
        }
    }

    // DB INSERT
    if ($boleh_absen) {
        $cek_siswa = mysqli_query($conn, "SELECT * FROM siswa WHERE rfid_uid = '$rfid'");
        
        if (mysqli_num_rows($cek_siswa) > 0) {
            $data_siswa = mysqli_fetch_assoc($cek_siswa);
            $siswa_nama = $data_siswa['nama'];
            
            // Ambil Foto
            $file_foto = isset($data_siswa['foto']) ? $data_siswa['foto'] : '';
            if (!empty($file_foto) && file_exists("uploads/siswa/" . $file_foto)) {
                $siswa_foto = "uploads/siswa/" . $file_foto;
            } else {
                $siswa_foto = "https://ui-avatars.com/api/?name=" . urlencode($siswa_nama) . "&background=random&size=200&bold=true";
            }

            $cek_double = mysqli_query($conn, "SELECT * FROM log_absensi WHERE rfid_uid = '$rfid' AND DATE(waktu) = '$today' AND status LIKE '$jenis%'");

            if (mysqli_num_rows($cek_double) > 0) {
                $status_msg = "warning";
                $pesan = "$siswa_nama sudah absen $jenis hari ini.";
            } else {
                $insert = mysqli_query($conn, "INSERT INTO log_absensi (rfid_uid, waktu, status) VALUES ('$rfid', '$waktu_sekarang_str', '$status_final')");
                if ($insert) {
                    if(strpos($pesan, $siswa_nama) === false) $pesan .= " ($siswa_nama)"; 
                } else {
                    $status_msg = "error"; $pesan = "Database Error.";
                }
            }
        } else {
            $status_msg = "error"; $pesan = "Kartu Tidak Dikenal!"; $siswa_foto = "";
        }
    }
}

// Format Tanggal Indo untuk Tampilan UI
$bulan_indo = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April', '05' => 'Mei', '06' => 'Juni',
    '07' => 'Juli', '08' => 'Agustus', '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];
$tgl_indo = date('d') . ' ' . $bulan_indo[date('m')] . ' ' . date('Y');
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kiosk Absensi</title>
    
    <!-- Tailwind CSS & SweetAlert -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #0f172a; }
        .font-mono { font-family: 'Space Mono', monospace; }
        .bg-animate {
            background: linear-gradient(-45deg, #1e1b4b, #312e81, #1e3a8a, #0f172a);
            background-size: 400% 400%;
            animation: gradient 15s ease infinite;
        }
        @keyframes gradient { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
        .glass-panel { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); }
        .btn-mode { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .btn-mode:hover { transform: translateY(-2px); }
        .btn-mode.active { transform: scale(1.02); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
        #rfidInput { caret-color: transparent; }
        .swal2-image { border-radius: 50%; border: 4px solid #e2e8f0; object-fit: cover; box-shadow: 0 10px 20px -5px rgba(0,0,0,0.2); }
        
        /* Mode Libur Style */
        .holiday-bg { background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); border: 2px dashed #ef4444; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4 bg-animate relative overflow-hidden">

    <a href="index.php" class="absolute top-6 left-6 flex items-center gap-2 text-white/60 hover:text-white transition-colors z-50">
        <div class="bg-white/10 p-2 rounded-full backdrop-blur-sm hover:bg-white/20">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        </div>
        <span class="text-sm font-medium hidden sm:inline">Dashboard</span>
    </a>

    <div class="w-full max-w-6xl h-[85vh] bg-white rounded-[2.5rem] shadow-2xl overflow-hidden flex flex-col md:flex-row relative z-10">
        
        <!-- SISI KIRI (Visual & Waktu) -->
        <div class="w-full md:w-5/12 bg-gradient-to-br from-indigo-600 to-blue-700 p-10 text-white flex flex-col justify-between relative overflow-hidden">
            <div class="absolute top-0 right-0 -mt-10 -mr-10 w-64 h-64 bg-white/10 rounded-full blur-3xl"></div>
            <div class="absolute bottom-0 left-0 -mb-10 -ml-10 w-40 h-40 bg-purple-500/20 rounded-full blur-2xl"></div>

            <div class="relative z-10">
                <h2 class="text-lg font-medium text-indigo-200 mb-1">Sistem Absensi Rfid</h2>
                <h1 class="text-3xl font-bold tracking-tight">SMK Ma'arif 4-5 Tambakboyo</h1>
                <div class="h-1 w-20 bg-indigo-400 mt-4 rounded-full"></div>
            </div>

            <div class="relative z-10 my-auto text-center">
                <div id="liveClock" class="font-mono text-7xl font-bold tracking-tighter drop-shadow-lg"><?php echo date('H:i'); ?></div>
                <div id="liveSeconds" class="font-mono text-2xl text-indigo-200 font-light mt-[-10px] mb-2"><?php echo date('s'); ?></div>
                <p class="text-lg font-medium opacity-90"><?php echo $hari_ini . ', ' . $tgl_indo; ?></p>
            </div>

            <div class="relative z-10 text-xs text-indigo-200/80 text-center md:text-left">
                <p>Pastikan kartu menempel sempurna pada reader.</p>
                <p>&copy; 2025 E-Absensi System</p>
            </div>
        </div>

        <!-- SISI KANAN (Interaksi) -->
        <div class="w-full md:w-7/12 p-8 md:p-12 bg-white flex flex-col relative">
            
            <?php if ($is_libur): ?>
                <!-- TAMPILAN MODE LIBUR -->
                <div class="h-full flex flex-col items-center justify-center text-center holiday-bg rounded-3xl p-8">
                    <div class="w-28 h-28 bg-red-100 text-red-500 rounded-full flex items-center justify-center mb-6 shadow-xl animate-bounce">
                        <!-- Icon Sad Emoticon -->
                        <svg xmlns="http://www.w3.org/2000/svg" width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <path d="M16 16s-1.5-2-4-2-4 2-4 2"></path>
                            <line x1="9" y1="9" x2="9.01" y2="9"></line>
                            <line x1="15" y1="9" x2="15.01" y2="9"></line>
                        </svg>
                    </div>
                    <h2 class="text-4xl font-extrabold text-slate-800 mb-2">SEKOLAH LIBUR</h2>
                    <div class="bg-white px-6 py-2 rounded-full shadow-sm border border-red-100 mt-2">
                        <p class="text-lg text-red-500 font-bold"><?php echo $ket_libur; ?></p>
                    </div>
                    <p class="mt-8 text-sm text-slate-500">Mesin absensi dinonaktifkan sementara.</p>
                </div>

            <?php else: ?>
                <!-- TAMPILAN MODE NORMAL -->
                <div class="flex justify-between items-end mb-8">
                    <div>
                        <h2 class="text-3xl font-bold text-slate-800 mb-1">Scan Kartu</h2>
                        <p class="text-slate-500">Pilih jenis absen & tempel kartu</p>
                    </div>
                    <div class="text-right">
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Batas Waktu</span>
                        <div class="text-2xl font-mono font-bold text-indigo-600"><?php echo $jadwal[$jenis_absen_terpilih]; ?></div>
                    </div>
                </div>

                <!-- Grid Tombol -->
                <div class="grid grid-cols-2 gap-4 mb-auto">
                    <?php 
                    $icons = [
                        'Datang' => '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/><path d="M19 5L4 20"/>', 
                        'Sholat Jamaah' => '<path d="M12 2l4 10h6l-5 4 2 9-7-5-7 5 2-9-5-4h6z"/>', 
                        'Pulang' => '<path d="M9 21v-6a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v6"/><path d="M5 12V7c0-1.1.9-2 2-2h10a2 2 0 0 1 2 2v5"/><path d="M3 10l9-7 9 7"/>' 
                    ];

                    foreach($jadwal as $key => $jam): 
                        $isActive = ($key == $jenis_absen_terpilih);
                        $btnClass = $isActive ? "bg-indigo-600 text-white shadow-lg shadow-indigo-200 ring-2 ring-indigo-600 ring-offset-2" : "bg-slate-50 text-slate-600 border border-slate-200 hover:border-indigo-300 hover:bg-indigo-50";
                    ?>
                    <button type="button" onclick="setMode('<?php echo $key; ?>')" class="btn-mode relative group flex items-center gap-4 p-4 rounded-2xl text-left w-full <?php echo $btnClass; ?>">
                        <div class="p-3 rounded-xl bg-white/20 backdrop-blur-sm <?php echo $isActive ? 'bg-white/20' : 'bg-white shadow-sm'; ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="<?php echo $isActive ? 'text-white' : 'text-indigo-600'; ?>">
                                <?php echo isset($icons[$key]) ? $icons[$key] : '<circle cx="12" cy="12" r="10"/>'; ?>
                            </svg>
                        </div>
                        <div>
                            <span class="block text-xs opacity-80 font-medium uppercase tracking-wider">Mode</span>
                            <span class="block font-bold text-lg leading-tight"><?php echo $key; ?></span>
                        </div>
                        <?php if($isActive): ?>
                            <div class="absolute top-3 right-3">
                                <span class="relative flex h-3 w-3"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-white opacity-75"></span><span class="relative inline-flex rounded-full h-3 w-3 bg-green-400"></span></span>
                            </div>
                        <?php endif; ?>
                    </button>
                    <?php endforeach; ?>
                </div>

                <!-- Indikator Standby -->
                <?php if (empty($status_msg)): ?>
                    <div class="mb-6 flex justify-center py-6">
                        <div class="relative">
                            <div class="w-24 h-24 bg-blue-50 rounded-full flex items-center justify-center border-4 border-blue-100 animate-pulse">
                                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-blue-500"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M12 12v.01"/></svg>
                            </div>
                            <div class="absolute -bottom-2 -right-2 bg-blue-600 text-white text-xs font-bold px-2 py-1 rounded-full">READY</div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Form Hidden -->
                <form method="POST" action="" id="scanForm" class="relative mt-8">
                    <input type="hidden" name="jenis_absen" id="jenis_absen_input" value="<?php echo $jenis_absen_terpilih; ?>">
                    <div class="relative group">
                        <div class="absolute -inset-1 bg-gradient-to-r from-indigo-500 to-blue-500 rounded-2xl blur opacity-20 group-hover:opacity-40 transition duration-500"></div>
                        <input type="text" name="rfid" id="rfidInput" class="relative w-full bg-white border-2 border-slate-200 text-slate-800 text-center font-mono text-xl p-5 rounded-xl focus:outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 transition-all placeholder:text-slate-300" placeholder="Klik di sini & Scan Kartu..." autocomplete="off" required>
                    </div>
                </form>
                
                <div class="mt-6 text-center">
                    <p class="text-xs text-gray-400">Mode Aktif:</p>
                    <p class="font-bold text-gray-600 uppercase tracking-widest"><?php echo $jenis_absen_terpilih; ?></p>
                    <p class="text-xs text-red-400 mt-1 font-semibold">Lewat 10 Menit / Tidak Hadir = ALPHA</p>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- JavaScript & SweetAlert Logic -->
    <script>
        function updateClock() {
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            document.getElementById('liveClock').innerText = `${hours}:${minutes}`;
            document.getElementById('liveSeconds').innerText = seconds;
        }
        setInterval(updateClock, 1000);
        updateClock(); 

        <?php if(!$is_libur): ?>
        // Notifikasi Logic
        const serverStatus = "<?php echo $status_msg; ?>";
        const serverMessage = "<?php echo $pesan; ?>";
        const serverPhoto = "<?php echo $siswa_foto; ?>";

        if (serverStatus) {
            let iconType = 'info';
            let titleText = 'Info';
            let popupColor = '#4338ca'; 

            if (serverStatus === 'success') { iconType = 'success'; titleText = 'Berhasil!'; popupColor = '#22c55e'; }
            else if (serverStatus === 'warning_late') { iconType = 'warning'; titleText = 'Terlambat!'; popupColor = '#f97316'; }
            else if (serverStatus === 'warning_early') { iconType = 'warning'; titleText = 'Pulang Awal'; popupColor = '#eab308'; }
            else if (serverStatus === 'warning') { iconType = 'info'; titleText = 'Sudah Absen'; popupColor = '#3b82f6'; }
            else if (serverStatus === 'error') { iconType = 'error'; titleText = 'Gagal / Alpha!'; popupColor = '#ef4444'; }

            if ('speechSynthesis' in window) {
                const utterance = new SpeechSynthesisUtterance(serverMessage);
                utterance.lang = 'id-ID'; utterance.rate = 0.9; window.speechSynthesis.speak(utterance);
            }

            let swalConfig = {
                title: titleText, text: serverMessage, timer: 3000, showConfirmButton: false,
                timerProgressBar: true, background: '#fff', color: '#1e293b', iconColor: popupColor,
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