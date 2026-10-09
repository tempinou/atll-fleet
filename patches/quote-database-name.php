<?php
// Patch appliqué à la construction de l'image sur
// vendor/fleetbase/core-api/src/Support/Utils.php.
//
// Les migrations de Fleetbase référencent les tables de la base principale
// depuis les autres bases (sandbox, storefront) en SQL brut :
//     new Expression(Utils::getFleetbaseDatabaseName() . '.companies')
// sans protéger le nom. Or le préfixe de base attribué par Cloudron (addon
// mysql « multipleDatabases ») est hexadécimal et peut commencer par un motif
// chiffre-e-chiffre (ex. « 1e087e… ») que MySQL lit comme un nombre : toutes
// ces migrations échouent alors en erreur de syntaxe 1064.
//
// Ce helper n'est appelé que depuis des migrations, toujours dans une
// Expression brute : renvoyer le nom entre backticks est donc sûr. Le build
// échoue si le texte attendu n'est plus là (version de core-api à revérifier).

$file = $argv[1] ?? '';
$code = file_get_contents($file);

$search = <<<'PHP'
        $databaseName = config('database.connections.' . $connection . '.database');

        return $databaseName;
PHP;

$replace = <<<'PHP'
        $databaseName = config('database.connections.' . $connection . '.database');

        // fleetbase-cloudron : nom protégé par des backticks (préfixe Cloudron
        // pouvant être lu comme un nombre par MySQL). Toujours utilisé en SQL brut.
        if (is_string($databaseName) && $databaseName !== '' && $databaseName[0] !== '`') {
            $databaseName = '`' . str_replace('`', '``', $databaseName) . '`';
        }

        return $databaseName;
PHP;

if (substr_count($code, $search) !== 1) {
    fwrite(STDERR, "quote-database-name: motif introuvable ou non unique dans {$file}\n");
    exit(1);
}

file_put_contents($file, str_replace($search, $replace, $code));
echo "quote-database-name: Utils::getFleetbaseDatabaseName() patché\n";
