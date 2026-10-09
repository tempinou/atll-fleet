<?php
// Crée les bases MySQL de Fleetbase (principale, sandbox, storefront) si elles
// n'existent pas. Avec l'addon mysql « multipleDatabases », Cloudron ne crée
// aucune base : l'application doit le faire. `artisan mysql:createdb` ne convient
// pas : il ouvre d'abord une connexion sur la base configurée (inexistante au
// premier démarrage) et n'entoure pas le nom de backticks, or le préfixe attribué
// par Cloudron peut commencer par un chiffre (« 1e08… » est lu comme un nombre).
// Ici on se connecte sans base sélectionnée et on protège les noms.
//
// Liste à compléter si une extension serveur déclare une base supplémentaire
// (clé « create-database » dans son composer.json) — seule storefront le fait.

$host     = getenv('DB_HOST');
$port     = getenv('DB_PORT') ?: '3306';
$user     = getenv('DB_USERNAME');
$password = getenv('DB_PASSWORD');
$database = getenv('DB_DATABASE');

if (!$host || !$user || !$database) {
    fwrite(STDERR, "createdb: DB_HOST, DB_USERNAME et DB_DATABASE sont requis\n");
    exit(1);
}

$pdo = new PDO("mysql:host={$host};port={$port}", $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_TIMEOUT => 10,
]);

foreach ([$database, $database . '_sandbox', $database . '_storefront'] as $name) {
    if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
        fwrite(STDERR, "createdb: nom de base invalide : {$name}\n");
        exit(1);
    }

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "createdb: {$name} OK\n";
}
