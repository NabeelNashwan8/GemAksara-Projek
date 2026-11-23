<?php
// =====================================================================
// NUSANTARA: satu file untuk XAMPP (PHP + MySQL)
// Letakkan file ini di C:\xampp\htdocs\nusantara\index.php
// Buka: http://localhost/nusantara/
// =====================================================================

// ---------- PENGATURAN (ubah sesuai kebutuhan) ----------
const DB_HOST = '127.0.0.1';
const DB_NAME = 'nusantara';
const DB_USER = 'root';
const DB_PASS = '';                       // XAMPP default: kosong
const ADMIN_EMAIL = 'admin@nusantara.id';  // akun admin
const ADMIN_PASSWORD = 'ganti-kata-sandi-admin'; // minimal 8 karakter
const DEBUG = true;                       // true: tampilkan detail error. Ubah false saat produksi.
const MAX_IMAGE_BYTES = 2 * 1024 * 1024;  // 2 MB
const UPLOAD_DIR = __DIR__ . '/uploads';
const TYPES = ['article', 'comic', 'video'];
const GENRES = [
    'article' => ['Tradisi', 'Sejarah', 'Kuliner', 'Kerajinan', 'Musik & Tari', 'Arsitektur'],
    'comic'   => ['Legenda', 'Petualangan', 'Fantasi', 'Sejarah', 'Aksi'],
    'video'   => ['Dokumenter', 'Wawancara Seniman', 'Festival & Upacara', 'Tutorial Kerajinan'],
];

session_start();
date_default_timezone_set('Asia/Jakarta');

// ---------- DATABASE ----------
function pdo_conn(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;
    $opt = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
    // Buat database kalau belum ada, lalu sambung ke dalamnya.
    $root = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, $opt);
    $root->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, $opt);
    return $pdo;
}

function q(string $sql, array $args = []): PDOStatement
{
    $st = pdo_conn()->prepare($sql);
    $st->execute($args);
    return $st;
}

function bootstrap(): void
{
    static $done = false;
    if ($done) return;
    $done = true;

    q("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(80) NOT NULL,
        email VARCHAR(120) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        role VARCHAR(10) NOT NULL DEFAULT 'user',
        created_at DATETIME NOT NULL
    )");
    q("CREATE TABLE IF NOT EXISTS contents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type VARCHAR(10) NOT NULL,
        slug VARCHAR(100) NOT NULL,
        title VARCHAR(200) NOT NULL,
        summary TEXT NOT NULL,
        genre VARCHAR(80) NOT NULL,
        region VARCHAR(80) NULL,
        author VARCHAR(120) NOT NULL,
        read_minutes INT NULL,
        cover_color VARCHAR(10) NOT NULL,
        cover_url VARCHAR(500) NULL,
        is_premium TINYINT NOT NULL DEFAULT 0,
        status VARCHAR(12) NOT NULL DEFAULT 'draft',
        body LONGTEXT NULL,
        video_url VARCHAR(500) NULL,
        episodes LONGTEXT NULL,
        published_at DATETIME NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        UNIQUE KEY uniq_type_slug (type, slug)
    )");

    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0775, true);
    if ((int)q("SELECT COUNT(*) AS c FROM contents")->fetch()['c'] === 0) seed_contents();
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

// Menyimpan baris konten. $id null = tambah baru.
function write_row(?int $id, array $r): int
{
    $r['updated_at'] = now();
    if ($id) {
        $sets = implode(',', array_map(fn($k) => "$k=?", array_keys($r)));
        q("UPDATE contents SET $sets WHERE id=?", [...array_values($r), $id]);
        return $id;
    }
    $r['created_at'] = now();
    $cols = implode(',', array_keys($r));
    $marks = implode(',', array_fill(0, count($r), '?'));
    q("INSERT INTO contents ($cols) VALUES ($marks)", array_values($r));
    return (int)pdo_conn()->lastInsertId();
}

// Data contoh awal (sama dengan kerangka sebelumnya).
function seed_contents(): void
{
    $seed = [
        ['article', 'filosofi-batik-parang', 'Filosofi di Balik Motif Batik Parang', 'Garis diagonal yang tidak terputus sebagai simbol perjuangan dan kesinambungan.', 'Tradisi', 'Jawa Tengah', 'Ayu Kusuma', 6, '#6b2020', 0, ['Motif parang adalah salah satu motif batik tertua di Jawa, dikenal dengan garis diagonal yang tidak pernah terputus.', 'Dahulu, motif ini hanya dikenakan kalangan keraton karena maknanya yang dianggap sakral.', 'Kini, motif parang beradaptasi ke berbagai media tanpa kehilangan akar filosofinya.'], null, null],
        ['article', 'ulos-batak', 'Ulos: Kain yang Menyatukan Marga', 'Dari kelahiran hingga pernikahan, ulos hadir di setiap fase hidup orang Batak.', 'Kerajinan', 'Sumatera Utara', 'Sari Lubis', 7, '#b25a33', 1, ['Ulos diberikan sebagai tanda kasih, doa, dan restu.', 'Setiap jenis ulos memiliki fungsi dan aturan pemberiannya sendiri.'], null, null],
        ['article', 'kecak-seratus-suara', 'Kecak: Nyanyian Seratus Suara', 'Tari tanpa gamelan yang lahir dari ritual sanghyang di Bali.', 'Musik & Tari', 'Bali', 'Made Wirawan', 5, '#1f6f63', 0, ['Kecak mengandalkan suara paduan ratusan penari pria tanpa iringan gamelan.'], null, null],
        ['comic', 'nanto', 'Nanto', 'Pahlawan muda menjaga keseimbangan alam nusantara bersama roh-roh penjaga.', 'Legenda', 'Nusantara', 'Studio Nusa', null, '#6b2020', 1, null, null, [['Awal Perjalanan', 0], ['Roh Penjaga Hutan', 0], ['Jejak Sang Leluhur', 1], ['Badai di Selat Nusa', 1], ['Pertarungan di Puncak Merapi', 1]]],
        ['comic', 'si-kancil-baru', 'Si Kancil Baru', 'Kancil modern berpetualang di hutan kota.', 'Petualangan', 'Sumatera', 'Rudi Hartono', null, '#1f6f63', 0, null, null, [['Hutan Kota', 0], ['Teman Baru', 0]]],
        ['video', 'proses-membatik', 'Proses Membatik Tradisional', 'Dari malam hingga pewarnaan, langkah demi langkah.', 'Dokumenter', 'Jawa Tengah', 'Tim Nusantara', 12, '#6b2020', 0, null, null, null],
        ['video', 'gamelan-harmoni', 'Gamelan Jawa: Harmoni Nusantara', 'Wawancara dengan pengrawit senior di Yogyakarta.', 'Wawancara Seniman', 'Yogyakarta', 'Tim Nusantara', 18, '#26334d', 1, null, null, null],
        ['video', 'tari-saman', 'Tari Saman dari Aceh', 'Kekompakan ratusan tangan dalam satu irama.', 'Festival & Upacara', 'Aceh', 'Tim Nusantara', 10, '#b25a33', 0, null, null, null],
    ];
    foreach ($seed as [$type, $slug, $title, $summary, $genre, $region, $author, $mins, $color, $premium, $body, $video, $episodes]) {
        $eps = null;
        if ($episodes !== null) {
            $eps = json_encode(array_map(fn($e) => ['title' => $e[0], 'isPremium' => (bool)$e[1], 'pages' => []], $episodes), JSON_UNESCAPED_UNICODE);
        }
        write_row(null, [
            'type' => $type, 'slug' => $slug, 'title' => $title, 'summary' => $summary,
            'genre' => $genre, 'region' => $region, 'author' => $author, 'read_minutes' => $mins,
            'cover_color' => $color, 'cover_url' => null, 'is_premium' => $premium ? 1 : 0,
            'status' => 'published',
            'body' => $body !== null ? json_encode($body, JSON_UNESCAPED_UNICODE) : null,
            'video_url' => $video, 'episodes' => $eps, 'published_at' => now(),
        ]);
    }
}

// ---------- HELPER ----------
function jout($data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function fail(string $msg, int $code = 400): void
{
    jout(['error' => $msg], $code);
}

function json_body(): array
{
    $d = json_decode((string)file_get_contents('php://input'), true);
    return is_array($d) ? $d : [];
}

function req_text(array $in, string $key, string $label, int $min, int $max): string
{
    $v = trim((string)($in[$key] ?? ''));
    if ($v === '') fail("$label wajib diisi.");
    if (mb_strlen($v) < $min) fail("$label minimal $min karakter.");
    if (mb_strlen($v) > $max) fail("$label maksimal $max karakter.");
    return $v;
}

// Tautan yang diizinkan: gambar hasil unggahan (uploads/...) atau https://.
function safe_url($value, string $label): ?string
{
    $v = trim((string)($value ?? ''));
    if ($v === '') return null;
    if (preg_match('#^uploads/[a-f0-9]{32}\.(jpg|png|webp)$#', $v)) return $v;
    if (preg_match('#^https://\S+$#', $v) && strlen($v) <= 500) return $v;
    fail("$label harus berupa gambar hasil unggahan atau tautan https://.");
    return null;
}

function slugify(string $s): string
{
    $s = strtolower($s);
    $s = trim(preg_replace('/[^a-z0-9]+/', '-', $s), '-');
    return substr($s !== '' ? $s : 'konten', 0, 80);
}

// Mengubah baris database menjadi data untuk JavaScript (nama kolom camelCase).
function to_content(array $r, bool $full = true): array
{
    $out = [
        'id' => (int)$r['id'], 'type' => $r['type'], 'slug' => $r['slug'], 'title' => $r['title'],
        'summary' => $r['summary'], 'genre' => $r['genre'], 'region' => $r['region'], 'author' => $r['author'],
        'readMinutes' => $r['read_minutes'] !== null ? (int)$r['read_minutes'] : null,
        'coverColor' => $r['cover_color'], 'coverUrl' => $r['cover_url'],
        'isPremium' => (bool)$r['is_premium'], 'status' => $r['status'], 'publishedAt' => $r['published_at'],
    ];
    if ($full) {
        $out['body'] = $r['body'] ? json_decode($r['body'], true) : [];
        $out['videoUrl'] = $r['video_url'];
        $out['episodes'] = $r['episodes'] ? json_decode($r['episodes'], true) : [];
    }
    return $out;
}

// Versi publik: isi premium dikunci. Belum ada langganan, jadi selalu terkunci.
function public_detail(array $c): array
{
    if ($c['type'] === 'comic') {
        foreach ($c['episodes'] as &$ep) {
            if (!empty($ep['isPremium'])) {
                $ep['pages'] = [];
                $ep['locked'] = true;
            }
        }
        unset($ep);
    } elseif ($c['isPremium']) {
        $c['body'] = [];
        $c['videoUrl'] = null;
        $c['locked'] = true;
    }
    return $c;
}

// ---------- AUTH ----------
function current_user(): ?array
{
    $id = $_SESSION['uid'] ?? null;
    if (!$id) return null;
    return q("SELECT id, name, email, role FROM users WHERE id=?", [$id])->fetch() ?: null;
}

function public_user(array $u): array
{
    return ['id' => (int)$u['id'], 'name' => $u['name'], 'email' => $u['email'], 'role' => $u['role'], 'isSubscribed' => false];
}

function require_admin(): array
{
    $u = current_user();
    if (!$u) fail('Silakan masuk terlebih dahulu.', 401);
    if ($u['role'] !== 'admin') fail('Hanya admin yang boleh mengakses halaman ini.', 403);
    return $u;
}

// Jika email dan kata sandi sama dengan pengaturan ADMIN_*, akun admin dibuat atau diperbarui.
// Jadi mengganti ADMIN_PASSWORD di file ini langsung berlaku saat masuk.
function admin_login_sync(string $email, string $password): void
{
    if ($email !== strtolower(trim(ADMIN_EMAIL)) || $password !== ADMIN_PASSWORD) return;
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $u = q("SELECT id FROM users WHERE email=?", [$email])->fetch();
    if ($u) {
        q("UPDATE users SET role='admin', password_hash=? WHERE id=?", [$hash, $u['id']]);
    } else {
        q("INSERT INTO users (name, email, password_hash, role, created_at) VALUES ('Admin', ?, ?, 'admin', ?)", [$email, $hash, now()]);
    }
}

// ---------- KONTEN (ADMIN) ----------
function type_param(string $t): string
{
    if (!in_array($t, TYPES, true)) fail('Jenis konten tidak valid.');
    return $t;
}

function unique_slug(string $type, string $base, ?int $exceptId): string
{
    $slug = $base;
    $n = 2;
    while (true) {
        $r = q("SELECT id FROM contents WHERE type=? AND slug=? AND id<>?", [$type, $slug, $exceptId ?? 0])->fetch();
        if (!$r) return $slug;
        $slug = $base . '-' . $n++;
    }
}

// Validasi input dari formulir admin, lalu simpan. Draf boleh belum lengkap, terbit harus lengkap.
function save_content(?int $id, array $in): int
{
    $type = (string)($in['type'] ?? '');
    if (!in_array($type, TYPES, true)) fail('Jenis konten tidak valid.');
    $status = (($in['status'] ?? 'draft') === 'published') ? 'published' : 'draft';

    $title = req_text($in, 'title', 'Judul', 3, 200);
    $summary = req_text($in, 'summary', 'Ringkasan', 5, 1000);
    $genre = req_text($in, 'genre', 'Genre', 1, 80);
    $author = req_text($in, 'author', 'Penulis', 2, 120);
    $region = trim((string)($in['region'] ?? '')) ?: null;
    $mins = ($in['readMinutes'] ?? '') === '' || !isset($in['readMinutes']) ? null : max(1, min(600, (int)$in['readMinutes']));
    $color = preg_match('/^#[0-9a-fA-F]{6}$/', (string)($in['coverColor'] ?? '')) ? $in['coverColor'] : '#8b2e2e';
    $cover = safe_url($in['coverUrl'] ?? null, 'Sampul');
    $premium = !empty($in['isPremium']) ? 1 : 0;

    $body = null;
    $video = null;
    $episodes = null;
    if ($type === 'article') {
        $raw = str_replace("\r", '', (string)($in['body'] ?? ''));
        $paras = array_values(array_filter(array_map('trim', preg_split('/\n\s*\n/', $raw)), fn($p) => $p !== ''));
        $body = json_encode($paras, JSON_UNESCAPED_UNICODE);
        if ($status === 'published' && !$paras) fail('Artikel yang diterbitkan harus punya isi.');
    }
    if ($type === 'video') {
        $video = safe_url($in['videoUrl'] ?? null, 'Tautan video');
        if ($status === 'published' && !$video) fail('Video yang diterbitkan harus punya tautan video.');
    }
    if ($type === 'comic') {
        $eps = [];
        foreach ((array)($in['episodes'] ?? []) as $ep) {
            $pages = [];
            foreach ((array)($ep['pages'] ?? []) as $p) {
                $pages[] = safe_url($p, 'Halaman komik');
            }
            $eps[] = [
                'title' => mb_substr(trim((string)($ep['title'] ?? 'Episode')), 0, 120) ?: 'Episode',
                'isPremium' => !empty($ep['isPremium']),
                'pages' => array_values(array_filter($pages)),
            ];
        }
        if ($status === 'published' && !$eps) fail('Komik yang diterbitkan harus punya minimal satu episode.');
        $episodes = json_encode($eps, JSON_UNESCAPED_UNICODE);
    }

    $row = [
        'type' => $type, 'slug' => unique_slug($type, slugify((string)($in['slug'] ?? '') ?: $title), $id),
        'title' => $title, 'summary' => $summary, 'genre' => $genre, 'region' => $region, 'author' => $author,
        'read_minutes' => $mins, 'cover_color' => $color, 'cover_url' => $cover, 'is_premium' => $premium,
        'status' => $status, 'body' => $body, 'video_url' => $video, 'episodes' => $episodes,
        'published_at' => $status === 'published' ? now() : null,
    ];
    // Tanggal terbit tidak diubah bila konten sudah pernah terbit.
    if ($id && $status === 'published') {
        $old = q("SELECT published_at FROM contents WHERE id=?", [$id])->fetch();
        if ($old && $old['published_at']) $row['published_at'] = $old['published_at'];
    }
    return write_row($id ?: null, $row);
}

// ---------- UNGGAHAN GAMBAR ----------
function handle_upload(array $in): array
{
    $data = (string)($in['dataUrl'] ?? '');
    if (!preg_match('#^data:image/(jpeg|png|webp);base64,(.+)$#s', $data, $m)) {
        fail('Format gambar harus JPG, PNG, atau WebP.');
    }
    $bin = base64_decode($m[2], true);
    if ($bin === false) fail('Gambar tidak bisa dibaca.');
    if (strlen($bin) > MAX_IMAGE_BYTES) fail('Ukuran gambar maksimal 2 MB.');

    // Periksa isi file dari header-nya, bukan hanya dari tipe yang dikirim.
    $ext = null;
    if (str_starts_with($bin, "\xFF\xD8\xFF")) $ext = 'jpg';
    elseif (str_starts_with($bin, "\x89PNG\r\n\x1a\n")) $ext = 'png';
    elseif (substr($bin, 0, 4) === 'RIFF' && substr($bin, 8, 4) === 'WEBP') $ext = 'webp';
    if (!$ext) fail('Isi file bukan JPG, PNG, atau WebP.');

    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0775, true);
    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    file_put_contents(UPLOAD_DIR . '/' . $name, $bin);
    return ['url' => 'uploads/' . $name];
}

// ---------- ROUTER API ----------
// Semua permintaan API lewat index.php?api=NAMA
function dispatch(string $api): void
{
    bootstrap();
    $write = $_SERVER['REQUEST_METHOD'] === 'POST';

    switch ($api) {
        case 'genres':
            jout(GENRES);

        case 'contents': // konten publik
            $type = type_param((string)($_GET['type'] ?? ''));
            $where = ["status='published'", 'type=?'];
            $args = [$type];
            if (($g = (string)($_GET['genre'] ?? '')) !== '') { $where[] = 'genre=?'; $args[] = $g; }
            if (($s = trim((string)($_GET['q'] ?? ''))) !== '') {
                $where[] = '(title LIKE ? OR summary LIKE ?)';
                $args[] = "%$s%";
                $args[] = "%$s%";
            }
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = min(50, max(1, (int)($_GET['limit'] ?? 12)));
            $sql = implode(' AND ', $where);
            $total = (int)q("SELECT COUNT(*) AS c FROM contents WHERE $sql", $args)->fetch()['c'];
            $rows = q("SELECT * FROM contents WHERE $sql ORDER BY published_at DESC, id DESC LIMIT $limit OFFSET " . (($page - 1) * $limit), $args)->fetchAll();
            jout(['items' => array_map(fn($r) => to_content($r, false), $rows), 'total' => $total, 'page' => $page, 'limit' => $limit]);

        case 'content': // detail publik
            $type = type_param((string)($_GET['type'] ?? ''));
            $row = q("SELECT * FROM contents WHERE type=? AND slug=? AND status='published'", [$type, (string)($_GET['slug'] ?? '')])->fetch();
            if (!$row) fail('Konten tidak ditemukan.', 404);
            jout(public_detail(to_content($row)));

        case 'register':
            if (!$write) fail('Metode harus POST.', 405);
            $in = json_body();
            $name = req_text($in, 'name', 'Nama', 2, 80);
            $email = strtolower(trim((string)($in['email'] ?? '')));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) fail('Format email tidak valid.');
            $pw = (string)($in['password'] ?? '');
            if (strlen($pw) < 8 || strlen($pw) > 100) fail('Kata sandi minimal 8 karakter.');
            if (q("SELECT id FROM users WHERE email=?", [$email])->fetch()) fail('Email sudah terdaftar.', 409);
            q("INSERT INTO users (name, email, password_hash, role, created_at) VALUES (?, ?, ?, 'user', ?)", [$name, $email, password_hash($pw, PASSWORD_DEFAULT), now()]);
            $u = q("SELECT * FROM users WHERE email=?", [$email])->fetch();
            session_regenerate_id(true);
            $_SESSION['uid'] = (int)$u['id'];
            jout(['user' => public_user($u)], 201);

        case 'login':
            if (!$write) fail('Metode harus POST.', 405);
            $in = json_body();
            $email = strtolower(trim((string)($in['email'] ?? '')));
            $pw = (string)($in['password'] ?? '');
            admin_login_sync($email, $pw);
            $u = q("SELECT * FROM users WHERE email=?", [$email])->fetch();
            if (!$u || !password_verify($pw, $u['password_hash'])) fail('Email atau kata sandi salah.', 401);
            session_regenerate_id(true);
            $_SESSION['uid'] = (int)$u['id'];
            jout(['user' => public_user($u)]);

        case 'logout':
            $_SESSION = [];
            session_destroy();
            jout(['ok' => true]);

        case 'me':
            $u = current_user();
            jout(['user' => $u ? public_user($u) : null]);

        // ----- Admin -----
        case 'admin_contents':
            require_admin();
            $where = ['1=1'];
            $args = [];
            if (($t = (string)($_GET['type'] ?? '')) !== '') { $where[] = 'type=?'; $args[] = type_param($t); }
            if (in_array($st = (string)($_GET['status'] ?? ''), ['draft', 'published'], true)) { $where[] = 'status=?'; $args[] = $st; }
            if (($s = trim((string)($_GET['q'] ?? ''))) !== '') { $where[] = '(title LIKE ? OR summary LIKE ?)'; $args[] = "%$s%"; $args[] = "%$s%"; }
            $rows = q("SELECT * FROM contents WHERE " . implode(' AND ', $where) . " ORDER BY updated_at DESC, id DESC", $args)->fetchAll();
            jout(['items' => array_map(fn($r) => to_content($r, false), $rows)]);

        case 'admin_content':
            require_admin();
            $row = q("SELECT * FROM contents WHERE id=?", [(int)($_GET['id'] ?? 0)])->fetch();
            if (!$row) fail('Konten tidak ditemukan.', 404);
            jout(to_content($row));

        case 'admin_save':
            if (!$write) fail('Metode harus POST.', 405);
            require_admin();
            $in = json_body();
            $id = isset($in['id']) && $in['id'] !== '' ? (int)$in['id'] : null;
            if ($id && !q("SELECT id FROM contents WHERE id=?", [$id])->fetch()) fail('Konten tidak ditemukan.', 404);
            $saved = save_content($id, $in);
            jout(to_content(q("SELECT * FROM contents WHERE id=?", [$saved])->fetch()));

        case 'admin_delete':
            if (!$write) fail('Metode harus POST.', 405);
            require_admin();
            $in = json_body();
            q("DELETE FROM contents WHERE id=?", [(int)($in['id'] ?? 0)]);
            jout(['ok' => true]);

        case 'admin_upload':
            if (!$write) fail('Metode harus POST.', 405);
            require_admin();
            jout(handle_upload(json_body()), 201);

        default:
            fail("Endpoint $api tidak ditemukan.", 404);
    }
}

if (isset($_GET['api'])) {
    try {
        dispatch((string)$_GET['api']);
    } catch (Throwable $e) {
        error_log($e->getMessage());
        fail(DEBUG ? 'Error server: ' . $e->getMessage() : 'Terjadi kesalahan pada server.', 500);
    }
}
?><!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>GemAksara</title>
<style>
  :root {
    --bg: #faf3e7; --card: #fff; --maroon: #8b2e2e; --maroon-dark: #6b2020; --gold: #c49b2d;
    --dark: #2b1b12; --gray: #736457; --gray-light: #a09282; --teal: #1f6f63; --navy: #26334d;
    --cream: #f0e5ce; --line: #e4d8c5; --radius: 12px;
  }
  @media (prefers-color-scheme: dark) { :root:not([data-theme="light"]) { --bg: #1d1510; } }
  * { box-sizing: border-box; }
  html, body { margin: 0; }
  body { font-family: "Inter", system-ui, -apple-system, sans-serif; background: var(--bg); color: var(--dark); line-height: 1.5; min-height: 100vh; }
  a { color: inherit; text-decoration: none; }
  button, input, select, textarea { font: inherit; }
  h1, h2, h3, h4 { margin: 0; line-height: 1.25; }
  p { margin: 0; }
  img { max-width: 100%; display: block; }
  .container { max-width: 1200px; margin: 0 auto; padding: 0 16px; }
  .section { padding: 36px 0; }
  .section-title { font-size: 24px; font-weight: 700; margin-bottom: 18px; }
  .page-title { font-size: 30px; font-weight: 700; margin: 8px 0 22px; }
  .row { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; }
  .stack { display: grid; gap: 14px; }
  .muted { color: var(--gray); }
  .muted-light { color: var(--gray-light); font-size: 13px; }
  .eyebrow { color: var(--gold); font-size: 13px; font-weight: 600; letter-spacing: 2px; text-transform: uppercase; }
  .empty { color: var(--gray); padding: 28px 0; }

  /* Header & footer */
  .site-header { position: sticky; top: 0; z-index: 10; background: var(--bg); border-bottom: 1px solid var(--line); }
  .site-header__inner { max-width: 1200px; margin: 0 auto; padding: 16px; display: flex; align-items: center; justify-content: space-between; gap: 20px; flex-wrap: wrap; }
  .logo { font-size: 22px; font-weight: 700; letter-spacing: .5px; }
  .logo span { color: var(--maroon); }
  .nav { display: flex; gap: 22px; font-size: 15px; font-weight: 500; flex-wrap: wrap; }
  .nav a.active { color: var(--maroon); font-weight: 600; }
  .actions { display: flex; gap: 12px; align-items: center; font-size: 14px; }
  .site-footer { margin-top: 48px; border-top: 1px solid var(--line); padding: 24px 16px; text-align: center; color: var(--gray-light); font-size: 13px; }

  /* Tombol, chip, badge */
  .btn { display: inline-flex; align-items: center; justify-content: center; border: 0; border-radius: 8px; padding: 11px 22px; font-size: 15px; font-weight: 600; background: var(--maroon); color: #fff; cursor: pointer; text-decoration: none; }
  .btn:hover { background: var(--maroon-dark); }
  .btn:disabled { opacity: .5; cursor: not-allowed; }
  .btn--small { padding: 8px 14px; font-size: 14px; }
  .btn--outline { background: transparent; color: var(--dark); box-shadow: inset 0 0 0 1.2px var(--dark); }
  .btn--outline:hover { background: var(--cream); }
  .btn--danger { background: transparent; color: #a33; box-shadow: inset 0 0 0 1.2px #a33; }
  .chip { border: 1.2px solid var(--line); background: #fff; color: var(--dark); border-radius: 999px; padding: 8px 16px; font-size: 14px; cursor: pointer; }
  .chip--selected { background: var(--maroon); border-color: var(--maroon); color: #fff; font-weight: 600; }
  .badge { display: inline-block; background: var(--cream); color: var(--maroon); border-radius: 999px; padding: 4px 11px; font-size: 12px; font-weight: 600; }
  .badge--premium { background: var(--gold); color: #fff; }
  .status { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
  .status--published { background: var(--teal); color: #fff; }
  .status--draft { background: var(--cream); color: var(--gray); }

  /* Hero & kartu */
  .hero { padding: 56px 0 24px; }
  .hero h1 { font-size: 40px; font-weight: 700; margin: 14px 0 16px; }
  .hero p { color: var(--gray); font-size: 17px; max-width: 560px; margin-bottom: 22px; }
  .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 24px 20px; }
  .card { display: block; }
  .card__cover { height: 170px; border-radius: var(--radius); margin-bottom: 10px; position: relative; overflow: hidden; }
  .card__cover img { width: 100%; height: 100%; object-fit: cover; }
  .card__cover .badge { position: absolute; top: 10px; right: 10px; }
  .card h3 { font-size: 16px; font-weight: 600; }
  .card p { margin-top: 4px; font-size: 13px; color: var(--gray); }
  .card__meta { font-size: 12px; color: var(--gray-light); margin-top: 6px; }
  .filters { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 18px; }
  .search, .select, .field input, .field select, .field textarea, .form input, .form textarea {
    padding: 11px 13px; border: 1.2px solid var(--line); border-radius: 8px; background: #fff; font-size: 14px; width: 100%;
  }
  .search { max-width: 340px; }
  .select { width: auto; }

  /* Detail */
  .narrow { max-width: 760px; margin: 0 auto; }
  .detail-cover { height: 300px; border-radius: 16px; margin: 20px 0; overflow: hidden; }
  .detail-cover img { width: 100%; height: 100%; object-fit: cover; }
  .article-title { font-size: 32px; font-weight: 700; margin: 10px 0 14px; }
  .byline { color: var(--gray-light); font-size: 13px; margin-bottom: 22px; }
  .article-body p { font-size: 17px; margin-bottom: 18px; }
  .paywall { border: 1.5px solid var(--gold); background: #fff; border-radius: 16px; padding: 26px; text-align: center; margin: 24px 0; display: grid; gap: 12px; justify-items: center; }
  .player { width: 100%; border-radius: 16px; background: #000; margin-bottom: 20px; }
  .episodes { background: var(--line); border-radius: var(--radius); overflow: hidden; display: grid; gap: 1px; margin: 16px 0 32px; }
  .episode { background: #fff; display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 18px; }
  .episode small { display: block; color: var(--gray-light); font-size: 12px; }
  .pages img { width: 100%; border-radius: 10px; margin-bottom: 12px; }

  /* Langganan */
  .price-card { max-width: 420px; margin: 24px auto; background: #fff; border: 1.5px solid var(--gold); border-radius: 20px; padding: 32px; text-align: center; }
  .price { font-size: 38px; font-weight: 700; color: var(--maroon); margin: 10px 0 16px; }
  .price small { font-size: 15px; color: var(--gray); font-weight: 400; }
  .benefits { list-style: none; padding: 0; margin: 0 0 20px; text-align: left; display: grid; gap: 10px; font-size: 14px; }
  .benefits li::before { content: "✓"; display: inline-grid; place-items: center; width: 18px; height: 18px; margin-right: 10px; border-radius: 50%; background: var(--teal); color: #fff; font-size: 11px; }
  .method-group { margin: 22px 0; }
  .method-group h4 { font-size: 14px; color: var(--gray); margin-bottom: 8px; }
  .method { background: #fff; border: 1px solid var(--line); border-radius: 10px; padding: 12px 16px; margin-bottom: 8px; font-weight: 600; font-size: 15px; }

  /* Form */
  .form { max-width: 380px; margin: 40px auto; background: #fff; border: 1px solid var(--line); border-radius: 16px; padding: 28px; display: grid; gap: 12px; }
  .error { color: var(--maroon); font-size: 14px; }
  .alert { padding: 12px 16px; border-radius: 8px; font-size: 14px; margin: 0 0 14px; }
  .alert--ok { background: #e6f2ef; color: var(--teal); }
  .alert--error { background: #f8e5e5; color: var(--maroon); }

  /* Admin */
  .admin-card { background: #fff; border: 1px solid var(--line); border-radius: 16px; padding: 22px; display: grid; gap: 14px; margin-bottom: 18px; }
  .admin-card h3 { font-size: 17px; }
  .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
  .field { display: grid; gap: 6px; font-size: 14px; font-weight: 500; }
  .field--wide { grid-column: 1 / -1; }
  .field--check { display: flex; align-items: center; gap: 8px; }
  .field--check input { width: auto; }
  .field textarea, .form textarea, .admin-card > textarea { resize: vertical; }
  .cover-preview { width: 150px; height: 210px; border-radius: 12px; object-fit: cover; background: var(--cream); flex-shrink: 0; }
  .episode-card { border: 1px solid var(--line); border-radius: 12px; padding: 16px; display: grid; gap: 12px; background: var(--bg); }
  .page-thumbs { display: flex; flex-wrap: wrap; gap: 8px; }
  .page-thumb { position: relative; width: 80px; height: 112px; border-radius: 8px; overflow: hidden; border: 1px solid var(--line); }
  .page-thumb img { width: 100%; height: 100%; object-fit: cover; }
  .page-thumb button { position: absolute; top: 4px; right: 4px; width: 22px; height: 22px; border: 0; border-radius: 50%; background: rgba(0,0,0,.6); color: #fff; cursor: pointer; }
  .table-wrap { background: #fff; border: 1px solid var(--line); border-radius: 12px; overflow-x: auto; }
  .admin-table { width: 100%; border-collapse: collapse; font-size: 14px; }
  .admin-table th, .admin-table td { padding: 12px 14px; text-align: left; border-bottom: 1px solid var(--line); vertical-align: top; }
  .admin-table th { font-size: 12px; color: var(--gray); text-transform: uppercase; letter-spacing: .5px; }
  .admin-table .actions { display: flex; gap: 12px; flex-wrap: wrap; }
  .admin-table .actions a, .admin-table .actions button { background: none; border: 0; padding: 0; color: var(--maroon); font-weight: 600; cursor: pointer; font-size: 14px; }
  .admin-table .actions button.danger { color: #a33; }

  /* Alat admin di halaman publik */
  .admin-strip { background: #2b1b12; color: #fff; font-size: 14px; }
  .admin-strip .site-header__inner { padding: 8px 16px; }
  .card-tools { display: flex; gap: 14px; margin-top: 8px; font-size: 13px; }
  .card-tools a, .card-tools button { background: none; border: 0; padding: 0; color: var(--maroon); font-weight: 600; cursor: pointer; font: inherit; font-weight: 600; }
  .card-tools button { color: #a33; }
  .admin-bar { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; background: var(--cream); border-radius: 10px; padding: 10px 14px; margin-bottom: 16px; font-size: 14px; }

  @media (max-width: 720px) {
    .hero h1 { font-size: 30px; }
    .form-grid { grid-template-columns: 1fr; }
    .article-title { font-size: 26px; }
    .detail-cover { height: 220px; }
  }
</style>
</head>
<body>
<div id="app"><p class="empty">Memuat…</p></div>
<script>
// ===== Data statis =====
const TYPE_LABEL = { article: 'Artikel', comic: 'Komik', video: 'Video' };
const TYPE_PATH = { article: 'artikel', comic: 'komik', video: 'video' };
const TYPES = Object.keys(TYPE_LABEL);
const PLAN = {
  name: 'GemAksara+', price: 75000,
  benefits: ['Akses semua video dokumenter budaya', 'Baca komik eksklusif, termasuk Nanto', 'Cerita & tradisi baru setiap minggu', 'Tanpa iklan, streaming kualitas tinggi', 'Dukung langsung seniman & pengrajin lokal'],
};
const PAYMENTS = [
  { name: 'E-Wallet', methods: ['GoPay', 'OVO', 'DANA', 'ShopeePay'] },
  { name: 'Transfer Bank (Virtual Account)', methods: ['Bank BCA', 'Bank Mandiri', 'Bank BNI', 'Bank BRI'] },
  { name: 'QRIS', methods: ['QRIS'] },
  { name: 'Kartu & Gerai Retail', methods: ['Kartu Kredit / Debit', 'Indomaret', 'Alfamart'] },
];

// ===== Keadaan aplikasi =====
const $ = (s) => document.querySelector(s);
let me = null;          // pengguna yang sedang login
let GENRES = { article: [], comic: [], video: [] };
let notice = null;      // pesan sekali tampil: { kind: 'ok' | 'err', text }
let seq = 0;            // nomor render terbaru, untuk membuang hasil lama

const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const rupiah = (n) => 'Rp' + Number(n).toLocaleString('id-ID');
const coverStyle = (c) => `background:${esc(c.coverColor || '#8b2e2e')}`;
const coverImg = (c) => (c.coverUrl ? `<img src="${esc(c.coverUrl)}" alt="">` : '');

// Pemanggil API. Semua permintaan lewat index.php?api=NAMA
async function api(name, { method = 'GET', body, query = {} } = {}) {
  const params = new URLSearchParams({ api: name });
  for (const [k, v] of Object.entries(query)) if (v !== '' && v != null) params.set(k, v);
  const res = await fetch(`index.php?${params}`, {
    method,
    headers: body !== undefined ? { 'Content-Type': 'application/json' } : {},
    body: body !== undefined ? JSON.stringify(body) : undefined,
  });
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(data.error || 'Terjadi kesalahan.');
  return data;
}

// Unggah gambar: file dibaca sebagai data URL lalu dikirim ke server.
function uploadImage(file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onerror = () => reject(new Error('File tidak bisa dibaca.'));
    reader.onload = () => api('admin_upload', { method: 'POST', body: { dataUrl: reader.result } }).then(resolve, reject);
    reader.readAsDataURL(file);
  });
}

function go(path) {
  if (location.hash === path) route();
  else location.hash = path;
}

// ===== Kerangka halaman =====
function layout(inner, section = '') {
  const link = (href, label, key) => `<a href="#/${href}" class="${section === key ? 'active' : ''}">${label}</a>`;
  const right = me
    ? `${me.role === 'admin' ? '<span class="badge badge--premium">ADMIN</span><a class="btn btn--small" href="#/admin">Panel Admin</a>' : ''}<span class="muted">${esc(me.name)}</span><button class="btn btn--small btn--outline" data-act="logout">Keluar</button>`
    : '<a class="btn btn--small" href="#/masuk">Masuk</a>';
  $('#app').innerHTML = `
    <header class="site-header"><div class="site-header__inner">
      <a href="#/" class="logo">NUSA<span>NTARA</span></a>
      <nav class="nav">${link('', 'Beranda', 'home')}${link('artikel', 'Artikel', 'article')}${link('komik', 'Komik', 'comic')}${link('video', 'Video', 'video')}${link('langganan', 'Langganan', 'langganan')}</nav>
      <div class="actions">${right}</div>
    </div></header>
    ${me && me.role === 'admin' ? `
    <div class="admin-strip"><div class="site-header__inner">
      <span><strong>Mode Admin</strong> · ubah konten langsung dari halaman ini</span>
      <span class="row"><a class="btn btn--small" href="#/admin/baru">+ Tambah Konten</a><a class="btn btn--small btn--outline" style="color:#fff;box-shadow:inset 0 0 0 1.2px #fff" href="#/admin">Kelola Semua Konten</a></span>
    </div></div>` : ''}
    <main class="container">${showNotice()}${inner}</main>
    <footer class="site-footer">© 2026 GemAksara — Merayakan Warisan Budaya Indonesia</footer>`;
}

function showNotice() {
  const n = notice;
  notice = null;
  return n ? `<p class="alert alert--${n.kind === 'err' ? 'error' : 'ok'}">${esc(n.text)}</p>` : '';
}

const cardTools = (c) => (me && me.role === 'admin') ? `
  <div class="card-tools">
    <a href="#/admin/edit/${c.id}">✎ Ubah</a>
    <button type="button" data-act="delete" data-id="${c.id}" data-title="${esc(c.title)}">Hapus</button>
  </div>` : '';

const card = (c) => `
  <div class="card-wrap">
    <a class="card" href="#/${TYPE_PATH[c.type]}/${esc(c.slug)}">
      <div class="card__cover" style="${coverStyle(c)}">${coverImg(c)}${c.isPremium ? '<span class="badge badge--premium">GemAksara+</span>' : ''}</div>
      <h3>${esc(c.title)}</h3>
      <p>${esc(c.summary)}</p>
      <div class="card__meta">${esc(c.genre)} · ${esc(c.author)}</div>
    </a>
    ${cardTools(c)}
  </div>`;

// ===== Router =====
async function route() {
  const my = ++seq;
  const [a, b, c] = location.hash.replace(/^#\/?/, '').split('/').filter(Boolean).map(decodeURIComponent);
  if (a === 'admin' && (!me || me.role !== 'admin')) return go(me ? '#/' : '#/masuk');
  try {
    if (!a) return await viewHome(my);
    if (TYPE_PATH.article === a || TYPE_PATH.comic === a || TYPE_PATH.video === a) {
      const type = TYPES.find((t) => TYPE_PATH[t] === a);
      return b ? await viewDetail(my, type, b) : await viewList(my, type);
    }
    if (a === 'langganan') return viewLangganan();
    if (a === 'masuk') return viewAuth('login');
    if (a === 'daftar') return viewAuth('register');
    if (a === 'admin' && !b) return await viewAdminList(my);
    if (a === 'admin' && b === 'baru') return await viewAdminForm(my, null);
    if (a === 'admin' && b === 'edit' && c) return await viewAdminForm(my, c);
    layout('<div class="section"><h1 class="page-title">Halaman tidak ditemukan</h1><a class="btn" href="#/">Ke beranda</a></div>');
  } catch (err) {
    if (my === seq) layout(`<p class="alert alert--error" style="margin-top:24px">${esc(err.message)}</p>`);
  }
}

// ===== Halaman publik =====
async function viewHome(my) {
  const [art, comic, video] = await Promise.all(TYPES.map((t) => api('contents', { query: { type: t, limit: 4 } })));
  if (my !== seq) return;
  const block = (title, t, data) => `
    <section class="section">
      <div class="row" style="justify-content:space-between;margin-bottom:18px">
        <h2 class="section-title" style="margin:0">${title}</h2>
        <a href="#/${TYPE_PATH[t]}">Lihat semua →</a>
      </div>
      ${data.items.length ? `<div class="grid">${data.items.map(card).join('')}</div>` : '<p class="empty">Belum ada konten.</p>'}
    </section>`;
  layout(`
    <section class="hero">
      <span class="eyebrow">Merayakan warisan budaya</span>
      <h1>Jelajahi tradisi, sejarah, dan seni dari seluruh GemAksara</h1>
      <p>Artikel, komik, dan video budaya yang dibuat bersama seniman dan pengrajin lokal.</p>
      <div class="row"><a class="btn" href="#/artikel">Mulai membaca</a><a class="btn btn--outline" href="#/langganan">Lihat GemAksara+</a></div>
    </section>
    ${block('Artikel terbaru', 'article', art)}
    ${block('Komik', 'comic', comic)}
    ${block('Video', 'video', video)}`);
}

async function viewList(my, type) {
  const st = { genre: '', q: '' };
  const chips = () => ['', ...GENRES[type]].map((g) =>
    `<button class="chip ${st.genre === g ? 'chip--selected' : ''}" data-genre="${esc(g)}">${g || 'Semua'}</button>`).join('');
  layout(`
    <div class="row" style="justify-content:space-between">
      <h1 class="page-title">${TYPE_LABEL[type]}</h1>
      ${(me && me.role === 'admin') ? `<a class="btn" href="#/admin/baru">+ Tambah ${TYPE_LABEL[type]}</a>` : ''}
    </div>
    <div class="filters" id="chips">${chips()}</div>
    <input class="search" id="q" type="search" placeholder="Cari judul atau ringkasan…">
    <div id="list"></div>`, type);

  const load = async () => {
    const data = await api('contents', { query: { type, genre: st.genre, q: st.q, limit: 24 } });
    if (my !== seq) return;
    $('#list').innerHTML = data.items.length
      ? `<div class="grid">${data.items.map(card).join('')}</div>`
      : '<p class="empty">Tidak ada konten yang cocok.</p>';
  };
  $('#chips').onclick = (e) => {
    const btn = e.target.closest('[data-genre]');
    if (!btn) return;
    st.genre = btn.dataset.genre;
    $('#chips').innerHTML = chips();
    load();
  };
  let timer;
  $('#q').oninput = (e) => {
    clearTimeout(timer);
    timer = setTimeout(() => { st.q = e.target.value.trim(); load(); }, 300);
  };
  await load();
}

async function viewDetail(my, type, slug) {
  const c = await api('content', { query: { type, slug } });
  if (my !== seq) return;
  const paywall = (text) => `
    <div class="paywall">
      <span class="badge badge--premium">Khusus GemAksara+</span>
      <p>${text}</p>
      <a class="btn" href="#/langganan">Berlangganan ${rupiah(PLAN.price)}/bulan</a>
    </div>`;

  let content = '';
  if (type === 'article') {
    content = c.locked
      ? paywall('Artikel ini khusus pelanggan.')
      : `<div class="article-body">${(c.body || []).map((p) => `<p>${esc(p)}</p>`).join('')}</div>`;
  } else if (type === 'video') {
    if (c.locked) content = paywall('Video ini khusus pelanggan.');
    else if (/\.(mp4|webm)(\?|$)/i.test(c.videoUrl || '')) content = `<video class="player" controls src="${esc(c.videoUrl)}"></video>`;
    else if (c.videoUrl) content = `<a class="btn" href="${esc(c.videoUrl)}" target="_blank" rel="noopener">Tonton video</a>`;
    else content = '<p class="empty">Video belum tersedia.</p>';
  } else {
    content = `
      <div class="episodes">${c.episodes.map((ep, i) => `
        <div class="episode">
          <div><strong>${i + 1}. ${esc(ep.title)}</strong>
            <small>${ep.locked ? 'Khusus GemAksara+' : `${(ep.pages || []).length} halaman`}</small></div>
          <span class="badge ${ep.isPremium ? 'badge--premium' : ''}">${ep.isPremium ? 'GemAksara+' : 'Gratis'}</span>
        </div>`).join('') || '<p class="empty">Belum ada episode.</p>'}
      </div>
      ${c.episodes.map((ep, i) => (ep.locked || !(ep.pages || []).length) ? '' : `
        <h3 style="margin:24px 0 12px">Episode ${i + 1}: ${esc(ep.title)}</h3>
        <div class="pages">${ep.pages.map((p) => `<img src="${esc(p)}" alt="">`).join('')}</div>`).join('')}`;
  }

  layout(`
    <div class="narrow">
      <a class="muted" href="#/${TYPE_PATH[type]}">← ${TYPE_LABEL[type]}</a>
      <div class="detail-cover" style="${coverStyle(c)}">${coverImg(c)}</div>
      <h1 class="article-title">${esc(c.title)}</h1>
      <p class="byline">${esc(c.author)} · ${esc(c.genre)}${c.region ? ' · ' + esc(c.region) : ''}${c.readMinutes ? ' · ' + c.readMinutes + ' menit' : ''}</p>
      ${(me && me.role === 'admin') ? `
      <div class="admin-bar">
        <strong>Admin:</strong>
        <a class="btn btn--small" href="#/admin/edit/${c.id}">✎ Ubah konten ini</a>
        <button type="button" class="btn btn--small btn--danger" data-act="delete" data-id="${c.id}" data-title="${esc(c.title)}">Hapus</button>
      </div>` : ''}
      <p class="muted" style="font-size:17px;margin-bottom:12px">${esc(c.summary)}</p>
      ${content}
    </div>`, type);
}

function viewLangganan() {
  layout(`
    <section class="section">
      <div class="price-card">
        <span class="eyebrow">Langganan</span>
        <h1 style="margin-top:8px">${PLAN.name}</h1>
        <div class="price">${rupiah(PLAN.price)}<small> / bulan</small></div>
        <ul class="benefits">${PLAN.benefits.map((b) => `<li>${esc(b)}</li>`).join('')}</ul>
        <button class="btn" style="width:100%" data-act="subscribe">Berlangganan</button>
        <p class="muted-light" style="margin-top:10px">Pembayaran belum tersedia di versi ini.</p>
      </div>
      <h2 class="section-title" style="text-align:center;margin-top:32px">Metode pembayaran</h2>
      <div style="max-width:520px;margin:0 auto">
        ${PAYMENTS.map((g) => `
          <div class="method-group"><h4>${esc(g.name)}</h4>
            ${g.methods.map((m) => `<div class="method">${esc(m)}</div>`).join('')}
          </div>`).join('')}
      </div>
    </section>`, 'langganan');
}

function viewAuth(mode) {
  const isLogin = mode === 'login';
  layout(`
    <form class="form" id="auth">
      <h1 style="font-size:24px">${isLogin ? 'Masuk' : 'Buat Akun'}</h1>
      ${isLogin ? '' : '<input name="name" placeholder="Nama" required minlength="2">'}
      <input name="email" type="email" placeholder="Email" required>
      <input name="password" type="password" placeholder="${isLogin ? 'Kata sandi' : 'Kata sandi (min. 8 karakter)'}" required minlength="${isLogin ? 1 : 8}">
      <p class="error" id="err"></p>
      <button class="btn" id="go">${isLogin ? 'Masuk' : 'Daftar'}</button>
      <p class="muted" style="font-size:14px">${isLogin
        ? 'Belum punya akun? <a href="#/daftar" style="text-decoration:underline">Daftar</a>'
        : 'Sudah punya akun? <a href="#/masuk" style="text-decoration:underline">Masuk</a>'}</p>
    </form>`);

  $('#auth').onsubmit = async (e) => {
    e.preventDefault();
    $('#go').disabled = true;
    try {
      const d = await api(isLogin ? 'login' : 'register', { method: 'POST', body: Object.fromEntries(new FormData(e.target)) });
      me = d.user;
      go(me.role === 'admin' ? '#/admin' : '#/');
    } catch (err) {
      $('#err').textContent = err.message;
      $('#go').disabled = false;
    }
  };
}

// ===== Admin: daftar konten =====
const adminFilter = { type: '', status: '', q: '' };

async function viewAdminList(my) {
  const data = await api('admin_contents', { query: adminFilter });
  if (my !== seq) return;
  const msg = showNotice();
  const rows = data.items.map((item) => `
    <tr>
      <td><strong>${esc(item.title)}</strong><div class="muted-light">/${esc(item.slug)}</div></td>
      <td>${TYPE_LABEL[item.type]}</td>
      <td>${esc(item.genre)}</td>
      <td><span class="status status--${item.status}">${item.status === 'published' ? 'Terbit' : 'Draf'}</span></td>
      <td>${item.isPremium ? 'Ya' : '—'}</td>
      <td><div class="actions">
        <a href="#/admin/edit/${item.id}">Ubah</a>
        <button data-act="toggle" data-id="${item.id}">${item.status === 'published' ? 'Tarik' : 'Terbitkan'}</button>
        <button class="danger" data-act="delete" data-id="${item.id}" data-title="${esc(item.title)}">Hapus</button>
      </div></td>
    </tr>`).join('');

  layout(`
    <div class="row" style="justify-content:space-between;margin-bottom:18px">
      <h1 class="page-title" style="margin:0">Semua Konten</h1>
      <a class="btn" href="#/admin/baru">+ Tambah Konten</a>
    </div>
    <div class="row" style="margin-bottom:18px">
      <input class="search" id="fq" type="search" placeholder="Cari judul atau ringkasan…" value="${esc(adminFilter.q)}">
      <select class="select" id="ft"><option value="">Semua jenis</option>
        ${TYPES.map((t) => `<option value="${t}" ${adminFilter.type === t ? 'selected' : ''}>${TYPE_LABEL[t]}</option>`).join('')}
      </select>
      <select class="select" id="fs"><option value="">Semua status</option>
        <option value="published" ${adminFilter.status === 'published' ? 'selected' : ''}>Terbit</option>
        <option value="draft" ${adminFilter.status === 'draft' ? 'selected' : ''}>Draf</option>
      </select>
    </div>
    ${msg}
    ${data.items.length ? `
      <div class="table-wrap"><table class="admin-table">
        <thead><tr><th>Judul</th><th>Jenis</th><th>Genre</th><th>Status</th><th>Premium</th><th>Aksi</th></tr></thead>
        <tbody>${rows}</tbody>
      </table></div>` : '<p class="empty">Belum ada konten. Mulai dengan menambah konten baru.</p>'}`, 'admin');

  $('#fq').onchange = (e) => { adminFilter.q = e.target.value.trim(); route(); };
  $('#ft').onchange = (e) => { adminFilter.type = e.target.value; route(); };
  $('#fs').onchange = (e) => { adminFilter.status = e.target.value; route(); };
}

// ===== Admin: formulir tambah dan ubah =====
const itemToForm = (item) => ({
  id: item.id, type: item.type, title: item.title, slug: item.slug, summary: item.summary,
  genre: item.genre, region: item.region || '', author: item.author || '',
  readMinutes: item.readMinutes ?? '', coverColor: item.coverColor || '#8b2e2e',
  coverUrl: item.coverUrl || '', isPremium: !!item.isPremium, status: item.status,
  body: (item.body || []).join('\n\n'), videoUrl: item.videoUrl || '',
  episodes: (item.episodes || []).map((e) => ({ title: e.title, isPremium: !!e.isPremium, pages: e.pages || [] })),
});

function formToPayload(F, id) {
  const p = {
    id: id || undefined, type: F.type, title: F.title, slug: F.slug, summary: F.summary,
    genre: F.genre, region: F.region, author: F.author,
    readMinutes: F.readMinutes === '' || F.readMinutes == null ? null : Number(F.readMinutes),
    coverColor: F.coverColor, coverUrl: F.coverUrl || null, isPremium: !!F.isPremium, status: F.status,
  };
  if (F.type === 'article') p.body = F.body;
  if (F.type === 'video') p.videoUrl = (F.videoUrl || '').trim() || null;
  if (F.type === 'comic') p.episodes = F.episodes;
  return p;
}

async function viewAdminForm(my, id) {
  let F = itemToForm({ type: 'article', status: 'draft', coverColor: '#8b2e2e' });
  if (id) {
    const item = await api('admin_content', { query: { id } });
    if (my !== seq) return;
    F = itemToForm(item);
  }

  layout(`
    <h1 class="page-title">${id ? 'Ubah Konten' : 'Tambah Konten'}</h1>
    <div id="msg"></div>
    <form class="stack" id="cf" style="max-width:880px">
      <section class="admin-card"><h3>Informasi dasar</h3>
        <div class="form-grid">
          <label class="field">Jenis konten
            <select name="type" ${id ? 'disabled' : ''}>${TYPES.map((t) => `<option value="${t}">${TYPE_LABEL[t]}</option>`).join('')}</select>
          </label>
          <label class="field">Status
            <select name="status"><option value="draft">Draf (belum tampil)</option><option value="published">Terbit</option></select>
          </label>
          <label class="field field--wide">Judul<input name="title" required minlength="3"></label>
          <label class="field field--wide">Slug (opsional)<input name="slug" placeholder="dibuat otomatis dari judul"></label>
          <label class="field field--wide">Ringkasan<textarea name="summary" rows="3" required minlength="5"></textarea></label>
          <label class="field">Genre<input name="genre" list="genre-options" required><datalist id="genre-options"></datalist></label>
          <label class="field">Wilayah<input name="region" placeholder="mis. Jawa Tengah"></label>
          <label class="field">Penulis / pembuat<input name="author" required minlength="2"></label>
          <label class="field"><span id="mins-label">Waktu baca (menit)</span><input name="readMinutes" type="number" min="1" max="600"></label>
          <label class="field">Warna sampul (cadangan)<input name="coverColor" type="color"></label>
          <label class="field field--check"><input name="isPremium" type="checkbox"> Khusus pelanggan GemAksara+</label>
        </div>
      </section>

      <section class="admin-card"><h3>Gambar sampul</h3>
        <div class="row">
          <div id="cover"></div>
          <div class="stack" style="gap:8px">
            <input type="file" id="coverFile" accept="image/jpeg,image/png,image/webp">
            <span class="muted-light">JPG, PNG, atau WebP. Maksimal 2 MB.</span>
            <button type="button" class="btn btn--small btn--outline" id="coverClear" style="width:fit-content">Hapus sampul</button>
          </div>
        </div>
      </section>

      <div id="typeSection"></div>

      <div class="row">
        <button class="btn" id="save">${id ? 'Simpan Perubahan' : 'Simpan'}</button>
        <a class="btn btn--outline" href="#/admin">Batal</a>
      </div>
    </form>`, 'admin');

  const form = $('#cf');

  const renderCover = () => {
    $('#cover').innerHTML = F.coverUrl
      ? `<img class="cover-preview" src="${esc(F.coverUrl)}" alt="Sampul">`
      : `<div class="cover-preview" style="background:${esc(F.coverColor)}"></div>`;
  };

  const renderEpisodes = () => {
    $('#eps').innerHTML = F.episodes.length ? F.episodes.map((ep, i) => `
      <div class="episode-card">
        <div class="row" style="justify-content:space-between">
          <strong>Episode ${i + 1}</strong>
          <button type="button" class="btn btn--small btn--outline" data-rm-ep="${i}">Hapus episode</button>
        </div>
        <label class="field">Judul episode<input value="${esc(ep.title)}" data-ep-title="${i}"></label>
        <label class="field field--check"><input type="checkbox" data-ep-premium="${i}" ${ep.isPremium ? 'checked' : ''}> Khusus GemAksara+</label>
        <label class="field">Halaman komik (gambar, bisa lebih dari satu)
          <input type="file" multiple accept="image/jpeg,image/png,image/webp" data-pages="${i}">
        </label>
        <span class="muted-light">${ep.pages.length} halaman</span>
        <div class="page-thumbs">${ep.pages.map((p, j) => `
          <div class="page-thumb"><img src="${esc(p)}" alt="Halaman ${j + 1}">
            <button type="button" data-rm-page="${i}:${j}" aria-label="Hapus halaman">×</button></div>`).join('')}
        </div>
      </div>`).join('') : '<p class="muted">Belum ada episode.</p>';
  };

  const renderTypeSection = () => {
    const box = $('#typeSection');
    $('#mins-label').textContent = F.type === 'video' ? 'Durasi (menit)' : 'Waktu baca (menit)';
    $('#genre-options').innerHTML = (GENRES[F.type] || []).map((g) => `<option value="${esc(g)}">`).join('');
    if (F.type === 'article') {
      box.innerHTML = `<section class="admin-card"><h3>Isi artikel</h3>
        <textarea name="body" rows="12" placeholder="Tulis paragraf pertama.  Pisahkan paragraf dengan satu baris kosong.">${esc(F.body)}</textarea></section>`;
    } else if (F.type === 'video') {
      box.innerHTML = `<section class="admin-card"><h3>Video</h3>
        <label class="field">Tautan video<input name="videoUrl" value="${esc(F.videoUrl)}" placeholder="https://… (file .mp4 atau tautan video)"></label>
        <span class="muted-light">File .mp4 atau .webm langsung bisa diputar di situs. Tautan lain dibuka di tab baru.</span></section>`;
    } else {
      box.innerHTML = `<section class="admin-card"><h3>Episode</h3>
        <div id="eps" class="stack"></div>
        <button type="button" class="btn btn--outline" style="width:fit-content" data-add-ep>+ Tambah episode</button></section>`;
      renderEpisodes();
    }
  };

  const setMsg = (text, kind = 'err') => {
    $('#msg').innerHTML = text ? `<p class="alert alert--${kind === 'err' ? 'error' : 'ok'}">${esc(text)}</p>` : '';
  };
  const setBusy = (text) => {
    const b = $('#save');
    b.disabled = !!text;
    b.textContent = text || (id ? 'Simpan Perubahan' : 'Simpan');
  };

  // Isi formulir dari data F
  for (const k of ['type', 'status', 'title', 'slug', 'summary', 'genre', 'region', 'author', 'readMinutes', 'coverColor', 'isPremium']) {
    const el = form.elements[k];
    if (!el) continue;
    if (el.type === 'checkbox') el.checked = !!F[k];
    else el.value = F[k] ?? '';
  }
  form.elements.type.value = F.type;
  renderCover();
  renderTypeSection();

  // Perubahan teks langsung ke F
  form.addEventListener('input', (e) => {
    const el = e.target;
    if (el.dataset.epTitle !== undefined) { F.episodes[+el.dataset.epTitle].title = el.value; return; }
    if (el.name) F[el.name] = el.type === 'checkbox' ? el.checked : el.value;
  });

  form.addEventListener('change', async (e) => {
    const el = e.target;
    if (el.name === 'type') {
      F.type = el.value;
      renderTypeSection();
    } else if (el.dataset.epPremium !== undefined) {
      F.episodes[+el.dataset.epPremium].isPremium = el.checked;
    } else if (el.dataset.pages !== undefined) {
      const i = +el.dataset.pages;
      const files = [...el.files];
      el.value = '';
      if (!files.length) return;
      setMsg('');
      setBusy(`Mengunggah ${files.length} halaman…`);
      try {
        for (const file of files) F.episodes[i].pages.push((await uploadImage(file)).url);
      } catch (err) {
        setMsg(err.message);
      } finally {
        setBusy('');
        renderEpisodes();
      }
    }
  });

  // Tombol tambah, hapus episode, dan hapus halaman
  form.addEventListener('click', (e) => {
    if (e.target.closest('[data-add-ep]')) {
      F.episodes.push({ title: `Episode ${F.episodes.length + 1}`, isPremium: false, pages: [] });
      return renderEpisodes();
    }
    const rmEp = e.target.closest('[data-rm-ep]');
    if (rmEp) { F.episodes.splice(+rmEp.dataset.rmEp, 1); return renderEpisodes(); }
    const rmPage = e.target.closest('[data-rm-page]');
    if (rmPage) {
      const [i, j] = rmPage.dataset.rmPage.split(':').map(Number);
      F.episodes[i].pages.splice(j, 1);
      renderEpisodes();
    }
  });

  $('#coverFile').onchange = async (e) => {
    const file = e.target.files[0];
    e.target.value = '';
    if (!file) return;
    setMsg('');
    setBusy('Mengunggah sampul…');
    try {
      F.coverUrl = (await uploadImage(file)).url;
      renderCover();
    } catch (err) {
      setMsg(err.message);
    } finally {
      setBusy('');
    }
  };
  $('#coverClear').onclick = () => { F.coverUrl = ''; renderCover(); };

  form.onsubmit = async (e) => {
    e.preventDefault();
    setMsg('');
    setBusy('Menyimpan…');
    try {
      await api('admin_save', { method: 'POST', body: formToPayload(F, id) });
      notice = { kind: 'ok', text: `"${F.title}" berhasil disimpan.` };
      go('#/admin');
    } catch (err) {
      setMsg(err.message);
      setBusy('');
    }
  };
}

// ===== Aksi tombol (delegasi) =====
document.addEventListener('click', async (e) => {
  const el = e.target.closest('[data-act]');
  if (!el) return;
  const { act, id } = el.dataset;
  try {
    if (act === 'logout') {
      await api('logout', { method: 'POST' });
      me = null;
      go('#/');
    } else if (act === 'subscribe') {
      alert('Pembayaran belum tersedia di versi ini.');
    } else if (act === 'toggle') {
      const item = await api('admin_content', { query: { id } });
      const f = itemToForm(item);
      f.status = item.status === 'published' ? 'draft' : 'published';
      await api('admin_save', { method: 'POST', body: formToPayload(f, item.id) });
      notice = { kind: 'ok', text: `"${item.title}" ${f.status === 'published' ? 'diterbitkan' : 'ditarik ke draf'}.` };
      route();
    } else if (act === 'delete') {
      if (!window.confirm(`Hapus "${el.dataset.title}"? Tindakan ini tidak bisa dibatalkan.`)) return;
      await api('admin_delete', { method: 'POST', body: { id: Number(id) } });
      notice = { kind: 'ok', text: `"${el.dataset.title}" dihapus.` };
      // Di halaman detail, kontennya sudah tidak ada: kembali ke beranda.
      if (/^#\/(artikel|komik|video)\/[^/]+/.test(location.hash)) go('#/');
      else route();
    }
  } catch (err) {
    notice = { kind: 'err', text: err.message };
    route();
  }
});

// ===== Mulai =====
(async function init() {
  try { me = (await api('me')).user; } catch { me = null; }
  try { GENRES = await api('genres'); } catch { /* tetap jalan tanpa daftar genre */ }
  window.addEventListener('hashchange', route);
  route();
})();
</script>
</body>
</html>
