<?php
session_start();
include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

// --- KEAMANAN ---
if(!isset($_SESSION['status']) || $_SESSION['status'] != "login"){
    header("location:login.php?pesan=belum_login");
    exit;
}

$today = date('Y-m-d');
$swal_type = ""; $swal_msg = "";

// --- LOGIC: BERI IZIN ---
if (isset($_POST['buat_izin'])) {
    $rfid_siswa = $_POST['rfid_siswa'];
    $alasan = mysqli_real_escape_string($conn, $_POST['alasan']);
    $pemberi = $_SESSION['username']; 

    $cek = mysqli_query($conn, "SELECT * FROM izin_pulang WHERE rfid_uid='$rfid_siswa' AND tanggal='$today'");
    if (mysqli_num_rows($cek) > 0) {
        $swal_type = "warning"; $swal_msg = "Siswa ini sudah memiliki izin pulang hari ini.";
    } else {
        $insert = mysqli_query($conn, "INSERT INTO izin_pulang (rfid_uid, tanggal, alasan, pemberi_izin) VALUES ('$rfid_siswa', '$today', '$alasan', '$pemberi')");
        if ($insert) {
            $swal_type = "success"; $swal_msg = "Gate Pass Berhasil Dibuat!";
        } else {
            $swal_type = "error"; $swal_msg = "Gagal menyimpan data.";
        }
    }
}

// --- LOGIC: HAPUS IZIN ---
if (isset($_GET['hapus'])) {
    $id = $_GET['hapus'];
    mysqli_query($conn, "DELETE FROM izin_pulang WHERE id='$id'");
    echo "<script>window.location='gate_pass.php';</script>";
}

// --- AMBIL DATA SISWA ---
$q_siswa = mysqli_query($conn, "SELECT * FROM siswa ORDER BY nama ASC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Gate Pass - Izin Pulang</title>
    <link rel="shortcut icon" href="gambar/logo.jpg">
    
    <!-- Libraries -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #f8fafc; }
        .select2-container .select2-selection--single { height: 45px; border-radius: 0.75rem; border: 1px solid #e2e8f0; display: flex; align-items: center; }
        
        /* --- CSS KHUSUS PRINTER STRUK (THERMAL) --- */
        @media print {
            @page { margin: 0; size: 80mm auto; } /* Lebar kertas 80mm */
            
            /* Sembunyikan elemen lain */
            body * { visibility: hidden; }
            
            /* Tampilkan Area Print */
            #printArea, #printArea * { 
                visibility: visible; 
            }
            
            #printArea {
                display: block !important; /* PENTING: Agar tidak hidden saat diprint */
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                max-width: 78mm; /* Sesuaikan dengan lebar kertas */
                padding: 5px;
                font-family: 'Courier New', monospace; /* Font struk */
                font-size: 12px;
                color: black;
                background: white;
                line-height: 1.3;
            }
            
            .no-print { display: none !important; }
            
            /* Helper untuk garis putus-putus */
            .dashed-line { 
                border-bottom: 1px dashed black; 
                margin: 8px 0; 
                display: block;
                width: 100%;
            }
        }
    </style>
</head>
<body class="text-slate-800">

    <!-- Navbar (No Print) -->
    <nav class="bg-white border-b border-slate-200 p-4 fixed w-full top-0 z-50 no-print">
        <div class="max-w-5xl mx-auto flex justify-between items-center">
            <div class="flex items-center gap-3">
                <a href="admin.php" class="p-2 bg-slate-100 rounded-lg hover:bg-slate-200 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                </a>
                <h1 class="font-bold text-lg text-slate-800">Izin Keluar</h1>
            </div>
            <div class="text-sm text-slate-500"><?php echo date('d F Y'); ?></div>
        </div>
    </nav>

    <!-- Main Content (No Print) -->
    <main class="max-w-5xl mx-auto px-4 pt-24 pb-12 no-print">
         <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <!-- FORM BUAT IZIN -->
            <div class="md:col-span-1">
                <div class="bg-white p-6 rounded-2xl shadow-lg border border-slate-100 sticky top-24">
                    <h2 class="font-bold text-lg mb-4 flex items-center gap-2 text-indigo-700">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                        Buat Surat Izin
                    </h2>
                    <form method="POST" class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Pilih Siswa</label>
                            <select name="rfid_siswa" id="selectSiswa" class="w-full" required>
                                <option value="">-- Cari Nama / NIS --</option>
                                <?php while($s = mysqli_fetch_assoc($q_siswa)): ?>
                                    <option value="<?php echo $s['rfid_uid']; ?>">
                                        <?php echo $s['nama']; ?> (<?php echo $s['kelas']; ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Alasan Pulang/Keluar</label>
                            <textarea name="alasan" rows="3" required class="w-full p-3 rounded-xl bg-slate-50 border border-slate-200 focus:ring-2 focus:ring-indigo-200 outline-none text-sm" placeholder="Contoh: Sakit perut, urusan keluarga..."></textarea>
                        </div>
                        <button type="submit" name="buat_izin" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-3 rounded-xl font-bold shadow-lg shadow-indigo-200 transition">
                            Terbitkan Izin
                        </button>
                    </form>
                </div>
            </div>

            <!-- DAFTAR IZIN HARI INI -->
            <div class="md:col-span-2">
                <div class="bg-white rounded-2xl shadow-lg border border-slate-100 overflow-hidden">
                    <div class="p-6 border-b border-slate-100 flex justify-between items-center">
                        <h3 class="font-bold text-lg text-slate-800">Daftar Izin Hari Ini</h3>
                        <span class="text-xs font-mono bg-slate-100 px-2 py-1 rounded"><?php echo $today; ?></span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 text-slate-500 uppercase text-xs font-bold">
                                <tr>
                                    <th class="px-6 py-4">Nama Siswa</th>
                                    <th class="px-6 py-4">Alasan</th>
                                    <th class="px-6 py-4 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php 
                                $q_izin = mysqli_query($conn, "SELECT i.*, s.nama, s.kelas, s.nis FROM izin_pulang i JOIN siswa s ON i.rfid_uid = s.rfid_uid WHERE i.tanggal = '$today' ORDER BY i.id DESC");
                                if(mysqli_num_rows($q_izin) > 0){
                                    while($row = mysqli_fetch_assoc($q_izin)){
                                ?>
                                <tr class="hover:bg-slate-50">
                                    <td class="px-6 py-4">
                                        <div class="font-bold text-slate-800"><?php echo $row['nama']; ?></div>
                                        <div class="text-xs text-slate-400"><?php echo $row['kelas']; ?></div>
                                    </td>
                                    <td class="px-6 py-4 text-slate-600"><?php echo $row['alasan']; ?></td>
                                    <td class="px-6 py-4 text-center flex justify-center gap-2">
                                        <!-- Tombol Cetak -->
                                        <button onclick="printTicket('<?php echo $row['nama']; ?>', '<?php echo $row['kelas']; ?>', '<?php echo $row['alasan']; ?>', '<?php echo date('d/m/Y H:i'); ?>')" class="text-blue-500 hover:text-blue-700 bg-blue-50 p-2 rounded-lg transition" title="Cetak Struk">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V2h12v7"></path><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><path d="M6 14h12v8H6z"></path></svg>
                                        </button>
                                        <!-- Hapus -->
                                        <a href="gate_pass.php?hapus=<?php echo $row['id']; ?>" onclick="return confirm('Batalkan izin ini?')" class="text-red-500 hover:text-red-700 bg-red-50 p-2 rounded-lg transition" title="Hapus">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                                        </a>
                                    </td>
                                </tr>
                                <?php }} else { ?>
                                <tr><td colspan="3" class="px-6 py-8 text-center text-slate-400">Belum ada izin keluar hari ini.</td></tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- AREA CETAK STRUK (Hidden on Screen, Visible on Print) -->
    <div id="printArea" class="hidden">
        <div style="text-align: center; margin-bottom: 5px;">
            <h2 style="margin:0; font-size: 14px; font-weight:bold;">SMK Ma'arif 4-5 Tambakboyo</h2>
            <p style="margin:0; font-size: 10px;">IZIN KELUAR</p>
        </div>
        
        <div class="dashed-line"></div>
        
        <div style="font-size: 12px; margin-bottom: 5px;">
            <table style="width: 100%; border: none;">
                <tr><td style="width: 50px;">Nama</td><td>: <span id="p_nama"></span></td></tr>
                <tr><td>Kelas</td><td>: <span id="p_kelas"></span></td></tr>
                <tr><td>Tgl</td><td>: <span id="p_tgl"></span></td></tr>
            </table>
        </div>

        <div class="dashed-line"></div>

        <div style="margin-bottom: 10px;">
            <p style="margin:0; font-size: 10px; font-weight:bold;">ALASAN:</p>
            <p style="margin:0; font-size: 12px; font-style: italic;" id="p_alasan"></p>
        </div>
        
        <div class="dashed-line"></div>

        <!-- Tanda Tangan: Hanya Security -->
        <div style="text-align: center; margin-top: 15px;">
            <p style="font-size: 10px;">Guru Piket</p>
            <br><br><br>
            <p style="font-size: 10px;">( ................. )</p>
        </div>
        
        <div style="text-align: center; margin-top: 10px;">
             <p style="margin:0; font-size: 10px;">*Harap serahkan suart izin kepada Guru Piket</p>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            $('#selectSiswa').select2({ placeholder: "Cari Siswa...", width: '100%' });
        });

        <?php if($swal_type): ?>
            Swal.fire({ icon: '<?php echo $swal_type; ?>', title: 'Info', text: '<?php echo $swal_msg; ?>', confirmButtonColor: '#4f46e5' });
        <?php endif; ?>

        function printTicket(nama, kelas, alasan, tgl) {
            document.getElementById('p_nama').innerText = nama;
            document.getElementById('p_kelas').innerText = kelas;
            document.getElementById('p_alasan').innerText = alasan;
            document.getElementById('p_tgl').innerText = tgl;
            window.print();
        }
    </script>
</body>
</html>