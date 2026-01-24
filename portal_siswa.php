<?php
session_start();
include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

// --- KEAMANAN: CEK LOGIN SISWA ---
if(!isset($_SESSION['status_siswa']) || $_SESSION['status_siswa'] != "login_siswa"){
    header("location:login_siswa.php");
    exit;
}

$nis_siswa = $_SESSION['nis_siswa'];
$nama_siswa = $_SESSION['nama_siswa'];
$rfid_siswa = $_SESSION['rfid_siswa'];

// --- LOGIC: PENGAJUAN IZIN + UPLOAD ---
$pesan_izin = "";
$tipe_pesan = "";

if(isset($_POST['ajukan_izin'])){
    $tgl_izin = mysqli_real_escape_string($conn, $_POST['tgl_izin']);
    $jenis_izin = mysqli_real_escape_string($conn, $_POST['jenis_izin']); 
    $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan']);
    
    // Proses Upload Bukti
    $bukti_nama = "";
    $upload_ok = false;

    if(isset($_FILES['bukti']) && $_FILES['bukti']['error'] == 0){
        $allowed_ext = ['jpg', 'jpeg', 'png', 'pdf'];
        $filename = $_FILES['bukti']['name'];
        $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $file_size = $_FILES['bukti']['size'];

        if(in_array($file_ext, $allowed_ext)){
            if($file_size <= 5000000){
                $new_filename = "bukti_" . $nis_siswa . "_" . time() . "." . $file_ext;
                $target_dir = "uploads/";
                if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }

                if(move_uploaded_file($_FILES['bukti']['tmp_name'], $target_dir . $new_filename)){
                    $bukti_nama = $new_filename;
                    $upload_ok = true;
                } else {
                    $tipe_pesan = "error"; $pesan_izin = "Gagal mengupload file.";
                }
            } else {
                $tipe_pesan = "error"; $pesan_izin = "Ukuran file max 5MB.";
            }
        } else {
            $tipe_pesan = "error"; $pesan_izin = "Format file harus JPG, PNG, atau PDF.";
        }
    } else {
        $tipe_pesan = "error"; $pesan_izin = "Bukti (Foto/Dokumen) WAJIB diupload!";
    }

    if($upload_ok){
        $status_db = $jenis_izin . " (" . $keterangan . ")";
        $waktu_insert = $tgl_izin . " 07:00:00"; 

        $cek_absen = mysqli_query($conn, "SELECT * FROM log_absensi WHERE rfid_uid='$rfid_siswa' AND DATE(waktu)='$tgl_izin'");
        
        if(mysqli_num_rows($cek_absen) > 0){
            $tipe_pesan = "error";
            $pesan_izin = "Anda sudah memiliki data absensi pada tanggal tersebut.";
            if(file_exists($target_dir . $new_filename)) unlink($target_dir . $new_filename);
        } else {
            // Default status_approval = 'Pending'
            $simpan = mysqli_query($conn, "INSERT INTO log_absensi (rfid_uid, waktu, status, bukti, status_approval) VALUES ('$rfid_siswa', '$waktu_insert', '$status_db', '$bukti_nama', 'Pending')");
            
            if($simpan){
                $tipe_pesan = "success"; $pesan_izin = "Pengajuan berhasil dikirim & menunggu persetujuan.";
            } else {
                $tipe_pesan = "error"; $pesan_izin = "Database Error.";
            }
        }
    }
}

// --- DATA STATISTIK PRIBADI (PERBAIKAN LOGIKA) ---

// 1. Hadir (Datang/Tadarus/Sholat/Pulang)
$q_hadir = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM log_absensi WHERE rfid_uid='$rfid_siswa' AND (status LIKE '%Datang%' OR status LIKE '%Tadarus%' OR status LIKE '%Sholat%' OR status LIKE '%Pulang%') AND status NOT LIKE '%Lebih Awal%'"));

// 2. Telat
$q_telat = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM log_absensi WHERE rfid_uid='$rfid_siswa' AND status LIKE '%Telat%'"));

// 3. Izin/Sakit (Hanya yang Pending atau Approved)
// Jika Rejected, jangan dihitung disini
$q_izin  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM log_absensi WHERE rfid_uid='$rfid_siswa' AND (status LIKE '%Sakit%' OR status LIKE '%Izin%') AND (status_approval = 'Pending' OR status_approval = 'Approved' OR status_approval IS NULL)"));

// 4. Alpha (Murni Alpha OR Izin yang Ditolak)
// Tambahkan logika OR status_approval = 'Rejected'
$q_alpha = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM log_absensi WHERE rfid_uid='$rfid_siswa' AND (status LIKE '%Alpha%' OR status LIKE '%Lebih Awal%' OR status_approval = 'Rejected')"));

// --- DATA RIWAYAT ---
$q_riwayat = mysqli_query($conn, "SELECT * FROM log_absensi WHERE rfid_uid='$rfid_siswa' ORDER BY waktu DESC LIMIT 20");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Siswa</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #f8fafc; }
        .file-input::file-selector-button {
            margin-right: 1rem; border: none; background-color: #f3e8ff; color: #7e22ce;
            padding: 0.5rem 1rem; border-radius: 0.5rem; font-weight: 600; font-size: 0.75rem; cursor: pointer;
        }
        .file-input::file-selector-button:hover { background-color: #e9d5ff; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none;  scrollbar-width: none; }
    </style>
</head>
<body class="text-slate-800">

    <!-- Topbar Mobile-First -->
    <nav class="bg-white/80 backdrop-blur-md border-b border-slate-200 fixed w-full z-50">
        <div class="max-w-5xl mx-auto px-4 h-16 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-blue-500 to-purple-500 p-0.5">
                    <div class="w-full h-full rounded-full bg-white flex items-center justify-center text-blue-600 font-bold">
                        <?php echo substr($nama_siswa, 0, 1); ?>
                    </div>
                </div>
                <div>
                    <h1 class="text-sm font-bold text-slate-800 leading-tight"><?php echo $nama_siswa; ?></h1>
                    <p class="text-[10px] text-slate-500 font-mono"><?php echo $nis_siswa; ?></p>
                </div>
            </div>
            <a href="logout.php" class="text-sm font-medium text-red-500 hover:text-red-600 bg-red-50 px-3 py-1.5 rounded-lg transition">Logout</a>
        </div>
    </nav>

    <main class="max-w-5xl mx-auto px-4 pt-24 pb-20 space-y-6">

        <!-- Welcome Banner -->
        <div class="bg-gradient-to-r from-blue-600 to-purple-600 rounded-2xl p-6 text-white shadow-lg shadow-blue-500/20 relative overflow-hidden">
            <div class="absolute top-0 right-0 -mt-4 -mr-4 w-32 h-32 bg-white/10 rounded-full blur-2xl"></div>
            <h2 class="text-2xl font-bold mb-1">Halo, <?php echo explode(' ', $nama_siswa)[0]; ?>! 👋</h2>
            <p class="text-blue-100 text-sm">Jangan lupa absen hari ini ya. Tetap semangat belajar!</p>
        </div>

        <!-- Statistik Grid -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-100">
                <p class="text-xs text-slate-400 font-bold uppercase">Hadir</p>
                <h3 class="text-2xl font-bold text-green-500"><?php echo $q_hadir['c']; ?></h3>
            </div>
            <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-100">
                <p class="text-xs text-slate-400 font-bold uppercase">Telat</p>
                <h3 class="text-2xl font-bold text-orange-500"><?php echo $q_telat['c']; ?></h3>
            </div>
            <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-100">
                <p class="text-xs text-slate-400 font-bold uppercase">Izin/Sakit</p>
                <h3 class="text-2xl font-bold text-blue-500"><?php echo $q_izin['c']; ?></h3>
            </div>
            <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-100">
                <p class="text-xs text-slate-400 font-bold uppercase">Alpha</p>
                <h3 class="text-2xl font-bold text-red-500"><?php echo $q_alpha['c']; ?></h3>
            </div>
        </div>

        <!-- Form Pengajuan Izin -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <button onclick="toggleForm()" class="w-full px-6 py-4 flex justify-between items-center bg-slate-50 hover:bg-slate-100 transition">
                <div class="flex items-center gap-3">
                    <div class="bg-purple-100 text-purple-600 p-2 rounded-lg">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                    </div>
                    <div class="text-left">
                        <h3 class="font-bold text-slate-800">Ajukan Izin / Sakit</h3>
                        <p class="text-xs text-slate-500">Upload bukti untuk mengajukan</p>
                    </div>
                </div>
                <svg id="iconChevron" xmlns="http://www.w3.org/2000/svg" width="20" height="20" class="text-slate-400 transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
            </button>
            
            <div id="formIzin" class="hidden p-6 border-t border-slate-100 bg-white">
                <form method="POST" enctype="multipart/form-data" class="space-y-5">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 mb-1.5">Tanggal Izin</label>
                            <input type="date" name="tgl_izin" required class="w-full p-3 rounded-xl bg-slate-50 border border-slate-200 focus:ring-2 focus:ring-purple-200 focus:border-purple-500 outline-none text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 mb-1.5">Jenis Pengajuan</label>
                            <select name="jenis_izin" required class="w-full p-3 rounded-xl bg-slate-50 border border-slate-200 focus:ring-2 focus:ring-purple-200 focus:border-purple-500 outline-none text-sm">
                                <option value="Sakit">Sakit</option>
                                <option value="Izin">Izin</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1.5">Keterangan</label>
                        <textarea name="keterangan" rows="2" required class="w-full p-3 rounded-xl bg-slate-50 border border-slate-200 focus:ring-2 focus:ring-purple-200 focus:border-purple-500 outline-none text-sm" placeholder="Contoh: Demam tinggi, ada acara keluarga..."></textarea>
                    </div>
                    <div class="bg-purple-50 border border-dashed border-purple-200 rounded-xl p-4">
                        <label class="block text-xs font-bold text-purple-700 mb-2 flex items-center gap-1">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path></svg>
                            Upload Bukti (Wajib)
                        </label>
                        <input type="file" name="bukti" required accept="image/*,.pdf" class="file-input w-full text-sm text-slate-500">
                    </div>
                    <button type="submit" name="ajukan_izin" class="w-full bg-purple-600 hover:bg-purple-700 text-white py-3 rounded-xl font-bold shadow-lg shadow-purple-200 transition transform active:scale-95">
                        Kirim Pengajuan
                    </button>
                </form>
            </div>
        </div>

        <!-- Riwayat Absensi -->
        <div>
            <h3 class="font-bold text-lg text-slate-800 mb-4 px-2 flex items-center gap-2">Riwayat Terbaru</h3>
            <div class="space-y-3">
                <?php if(mysqli_num_rows($q_riwayat) > 0): ?>
                    <?php while($row = mysqli_fetch_assoc($q_riwayat)): 
                        $status = $row['status'];
                        $approval = isset($row['status_approval']) ? $row['status_approval'] : '';
                        $date = strtotime($row['waktu']);
                        
                        // STYLE DEFAULT
                        $icon = '<path d="M20 6L9 17l-5-5"/>'; 
                        $bg_icon = "bg-green-100 text-green-600";
                        $border = "border-l-4 border-green-500";

                        // 1. JIKA DITOLAK -> ALPHA (MERAH)
                        if ($approval == 'Rejected') {
                            $bg_icon = "bg-red-100 text-red-600"; 
                            $border = "border-l-4 border-red-500";
                            $icon = '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>';
                            // Ubah tampilan status agar jelas ditolak
                            $status = "Pengajuan Ditolak (Dianggap Alpha)";
                        }
                        // 2. JIKA PENDING -> KUNING
                        elseif ($approval == 'Pending') {
                            $bg_icon = "bg-yellow-100 text-yellow-600";
                            $border = "border-l-4 border-yellow-500";
                            $icon = '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>';
                            $status .= " (Menunggu)";
                        }
                        // 3. STATUS LAIN
                        elseif(strpos($status, 'Telat') !== false) { 
                            $bg_icon = "bg-orange-100 text-orange-600"; 
                            $border = "border-l-4 border-orange-500";
                            $icon = '<circle cx="12" cy="12" r="10"/>';
                        }
                        elseif(strpos($status, 'Sakit') !== false || strpos($status, 'Izin') !== false) { 
                            $bg_icon = "bg-blue-100 text-blue-600"; 
                            $border = "border-l-4 border-blue-500";
                            $icon = '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>';
                        }
                        elseif(strpos($status, 'Alpha') !== false || strpos($status, 'Lebih Awal') !== false) { 
                            $bg_icon = "bg-red-100 text-red-600"; 
                            $border = "border-l-4 border-red-500";
                            $icon = '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>';
                        }
                    ?>
                    <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-100 flex items-center gap-4 <?php echo $border; ?>">
                        <div class="p-2 rounded-full flex-shrink-0 <?php echo $bg_icon; ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?php echo $icon; ?></svg>
                        </div>
                        <div class="flex-grow">
                            <h4 class="font-bold text-slate-700 text-sm"><?php echo $status; ?></h4>
                            <p class="text-xs text-slate-400"><?php echo date('l, d F Y', $date); ?></p>
                            <?php if(!empty($row['bukti'])): ?>
                                <a href="uploads/<?php echo $row['bukti']; ?>" target="_blank" class="text-[10px] text-blue-500 hover:underline flex items-center gap-1 mt-1">Lihat Bukti</a>
                            <?php endif; ?>
                        </div>
                        <div class="text-right">
                            <span class="font-mono text-sm font-bold text-slate-600 bg-slate-100 px-2 py-1 rounded"><?php echo date('H:i', $date); ?></span>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center py-12 bg-white rounded-2xl border border-slate-100 border-dashed">
                        <p class="text-slate-400 text-sm">Belum ada riwayat.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </main>

    <script>
        function toggleForm() {
            const form = document.getElementById('formIzin');
            const icon = document.getElementById('iconChevron');
            form.classList.toggle('hidden');
            if (form.classList.contains('hidden')) icon.style.transform = 'rotate(0deg)';
            else icon.style.transform = 'rotate(180deg)';
        }
        <?php if($pesan_izin): ?>
            Swal.fire({
                icon: '<?php echo $tipe_pesan; ?>',
                title: '<?php echo $tipe_pesan == "success" ? "Berhasil!" : "Gagal"; ?>',
                text: '<?php echo $pesan_izin; ?>',
                confirmButtonColor: '#9333ea'
            });
        <?php endif; ?>
    </script>

</body>
</html>