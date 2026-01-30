<?php
// Mengambil data dari Environment Variable server
// Jika tidak ada (sedang di localhost), gunakan nilai default (root/kosong)

$host = $_ENV["MYSQLHOST"] ?? 'localhost';
$user = $_ENV["MYSQLUSER"] ?? 'root';
$pass = $_ENV["MYSQLPASSWORD"] ?? '';
$db   = $_ENV["MYSQLDATABASE"] ?? 'absen';
$port = $_ENV["MYSQLPORT"] ?? '3306';

$conn = mysqli_connect($host, $user, $pass, $db, $port);

if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}
?>