<?php
// Crée les bases MySQL de Fleetbase (principale + sandbox) si elles n'existent
// pas. Avec l'addon mysql « multipleDatabases », Cloudron ne crée aucune base :
// l'application doit le faire, et `artisan mysql:createdb` ne le peut pas car il
// ouvre d'abord une connexion sur la base configurée (inexistante au premier
// démarrage). Ici on se connecte sans base sélectionnée.

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

foreach ([$database, $database . '_sandbox'] as $name) {
    if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
        fwrite(STDERR, "createdb: nom de base invalide : {$name}\n");
        exit(1);
    }

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "createdb: {$name} OK\n";
}
