<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
?>
<div class="menu">
    <div class="brand-group">
        <a class="brand-icon" href="/index.php" title="Zur Startseite">
            <img src="/media/LogoNurGesicht.png" alt="LibriLeves Logo" class="brand-logo">
        </a>
        <img src="/media/LogoNurTitel.png" alt="LibriLeves" class="brand-wordmark">
    </div>

    <nav class="nav-links">
        <a href="/admin/books/index.php">Katalogisierung</a>
        <a href="/admin/users/benutzerverwaltung.php">Benutzerverwaltung</a>
        <a href="/admin/loans/ausleihe.php">Ausleihe</a>
        <a href="/admin/backup/index.php">Sicherung</a>
        <a href="/admin/logout.php">Logout</a>
    </nav>
</div>

<!-- Busy Overlay -->
<div id="busy-overlay" class="busy-overlay" aria-hidden="true" style="display:none;">
  <div class="busy-box">
    <div class="spinner"></div>
    <div class="busy-text" id="busy-text">Bitte warten…</div>
  </div>
</div>

<script>
  function showBusy(msg){
    const o = document.getElementById('busy-overlay');
    const t = document.getElementById('busy-text');
    if (t && msg) t.textContent = msg;
    o.style.display = 'flex';
    o.setAttribute('aria-hidden','false');
  }
  function hideBusy(){
    const o = document.getElementById('busy-overlay');
    o.style.display = 'none';
    o.setAttribute('aria-hidden','true');
  }
  // Alle Formulare automatisch abfangen
  document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('form').forEach(f=>{
      f.addEventListener('submit', function(){
        const label = this.getAttribute('data-busy') || 'Bitte warten…';
        showBusy(label);
      }, {passive:true});
    });
    // Links/Buttons mit data-busy
    document.querySelectorAll('[data-busy]').forEach(el=>{
      el.addEventListener('click', function(){ showBusy(this.getAttribute('data-busy')); });
    });
  });
  // Automatisches Schließen bei Seitenwechsel (falls Navigation per Link)
  window.addEventListener('pageshow', ()=>hideBusy());
</script>
<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
?>
<div class="menu">
    <div class="brand-group">
        <a class="brand-icon" href="/index.php" title="Zur Startseite">
            <img src="/media/LogoNurGesicht.png" alt="LibriLeves Logo" class="brand-logo">
        </a>
        <img src="/media/LogoNurTitel.png" alt="LibriLeves" class="brand-wordmark">
    </div>

    <nav class="nav-links">
        <a href="/admin/books/index.php">Katalogisierung</a>
        <a href="/admin/users/benutzerverwaltung.php">Benutzerverwaltung</a>
        <a href="/admin/loans/ausleihe.php">Ausleihe</a>
        <a href="/admin/backup/index.php">Sicherung</a>
        <a href="/admin/logout.php">Logout</a>
    </nav>
</div>

<!-- Busy Overlay -->
<div id="busy-overlay" class="busy-overlay" aria-hidden="true" style="display:none;">
  <div class="busy-box">
    <div class="spinner"></div>
    <div class="busy-text" id="busy-text">Bitte warten…</div>
  </div>
</div>

<script>
  function showBusy(msg){
    const o = document.getElementById('busy-overlay');
    const t = document.getElementById('busy-text');
    if (t && msg) t.textContent = msg;
    o.style.display = 'flex';
    o.setAttribute('aria-hidden','false');
  }
  function hideBusy(){
    const o = document.getElementById('busy-overlay');
    o.style.display = 'none';
    o.setAttribute('aria-hidden','true');
  }
  // Alle Formulare automatisch abfangen
  document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('form').forEach(f=>{
      f.addEventListener('submit', function(){
        const label = this.getAttribute('data-busy') || 'Bitte warten…';
        showBusy(label);
      }, {passive:true});
    });
    // Links/Buttons mit data-busy
    document.querySelectorAll('[data-busy]').forEach(el=>{
      el.addEventListener('click', function(){ showBusy(this.getAttribute('data-busy')); });
    });
  });
  // Automatisches Schließen bei Seitenwechsel (falls Navigation per Link)
  window.addEventListener('pageshow', ()=>hideBusy());
</script>
