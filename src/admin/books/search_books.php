<?php
session_start();
include '../../db.php'; // Verbindung zur Datenbank herstellen

// Suche Bücher, bei denen bildlink entweder NULL, leer, der Google Books-Link oder "Nicht verfügbar" ist
$sql = "SELECT id, titel, isbn FROM books 
        WHERE bildlink IS NULL
           OR TRIM(bildlink) = ''
           OR TRIM(bildlink) = 'Nicht verfügbar'
           OR TRIM(bildlink) = 'http://books.google.com/books/content?id=R1aZEAAAQBAJ&printsec=frontcover&img=1&zoom=1&edge=curl&source=gbs_api'";
$result = $conn->query($sql);

$books = array();
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
    <title>Cover Suche</title>
    <style>
        /* Basis-Styling */
        body { font-family: Arial, sans-serif; }
        #book-container {
            max-width: 600px;
            margin: 20px auto;
            text-align: center;
        }
        #cover-img {
            max-width: 200px;
            max-height: 300px;
            margin: 10px;
        }
        #progress {
            margin-top: 20px;
            font-weight: bold;
        }
        button {
            padding: 10px 20px;
            margin: 10px;
        }
    </style>
</head>
<body>
<div id="book-container">
    <h2 id="book-title">Buchtitel</h2>
    <p id="book-isbn"></p>
    <img id="cover-img" src="" alt="Cover Vorschau">
    <div>
        <button id="accept-btn">Cover akzeptieren</button>
        <button id="skip-btn">Weiter</button>
    </div>
    <div id="progress"></div>
</div>

<script>
console.log("JavaScript ist aktiv.");

// Die Buchdaten aus der Datenbank (als JSON aus PHP)
let books = <?php echo json_encode($books); ?>;
let currentIndex = 0;

function updateProgress() {
    let progressText = "Buch " + (currentIndex + 1) + " von " + books.length;
    document.getElementById("progress").innerText = progressText;
}

function showBook() {
    if (currentIndex >= books.length) {
        document.getElementById("book-container").innerHTML = "<h2>Keine weiteren Bücher ohne passenden Coverlink.</h2>";
        return;
    }
    let book = books[currentIndex];
    document.getElementById("book-title").innerText = book.titel;
    document.getElementById("book-isbn").innerText = "ISBN: " + book.isbn;
    updateProgress();

    // Statt eines statischen Links wird nun per AJAX der alternative Cover-Link (über DuckDuckGo) abgefragt.
    let xhr = new XMLHttpRequest();
    xhr.open("GET", "searchcover.php?isbn=" + encodeURIComponent(book.isbn), true);
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            try {
                let response = JSON.parse(xhr.responseText);
                if(response.cover){
                    document.getElementById("cover-img").src = response.cover;
                    document.getElementById("cover-img").alt = "Cover von " + book.titel;
                } else {
                    document.getElementById("cover-img").src = "";
                    document.getElementById("cover-img").alt = "Kein Cover gefunden.";
                }
            } catch(e) {
                document.getElementById("cover-img").src = "";
                document.getElementById("cover-img").alt = "Fehler bei der Cover-Suche.";
            }
        }
    };
    xhr.send();
}

// Klick-Handler: Cover akzeptieren (speichert per AJAX die Auswahl in der DB)
document.getElementById("accept-btn").addEventListener("click", function() {
    let book = books[currentIndex];
    let coverUrl = document.getElementById("cover-img").src;
    
    let xhr = new XMLHttpRequest();
    xhr.open("POST", "update.php", true);
    xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    xhr.onreadystatechange = function(){
        if (xhr.readyState === 4 && xhr.status === 200) {
            currentIndex++;
            showBook();
        }
    };
    xhr.send("id=" + encodeURIComponent(book.id) + "&bildlink=" + encodeURIComponent(coverUrl));
});

// Klick-Handler: Überspringen
document.getElementById("skip-btn").addEventListener("click", function() {
    currentIndex++;
    showBook();
});

// Start: Erstes Buch anzeigen (oder Hinweis, wenn keine Bücher fehlen)
if (books.length > 0) {
    showBook();
} else {
    document.getElementById("book-container").innerHTML = "<h2>Alle Bücher haben bereits einen passenden Coverlink.</h2>";
}
</script>
</body>
</html>
