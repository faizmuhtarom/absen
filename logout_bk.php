<?php
session_start();
unset($_SESSION['id_bk']);
unset($_SESSION['nama_bk']);
unset($_SESSION['status_bk']);
session_destroy();
header("location:login_bk.php");
?>