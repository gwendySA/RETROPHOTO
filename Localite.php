<?php
// Important : aucune sortie avant ces headers !
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Access-Control-Allow-Origin: *');

require_once 'Bdd.php';

set_time_limit(0);
ob_implicit_flush(true);

$bdd = Bdd::getConnexion();

// Étape 1 : Repérage des doublons par localité/département/pays
$requete = "
    SELECT LO_LOCALITE, LO_DEPARTEMENT, LO_PAYS, COUNT(*) as nb
    FROM Localite
    WHERE LO_LOCALITE NOT LIKE '%DIVERS%'
    GROUP BY LO_LOCALITE, LO_DEPARTEMENT, LO_PAYS
    HAVING nb > 1
";

$résultat = $bdd->query($requete);
$doublons = $résultat->fetchAll(PDO::FETCH_ASSOC);
$total = count($doublons);
$index = 0;

// Envoie du total initial
echo "data: " . json_encode(['total' => $total, 'index' => 0]) . "\n\n";
ob_flush(); flush();

// Ouverture ou création du fichier CSV
$csv = fopen("homonymes_stricts.csv", "w");
if (!$csv) {
    echo "data: " . json_encode(['error' => "Impossible d'ouvrir le fichier CSV."]) . "\n\n";
    ob_flush(); flush();
    exit;
}
fputcsv($csv, ['LO_COMPTEUR', 'Nom', 'Département', 'Pays']);

// Étape 2 : Traitement de chaque doublon
foreach ($doublons as $groupe) {
    $nom = $groupe['LO_LOCALITE'];
    $dep = $groupe['LO_DEPARTEMENT'];
    $pays = $groupe['LO_PAYS'];

    $stmt = $bdd->prepare("
        SELECT LO_COMPTEUR, LO_LOCALITE, LO_DEPARTEMENT, LO_PAYS
        FROM Localite
        WHERE LO_LOCALITE = :nom AND LO_DEPARTEMENT = :dep AND LO_PAYS = :pays
    ");
    $stmt->execute([
        'nom' => $nom,
        'dep' => $dep,
        'pays' => $pays
    ]);

    $locs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($locs as $loc) {
        // Écriture dans le CSV
        fputcsv($csv, [
            $loc['LO_COMPTEUR'],
            $loc['LO_LOCALITE'],
            $loc['LO_DEPARTEMENT'],
            $loc['LO_PAYS']
        ]);

        // Envoi au client (SSE)
        echo "data: " . json_encode([
                'id' => $loc['LO_COMPTEUR'],
                'nomBase' => $loc['LO_LOCALITE'],
                'nomLocal' => $loc['LO_LOCALITE'],
                'pays' => $loc['LO_PAYS']
            ]) . "\n\n";
        ob_flush(); flush();
        usleep(50000); // petit délai pour laisser le temps au front
    }

    $index++;
    echo "data: " . json_encode(['total' => $total, 'index' => $index]) . "\n\n";
    ob_flush(); flush();
}

// Clôture
fclose($csv);
echo "data: [FIN]\n\n";
flush();
