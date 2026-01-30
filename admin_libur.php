<?php
session_start();
include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

// --- KEAMANAN ---
if(!isset($_SESSION['status']) || $_SESSION['status'] != "login"){
    header("location:login.php?pesan=belum_login");
    exit;
}

// --- LOGIC 1: SIMPAN / UPDATE LIBUR ---
if(isset($_POST['simpan_libur'])){
    $tgl = $_POST['tanggal'];
    $ket = mysqli_real_escape_string($conn, $_POST['keterangan']);
    
    // Cek apakah data sudah ada
    $cek = mysqli_query($conn, "SELECT id FROM hari_libur WHERE tanggal='$tgl'");
    if(mysqli_num_rows($cek) > 0){
        // Update
        mysqli_query($conn, "UPDATE hari_libur SET keterangan='$ket' WHERE tanggal='$tgl'");
    } else {
        // Insert
        mysqli_query($conn, "INSERT INTO hari_libur (tanggal, keterangan) VALUES ('$tgl', '$ket')");
    }
    // Redirect untuk refresh
    header("Location: admin_libur.php?bulan=".date('m', strtotime($tgl))."&tahun=".date('Y', strtotime($tgl)));
    exit;
}

// --- LOGIC 2: HAPUS LIBUR ---
if(isset($_POST['hapus_libur'])){
    $tgl = $_POST['tanggal'];
    mysqli_query($conn, "DELETE FROM hari_libur WHERE tanggal='$tgl'");
    header("Location: admin_libur.php?bulan=".date('m', strtotime($tgl))."&tahun=".date('Y', strtotime($tgl)));
    exit;
}

// --- NAVIGASI KALENDER ---
$bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('m');
$tahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');

// Navigasi Prev/Next
$prev_bulan = date('m', mktime(0, 0, 0, $bulan - 1, 1, $tahun));
$prev_tahun = date('Y', mktime(0, 0, 0, $bulan - 1, 1, $tahun));
$next_bulan = date('m', mktime(0, 0, 0, $bulan + 1, 1, $tahun));
$next_tahun = date('Y', mktime(0, 0, 0, $bulan + 1, 1, $tahun));

// Data Kalender
$jumlah_hari = cal_days_in_month(CAL_GREGORIAN, $bulan, $tahun);
$hari_pertama = date('N', mktime(0, 0, 0, $bulan, 1, $tahun)); // 1 (Senin) - 7 (Minggu)
$nama_bulan_str = date('F Y', mktime(0, 0, 0, $bulan, 1, $tahun));

// Ambil Data Libur Bulan Ini
$libur_array = [];
$q_libur = mysqli_query($conn, "SELECT * FROM hari_libur WHERE MONTH(tanggal)='$bulan' AND YEAR(tanggal)='$tahun'");
while($r = mysqli_fetch_assoc($q_libur)){
    $libur_array[$r['tanggal']] = $r['keterangan'];
}

// --- PERSIAPAN MODAL EDIT ---
$modal_active = false;
$modal_tgl = "";
$modal_ket = "";
$is_existing = false;

if(isset($_GET['manage_date'])){
    $modal_active = true;
    $modal_tgl = $_GET['manage_date'];
    
    // Cek di DB
    $cek_modal = mysqli_query($conn, "SELECT keterangan FROM hari_libur WHERE tanggal='$modal_tgl'");
    if(mysqli_num_rows($cek_modal) > 0){
        $d_modal = mysqli_fetch_assoc($cek_modal);
        $modal_ket = $d_modal['keterangan'];
        $is_existing = true;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Atur Hari Libur - Admin</title>
    <link rel="shortcut icon" href="gambar/logo.jpg">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #f8fafc; }
        .calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 8px; }
        .day-card { min-height: 100px; }
        
        /* Modal Animation */
        .modal-enter { animation: modalPop 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
        @keyframes modalPop {
            0% { transform: scale(0.95); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }
    </style>
</head>
<body class="text-slate-800 flex flex-col min-h-screen">

    <!-- Navbar -->
    <nav class="bg-white border-b border-slate-200 p-4 fixed w-full top-0 z-40">
        <div class="max-w-5xl mx-auto flex justify-between items-center">
            <div class="flex items-center gap-3">
                <a href="admin.php" class="p-2 bg-slate-100 rounded-lg hover:bg-slate-200 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                </a>
                <h1 class="font-bold text-lg text-slate-800">Kalender Akademik</h1>
            </div>
            <div class="text-sm text-slate-500">Admin Panel</div>
        </div>
    </nav>

    <main class="max-w-5xl w-full mx-auto px-4 pt-24 pb-12 flex-grow">
        
        <!-- Kontrol Bulan -->
        <div class="flex justify-between items-center mb-6">
            <a href="?bulan=<?php echo $prev_bulan; ?>&tahun=<?php echo $prev_tahun; ?>" class="bg-white p-2 rounded-lg shadow-sm border border-slate-200 hover:bg-slate-50 transition">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
            </a>
            <h2 class="text-2xl font-bold text-slate-800 uppercase tracking-wide"><?php echo $nama_bulan_str; ?></h2>
            <a href="?bulan=<?php echo $next_bulan; ?>&tahun=<?php echo $next_tahun; ?>" class="bg-white p-2 rounded-lg shadow-sm border border-slate-200 hover:bg-slate-50 transition">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </a>
        </div>

        <!-- Header Hari -->
        <div class="grid grid-cols-7 gap-2 mb-2 text-center">
            <div class="font-bold text-slate-400 text-sm py-2">Sen</div>
            <div class="font-bold text-slate-400 text-sm py-2">Sel</div>
            <div class="font-bold text-slate-400 text-sm py-2">Rab</div>
            <div class="font-bold text-slate-400 text-sm py-2">Kam</div>
            <div class="font-bold text-slate-400 text-sm py-2">Jum</div>
            <div class="font-bold text-red-400 text-sm py-2">Sab</div>
            <div class="font-bold text-red-500 text-sm py-2">Min</div>
        </div>

        <!-- Grid Kalender -->
        <div class="calendar-grid">
            <?php
            // Kotak kosong di awal bulan
            for ($x = 1; $x < $hari_pertama; $x++) {
                echo '<div class="day-card bg-slate-50/50 rounded-xl"></div>';
            }

            // Loop Tanggal
            for ($d = 1; $d <= $jumlah_hari; $d++) {
                $tgl_full = sprintf("%04d-%02d-%02d", $tahun, $bulan, $d);
                $is_minggu = (date('N', strtotime($tgl_full)) == 7);
                $is_db_libur = array_key_exists($tgl_full, $libur_array);
                
                // Style Default
                $bg_class = "bg-white border-slate-200 hover:border-blue-400 hover:shadow-md";
                $text_class = "text-slate-700";
                $ket_display = "";

                // Style Minggu
                if ($is_minggu) {
                    $bg_class = "bg-red-50 border-red-100";
                    $text_class = "text-red-500";
                    $ket_display = "<span class='text-[10px] text-red-400 font-medium'>Libur Rutin</span>";
                }
                
                // Style Libur Database (Overwrite Minggu jika tgl merah jatuh di minggu)
                if ($is_db_libur) {
                    $bg_class = "bg-red-500 border-red-600 shadow-md transform scale-[1.02] z-10";
                    $text_class = "text-white";
                    $ket_display = "<span class='text-xs text-white/90 font-medium leading-tight line-clamp-2'>".$libur_array[$tgl_full]."</span>";
                }

                // Link Toggle (Kecuali Minggu murni tanpa event DB, biar ga menuhin database)
                // Jika minggu tapi ada event DB, tetap bisa diedit
                $link = "?bulan=$bulan&tahun=$tahun&manage_date=$tgl_full";

                echo "<a href='$link' class='day-card border p-3 rounded-xl flex flex-col justify-between transition relative group $bg_class'>";
                echo "<span class='font-bold text-lg $text_class'>$d</span>";
                echo $ket_display;
                
                // Indikator Edit (Hover)
                echo "<div class='absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition'>";
                echo "<svg xmlns='http://www.w3.org/2000/svg' class='h-4 w-4 " . ($is_db_libur ? "text-white" : "text-slate-400") . "' fill='none' viewBox='0 0 24 24' stroke='currentColor'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z' /></svg>";
                echo "</div>";
                
                echo "</a>";
            }
            ?>
        </div>

        <div class="mt-8 p-4 bg-blue-50 rounded-xl border border-blue-100 text-sm text-blue-700 flex items-start gap-3">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
            <div>
                <strong>Petunjuk:</strong> Klik pada tanggal manapun untuk <strong>Mengatur Keterangan Libur</strong>. Tanggal yang ditandai merah adalah hari libur sekolah.
            </div>
        </div>

    </main>

    <!-- FOOTER -->
    <footer class="bg-white border-t border-slate-200 py-6 mt-auto">
        <div class="max-w-5xl mx-auto px-4 text-center">
            <p class="text-slate-500 text-xs sm:text-sm font-medium">
                &copy; <?php echo date('Y'); ?> <span class="font-bold text-blue-600">SMK Ma'arif 4-5 Tambakboyo</span>.
            </p>
        </div>
    </footer>

    <!-- MODAL EDIT/TAMBAH LIBUR -->
    <?php if($modal_active): ?>
    <div class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm modal-enter overflow-hidden">
            <div class="bg-slate-50 px-6 py-4 border-b border-slate-100 flex justify-between items-center">
                <h3 class="font-bold text-lg text-slate-800">Atur Hari Libur</h3>
                <a href="admin_libur.php?bulan=<?php echo $bulan; ?>&tahun=<?php echo $tahun; ?>" class="text-slate-400 hover:text-slate-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </a>
            </div>
            
            <form method="POST" class="p-6">
                <input type="hidden" name="tanggal" value="<?php echo $modal_tgl; ?>">
                
                <div class="mb-4">
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Tanggal</label>
                    <div class="p-3 bg-slate-100 rounded-xl font-mono text-slate-700 font-bold border border-slate-200">
                        <?php echo date('d F Y', strtotime($modal_tgl)); ?>
                    </div>
                </div>

                <div class="mb-6">
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Keterangan Libur</label>
                    <input type="text" name="keterangan" value="<?php echo $modal_ket; ?>" required autofocus
                           class="w-full p-3 rounded-xl border border-slate-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition" 
                           placeholder="Contoh: Awal Puasa Ramadhan">
                </div>

                <div class="flex gap-3">
                    <?php if($is_existing): ?>
                        <!-- Tombol Hapus (Jika sudah ada) -->
                        <button type="submit" name="hapus_libur" class="flex-1 bg-red-100 text-red-600 py-3 rounded-xl font-bold hover:bg-red-200 transition">
                            Hapus Libur
                        </button>
                        <!-- Tombol Update -->
                        <button type="submit" name="simpan_libur" class="flex-1 bg-blue-600 text-white py-3 rounded-xl font-bold hover:bg-blue-700 shadow-lg shadow-blue-200 transition">
                            Simpan Perubahan
                        </button>
                    <?php else: ?>
                        <!-- Tombol Simpan Baru -->
                        <button type="submit" name="simpan_libur" class="w-full bg-blue-600 text-white py-3 rounded-xl font-bold hover:bg-blue-700 shadow-lg shadow-blue-200 transition">
                            Tetapkan Libur
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

</body>
</html>