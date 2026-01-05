<?php
if (!isset($_GET['isbn']) || empty($_GET['isbn'])) {
    echo json_encode(["error" => "Keine ISBN angegeben."]);
    exit;
}

$isbn = $_GET['isbn'];
$query = urlencode($isbn . " cover");

// Zunächst: vqd-Token ermitteln
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://duckduckgo.com/?q=".$query);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36");
$html = curl_exec($ch);
if(curl_errno($ch)) {
    echo json_encode(["error" => "cURL-Fehler: ".curl_error($ch)]);
    exit;
}
curl_close($ch);

// Den vqd-Token mittels Regex extrahieren
if (preg_match("/vqd='([\d-]+)'/", $html, $matches)) {
    $vqd = $matches[1];
} else {
    echo json_encode(["error" => "Kein vqd-Token gefunden."]);
    exit;
}

// Nun über die inoffizielle API von DuckDuckGo eine Bildsuche durchführen
$apiUrl = "https://duckduckgo.com/i.js?l=de-en&o=json&q=" . $query . "&vqd=" . $vqd;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36");
$response = curl_exec($ch);
if(curl_errno($ch)) {
    echo json_encode(["error" => "cURL-Fehler bei API-Aufruf: ".curl_error($ch)]);
    exit;
}
curl_close($ch);

$data = json_decode($response, true);
if (!$data || !isset($data['results'])) {
    echo json_encode(["error" => "Keine Ergebnisse gefunden."]);
    exit;
}

// Ergebnis herausfiltern: Suche das erste Bild, bei dem in der URL (oder im "image"-Feld) "cover" vorkommt
$coverUrl = null;
foreach ($data['results'] as $result) {
    // Es kann sein, dass die API das Bild im Feld 'image' oder 'thumbnail' liefert
    if (isset($result['image']) && stripos($result['image'], "cover") !== false) {
        $coverUrl = $result['image'];
        break;
    }
    if (isset($result['url']) && stripos($result['url'], "cover") !== false) {
        $coverUrl = $result['url'];
        break;
    }
}

if ($coverUrl) {
    echo json_encode(["cover" => $coverUrl]);
} else {
    echo json_encode(["cover" => ""]);
}
?>
