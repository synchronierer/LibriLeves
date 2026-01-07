<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/security.php';

start_secure_session();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email    = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    $sql = "SELECT * FROM users WHERE email = ? AND benutzertyp = 'admin'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['role']    = $user['benutzertyp'];

            // Flash setzen
            flash_success('Erfolgreich als Admin angemeldet.');

            // Ziel bestimmen: ggf. vorherige Seite, sonst direkt zur Katalogisierung
            $redirectUrl = $_SESSION['redirect_after_login'] ?? 'books/index.php';
            unset($_SESSION['redirect_after_login']);
            header("Location: $redirectUrl");
            exit();
        } else {
            $error = "Ungültiges Passwort.";
        }
    } else {
        $error = "Benutzer nicht gefunden.";
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leseecke - Büchereiverwaltung</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <?php include '../menu.php'; ?>
    <h2>Admin Login</h2>
    <?php if (!empty($error)): ?>
        <div class="popup error" style="display:block;"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <form action="" method="POST">
        <input type="email" name="email" placeholder="E-Mail" required>
        <input type="password" name="password" placeholder="Passwort" required>
        <input type="submit" value="Einloggen">
    </form>
</body>
</html>
