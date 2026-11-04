<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function magic_edit_json(int $status, array $payload): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SESSION['role'] ?? '') === 'siswa') {
    magic_edit_json(403, ['success' => false, 'message' => 'Fitur ini hanya tersedia untuk akun guru dan admin.']);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    magic_edit_json(405, ['success' => false, 'message' => 'Metode permintaan tidak diizinkan.']);
}
$csrf = (string)($_POST['csrf_token'] ?? '');
$sessionCsrf = (string)($_SESSION['magic_edit_csrf'] ?? '');
if ($csrf === '' || $sessionCsrf === '' || !hash_equals($sessionCsrf, $csrf)) {
    magic_edit_json(419, ['success' => false, 'message' => 'Sesi keamanan tidak valid. Muat ulang halaman lalu coba lagi.']);
}

$config = [];
$configFile = __DIR__ . '/../../config/magic_edit.local.php';
if (is_file($configFile)) {
    $loaded = require $configFile;
    if (is_array($loaded)) $config = $loaded;
}
$apiKey = trim((string)(getenv('OPENAI_API_KEY') ?: ($config['api_key'] ?? '')));
if ($apiKey === '') {
    magic_edit_json(503, [
        'success' => false,
        'message' => 'Magic Edit belum dikonfigurasi. Salin config/magic_edit.example.php menjadi config/magic_edit.local.php, lalu isi API Key OpenAI pada server.'
    ]);
}
if (!function_exists('curl_init')) {
    magic_edit_json(500, ['success' => false, 'message' => 'Ekstensi PHP cURL belum aktif. Aktifkan extension=curl pada php.ini lalu restart Apache.']);
}
if (!isset($_FILES['image']) || (int)($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    magic_edit_json(422, ['success' => false, 'message' => 'Pilih foto yang valid sebelum menjalankan Magic Edit.']);
}

$imageFile = $_FILES['image'];
if ((int)$imageFile['size'] > 20 * 1024 * 1024) {
    magic_edit_json(413, ['success' => false, 'message' => 'Ukuran foto setelah optimasi maksimal 20 MB.']);
}
$imageMime = (new finfo(FILEINFO_MIME_TYPE))->file($imageFile['tmp_name']);
$allowedMimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
if (!isset($allowedMimes[$imageMime])) {
    magic_edit_json(415, ['success' => false, 'message' => 'Format foto harus JPG, PNG, atau WebP.']);
}
$imageInfo = @getimagesize($imageFile['tmp_name']);
if (!$imageInfo || $imageInfo[0] < 64 || $imageInfo[1] < 64 || $imageInfo[0] > 4096 || $imageInfo[1] > 4096) {
    magic_edit_json(422, ['success' => false, 'message' => 'Resolusi foto tidak valid atau terlalu besar. Maksimal sisi foto 4096 piksel.']);
}

$prompt = trim((string)($_POST['prompt'] ?? ''));
if ($prompt === '' || mb_strlen($prompt) > 2000) {
    magic_edit_json(422, ['success' => false, 'message' => 'Instruksi wajib diisi dan maksimal 2.000 karakter.']);
}
$quality = (string)($_POST['quality'] ?? 'high');
if (!in_array($quality, ['low', 'medium', 'high'], true)) $quality = 'high';
$n = (int)($_POST['n'] ?? 1);
if (!in_array($n, [1, 2, 3], true)) $n = 1;

$postFields = [
    'model' => (string)($config['model'] ?? 'gpt-image-2.5-sunburst'),
    'prompt' => $prompt,
    'quality' => $quality,
    'size' => 'auto',
    'output_format' => 'png',
    'n' => (string)$n,
    'safety_identifier' => hash('sha256', 'absensi-magic-edit:' . (string)($_SESSION['akun_id'] ?? 'user')),
];

$imageName = 'magic-edit-input.' . $allowedMimes[$imageMime];
$postFields['image[]'] = new CURLFile($imageFile['tmp_name'], $imageMime, $imageName);

if (isset($_FILES['mask']) && (int)($_FILES['mask']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
    $maskFile = $_FILES['mask'];
    if ((int)$maskFile['size'] > 4 * 1024 * 1024) {
        magic_edit_json(413, ['success' => false, 'message' => 'Mask seleksi terlalu besar. Kurangi ukuran foto atau area brush, lalu coba lagi.']);
    }
    $maskMime = (new finfo(FILEINFO_MIME_TYPE))->file($maskFile['tmp_name']);
    $maskInfo = @getimagesize($maskFile['tmp_name']);
    if ($maskMime !== 'image/png' || !$maskInfo || (int)$maskInfo[0] !== (int)$imageInfo[0] || (int)$maskInfo[1] !== (int)$imageInfo[1] || (int)($maskInfo['channels'] ?? 0) !== 4) {
        magic_edit_json(422, ['success' => false, 'message' => 'Mask seleksi harus berupa PNG transparan dengan ukuran yang sama seperti foto.']);
    }
    $postFields['mask'] = new CURLFile($maskFile['tmp_name'], 'image/png', 'magic-edit-mask.png');
    $postFields['prompt'] = 'Edit hanya area transparan yang ditandai pada mask. Pertahankan area yang tidak ditandai, wajah, identitas, ekspresi, pose, dan komposisi asli kecuali instruksi secara jelas meminta perubahan. Instruksi pengguna: ' . $prompt;
}

$ch = curl_init('https://api.openai.com/v1/images/edits');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POSTFIELDS => $postFields,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $apiKey],
    CURLOPT_CONNECTTIMEOUT => 30,
    CURLOPT_TIMEOUT => max(60, min(300, (int)($config['timeout'] ?? 240))),
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
]);
$response = curl_exec($ch);
$curlError = curl_error($ch);
$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false) {
    magic_edit_json(502, ['success' => false, 'message' => 'Tidak dapat menghubungi layanan AI. Periksa koneksi internet server dan konfigurasi SSL. ' . $curlError]);
}
$data = json_decode($response, true);
if ($status < 200 || $status >= 300) {
    $apiMessage = is_array($data) ? (string)($data['error']['message'] ?? '') : '';
    if ($status === 401) $apiMessage = 'API Key OpenAI tidak valid atau tidak memiliki akses.';
    if ($status === 429) $apiMessage = 'Kuota atau batas permintaan API sedang tercapai. Periksa billing dan limit akun OpenAI.';
    if ($apiMessage === '') $apiMessage = 'Layanan AI mengembalikan respons yang tidak berhasil (HTTP ' . $status . ').';
    magic_edit_json(502, ['success' => false, 'message' => $apiMessage]);
}
$items = is_array($data) ? ($data['data'] ?? []) : [];
$images = [];
foreach ($items as $item) {
    if (!empty($item['b64_json'])) {
        $images[] = ['b64_json' => $item['b64_json'], 'mime' => 'image/png'];
    }
}
if (!$images) {
    magic_edit_json(502, ['success' => false, 'message' => 'AI tidak mengembalikan gambar. Coba sederhanakan instruksi atau ulangi proses.']);
}
magic_edit_json(200, ['success' => true, 'images' => $images]);
