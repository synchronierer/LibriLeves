<?php
// src/includes/covers.php
// Mehrquellen-Cover-Suche + optional lokales Speichern

require_once __DIR__ . '/isbn.php';

function http_head_ok(string $url): bool {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_NOBODY => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_TIMEOUT => 6,
        CURLOPT_USERAGENT => 'LibriLeves/1.0 (+cover-check)'
    ]);
    $ok = curl_exec($ch);
    $code = $ok ? curl_getinfo($ch, CURLINFO_RESPONSE_CODE) : 0;
    $type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    return ($code >= 200 && $code < 300) && (stripos($type ?? '', 'image/') !== false);
}

function google_books_covers(string $isbn): array {
    $key = getenv('GOOGLE_BOOKS_API_KEY') ?: '';
    $url = "https://www.googleapis.com/books/v1/volumes?q=isbn:" . rawurlencode($isbn);
    if ($key) $url .= "&key=" . rawurlencode($key);

    $json = @file_get_contents($url);
    if ($json === false) return [];
    $data = json_decode($json, true);
    if (!isset($data['items'][0]['volumeInfo'])) return [];
    $vi = $data['items'][0]['volumeInfo'];
    $imgs = $vi['imageLinks'] ?? [];

    $order = ['extraLarge','large','medium','small','thumbnail','smallThumbnail'];
    $out = [];
    foreach ($order as $k) if (!empty($imgs[$k])) $out[] = $imgs[$k];
    // etwas größere Google-Varianten versuchen
    $out = array_map(fn($u) => preg_replace('/zoom=\d+/', 'zoom=1', $u), $out);
    return array_values(array_unique($out));
}

function openlibrary_covers_by_isbn(string $isbn): array {
    $sizes = ['L','M','S'];
    $out = [];
    foreach ($sizes as $s) {
        $out[] = "https://covers.openlibrary.org/b/isbn/" . rawurlencode($isbn) . "-{$s}.jpg?default=false";
    }
    // zusätzliche Hinweise aus Books-API
    $api = "https://openlibrary.org/api/books?bibkeys=ISBN:" . rawurlencode($isbn) . "&format=json&jscmd=data";
    $json = @file_get_contents($api);
    if ($json !== false) {
        $data = json_decode($json, true);
        $key = "ISBN:" . $isbn;
        if (!empty($data[$key]['cover'])) {
            foreach (['large','medium','small'] as $k) {
                if (!empty($data[$key]['cover'][$k])) $out[] = $data[$key]['cover'][$k];
            }
        }
    }
    return array_values(array_unique($out));
}

/* Kandidaten anhand ISBN (inkl. 10/13 Varianten) */
function cover_candidates_for_isbn(string $isbn): array {
    $cands = []; $seen = [];
    foreach (isbn_variants($isbn) as $v) {
        foreach (google_books_covers($v) as $u) if (!isset($seen[$u])) { $seen[$u]=1; $cands[]=['source'=>'google','url'=>$u]; }
        foreach (openlibrary_covers_by_isbn($v) as $u) if (!isset($seen[$u])) { $seen[$u]=1; $cands[]=['source'=>'openlibrary','url'=>$u]; }
    }
    $valid = [];
    foreach ($cands as $c) if (http_head_ok($c['url'])) $valid[] = $c;
    return $valid;
}

/* Kandidaten anhand Titel/Autor (Fallback, wie in search_books) */
function cover_candidates_for_meta(string $titel, string $autor=''): array {
    $out = [];

    // Google Books: Suche intitle:/inauthor:
    $q = "https://www.googleapis.com/books/v1/volumes?q=intitle:" . rawurlencode($titel);
    $key = getenv('GOOGLE_BOOKS_API_KEY') ?: '';
    if ($autor !== '') $q .= "+inauthor:" . rawurlencode($autor);
    if ($key) $q .= "&key=" . rawurlencode($key);

    $json = @file_get_contents($q);
    if ($json !== false) {
        $data = json_decode($json, true);
        if (!empty($data['items'])) {
            foreach ($data['items'] as $it) {
                $imgs = $it['volumeInfo']['imageLinks'] ?? [];
                foreach (['extraLarge','large','medium','small','thumbnail','smallThumbnail'] as $k) {
                    if (!empty($imgs[$k])) $out[] = ['source'=>'google-meta','url'=>$imgs[$k]];
                }
            }
        }
    }

    // Open Library: Search API -> cover_i -> URLs
    $ol = "https://openlibrary.org/search.json?title=" . rawurlencode($titel);
    if ($autor !== '') $ol .= "&author=" . rawurlencode($autor);
    $json2 = @file_get_contents($ol);
    if ($json2 !== false) {
        $data2 = json_decode($json2, true);
        if (!empty($data2['docs'])) {
            foreach ($data2['docs'] as $doc) {
                if (!empty($doc['cover_i'])) {
                    $cid = (int)$doc['cover_i'];
                    foreach (['L','M','S'] as $sz) {
                        $out[] = ['source'=>'openlib-meta', 'url'=>"https://covers.openlibrary.org/b/id/{$cid}-{$sz}.jpg"];
                    }
                }
            }
        }
    }

    // deduplizieren + validieren
    $seen = []; $final = [];
    foreach ($out as $c) if (!isset($seen[$c['url']])) { $seen[$c['url']]=1; $final[]=$c; }
    $valid = [];
    foreach ($final as $c) if (http_head_ok($c['url'])) $valid[] = $c;
    return $valid;
}

/* Cover lokal speichern (optional) */
function download_cover_locally(string $url, string $isbnOrName): ?string {
    $id = isbn_clean($isbnOrName);
    if ($id === '') $id = preg_replace('/[^A-Za-z0-9_\-]/','-',$isbnOrName);

    $root = dirname(__DIR__); // /var/www/html
    $dir  = $root . '/media/covers';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_USERAGENT => 'LibriLeves/1.0 (+cover-download)'
    ]);
    $bin = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    if ($code < 200 || $code >= 300 || !$bin) return null;

    $ext = '.jpg';
    if (stripos($type, 'png') !== false) $ext = '.png';
    elseif (stripos($type, 'webp') !== false) $ext = '.webp';

    $path = $dir . '/' . $id . $ext;
    if (@file_put_contents($path, $bin) === false) return null;
    @chmod($path, 0664);

    return '/media/covers/' . $id . $ext;
}
