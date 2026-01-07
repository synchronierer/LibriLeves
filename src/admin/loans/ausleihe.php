<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/security.php';

start_secure_session();
require_admin();

// Flash-/Seitenmeldung
$message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);

// Default-Ausleihdauer aus ENV (nur 1/2/3/4 erlaubt), Fallback 4
$envDefaultWeeks = (int)(getenv('LOAN_DEFAULT_WEEKS') ?: 4);
if (!in_array($envDefaultWeeks, [1,2,3,4], true)) $envDefaultWeeks = 4;

// Ausleihe per klassischem POST (Formular unten)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['loan'])) {
    if (!csrf_validate($_POST['csrf'] ?? null)) {
        $_SESSION['message'] = "CSRF-Prüfung fehlgeschlagen.";
        header('Location: ausleihe.php');
        exit();
    }
    $user_id    = (int)($_POST['user_id'] ?? 0);
    $book_id    = (int)($_POST['book_id'] ?? 0);
    $loan_weeks = (int)($_POST['loan_weeks'] ?? $envDefaultWeeks);
    if (!in_array($loan_weeks, [1,2,3,4], true)) $loan_weeks = $envDefaultWeeks;

    if ($user_id <= 0 || $book_id <= 0) {
        $_SESSION['message'] = "Bitte Benutzer und Buch auswählen.";
        header('Location: ausleihe.php');
        exit();
    }

    // Doppel-Ausleihe verhindern: irgendein loans-Eintrag vorhanden?
    $check = $conn->prepare("SELECT 1 FROM loans WHERE book_id = ?");
    $check->bind_param("i", $book_id);
    $check->execute();
    $exists = $check->get_result()->num_rows > 0;
    $check->close();

    if ($exists) {
        $_SESSION['message'] = "Das Buch ist bereits ausgeliehen.";
    } else {
        $loan_date = date('Y-m-d H:i:s');
        $return_date = date('Y-m-d H:i:s', strtotime('+' . $loan_weeks . ' weeks'));
        $due_date_str = date('Y-m-d', strtotime($return_date)); // nur Datum für Anzeige

        $ins = $conn->prepare("INSERT INTO loans (user_id, book_id, loan_date, return_date) VALUES (?, ?, ?, ?)");
        $ins->bind_param("iiss", $user_id, $book_id, $loan_date, $return_date);
        if ($ins->execute()) {
            $_SESSION['message'] = "Ausleihe erfasst. Fällig am: " . $due_date_str;
        } else {
            $_SESSION['message'] = "Fehler beim Ausleihen des Buches: " . $conn->error;
        }
        $ins->close();
    }
    header('Location: ausleihe.php');
    exit();
}

// Benutzerliste für Autocomplete (Server-seitig ziehen wir via API, hier nur initial UI)
$users = [];
$uRes = $conn->query("SELECT id, name, vorname FROM users ORDER BY vorname, name");
while ($row = $uRes->fetch_assoc()) { $users[] = $row; }

// Alle Ausleihen (inkl. überfällige) – Fälligkeit als Datum (ohne Uhrzeit)
$loans = [];
$sql = "SELECT l.loan_id, u.name, u.vorname, b.titel, b.barcode,
               DATE(l.return_date) AS due_date,
               CASE WHEN NOW() > l.return_date THEN 'überfällig' ELSE 'fristgerecht' END AS status
        FROM loans l
        JOIN users u ON l.user_id = u.id
        JOIN books b ON l.book_id = b.id
        ORDER BY l.return_date ASC";
$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) { $loans[] = $row; }

$csrf = csrf_token();
include '../../menu.php';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Ausleihe - Bücherverwaltung</title>
    <link rel="stylesheet" href="../../style.css">
    <style>
      .auto-wrap { position: relative; }
      .auto-list {
        position: absolute; top: 100%; left: 0; right: 0; z-index: 1001;
        background: #fff; border: 1px solid #ddd; border-top: none; max-height: 240px; overflow-y: auto;
      }
      .auto-item { padding: 8px 10px; cursor: pointer; }
      .auto-item:hover, .auto-item.active { background: #ffe082; }
      .chip {
        display:inline-flex; align-items:center; gap:8px;
        background:#ffeb3b; border-radius:16px; padding:4px 10px; margin-top:8px;
      }
      .chip .x { cursor:pointer; font-weight:bold; }
      .row { display:flex; gap:20px; flex-wrap:wrap; }
      .col { flex:1; min-width:280px; }
      .badge { display:inline-block; padding:2px 8px; border-radius:12px; font-size:0.85em; }
      .badge.overdue { background:#d32f2f; color:#fff; }
      .badge.ok { background:#5CA32D; color:#fff; }
      .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
      .muted { color:#666; font-size:0.9em; }
      /* Sortierbare Tabelle */
      .sortable { cursor: pointer; user-select: none; }
      .sort-indicator { margin-left: 6px; opacity: .6; }
      th.sorted-asc .sort-indicator::after { content: "▲"; }
      th.sorted-desc .sort-indicator::after { content: "▼"; }
    </style>
</head>
<body>
<br>
<h1>Ausleihe</h1>

<?php if ($message): ?>
  <div class="message-box" style="display:block;"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<!-- Elegante Auswahl: Benutzer und Buch mit Autocomplete -->
<form action="" method="POST" id="loanForm">
  <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
  <input type="hidden" name="user_id" id="user_id">
  <input type="hidden" name="book_id" id="book_id">

  <div class="row">
    <div class="col">
      <h3>Benutzer auswählen</h3>
      <div class="auto-wrap">
        <input type="text" id="userInput" placeholder="Vorname, Name oder E-Mail (min. 2 Zeichen)" autocomplete="off">
        <div id="userList" class="auto-list" style="display:none;"></div>
      </div>
      <div id="userChip" class="chip" style="display:none;"></div>
      <p class="muted">Tipp: Mit Pfeiltasten durch die Treffer navigieren, Enter wählt aus.</p>
    </div>

    <div class="col">
      <h3>Buch auswählen</h3>
      <div class="auto-wrap">
        <input type="text" id="bookInput" placeholder="Titel, Autor, ISBN oder Barcode (min. 2 Zeichen)" autocomplete="off">
        <div id="bookList" class="auto-list" style="display:none;"></div>
      </div>
      <div id="bookChip" class="chip" style="display:none;"></div>
      <p class="muted">Ausgeliehene Bücher sind in der Liste deaktiviert.</p>
    </div>

    <div class="col">
      <h3>Ausleihzeitraum</h3>
      <select name="loan_weeks" id="loan_weeks" class="button" required>
        <?php foreach ([1,2,3,4] as $w): ?>
          <option value="<?php echo $w; ?>" <?php echo ($w===$envDefaultWeeks?'selected':''); ?>>
            <?php echo $w; ?> Woche<?php echo $w>1?'n':''; ?>
          </option>
        <?php endforeach; ?>
      </select>
      <br><br>
      <input type="submit" name="loan" id="loanSubmit" value="Ausleihen" disabled>
    </div>
  </div>
</form>

<h2>Ausleihen (Spalten sortierbar)</h2>
<table class="loansTable" id="loansTable">
  <thead>
    <tr>
      <th class="sortable" data-type="text">Titel <span class="sort-indicator"></span></th>
      <th class="sortable" data-type="text">Barcode <span class="sort-indicator"></span></th>
      <th class="sortable" data-type="text">Benutzer <span class="sort-indicator"></span></th>
      <th class="sortable" data-type="date">Fällig am <span class="sort-indicator"></span></th>
      <th class="sortable" data-type="text">Status <span class="sort-indicator"></span></th>
      <th>Rückgabe</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($loans as $loan): ?>
      <tr>
        <td><?php echo htmlspecialchars($loan['titel']); ?></td>
        <td class="mono"><?php echo htmlspecialchars($loan['barcode']); ?></td>
        <td><?php echo htmlspecialchars($loan['vorname'] . ' ' . $loan['name']); ?></td>
        <td><?php echo htmlspecialchars($loan['due_date']); ?></td>
        <td>
          <?php if ($loan['status'] === 'überfällig'): ?>
            <span class="badge overdue">überfällig</span>
          <?php else: ?>
            <span class="badge ok">fristgerecht</span>
          <?php endif; ?>
        </td>
        <td>
          <form action="return.php" method="POST" class="return-form">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
            <input type="hidden" name="loan_id" value="<?php echo (int)$loan['loan_id']; ?>">
            <input type="submit" value="Rückgabe">
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<script>
// Debounce-Helfer
function debounce(fn, delay){ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a), delay); }; }
function byId(id){ return document.getElementById(id); }
function enableSubmitIfReady(){
  const ready = byId('user_id').value && byId('book_id').value;
  byId('loanSubmit').disabled = !ready;
}

/* Autocomplete-Widget */
function attachAutocomplete({inputEl, listEl, fetchUrl, renderItem, onSelect}) {
  let items = [];
  let active = -1;
  function closeList(){ listEl.style.display='none'; listEl.innerHTML=''; items=[]; active=-1; }
  function openList(){ if (items.length) listEl.style.display='block'; else closeList(); }
  function render(){
    listEl.innerHTML = '';
    items.forEach((it, idx)=>{
      const div = document.createElement('div');
      div.className = 'auto-item' + (idx===active?' active':'');
      div.innerHTML = renderItem(it);
      if (!it.disabled) div.addEventListener('click', ()=>{ onSelect(it); closeList(); });
      else { div.style.opacity=.5; div.style.pointerEvents='none'; }
      listEl.appendChild(div);
    });
    openList();
  }
  const doFetch = debounce(async (q)=>{
    if (q.length < 2) { closeList(); return; }
    try {
      const res = await fetch(fetchUrl + encodeURIComponent(q), {credentials:'same-origin'});
      const data = await res.json();
      items = (data && data.results) ? data.results : [];
      active = -1; render();
    } catch(e){ closeList(); }
  }, 220);
  inputEl.addEventListener('input', e=> doFetch(e.target.value.trim()));
  inputEl.addEventListener('keydown', e=>{
    if (listEl.style.display!=='block') return;
    if (e.key==='ArrowDown'){ e.preventDefault(); active = Math.min(active+1, items.length-1); render(); }
    else if (e.key==='ArrowUp'){ e.preventDefault(); active = Math.max(active-1, 0); render(); }
    else if (e.key==='Enter'){ if (active>=0 && items[active] && !items[active].disabled){ e.preventDefault(); onSelect(items[active]); closeList(); } }
    else if (e.key==='Escape'){ closeList(); }
  });
  document.addEventListener('click', e=> { if (!listEl.contains(e.target) && e.target!==inputEl) closeList(); });
}

/* Benutzer-Autocomplete */
attachAutocomplete({
  inputEl: byId('userInput'),
  listEl: byId('userList'),
  fetchUrl: 'api_search_users.php?q=',
  renderItem: it => `${it.vorname} ${it.name} <span class="muted">(${it.email})</span>`,
  onSelect: it => {
    byId('user_id').value = it.id;
    byId('userInput').value = `${it.vorname} ${it.name} (${it.email})`;
    const chip = byId('userChip');
    chip.innerHTML = `${it.vorname} ${it.name} <span class="x" title="Auswahl entfernen">×</span>`;
    chip.style.display = 'inline-flex';
    chip.querySelector('.x').onclick = ()=> {
      byId('user_id').value = '';
      byId('userInput').value = '';
      chip.style.display = 'none';
      enableSubmitIfReady();
      byId('userInput').focus();
    };
    enableSubmitIfReady();
  }
});

/* Buch-Autocomplete */
attachAutocomplete({
  inputEl: byId('bookInput'),
  listEl: byId('bookList'),
  fetchUrl: 'api_search_books.php?q=',
  renderItem: it => {
    const loan = it.loaned ? ' <span class="muted">— ausgeliehen</span>' : '';
    return `<span class="mono">${it.barcode || ''}</span> ${it.titel} <span class="muted">(${it.autor || ''}${it.autor?', ':''}${it.isbn || ''})</span>${loan}`;
  },
  onSelect: it => {
    if (it.loaned) return; // sicherheitshalber
    byId('book_id').value = it.id;
    byId('bookInput').value = `${it.titel} (${it.autor || ''}${it.autor?', ':''}${it.isbn || ''})`;
    const chip = byId('bookChip');
    chip.innerHTML = `${it.titel} <span class="x" title="Auswahl entfernen">×</span>`;
    chip.style.display = 'inline-flex';
    chip.querySelector('.x').onclick = ()=> {
      byId('book_id').value = '';
      byId('bookInput').value = '';
      chip.style.display = 'none';
      enableSubmitIfReady();
      byId('bookInput').focus();
    };
    enableSubmitIfReady();
  }
});

enableSubmitIfReady();

/* Tabellen-Sortierung (Client-seitig) */
(function(){
  const table = document.getElementById('loansTable');
  if (!table) return;
  const tbody = table.tBodies[0];
  const headers = table.tHead.rows[0].cells;

  function getCellText(tr, idx) {
    return tr.cells[idx].innerText.trim();
  }
  function compare(a, b, type) {
    if (type === 'num') {
      const na = parseFloat(a.replace(',', '.')) || 0;
      const nb = parseFloat(b.replace(',', '.')) || 0;
      return na - nb;
    } else if (type === 'date') {
      // Format YYYY-MM-DD: String-Vergleich reicht, ist lexikographisch korrekt
      return a.localeCompare(b);
    } else {
      return a.localeCompare(b, 'de', {sensitivity:'base'});
    }
  }

  Array.from(headers).forEach((th, idx) => {
    if (!th.classList.contains('sortable')) return;
    th.addEventListener('click', () => {
      const type = th.dataset.type || 'text';
      const currentlyDesc = th.classList.contains('sorted-desc');
      // Reset Klassen
      Array.from(headers).forEach(h => h.classList.remove('sorted-asc', 'sorted-desc'));
      // Richtung toggeln
      const desc = !currentlyDesc;
      th.classList.add(desc ? 'sorted-desc' : 'sorted-asc');

      const rows = Array.from(tbody.rows);
      rows.sort((r1, r2) => {
        const t1 = getCellText(r1, idx);
        const t2 = getCellText(r2, idx);
        const cmp = compare(t1, t2, type);
        return desc ? -cmp : cmp;
      });
      rows.forEach(r => tbody.appendChild(r));
    });
  });
})();
</script>
</body>
</html>
