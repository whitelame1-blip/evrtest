<div id="app-viewport">
      <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flag-icons/6.6.6/css/flag-icons.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="dashboard.css">
<script src="dashboard.js"></script>
    </div>

<script>
// Function to swap pages without refreshing
function navigateTo(url) {
    fetch(url)
        .then(response => response.text())
        .then(html => {
            document.getElementById('app-viewport').innerHTML = html;
        });
}

// Load dashboard by default
window.onload = () => navigateTo('dashboard.php');
</script>
