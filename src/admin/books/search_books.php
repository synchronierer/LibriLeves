<?php
// src/admin/books/search_books.php
session_start();
include '../../db.php';

// Nur Bücher ohne verwertbares Cover anbieten
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
  <title>Cover-Nachrecherche (manuelle Auswahl)</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../../style.css">
  <style>
    .wrap { max-width: 1100px; margin: 20px auto; }
    .panel { background:#fff; border:1px solid #ddd; border-radius:12px; padding:16px; box-shadow: 0 2px 10px rgba(0,0,0,.06); }
    .layout { display:grid; grid-template-columns: 330px 1fr; gap:16px; align-items:start; }
    .muted { color:#666; font-size: .95em; }
    .stack > * { margin: 8px 0; }
    .grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(140px,1fr)); gap:14px; }
    .card { border:1px solid #ddd; border-radius:10px; padding:10px; text-align:center; background:#fff; cursor:pointer; }
    .card img { max-width:100%; max-height:200px; display:block; margin:0 auto 8px; }
    .sel { box-shadow: 0 0 0 3px rgba(44,160,207,.35); }
    .row { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
    .empty { text-align:center; padding:30px 10px; color:#666; }
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
    .button.secondary { background:#eee; }
    #bookSelect { width:100%; padding:10px; border:2px solid #d32f2f; border-radius:6px; }
    #filter { width:100%; padding:10px; border:1px solid #bbb; border-radius:6px; }
  </style>
</head>
<body>
  <?php include '../../menu.php'; ?>
  <h1>Cover-Nachrecherche (manuelle Auswahl)</h1>

  <div class="wrap panel">
    <div class="layout">
      <!-- Linke Spalte: Auswahl -->
      <div class="stack">
        <div>
          <label for="filter"><strong>Suche in Büchern ohne Cover</strong></label>
          <input type="text" id="filter" placeholder="Titel oder ISBN filtern …">
          <div class="muted" id="count"></div>
        </div>

        <div>
          <select id="bookSelect" size="12"></select>
        </div>

        <div class="row">
          <label class="row" style="gap:8px;">
            <input type="checkbox" id="saveLocal" checked> Cover lokal speichern (empfohlen)
          </label>
          <label class="row" style="gap:8px;">
            <input type="checkbox" id="autoBest" checked> Bestes Cover vorselektieren
          </label>
        </div>

        <div class="muted">
          Hinweis: Es werden nur Bücher ohne gültiges Cover angezeigt.
        </div>
      </div>

      <!-- Rechte Spalte: Kandidaten -->
      <div>
        <h3 id="title">Kein Buch ausgewählt</h3>
        <p id="isbn" class="mono muted">ISBN: —</p>
        <div id="grid" class="grid"></div>

        <div class="row" style="margin-top:12px;">
          <button id="accept" class="button">Cover übernehmen</button>
          <button id="clear" class="button secondary">Auswahl leeren</button>
        </div>
        <div id="status" class="muted" style="margin-top:10px;"></div>
      </div>
    </div>
  </div>

<script>
const books = <?php echo json_encode($books); ?>;
let filtered = [...books];
let selectedBook = null;
let selectedUrl = '';

const els = {
  filter: document.getElementById('filter'),
  count: document.getElementById('count'),
  select: document.getElementById('bookSelect'),
  title: document.getElementById('title'),
  isbn: document.getElementById('isbn'),
  grid: document.getElementById('grid'),
  status: document.getElementById('status'),
  saveLocal: document.getElementById('saveLocal'),
  autoBest: document.getElementById('autoBest'),
  accept: document.getElementById('accept'),
  clear: document.getElementById('clear'),
};

function renderSelect() {
  els.select.innerHTML = '';
  filtered.forEach(b => {
    const opt = document.createElement('option');
    opt.value = String(b.id);
    const label = `${b.titel || '—'}${b.isbn ? '  ·  ISBN: ' + b.isbn : ''}  ·  ID: ${b.id}`;
    opt.textContent = label;
    els.select.appendChild(opt);
  });
  els.count.textContent = filtered.length > 0
    ? `${filtered.length} Buch/Bücher ohne Cover`
    : 'Keine Bücher ohne Cover gefunden';
}

function applyFilter() {
  const q = els.filter.value.trim().toLowerCase();
  filtered = books.filter(b => {
    return (b.titel && b.titel.toLowerCase().includes(q)) ||
           (b.isbn && b.isbn.toLowerCase().includes(q)) ||
           String(b.id).includes(q);
  });
  renderSelect();
}

function selectBookById(id) {
  selectedBook = filtered.find(b => String(b.id) === String(id)) || null;
  selectedUrl = '';
  els.grid.innerHTML = '';
  if (!selectedBook) {
    els.title.textContent = 'Kein Buch ausgewählt';
    els.isbn.textContent  = 'ISBN: —';
    return;
  }
  els.title.textContent = selectedBook.titel || '—';
  els.isbn.textContent  = 'ISBN: ' + (selectedBook.isbn || '—');
  loadCandidates(selectedBook.id);
}

function selectUrl(u) {
  selectedUrl = u;
  document.querySelectorAll('.card').forEach(c => c.classList.remove('sel'));
  const node = document.querySelector(`.card[data-url="${CSS.escape(u)}"]`);
  if (node) node.classList.add('sel');
}

function loadCandidates(bookId) {
  els.status.textContent = 'Lade Kandidaten …';
  els.grid.innerHTML = '';
  fetch(`api_cover_candidates.php?id=${encodeURIComponent(bookId)}&limit=12`, {credentials:'same-origin'})
    .then(r => r.json())
    .then(data => {
      els.status.textContent = '';
      const list = (data && data.candidates) ? data.candidates : [];
      if (!Array.isArray(list) || list.length === 0) {
        els.grid.innerHTML = '<div class="empty" style="grid-column:1/-1;">Keine Cover-Kandidaten gefunden.</div>';
        return;
      }
      list.forEach((c, i) => {
        const div = document.createElement('div');
        div.className = 'card';
        div.dataset.url = c.url;
        div.innerHTML = `<img src="${c.url}" alt="Cover"><div class="muted">${c.source || ''}</div>`;
        div.addEventListener('click', () => selectUrl(c.url));
        els.grid.appendChild(div);
        if (i === 0 && els.autoBest.checked) selectUrl(c.url);
      });
    })
    .catch(() => {
      els.status.textContent = 'Kandidaten konnten nicht geladen werden.';
    });
}

els.select.addEventListener('change', (e) => {
  selectBookById(e.target.value);
});

els.filter.addEventListener('input', () => {
  applyFilter();
  // Auswahl zurücksetzen
  selectedBook = null;
  selectedUrl = '';
  els.title.textContent = 'Kein Buch ausgewählt';
  els.isbn.textContent  = 'ISBN: —';
  els.grid.innerHTML = '';
});

els.accept.addEventListener('click', () => {
  if (!selectedBook) { els.status.textContent = 'Bitte zuerst ein Buch auswählen.'; return; }
  // Falls nichts angeklickt wurde, nimm die erste Karte
  if (!selectedUrl) {
    const first = document.querySelector('.card');
    if (first) selectedUrl = first.dataset.url;
  }
  const form = new URLSearchParams();
  form.append('id', selectedBook.id);
  form.append('url', selectedUrl || '');
  form.append('save_local', els.saveLocal.checked ? 'true' : 'false');

  els.status.textContent = 'Speichere …';
  fetch('api_cover_save.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: form.toString(),
    credentials: 'same-origin'
  })
  .then(r => r.json())
  .then(res => {
    if (!res || !res.ok) throw new Error('Fehler beim Speichern');
    els.status.textContent = 'Gespeichert.';
    // Entferne das Buch aus der Gesamtliste & Filterliste
    const idStr = String(selectedBook.id);
    const idxAll = books.findIndex(b => String(b.id) === idStr);
    if (idxAll >= 0) books.splice(idxAll, 1);
    const idxFilt = filtered.findIndex(b => String(b.id) === idStr);
    if (idxFilt >= 0) filtered.splice(idxFilt, 1);
    renderSelect();
    // UI zurücksetzen
    selectedBook = null;
    selectedUrl = '';
    els.title.textContent = 'Kein Buch ausgewählt';
    els.isbn.textContent  = 'ISBN: —';
    els.grid.innerHTML = '';
  })
  .catch(() => {
    els.status.textContent = 'Fehler beim Speichern.';
  });
});

els.clear.addEventListener('click', () => {
  selectedBook = null;
  selectedUrl = '';
  els.select.selectedIndex = -1;
  els.title.textContent = 'Kein Buch ausgewählt';
  els.isbn.textContent  = 'ISBN: —';
  els.grid.innerHTML = '';
});

// Initial
applyFilter();
// Optional: ersten Eintrag vorwählen
if (filtered.length > 0) {
  els.select.selectedIndex = 0;
  selectBookById(filtered[0].id);
}
</script>
</body>
</html>
