<?php
$files = [
    'https://raw.githubusercontent.com/PHPMailer/PHPMailer/master/src/PHPMailer.php' => 'PHPMailer.php',
    'https://raw.githubusercontent.com/PHPMailer/PHPMailer/master/src/SMTP.php'      => 'SMTP.php',
    'https://raw.githubusercontent.com/PHPMailer/PHPMailer/master/src/Exception.php'  => 'Exception.php',
];

foreach ($files as $url => $file) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_USERAGENT, 'PHP/downloader');
    $data = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($data && $code === 200) {
        file_put_contents(__DIR__ . '/phpmailer/' . $file, $data);
        echo "Downloaded: $file\n";
    } else {
        echo "FAILED: $file (HTTP $code) $err\n";
    }
}
echo "Done.\n";
