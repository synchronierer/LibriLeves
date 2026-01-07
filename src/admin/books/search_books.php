<?php
// src/admin/books/search_books.php
session_start();
include '../../db.php';

// Bücher ermitteln, die ein Cover brauchen
$sql = "SELECT id, titel, isbn FROM books 
        WHERE bildlink IS NULL
           OR TRIM(bildlink) = ''
           OR TRIM(bildlink) = 'Nicht verfgbar'
           OR TRIM(bildlink) = 'http://books.google.com/books/content?id=R1aZEAAAQBAJ&printsec=frontcover&img=1&zoom=1&edge=curl&source=gbs_api'";
$result = $conn->query($sql);

$books = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()){
        $books[] = $row;
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Automatische Cover-Nachrecherche</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../style.css">
    <style>
      .cover-tool {
        max-width: 980px; margin: 20px auto; background: var(--surface);
        border: 1px solid var(--border); border-radius: 12px; padding: 20px; box-shadow: var(--shadow);
      }
      .tool-head { text-align:center; }
      .grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(140px,1fr)); gap:14px; margin-top:16px; }
      .card { border:1px solid var(--border); border-radius:10px; padding:10px; text-align:center; background:#fff; }
      .card img { max-width:100%; max-height:200px; display:block; margin:0 auto 8px; }
      .muted { color: var(--ink-muted); font-size:.9em; }
      .actions { display:flex; gap:10px; justify-content:center; margin-top:14px; flex-wrap: wrap; }
      .progress { text-align:center; margin-top:14px; font-weight:700; color: var(--ink-muted); }
      .row { display:flex; gap:20px; align-items:center; justify-content:center; flex-wrap:wrap; }
      .row label { display:inline-flex; align-items:center; gap:8px; }
      .empty { text-align:center; padding:40px 10px; }
    </style>
</head>
<body>
    <?php include '../../menu.php'; ?>
    <h1>Automatische Cover-Nachrecherche</h1>

    <div class="cover-tool">
      <div class="tool-head">
        <h3 id="book-title">Buchtitel</h3>
        <p id="book-isbn" class="mono muted">ISBN: —</p>
        <div class="row">
          <label><input type="checkbox" id="saveLocal" checked> Cover lokal speichern (empfohlen)</label>
          <label><input type="checkbox" id="autoBest" checked> Bestes Cover automatisch vorselektieren</label>
        </div>
      </div>

      <div id="grid" class="grid"></div>

      <div class="actions">
        <button id="accept" class="button">Cover übernehmen</button>
        <button id="skip" class="button secondary">Weiter</button>
      </div>
      <div id="progress" class="progress"></div>
    </div>

<script>
const books = <?php echo json_encode($books); ?>;
let idx = 0;
let selectedUrl = '';

function updateProgress(){
  document.getElementById('progress').innerText = (books.length>0)
    ? `Buch ${idx+1} von ${books.length}`
    : 'Keine Bücher ohne Cover gefunden';
}

function selectUrl(u){
  selectedUrl = u;
  document.querySelectorAll('.card').forEach(c => c.style.boxShadow='none');
  const tgt = document.querySelector(`.card[data-url="${CSS.escape(u)}"]`);
  if (tgt) tgt.style.boxShadow = '0 0 0 3px rgba(44,160,207,.35)';
}

function loadCandidates(){
  const grid = document.getElementById('grid');
  grid.innerHTML = '';
  selectedUrl = '';

  if (idx >= books.length) {
    document.querySelector('.cover-tool').innerHTML = '<div class="empty"><h3>Fertig 🎉</h3><p>Keine weiteren Bücher ohne Cover.</p></div>';
    return;
  }

  const b = books[idx];
  document.getElementById('book-title').innerText = b.titel || '—';
  document.getElementById('book-isbn').innerText  = 'ISBN: ' + (b.isbn || '—');
  updateProgress();

  fetch(`api_cover_candidates.php?id=${encodeURIComponent(b.id)}&limit=12`, {credentials:'same-origin'})
    .then(r => r.json())
    .then(data => {
      if (!data || !data.ok) throw new Error('Fehler bei Kandidatenabfrage');
      const list = data.candidates || [];
      if (list.length === 0) {
        grid.innerHTML = '<div class="empty muted" style="grid-column:1/-1;">Keine Kandidaten gefunden. Du kannst weiter klicken.</div>';
        return;
      }
      list.forEach((c, i) => {
        const div = document.createElement('div');
        div.className = 'card';
        div.dataset.url = c.url;
        div.innerHTML = `<img src="${c.url}" alt="Cover"><div class="muted">${c.source}</div>`;
        div.addEventListener('click', () => selectUrl(c.url));
        grid.appendChild(div);
        if (i===0 && document.getElementById('autoBest').checked) selectUrl(c.url);
      });
    })
    .catch(() => {
      grid.innerHTML = '<div class="empty muted" style="grid-column:1/-1;">Kandidaten konnten nicht geladen werden.</div>';
    });
}

document.getElementById('accept').addEventListener('click', () => {
  if (idx >= books.length) return;
  const b = books[idx];
  const url = selectedUrl || '';
  const saveLocal = document.getElementById('saveLocal').checked;

  // Wenn nichts ausgewählt wurde, versuche zuerst die erste Karte zu nehmen
  if (!url) {
    const first = document.querySelector('.card');
    if (first) selectedUrl = first.dataset.url;
  }

  const form = new URLSearchParams();
  form.append('id', b.id);
  form.append('url', selectedUrl || '');
  form.append('save_local', saveLocal ? 'true' : 'false');

  fetch('api_cover_save.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: form.toString(),
    credentials: 'same-origin'
  }).then(r => r.json()).then(res => {
    idx++;
    loadCandidates();
  }).catch(() => {
    idx++;
    loadCandidates();
  });
});

document.getElementById('skip').addEventListener('click', () => {
  idx++;
  loadCandidates();
});

// Start
if (books.length > 0) { loadCandidates(); }
else { updateProgress(); }
</script>
</body>
</html>
