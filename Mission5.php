<?php
require_once 'bdd.php';

function normaliser($str) {
    $str = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str);
    return strtolower(preg_replace('/[\s\(\)\-\'".,]/u', '', $str));
}

$resultat = null;

if ((isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') || php_sapi_name() === 'cli') {
    $pdo = Bdd::getConnexion();

    // APIs publiques
    $regions = json_decode(file_get_contents('https://geo.api.gouv.fr/regions'), true);
    $departements = json_decode(file_get_contents('https://geo.api.gouv.fr/departements'), true);
    $pays = json_decode(file_get_contents('https://restcountries.com/v3.1/all'), true);

    $localites = [];

    // Ajout des pays
    foreach ($pays as $p) {
        $nom = $p['translations']['fra']['common'] ?? $p['name']['common'];
        $nomFinal = $nom . ' chercher par nom de localité';
        $localites[] = $nomFinal;
    }

    // Ajout des régions
    foreach ($regions as $r) {
        $nom = $r['nom'] . ' chercher par nom de localité';
        $localites[] = $nom;
    }

    // Ajout des départements
    foreach ($departements as $d) {
        $nom = $d['nom'] . ' chercher par nom de localité';
        $localites[] = $nom;
    }

    // Récupération des localités existantes
    $localitesExistantes = $pdo->query("SELECT LO_LOCALITE FROM Localite")->fetchAll(PDO::FETCH_COLUMN);
    $localitesExistantesNorm = array_map('normaliser', $localitesExistantes);

    // Récupération de l'ID max
    $stmt = $pdo->query("SELECT MAX(LO_COMPTEUR) FROM Localite");
    $maxId = $stmt->fetchColumn();
    if ($maxId === null) $maxId = 0;

    $ajoutees = [];

    foreach ($localites as $nom) {
        $nomNorm = normaliser($nom);

        if (!in_array($nomNorm, $localitesExistantesNorm)) {
            $maxId++;

            $stmt = $pdo->prepare("INSERT INTO Localite (LO_COMPTEUR, LO_LOCALITE) VALUES (:id, :nom)");
            $stmt->execute(['id' => $maxId, 'nom' => $nom]);

            $ajoutees[] = $nom;
        }
    }

    // Génération d'un fichier CSV
    if (!empty($ajoutees)) {
        $fichierCSV = fopen('localites_ajoutees.csv', 'w');
        fputcsv($fichierCSV, ['ID', 'Localité']);
        $startId = $maxId - count($ajoutees) + 1;
        foreach ($ajoutees as $i => $nomLocalite) {
            fputcsv($fichierCSV, [$startId + $i, $nomLocalite]);
        }
        fclose($fichierCSV);
    }

    // Résultat
    $resultat = [
        'total' => count($ajoutees),
        'localites' => $ajoutees
    ];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mission 5 – Création Localités</title>
    <style>
        body { font-family: sans-serif; margin: 2em; }
        button { padding: 0.5em 1em; font-size: 16px; }
        .success { margin-top: 1em; padding: 1em; background: #e0ffe0; border: 1px solid #88cc88; }
    </style>
</head>
<body>
<h1>Mission 5 – Ajout automatique des localités</h1>
<form method="POST">
    <button type="submit">Exécuter la mission</button>
</form>

<?php if ($resultat): ?>
    <div class="success">
        <h2>✔ <?= $resultat['total'] ?> localité(s) ajoutée(s)</h2>
        <ul>
            <?php foreach ($resultat['localites'] as $loc): ?>
                <li><?= htmlspecialchars($loc) ?></li>
            <?php endforeach; ?>
        </ul>
        <p>📄 Le fichier <strong>localites_ajoutees.csv</strong> a été généré.</p>
    </div>
<?php endif; ?>
</body>
</html>
