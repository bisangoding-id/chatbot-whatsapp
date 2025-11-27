<title>Form Registrasi</title>
<link rel="stylesheet" href="style.css">

<div class="container">
    <h2>Registrasi Member</h2>
  <form action="" method="POST">
        <input type="text" name="nama" placeholder="Nama lengkap" required>
        <input type="email" name="email" placeholder="Email aktif" required>
        <input type="text" name="wa" placeholder="Nomor WhatsApp (08xxxx)" required>
        <button type="submit" name="kirim">Kirim</button>
    </form>
</div>



<?php
$koneksi = new mysqli("localhost", "root", "", "db_regis");

if (isset($_POST['kirim'])) {

  $nama     = htmlspecialchars($_POST['nama']);
  $email    = htmlspecialchars($_POST['email']);
  $wa_input = htmlspecialchars($_POST['wa']);

    $wa = ltrim($wa_input, "+");
    if (substr($wa, 0, 1) === "0") {
        $wa = "62" . substr($wa, 1);
    }

    $stmt = $koneksi->prepare("INSERT INTO member (nama, email, wa, tanggal) 
            VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("sss", $nama, $email, $wa_input);
    $stmt->execute();
    $stmt->close();

    $token = ""; // Seusaikan tokennya

$pesan = "
Halo $nama, terima kasih sudah registrasi! 

Berikut data yang Anda kirim:
   
Nama     : $nama
Email    : $email
Nomor WA : $wa_input

Admin akan segera menghubungi untuk info selanjutnya. ";

$data = [
        'target'  => $wa,
        'message' => $pesan,
];

    $curl = curl_init(); // 
    curl_setopt_array($curl, [ 
        CURLOPT_URL => "https://api.fonnte.com/send", 
        CURLOPT_RETURNTRANSFER => true, 
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_HTTPHEADER => ["Authorization: $token"]
    ]);

    $response = curl_exec($curl);
    curl_close($curl);

    file_put_contents("log_fonnte.txt", date("Y-m-d H:i:s") . 
    " | $wa | $response\n", FILE_APPEND);

    $res = json_decode($response, true);

    // Notifikasi
    if (isset($res["status"]) && $res["status"] == 1) {
        echo "<div class='success'>Registrasi berhasil! 
        Pesan WhatsApp telah dikirim ke <b>$wa_input</b>.</div>";
    } else {
        echo "<div class='error'>WA gagal dikirim. 
        Namun data tersimpan di database.</div>";
    }
}

?>

