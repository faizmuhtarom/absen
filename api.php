<?php
include 'koneksi.php';

if(isset($_GET['rfid'])) {
    $rfid = $_GET['rfid'];
    
    // Cek apakah kartu terdaftar
    $cek_siswa = mysqli_query($conn, "SELECT * FROM siswa WHERE rfid_uid = '$rfid'");
    
    if(mysqli_num_rows($cek_siswa) > 0) {
        // Simpan Log
        mysqli_query($conn, "INSERT INTO log_absensi (rfid_uid, status) VALUES ('$rfid', 'Hadir')");
        echo "BERHASIL: Data Masuk";
    } else {
        echo "GAGAL: Kartu Tidak Dikenal";
    }
}
?>