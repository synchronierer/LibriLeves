### LibriLeves – the lightweight library tool
<img src="src/media/LogoNurGesicht.png" alt="LibriLeves mascot" height="150"><img src="src/media/LogoNurTitel.png" alt="LibriLeves" height="150">  


A friendly, open‑source library management system for schools. Catalog books, manage users and loans, and keep your shelf tidy – fast and fun.

- 📚 Book cataloging via ISBN (with auto metadata + cover)
- 👩‍🏫 User management (admins, readers)
- 🔄 Loans & returns with due dates
- 🔎 OPAC search for students
- 💾 One‑click backups (SQL and CSV) + restore
- 🖼️ Automatic cover re‑search tool
- 🐳 Dockerized (PHP/Apache + MariaDB + phpMyAdmin)
- 🟢 Clean, playful UI with busy/loader overlay

---

### Features

- 📖 Book Cataloging
  - Add by ISBN; metadata via Google Books API
  - Robust cover search using Google Books and Open Library
  - Local cover caching to src/media/covers for faster load
- 📦 Inventory
  - Track location, barcodes, availability status
- 👥 Users
  - Admin and reader roles
  - Password hashing (PHP password_hash)
- 🔄 Loans
  - Quick checkout/return views
  - Configurable default loan period (env)
- 🔎 Public Search (OPAC)
  - Search by title, author, ISBN, barcode
  - Clear “available/loaned” indicator
- 🖼️ Cover Re‑search
  - Admin tool suggests covers for items missing a thumbnail
  - Accept in one click; optional local download
- 💾 Backups
  - Export full database as SQL
  - Export each table as CSV
  - Import from SQL (drops and recreates database)
  - Import from CSV (truncates and refills tables)
- 🧭 Busy Overlay
  - Global “Please wait…” overlay during long operations (search, save, import, loan)
- 🎨 Branding
  - Large header with icon + wordmark
  - Favicon + manifest included

---

### Quick Start (Docker)

Requirements:
- Docker 20+
- Docker Compose v2+

Steps:

```bash
# 1) Clone
git clone https://github.com/synchronierer/LibriLeves.git
cd LibriLeves/docker

# 2) Optional: set env (Google Books API key, loan duration)
cp .env.example .env   # if you create one; see sample below
# or create docker/.env with:
# GOOGLE_BOOKS_API_KEY=your_api_key_or_empty
# LOAN_DEFAULT_WEEKS=4

# 3) Start all services
docker compose up -d --build
```

Access:
- Web UI: http://localhost:8765
- phpMyAdmin: http://localhost:5678 (host: db, user: bibadmin, pass: bibadmin)

Default login (if provided by your DB seed):
- Admin user: see “Admin account” below.

Stop:
```bash
docker compose down
```

---

### Configuration

- Services and ports (docker/docker-compose.yml)
  - Web: 8765 → PHP 8.2 + Apache
  - MariaDB: internal
  - phpMyAdmin: 5678
- Bind mounts
  - ../src → /var/www/html
  - ../db_data → /var/lib/mysql (persistent DB data; not versioned)
- Init script
  - docker/init-perms.sh runs at container start to ensure media/covers is writable by Apache
- Environment variables (docker/.env)
  - GOOGLE_BOOKS_API_KEY=your_key (optional; improves quota/quality)
  - LOAN_DEFAULT_WEEKS=4

Example docker/.env:
```dotenv
GOOGLE_BOOKS_API_KEY=
LOAN_DEFAULT_WEEKS=4
```

---

### Admin account

The app expects an admin user in the users table. Options:
- Use your init SQL to seed an admin user (email + hashed password).
- Or generate a password hash and insert via phpMyAdmin:

```bash
# generate a bcrypt hash locally
php -r 'echo password_hash("admin123", PASSWORD_DEFAULT).PHP_EOL;'
```

Then in phpMyAdmin, run:

```sql
INSERT INTO users (name, vorname, email, password, benutzertyp)
VALUES ('Admin','Libri','admin@example.com','<paste_hash_here>','admin');
```

After first login, change the password.

---

### How to use

- Katalogisierung (Admin → Cataloging)
  - Add via ISBN → metadata + cover suggestions from multiple sources
  - Choose a cover (optionally save locally for speed)
- Bestand (Admin → Cataloging → Manage inventory)
  - Filter by title/author/ISBN/barcode, edit or delete
- Ausleihe (Admin → Loans)
  - Pick user and book; confirm checkout
  - Return moves entry to history
- OPAC (Homepage)
  - Students search title/author/ISBN/barcode; see availability
- Sicherung (Admin → Backup)
  - SQL Export: full dump (DROP/CREATE/INSERT)
  - SQL Import: replaces entire database
  - CSV Export: one file per table with headers
  - CSV Import: truncates tables and imports from files

---

### Backup & Restore details

- SQL Export
  - Produces a standards‑compliant dump with DROP/CREATE/INSERT and FK handling
- SQL Import
  - Drops all tables, runs uploaded SQL
- CSV Export
  - One CSV per table, UTF‑8 with BOM, semicolon-delimited, first row = headers
- CSV Import
  - Truncates all tables, temporarily disables foreign keys, then inserts rows
  - File names should match the table name, e.g. books.csv

Tip: Always create a SQL export before large imports.

---

### Cover fetching

- Sources
  - Google Books (imageLinks, multiple sizes)
  - Open Library (ISBN endpoints + search)
- Strategy
  - Try ISBN (13/10 variants), validate URLs via HTTP HEAD
  - Fallback search by title/author if ISBN returns no image
  - Optional local download to src/media/covers for speed and stability

---

### Project structure

```
LibriLeves/
├── docker/
│   ├── Dockerfile
│   ├── docker-compose.yml
│   └── init-perms.sh
├── src/
│   ├── admin/
│   │   ├── books/… (cataloging, ISBN add, cover re-search)
│   │   ├── loans/… (loan/return)
│   │   ├── users/… (user management)
│   │   └── backup/… (SQL/CSV export & import)
│   ├── includes/
│   │   ├── isbn.php
│   │   └── covers.php
│   ├── media/ (logos, favicons, covers/)
│   ├── menu.php, style.css
│   └── index.php (OPAC search)
└── db_data/ (local MariaDB data, not versioned)
```

---

### Development tips

- phpMyAdmin
  - URL: http://localhost:5678
  - PMA_HOST=db, PMA_USER=bibadmin, PMA_PASSWORD=bibadmin
- Logs
  - Container logs: docker compose logs -f web
- Rebuild after code changes in docker/ only:
  ```bash
  docker compose up -d --build web
  ```
- Cache/Hard reload
  - Vivaldi/Chrome: Shift + Reload or Ctrl+F5 / Cmd+Shift+R

---

### Permissions

The container initializes correct permissions for media/covers at startup:
- Directories: 2775 (setgid for group inheritance)
- Files: 664
- Owner: www-data

If you bind‑mount src/, the init‑script ensures Apache can write to src/media/covers.

---

### Security notes

- Admin area protected by login
- Passwords stored as bcrypt hashes
- Use long, unique admin passwords
- Keep docker/.env out of version control
- Backups may contain sensitive data – store them securely

---

### Troubleshooting

- “Permission denied” on cover download
  - Restart containers to rerun init‑perms: docker compose up -d
- No covers found
  - Add a GOOGLE_BOOKS_API_KEY and retry
  - Ensure outbound internet access from the container
- Slow searches
  - First call may hit external APIs; subsequent loads are faster with local covers

---

### Customization

- Logos: replace files in src/media/
  - Logo icon: LogoNurGesicht.png
  - Wordmark: LogoNurTitel.png
- Favicon/manifest: src/media/favicon.svg + generated PNGs in src/
- Colors: adjust CSS variables at the top of src/style.css

---

### Tech stack

- PHP 8.2 + Apache
- MariaDB
- Docker / Docker Compose
- phpMyAdmin
- Google Books API, Open Library (covers)

---

### License

- MIT License – see LICENSE.

---

### Acknowledgments

- 📚 Google Books API
- 📖 Open Library
- 💛 Everyone contributing to school libraries

---

### Roadmap ideas

- ZIP export of all CSVs in one click
- Batch auto‑cover mode (no prompts)
- CSV schema validator and sample files
- Role “teacher” with limited admin powers
- E‑mail reminders for due loans

Happy shelving! 🚀
