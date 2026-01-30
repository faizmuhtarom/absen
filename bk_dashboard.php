<?php
session_start();
include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

// --- KEAMANAN ---
if(!isset($_SESSION['status_bk']) || $_SESSION['status_bk'] != "login_bk"){
    header("location:login_bk.php");
    exit;
}

$nama_bk = $_SESSION['nama_bk'];

// --- FILTER & CONFIG ---
$bulan_ini = isset($_GET['bulan']) ? $_GET['bulan'] : date('m');
$tahun_ini = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');
$kelas_pilih = isset($_GET['kelas']) ? $_GET['kelas'] : '';
$batas_alpha = isset($_GET['batas']) ? $_GET['batas'] : 3; // Default tampilkan yang > 3 alpha

// --- QUERY UTAMA ---
// Menghitung jumlah pelanggaran per siswa
$query = "SELECT s.nama, s.kelas, s.nis, s.id, 
          COUNT(l.id) as total_alpha
          FROM siswa s 
          JOIN log_absensi l ON s.rfid_uid = l.rfid_uid
          WHERE MONTH(l.waktu) = '$bulan_ini' 
          AND YEAR(l.waktu) = '$tahun_ini'
          AND (l.status LIKE '%Alpha%' OR l.status LIKE '%Bolos%' OR l.status_approval = 'Rejected')";

if (!empty($kelas_pilih)) {
    $query .= " AND s.kelas = '$kelas_pilih'";
}

$query .= " GROUP BY s.id HAVING total_alpha >= $batas_alpha ORDER BY total_alpha DESC";

$result = mysqli_query($conn, $query);
$jumlah_pelanggar = mysqli_num_rows($result);

// Ambil Daftar Kelas
$q_kelas = mysqli_query($conn, "SELECT DISTINCT kelas FROM siswa ORDER BY kelas ASC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard BK - Generator SP</title>
    <link rel="shortcut icon" href="gambar/logo.jpg">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #fff1f2; }
        @media print {
            .no-print { display: none !important; }
            .print-area { display: block !important; position: absolute; top: 0; left: 0; width: 100%; }
            @page { margin: 2cm; size: A4; }
            .surat-header { text-align: center; border-bottom: 3px double black; padding-bottom: 10px; margin-bottom: 20px; }
            .surat-logo { width: 80px; float: left; }
            .surat-body { font-size: 12pt; line-height: 1.5; text-align: justify; }
            .surat-ttd { float: right; width: 200px; text-align: center; margin-top: 50px; }
        }
        .print-area { display: none; }
    </style>
</head>
<body class="text-slate-800">

    <!-- Navbar -->
    <nav class="bg-rose-700 text-white p-4 shadow-lg sticky top-0 z-50 no-print">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div class="flex items-center gap-3">
                <div class="bg-white/20 p-2 rounded-lg">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                </div>
                <div>
                    <h1 class="font-bold text-lg leading-tight">Dashboard BK</h1>
                    <p class="text-xs text-rose-200">Monitoring & Tindak Lanjut</p>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-sm hidden md:inline">Halo, <b><?php echo $nama_bk; ?></b></span>
                <a href="logout_bk.php" class="bg-white text-rose-700 px-4 py-2 rounded-lg text-sm font-bold hover:bg-rose-50 transition">Logout</a>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 pt-8 pb-12 no-print">

        <!-- Filter Bar -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-rose-100 mb-8">
            <h2 class="text-lg font-bold text-slate-700 mb-4 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" class="text-rose-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
                Filter Data Pelanggaran
            </h2>
            <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <select name="bulan" class="p-2.5 rounded-xl border border-slate-200 bg-slate-50 focus:ring-2 focus:ring-rose-500 outline-none">
                    <?php 
                    $bln_arr = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
                    foreach($bln_arr as $k=>$v){
                        $sel = ($k == $bulan_ini) ? 'selected' : '';
                        echo "<option value='$k' $sel>$v</option>";
                    }
                    ?>
                </select>
                <select name="tahun" class="p-2.5 rounded-xl border border-slate-200 bg-slate-50 focus:ring-2 focus:ring-rose-500 outline-none">
                    <?php 
                    for($t=2023; $t<=date('Y'); $t++){
                        $sel = ($t == $tahun_ini) ? 'selected' : '';
                        echo "<option value='$t' $sel>$t</option>";
                    }
                    ?>
                </select>
                <select name="kelas" class="p-2.5 rounded-xl border border-slate-200 bg-slate-50 focus:ring-2 focus:ring-rose-500 outline-none">
                    <option value="">Semua Kelas</option>
                    <?php while($k = mysqli_fetch_assoc($q_kelas)): ?>
                        <option value="<?php echo $k['kelas']; ?>" <?php echo ($kelas_pilih == $k['kelas']) ? 'selected' : ''; ?>><?php echo $k['kelas']; ?></option>
                    <?php endwhile; ?>
                </select>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400 text-xs">Min Alpha:</div>
                    <input type="number" name="batas" value="<?php echo $batas_alpha; ?>" min="1" class="w-full pl-20 p-2.5 rounded-xl border border-slate-200 bg-slate-50 focus:ring-2 focus:ring-rose-500 outline-none">
                </div>
                <button type="submit" class="bg-rose-600 hover:bg-rose-700 text-white font-bold py-2.5 rounded-xl transition shadow-lg shadow-rose-200">
                    Tampilkan
                </button>
            </form>
        </div>

        <!-- Hasil -->
        <div class="bg-white rounded-2xl shadow-lg border border-slate-200 overflow-hidden">
            <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-rose-50/50">
                <h3 class="font-bold text-lg text-slate-800">Daftar Siswa Bermasalah</h3>
                <span class="bg-red-100 text-red-600 px-3 py-1 rounded-full text-xs font-bold border border-red-200">
                    <?php echo $jumlah_pelanggar; ?> Siswa Ditemukan
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-500 font-bold uppercase text-xs">
                        <tr>
                            <th class="px-6 py-4">Nama Siswa</th>
                            <th class="px-6 py-4">Kelas</th>
                            <th class="px-6 py-4 text-center">Total Alpha</th>
                            <th class="px-6 py-4 text-center">Status</th>
                            <th class="px-6 py-4 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if($jumlah_pelanggar > 0): ?>
                            <?php while($row = mysqli_fetch_assoc($result)): ?>
                            <tr class="hover:bg-rose-50 transition">
                                <td class="px-6 py-4">
                                    <p class="font-bold text-slate-800"><?php echo $row['nama']; ?></p>
                                    <p class="text-xs text-slate-400 font-mono"><?php echo $row['nis']; ?></p>
                                </td>
                                <td class="px-6 py-4"><span class="bg-slate-100 px-2 py-1 rounded text-xs font-bold text-slate-600"><?php echo $row['kelas']; ?></span></td>
                                <td class="px-6 py-4 text-center">
                                    <span class="text-lg font-bold text-red-600"><?php echo $row['total_alpha']; ?>x</span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="text-xs font-bold text-red-500 bg-red-100 px-2 py-1 rounded border border-red-200">PERLU SP</span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <button onclick="cetakSP('<?php echo $row['nama']; ?>', '<?php echo $row['kelas']; ?>', '<?php echo $row['nis']; ?>', '<?php echo $row['total_alpha']; ?>')" class="bg-slate-800 hover:bg-slate-900 text-white px-4 py-2 rounded-lg text-xs font-bold flex items-center gap-2 mx-auto transition shadow-md">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                                        Cetak SP
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="px-6 py-12 text-center text-slate-400 italic">Tidak ada siswa yang melebihi batas alpha bulan ini.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- TEMPLATE SURAT (Hidden, Only Visible on Print) -->
    <div id="printArea" class="print-area hidden">
        <div class="surat-header">
            <img src="gambar/logo.jpg" class="surat-logo">
            <h1 style="margin:0; font-size:16pt;">SMK MA'ARIF 4-5 TAMBAKBOYO</h1>
            <p class="surat-address">Jl. Raya Tambakboyo No. 123, Tuban, Jawa Timur</p>
        </div>

        <div style="text-align: center; margin-bottom: 30px;">
            <h2 style="text-decoration: underline; margin-bottom: 5px;">SURAT PERINGATAN (SP)</h2>
            <p>Nomor: .../BK/SMK/<?php echo date('m/Y'); ?></p>
        </div>

        <div class="surat-body">
            <p>Yth. Orang Tua / Wali Murid,</p>
            <p>Dengan hormat,</p>
            <p>Memberitahukan bahwa siswa di bawah ini:</p>
            
            <table style="margin: 20px 0; width: 100%;">
                <tr><td style="width: 150px;">Nama</td><td>: <span id="sp_nama"></span></td></tr>
                <tr><td>NIS</td><td>: <span id="sp_nis"></span></td></tr>
                <tr><td>Kelas</td><td>: <span id="sp_kelas"></span></td></tr>
                <tr><td>Jumlah Alpha</td><td>: <b><span id="sp_jumlah"></span> Hari</b> (Bulan <?php echo date('F'); ?>)</td></tr>
            </table>

            <p>Telah tidak hadir tanpa keterangan (Alpha) melebihi batas toleransi yang ditentukan sekolah. Kami mohon kerjasamanya untuk membina putra/putrinya di rumah agar lebih disiplin dalam kehadiran sekolah.</p>
            <p>Demikian surat peringatan ini kami sampaikan agar menjadi perhatian.</p>
        </div>

        <div class="surat-ttd">
            <p>Tambakboyo, <?php echo date('d F Y'); ?></p>
            <p>Guru Bimbingan Konseling,</p>
            <br><br><br>
            <p><b><?php echo $nama_bk; ?></b></p>
        </div>
    </div>

    <script>
        function cetakSP(nama, kelas, nis, jumlah) {
            document.getElementById('sp_nama').innerText = nama;
            document.getElementById('sp_kelas').innerText = kelas;
            document.getElementById('sp_nis').innerText = nis;
            document.getElementById('sp_jumlah').innerText = jumlah;
            window.print();
        }
    </script>

</body>
</html>