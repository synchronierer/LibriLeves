-- docker/init.sql

-- Schema für LibriLeves

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  vorname VARCHAR(100) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  benutzertyp ENUM('admin','leser') NOT NULL DEFAULT 'leser'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS books (
  id INT AUTO_INCREMENT PRIMARY KEY,
  titel VARCHAR(255) NOT NULL,
  autor VARCHAR(255),
  mindestalter VARCHAR(50),
  erscheinungsjahr VARCHAR(50),
  verlag VARCHAR(255),
  isbn VARCHAR(20),
  ort VARCHAR(255),
  barcode VARCHAR(64) UNIQUE,
  bildlink TEXT,
  mediennummer VARCHAR(64) UNIQUE,
  herausgeber VARCHAR(255),
  INDEX idx_books_titel (titel),
  INDEX idx_books_autor (autor),
  INDEX idx_books_isbn (isbn),
  INDEX idx_books_barcode (barcode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS loans (
  loan_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  book_id INT NOT NULL UNIQUE,
  loan_date DATETIME NOT NULL,
  return_date DATETIME NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
  INDEX idx_loans_return_date (return_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS historie (
  id INT AUTO_INCREMENT PRIMARY KEY,
  loan_id INT NOT NULL,
  user_id INT NOT NULL,
  book_id INT NOT NULL,
  loan_date DATETIME NOT NULL,
  return_date DATETIME NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (book_id) REFERENCES books(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Optional: Beispielbuch zum Testen (ohne eindeutige Felderkonflikte)
INSERT INTO books (titel, autor, isbn, barcode, ort)
VALUES ('Beispielbuch', 'Max Muster', '9780000000002', '9780000000002_001', 'Regal A')
ON DUPLICATE KEY UPDATE titel = VALUES(titel);
