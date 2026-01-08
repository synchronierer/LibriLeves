<?php
// Einfacher Parser für "ab 8 Jahren", "8+", "ab12", "ab 10J", etc.
function age_from_explicit_patterns(string $text): ?int {
    $text = mb_strtolower($text);
    // Beispiele: "ab 8 jahren", "ab8", "8+", "7-10 jahre"
    if (preg_match('/\bab\s*(\d{1,2})\s*(jahr|jahren|j\.)?\b/u', $text, $m)) {
        $a = (int)$m[1]; if ($a >= 3 && $a <= 18) return $a;
    }
    if (preg_match('/\b(\d{1,2})\s*\+\b/u', $text, $m)) {
        $a = (int)$m[1]; if ($a >= 3 && $a <= 18) return $a;
    }
    if (preg_match('/\b(\d{1,2})\s*-\s*(\d{1,2})\s*(jahr|jahren)\b/u', $text, $m)) {
        $a = (int)$m[1]; if ($a >= 3 && $a <= 18) return $a;
    }
    return null;
}

// Kategorien grob auf Altersklassen mappen
function age_from_categories(array $cats): ?int {
    $min = null;
    foreach ($cats as $c) {
        $cLow = mb_strtolower($c);
        // Englisch (Google Books oft engl. BISAC)
        if (strpos($cLow, 'juvenile') !== false) $min = max($min ?? 0, 6);     // Kinder ~6+
        if (strpos($cLow, 'young adult') !== false) $min = max($min ?? 0, 12); // Jugend ~12+
        if (strpos($cLow, 'early reader') !== false || strpos($cLow, 'beginner reader') !== false) $min = max($min ?? 0, 6); // Erstleser 6–8
        // Deutsch
        if (preg_match('/erstleser|lesestarter|leserabe|silbenmethode|erste\s*klasse/u', $cLow)) $min = max($min ?? 0, 6);
        if (preg_match('/kinderbuch|kinderbücher/u', $cLow)) $min = max($min ?? 0, 6);
        if (preg_match('/jugendbuch|jugendroman|jugend/u', $cLow)) $min = max($min ?? 0, 12);
    }
    return $min;
}

// LIX (Lesbarkeitsindex) anhand der Beschreibung
function lix_index(string $text): ?float {
    $text = trim($text);
    if ($text === '') return null;
    // Sätze grob zählen
    $sentences = preg_split('/[.!?]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
    $S = max(count($sentences), 1);
    // Wörter zählen
    $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
    $W = max(count($words), 1);
    // Lange Wörter (>6 Buchstaben)
    $long = 0;
    foreach ($words as $w) {
        $w = preg_replace('/[^\p{L}]/u', '', $w);
        if (mb_strlen($w) > 6) $long++;
    }
    return ($W / $S) + (100.0 * $long / $W);
}

// LIX grob in Mindestalter umsetzen (Heuristik)
function age_from_lix(?float $lix): ?int {
    if ($lix === null) return null;
    // sehr grobe Heuristik für DE:
    // <=25: sehr leicht (~2.–3. Kl) -> 7–9 -> min 7
    // 26–30: leicht (~3.–4. Kl)     -> 9–10 -> min 9
    // 31–40: mittel (~5.–6. Kl)     -> 11–12 -> min 11
    // 41–50: anspruchsvoll (~7.–9.) -> 13–15 -> min 13
    // 51–60: schwer (~10.–12.)      -> 16–18 -> min 16
    // >60: sehr schwer (Erwachsene) -> 18+
    if ($lix <= 25) return 7;
    if ($lix <= 30) return 9;
    if ($lix <= 40) return 11;
    if ($lix <= 50) return 13;
    if ($lix <= 60) return 16;
    return 18;
}

// Hauptfunktion: schlägt Mindestalter vor (konservativ das Maximum der Signale)
function propose_min_age(array $meta): ?int {
    // meta: ['titel','beschreibung','categories'=>[],'publisher'=>...]
    $texts = [];
    if (!empty($meta['titel']))        $texts[] = $meta['titel'];
    if (!empty($meta['untertitel']))   $texts[] = $meta['untertitel'];
    if (!empty($meta['beschreibung'])) $texts[] = $meta['beschreibung'];
    if (!empty($meta['publisher']))    $texts[] = $meta['publisher'];

    $explicit = null;
    foreach ($texts as $t) {
        $a = age_from_explicit_patterns($t);
        if ($a !== null) { $explicit = $a; break; }
    }

    $fromCats = !empty($meta['categories']) && is_array($meta['categories'])
        ? age_from_categories($meta['categories'])
        : null;

    $lixAge = null;
    if (!empty($meta['beschreibung'])) {
        $lix = lix_index($meta['beschreibung']);
        $lixAge = age_from_lix($lix);
    }

    // Konservativ: nimm die höchste gefundene Altersuntergrenze
    $candidates = array_filter([$explicit, $fromCats, $lixAge], fn($v)=>$v!==null);
    if (empty($candidates)) return null;
    return max($candidates);
}
