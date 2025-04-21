<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($data['settings']['sitetitle']) ?></title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Merriweather:wght@300;400;700&family=Source+Sans+Pro:wght@400;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome für Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Externe CSS-Datei -->
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<!-- Blog Header -->
<header class="blog-header">
    <div class="container">
        <h1 class="blog-title"><?= htmlspecialchars($data['settings']['sitetitle']) ?></h1>
        <p class="blog-description"><?= htmlspecialchars($data['settings']['description']) ?></p>
    </div>
</header>

