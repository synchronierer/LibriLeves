<?php
// src/includes/isbn.php
// Hilfen zum Bereinigen/Umwandeln/Prüfen von ISBN

function isbn_clean(string $raw): string {
    $s = strtoupper(preg_replace('/[^0-9Xx]/', '', $raw));
    return $s;
}

function isbn_is_valid10(string $isbn10): bool {
    if (!preg_match('/^[0-9]{9}[0-9X]$/', $isbn10)) return false;
    $sum = 0;
    for ($i=0; $i<9; $i++) $sum += ((10-$i) * (int)$isbn10[$i]);
    $check = 11 - ($sum % 11);
    $checkChar = ($check === 10) ? 'X' : (($check === 11) ? '0' : (string)$check);
    return $checkChar === $isbn10[9];
}

function isbn_is_valid13(string $isbn13): bool {
    if (!preg_match('/^[0-9]{13}$/', $isbn13)) return false;
    $sum = 0;
    for ($i=0; $i<12; $i++) $sum += ((($i % 2) ? 3 : 1) * (int)$isbn13[$i]);
    $check = (10 - ($sum % 10)) % 10;
    return $check === (int)$isbn13[12];
}

function isbn10_to_13(string $isbn10): ?string {
    $isbn10 = isbn_clean($isbn10);
    if (strlen($isbn10) !== 10 || !isbn_is_valid10($isbn10)) return null;
    $core = '978' . substr($isbn10, 0, 9);
    $sum = 0;
    for ($i=0; $i<12; $i++) $sum += ((($i % 2) ? 3 : 1) * (int)$core[$i]);
    $check = (10 - ($sum % 10)) % 10;
    return $core . $check;
}

function isbn13_to_10(string $isbn13): ?string {
    $isbn13 = isbn_clean($isbn13);
    if (strlen($isbn13) !== 13 || !isbn_is_valid13($isbn13)) return null;
    if (substr($isbn13, 0, 3) !== '978') return null; // nur 978 unterstützbar
    $core = substr($isbn13, 3, 9);
    $sum = 0;
    for ($i=0; $i<9; $i++) $sum += ((10-$i) * (int)$core[$i]);
    $check = 11 - ($sum % 11);
    $checkChar = ($check === 10) ? 'X' : (($check === 11) ? '0' : (string)$check);
    return $core . $checkChar;
}

function isbn_variants(string $isbn): array {
    $c = isbn_clean($isbn);
    $out = [];
    if (strlen($c) === 13 && isbn_is_valid13($c)) {
        $out[] = $c;
        $v10 = isbn13_to_10($c);
        if ($v10) $out[] = $v10;
    } elseif (strlen($c) === 10 && isbn_is_valid10($c)) {
        $out[] = $c;
        $v13 = isbn10_to_13($c);
        if ($v13) $out[] = $v13;
    } else {
        // unsicher, trotzdem beides versuchen
        $out[] = $c;
    }
    return array_values(array_unique($out));
}
