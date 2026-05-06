<?php
$page_title = isset($page_title) ? $page_title : 'Fix These Sentences Streak App';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="css/app.css">
</head>
<body>
    <main class="app-shell">
        <?php echo $page_content; ?>
    </main>
    <script src="js/app.js"></script>
</body>
</html>
