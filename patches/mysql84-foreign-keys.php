<?php
// Patch appliqué à la construction de l'image sur chaque
// vendor/fleetbase/*/config/database.connections.php.
//
// Fleetbase est développé sur MySQL 8.0, où une clé étrangère peut référencer
// une colonne simplement indexée. Cloudron fournit MySQL 8.4, qui le refuse par
// défaut (restrict_fk_on_non_standard_key=ON) : la toute première migration de
// permissions échoue en « 6125 Missing unique key for constraint ».
//
// On désactive ce garde-fou pour les sessions de l'application, sur toutes ses
// connexions MySQL (principale, sandbox, storefront…). Le commentaire versionné
// /*!80400 … */ n'est exécuté que par MySQL >= 8.4 : sur une version antérieure
// la commande se réduit à « SET @fleetbase_cloudron = 1 », sans effet.
//
// Le build échoue si l'ancre attendue n'est plus là.

$anchor = "if (extension_loaded('pdo_mysql')) {";
$insert = $anchor . "\n"
    . "    // fleetbase-cloudron : MySQL 8.4 refuse par défaut les clés étrangères vers une\n"
    . "    // colonne non unique, que le schéma de Fleetbase (conçu pour 8.0) utilise.\n"
    . "    \$mysql_options[PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET /*!80400 SESSION restrict_fk_on_non_standard_key = OFF, */ @fleetbase_cloudron = 1';";

$files = array_slice($argv, 1);

if ($files === []) {
    fwrite(STDERR, "mysql84-foreign-keys: aucun fichier database.connections.php trouvé\n");
    exit(1);
}

foreach ($files as $file) {
    $code = file_get_contents($file);

    if (substr_count($code, $anchor) !== 1) {
        fwrite(STDERR, "mysql84-foreign-keys: ancre introuvable ou non unique dans {$file}\n");
        exit(1);
    }

    file_put_contents($file, str_replace($anchor, $insert, $code));
    echo "mysql84-foreign-keys: {$file} patché\n";
}
