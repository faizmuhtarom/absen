<?php
session_start();
include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

// --- KEAMANAN ---
if(!isset($_SESSION['status']) || $_SESSION['status'] != "login"){
    header("location:login.php?pesan=belum_login");
    exit;
}

// --- CONFIG ---
$batas_alpha_sp1 = 3; // Minimal 3x Alpha untuk kena SP1
$bulan_ini = isset($_GET['bulan']) ? $_GET['bulan'] : date('m');
$tahun_ini = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');

// --- QUERY SISWA BERMASALAH ---
// Menghitung Alpha Murni + Izin Ditolak + Alpha Otomatis
$query_sp = "SELECT s.id, s.nama, s.kelas, s.nis, 
             COUNT(l.id) as jumlah_alpha 
             FROM siswa s
             JOIN log_absensi l ON s.rfid_uid = l.rfid_uid
             WHERE (l.status LIKE '%Alpha%' OR l.status LIKE '%Bolos%' OR l.status_approval = 'Rejected')
             AND MONTH(l.waktu) = '$bulan_ini' 
             AND YEAR(l.waktu) = '$tahun_ini'
             GROUP BY s.id
             HAVING jumlah_alpha >= $batas_alpha_sp1
             ORDER BY jumlah_alpha DESC";

$result_sp = mysqli_query($conn, $query_sp);
$jumlah_pelanggar = mysqli_num_rows($result_sp);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Generator SP - Admin</title>
    <link rel="shortcut icon" href="gambar/logo.jpg">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #f8fafc; }
        
        /* CSS KHUSUS CETAK */
        @media print {
            .no-print { display: none !important; }
            body { background: white; color: black; }
            #printArea { display: block !important; position: absolute; top: 0; left: 0; width: 100%; z-index: 9999; background: white; padding: 0; margin: 0; }
            @page { margin: 2cm; size: A4; }
            
            /* Hapus highlight edit saat print */
            [contenteditable="true"] { outline: none !important; background: transparent !important; border: none !important; }
            
            .surat-header { border-bottom: 3px double black; padding-bottom: 10px; margin-bottom: 20px; display: flex; align-items: center; gap: 20px; }
            .surat-logo { width: 80px; height: auto; }
            .surat-kop-text { text-align: center; flex: 1; }
            .surat-body { font-size: 12pt; line-height: 1.6; text-align: justify; }
            .surat-ttd { float: right; width: 250px; text-align: center; margin-top: 50px; }
        }
        
        /* Highlight area yang bisa diedit */
        .editable:hover {
            background-color: #fff1f2;
            outline: 1px dashed #f43f5e;
            cursor: text;
        }
        
        .print-area { display: none; }
    </style>
</head>
<body class="text-slate-800">

    <!-- Navbar (No Print) -->
    <nav class="bg-white border-b border-slate-200 p-4 fixed w-full top-0 z-50 no-print">
        <div class="max-w-6xl mx-auto flex justify-between items-center">
            <div class="flex items-center gap-3">
                <a href="admin.php" class="p-2 bg-slate-100 rounded-lg hover:bg-slate-200 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                </a>
                <h1 class="font-bold text-lg text-slate-800">Generator Surat Peringatan</h1>
            </div>
            <div class="text-sm text-slate-500">
                <form method="GET" class="flex gap-2">
                    <select name="bulan" onchange="this.form.submit()" class="border rounded p-1 text-xs bg-slate-50">
                        <?php for($i=1;$i<=12;$i++){ $s=($i==$bulan_ini)?'selected':''; echo "<option value='$i' $s>".date('F', mktime(0,0,0,$i,10))."</option>"; } ?>
                    </select>
                </form>
            </div>
        </div>
    </nav>

    <main class="max-w-6xl mx-auto px-4 pt-24 pb-12 no-print">
        
        <!-- Info Box -->
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6 flex items-start gap-3 shadow-sm">
            <div class="text-blue-500 mt-1">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
            </div>
            <div>
                <h3 class="font-bold text-blue-800">Mode Edit Aktif</h3>
                <p class="text-sm text-blue-600">
                    Siswa di bawah ini memiliki <b>Alpha > <?php echo $batas_alpha_sp1; ?> kali</b>. Klik "Buat Surat", lalu Anda bisa <b>mengedit teks surat langsung</b> di layar sebelum mencetak.
                </p>
            </div>
        </div>

        <!-- Tabel Pelanggaran -->
        <div class="bg-white rounded-2xl shadow-lg border border-slate-200 overflow-hidden">
            <div class="p-6 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                <h3 class="font-bold text-slate-800">Daftar Siswa Bermasalah</h3>
                <span class="bg-red-100 text-red-600 px-3 py-1 rounded-full text-xs font-bold"><?php echo $jumlah_pelanggar; ?> Siswa</span>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-100 text-slate-500 uppercase text-xs font-bold">
                        <tr>
                            <th class="px-6 py-4">Nama Siswa</th>
                            <th class="px-6 py-4">Kelas</th>
                            <th class="px-6 py-4 text-center">Jml Alpha</th>
                            <th class="px-6 py-4 text-center">Status</th>
                            <th class="px-6 py-4 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if($jumlah_pelanggar > 0): ?>
                            <?php while($row = mysqli_fetch_assoc($result_sp)): ?>
                            <tr class="hover:bg-slate-50 transition">
                                <td class="px-6 py-4 font-bold text-slate-800">
                                    <?php echo $row['nama']; ?>
                                    <span class="block text-xs text-slate-400 font-normal font-mono"><?php echo $row['nis']; ?></span>
                                </td>
                                <td class="px-6 py-4"><span class="bg-slate-100 px-2 py-1 rounded text-xs font-bold text-slate-600"><?php echo $row['kelas']; ?></span></td>
                                <td class="px-6 py-4 text-center">
                                    <span class="text-lg font-bold text-red-600"><?php echo $row['jumlah_alpha']; ?>x</span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="bg-rose-100 text-rose-700 px-2 py-1 rounded text-[10px] font-bold border border-rose-200 uppercase">
                                        Perlu SP 1
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <button onclick="bukaSurat('<?php echo $row['nama']; ?>', '<?php echo $row['kelas']; ?>', '<?php echo $row['nis']; ?>', '<?php echo $row['jumlah_alpha']; ?>')" class="bg-slate-800 hover:bg-slate-900 text-white px-4 py-2 rounded-lg text-xs font-bold transition shadow hover:shadow-lg flex items-center gap-2 mx-auto">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                                        Buat Surat
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="px-6 py-16 text-center text-slate-400 italic">
                                    <div class="flex flex-col items-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mb-3 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        Tidak ada siswa yang mencapai batas pelanggaran bulan ini.
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- TEMPLATE SURAT (Visible only on Preview/Print) -->
    <div id="printArea" class="hidden bg-white p-16 max-w-[21cm] mx-auto shadow-2xl mt-10 mb-20 relative text-black">
        
        <!-- Controls (No Print) -->
        <div class="no-print absolute top-4 right-4 flex gap-2">
            <button onclick="tutupPreview()" class="bg-slate-200 text-slate-600 px-4 py-2 rounded-lg font-bold hover:bg-slate-300 text-sm">Tutup</button>
            <button onclick="window.print()" class="bg-blue-600 text-white px-4 py-2 rounded-lg font-bold hover:bg-blue-700 flex items-center gap-2 text-sm shadow-lg">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                Cetak / PDF
            </button>
        </div>

        <!-- KOP SURAT -->
        <div class="surat-header flex items-center justify-center border-b-4 border-black pb-4 mb-8 gap-4">
            <!-- Ganti src dengan logo sekolah -->
            <img src="gambar/logo.jpg" class="w-24 h-24 object-contain">
            <div class="text-center flex-1">
                <h2 class="text-xl font-bold uppercase tracking-wide">YAYASAN PENDIDIKAN MA'ARIF NU</h2>
                <h1 class="text-3xl font-extrabold uppercase tracking-wider my-1">SMK MA'ARIF 4-5 TAMBAKBOYO</h1>
                <p class="text-sm">Jl. Raya Tambakboyo No. 123, Tuban, Jawa Timur - Kode Pos 62353</p>
                <p class="text-sm">Telp: (0356) 123456 | Email: smkmaarif45@sch.id</p>
            </div>
        </div>

        <!-- ISI SURAT -->
        <div class="text-justify leading-relaxed font-serif">
            <div class="text-center mb-8">
                <h2 class="text-xl font-bold underline editable uppercase" contenteditable="true">SURAT PERINGATAN PERTAMA (SP-1)</h2>
                <p class="editable" contenteditable="true">Nomor: 421.5/....../BK/SMK/<?php echo date('Y'); ?></p>
            </div>

            <p class="mb-2 editable" contenteditable="true">Yth. Orang Tua / Wali Murid,</p>
            <p class="mb-6 editable" contenteditable="true">Di Tempat</p>
            
            <p class="mb-4 editable" contenteditable="true">Dengan hormat,</p>
            <p class="mb-4 editable" contenteditable="true">Sehubungan dengan ketidakhadiran siswa di sekolah, kami memberitahukan bahwa:</p>
            
            <table class="w-full mb-6 ml-4">
                <tr><td class="w-40 font-bold py-1">Nama</td><td>: <span id="sp_nama"></span></td></tr>
                <tr><td class="font-bold py-1">NIS</td><td>: <span id="sp_nis"></span></td></tr>
                <tr><td class="font-bold py-1">Kelas</td><td>: <span id="sp_kelas"></span></td></tr>
                <tr><td class="font-bold py-1">Jumlah Alpha</td><td>: <b><span id="sp_jumlah"></span> Hari</b> (Pada Bulan <?php echo date('F'); ?>)</td></tr>
            </table>

            <div class="editable" contenteditable="true">
                <p class="mb-4">
                    Berdasarkan data absensi elektronik kami, siswa tersebut telah tidak hadir tanpa keterangan (Alpha) melebihi batas toleransi tata tertib sekolah.
                </p>
                <p class="mb-4">
                    Oleh karena itu, kami memberikan <b>Peringatan Pertama (SP-1)</b> dan mengharapkan Bapak/Ibu dapat membina serta mengawasi kedisiplinan putra/putrinya agar kejadian ini tidak terulang kembali. Kehadiran di sekolah sangat penting untuk kelancaran proses belajar mengajar.
                </p>
                <p class="mb-8">
                    Demikian surat peringatan ini kami sampaikan agar menjadi perhatian. Atas kerjasamanya kami ucapkan terima kasih.
                </p>
            </div>
        </div>

        <!-- TANDA TANGAN -->
        <div class="flex justify-end mt-16 text-center">
            <div class="w-64">
                <p class="editable" contenteditable="true">Tambakboyo, <?php echo date('d F Y'); ?></p>
                <p class="editable" contenteditable="true">Guru Bimbingan Konseling,</p>
                <br><br><br><br>
                <p class="font-bold underline editable" contenteditable="true">( Nama Guru BK )</p>
                <p class="editable" contenteditable="true">NIP. .........................</p>
            </div>
        </div>
    </div>

    <script>
        function bukaSurat(nama, kelas, nis, jumlah) {
            // Isi Data Otomatis
            document.getElementById('sp_nama').innerText = nama;
            document.getElementById('sp_kelas').innerText = kelas;
            document.getElementById('sp_nis').innerText = nis;
            document.getElementById('sp_jumlah').innerText = jumlah;
            
            // Tampilkan Mode Preview, Sembunyikan Dashboard
            document.querySelector('main').classList.add('hidden');
            document.querySelector('nav').classList.add('hidden');
            document.getElementById('printArea').classList.remove('hidden');
            window.scrollTo(0,0);
        }

        function tutupPreview() {
            document.querySelector('main').classList.remove('hidden');
            document.querySelector('nav').classList.remove('hidden');
            document.getElementById('printArea').classList.add('hidden');
        }
    </script>
</body>
</html>