<?php

/*
-------------------------------------------------------
1. Terima data setelah user kirim pesan
-------------------------------------------------------
*/

$input = file_get_contents("php://input"); // Ambil data mentah JSON dari API
file_put_contents("debug_input.txt", $input . "\n\n", FILE_APPEND); // log data mentah ke file untuk debugging

$data = json_decode($input, true) ?? []; // ubah json ke array associative bila tidak ada data jadikan array kosong

$pengirim = $data['sender'] ?? $data['pengirim'] ?? $data['from'] ?? ''; // nomor pengirim
$pesan    = $data['pesan'] ?? $data['message'] ?? ''; // isi pesan
$namaWA   = $data['name'] ?? ''; // nama  pengirim


// Jika file ini dijalankan langsung dari localhost

if(empty($input)){
    echo "Webhook berjalan, namun tidak ada data masuk !";
    exit;
}

// Jika tidak ada kata kunci (nama, email, wa) tidak perlu diproses

if (!preg_match('/nama\s*:|email\s*:|wa\s*:|no\.?wa\s*:/i', $pesan)) {
    exit("IGNORE");
}



/*
------------------------------
2. Parsing pesan format bebas
------------------------------

Formatnya bebas, asalkan ada kata kunci 
dan dipisah dengan ":"

Contoh:
Untuk Registrasi Member, silahkan lengkapi data berikut:
Nama  : Ikmal Maulana
Email : ikmal@bisangoding.id
No.WA : 08123456789

*/

$nama = $email = $wa_user = ''; // inisialisasi variabel

$baris = explode("\n", $pesan); // memecah pesan menjadi baris-baris

$mapping = [ // mapping kata kunci ke variabel
    'nama' => 'nama',
    'email' => 'email',
    'wa' => 'wa_user',
    'no.wa' => 'wa_user',
    'nomor wa' => 'wa_user'
];

foreach ($baris as $b) { // proses setiap baris
    $b = trim($b); // hanya bersihkan spasi, jangan ubah huruf user
    $lineLower = strtolower($b); // ubah kata kunci ke huruf kecil untuk pencocokan

    foreach ($mapping as $key => $var) { // cek setiap kata kunci
        
        if (strpos($lineLower, strtolower($key)) !== false && strpos($b, ':') !== false) {
            $parts = explode(':', $b, 2); // pecah berdasarkan ":"
            $value = trim($parts[1] ?? ''); // ambil nilai setelah ":"

            if (!empty($value)) {
                $$var = $value; // simpan ke variabel dinamis (kata kunci dan variabel)
                break;
            }
        }
    }
}


// Jika user tidak isi nomor WA → pakai nomor si pengirim
if (empty($wa_user)) {
    $wa_user = $pengirim;
}

/*
 -------------------------------------------------
 3. Konversi nomor WA ke 62 (Format Internasional)
 -------------------------------------------------
*/

$wa_user = preg_replace('/\D/', '', $wa_user); // Hapus karakter non angka

if (substr($wa_user, 0, 1) == '0') { // jika nomor diawali 0
    $wa_user = "62" . substr($wa_user, 1); // ganti 0 dengan 62
}

/*
-------------------------------
4. Simpan ke Database
-------------------------------
*/

$koneksi = new mysqli("localhost", "root", "", "db_regis"); //  konfigurasi database 

if ($koneksi->connect_error) {
    file_put_contents("db_error.txt", $koneksi->connect_error, FILE_APPEND); // log error koneksi database
    exit("DB ERROR");
}

$query = $koneksi->prepare("INSERT INTO member (nama, email, wa, tanggal) VALUES (?, ?, ?, NOW())"); // siapkan query insert
$query->bind_param("sss", $nama, $email, $wa_user); // bind (pasang) parameter ke query
$query->execute(); // eksekusi query
$query->close(); // tutup statement
$koneksi->close(); // tutup koneksi database

/*
------------------
5. Balasan ke User 
------------------
*/

$token = "*********"; // Sesuaikan tokennya

$balasan = "
Terima kasih, *$nama* registrasi member anda berhasil!

Berikut data yang kami terima

Nama : $nama
Email : $email
No.WA : $wa_user

Admin akan segera menghubungi anda untuk proses lebih lanjut.

Terima kasih.
*www.bisangoding.id*
";

$dataSend = [
    'target' => $pengirim, // nomor tujuan pengirim pesan
    'message' => $balasan // isi pesan balasan
];

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => "https://api.fonnte.com/send", // API endpoint Fonnte
    CURLOPT_RETURNTRANSFER => true, // 
    CURLOPT_POST => true, // metode POST
    CURLOPT_POSTFIELDS => http_build_query($dataSend), // data POST
    CURLOPT_HTTPHEADER => ["Authorization: $token"] // header otorisasi dengan token
]);

$response = curl_exec($curl); // eksekusi request
curl_close($curl); // tutup sesi curl

// log respons curl
file_put_contents("curl_log.txt", $response . "\n", FILE_APPEND); // histori respons curl


?>
