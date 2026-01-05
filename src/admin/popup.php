<?php
function showPopup($message, $type = 'error') {
    $popupClass = $type === 'error' ? 'alert-danger' : 'alert-success';
    echo "<div class='alert $popupClass mt-3' role='alert'>";
    echo htmlspecialchars($message);
    echo "</div>";
}
?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const popup = document.querySelector('.alert');
        if (popup) {
            popup.style.display = 'block';
            setTimeout(() => {
                popup.style.display = 'none';
            }, 5000); // Popup nach 5 Sekunden ausblenden
        }
    });
</script>

