<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- en layout.php <head> -->
    <?php if (function_exists('get_csrf_token')): ?>
        <meta name="csrf-token" content="<?= htmlspecialchars(get_csrf_token()) ?>">
    <?php endif; ?>
    <title>Tarjeta de Fidelidad</title>
    <link rel="icon" type="image/png" href="/build/favicon/favicon-96x96.png" sizes="96x96"/>
    <link rel="icon" type="image/svg+xml" href="/build/favicon/favicon.svg" />
    <link rel="shortcut icon" href="/build/favicon/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/build/favicon/apple-touch-icon.png" />
    <meta name="apple-mobile-web-app-title" content="Laddev" />
    <link rel="manifest" href="/build/favicon/site.webmanifest" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;700;900&display=swap" rel="stylesheet"> 
    <link rel="stylesheet" href="/build/css/app.css">
</head>
<body>
    <?php require_once __DIR__ . '/templates/alerts.php'; ?>
    <?php echo $contenido; ?>
</body>
<script src="/build/js/alerts.js"></script>
</html>