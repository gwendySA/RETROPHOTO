<?php
$host = 'localhost';
$dbname = 'c5basedistribtest';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    echo "✅ Connexion réussie à la base de données.<br><br>";

    echo "<h3>🔍 Doublons stricts de localités dans même département et même pays :</h3>";

    // Création du fichier CSV
    $csvFile = fopen("homonymes_stricts.csv", "w");
    if (!$csvFile) {
        die("❌ Impossible d'ouvrir le fichier CSV.");
    }

    // En-têtes CSV
    fputcsv($csvFile, ['LO_COMPTEUR', 'Nom', 'Département', 'Pays']);

    // Requête de regroupement des doublons
    $sql = "
        SELECT LO_LOCALITE, LO_DEPARTEMENT, LO_PAYS, COUNT(*) as nb
        FROM Localite
        WHERE LO_LOCALITE NOT LIKE '%DIVERS%'
        GROUP BY LO_LOCALITE, LO_DEPARTEMENT, LO_PAYS
        HAVING nb > 1
    ";
    $stmt = $pdo->query($sql);
    $doublons = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($doublons as $groupe) {
        $nom = $groupe['LO_LOCALITE'];
        $dep = $groupe['LO_DEPARTEMENT'];
        $pays = $groupe['LO_PAYS'];
        $nb = $groupe['nb'];

        echo "<strong>" . htmlspecialchars($nom) . "</strong> : $nb doublons<br>";

        // Récupération des localités détaillées
        $sqlDetails = "
            SELECT LO_COMPTEUR, LO_LOCALITE, LO_DEPARTEMENT, LO_PAYS
            FROM Localite
            WHERE LO_LOCALITE = :nom
              AND LO_DEPARTEMENT = :dep
              AND LO_PAYS = :pays
        ";
        $stmtDetails = $pdo->prepare($sqlDetails);
        $stmtDetails->execute([
            'nom' => $nom,
            'dep' => $dep,
            'pays' => $pays
        ]);
        $localites = $stmtDetails->fetchAll(PDO::FETCH_ASSOC);

        foreach ($localites as $loc) {
            echo "- ID {$loc['LO_COMPTEUR']} | Département: {$loc['LO_DEPARTEMENT']} | Pays: {$loc['LO_PAYS']}<br>";
            fputcsv($csvFile, [$loc['LO_COMPTEUR'], $loc['LO_LOCALITE'], $loc['LO_DEPARTEMENT'], $loc['LO_PAYS']]);
        }

        echo "<br>";
    }

    fclose($csvFile);
    echo "<br>✅ Export CSV terminé dans <strong>homonymes_stricts.csv</strong>";

} catch (PDOException $e) {
    echo "❌ Erreur : " . $e->getMessage();
}
?>
