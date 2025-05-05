<?php
$host = 'localhost';
$dbname = 'c5basedistribtest';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    echo "✅ Connexion réussie à la base de données.<br><br>";

    // 1. Localités françaises (57, 67, 68)
    $stmtFr = $pdo->prepare("
        SELECT LO_COMPTEUR, LO_LOCALITE, LO_DEPARTEMENT 
        FROM Localite 
        WHERE LO_DEPARTEMENT IN ('57', '67', '68') 
          AND LO_LOCALITE IS NOT NULL
    ");
    $stmtFr->execute();
    $frLocalites = $stmtFr->fetchAll(PDO::FETCH_ASSOC);

    // Index FR par nom (lowercase)
    $frIndex = [];
    foreach ($frLocalites as $fr) {
        $nom = mb_strtolower(trim($fr['LO_LOCALITE']));
        $frIndex[$nom]['ids'][] = $fr['LO_COMPTEUR'];
        $frIndex[$nom]['departements'][] = $fr['LO_DEPARTEMENT'];
        $frIndex[$nom]['nom_original'] = $fr['LO_LOCALITE'];
    }

    // 2. Localités allemandes
    $stmtDe = $pdo->prepare("
        SELECT LO_COMPTEUR, LO_LOCALITE 
        FROM Localite 
        WHERE LO_PAYS = 2 
          AND LO_LOCALITE IS NOT NULL
    ");
    $stmtDe->execute();
    $deLocalites = $stmtDe->fetchAll(PDO::FETCH_ASSOC);

    // Index DE par nom (lowercase)
    $deIndex = [];
    foreach ($deLocalites as $de) {
        $nom = mb_strtolower(trim($de['LO_LOCALITE']));
        $deIndex[$nom][] = $de['LO_COMPTEUR'];
    }

    // 3. Construction du CSV
    $homonymes = [["IDS_FR", "NOM_LOCALITE", "DEPARTEMENTS", "IDS_ALLEMANDS"]];
    foreach ($frIndex as $nomLower => $dataFr) {
        if (isset($deIndex[$nomLower])) {
            $idsFr = implode(',', $dataFr['ids']);
            $deptsFr = implode(',', array_unique($dataFr['departements']));
            $idsDe = implode(',', $deIndex[$nomLower]);
            $homonymes[] = [$idsFr, $dataFr['nom_original'], $deptsFr, $idsDe];
        }
    }

    // 4. Export CSV
    $fileName = "homonymes_fr_de_groupes.csv";
    $filePath = __DIR__ . '/' . $fileName;
    $fp = fopen($filePath, 'w');

    foreach ($homonymes as $row) {
        fputcsv($fp, $row, ';');
    }
    fclose($fp);

    echo "Fichier CSV généré : <a href='" . htmlspecialchars($fileName) . "' download>Télécharger</a><br>";
    echo "Total homonymes trouvés : " . (count($homonymes) - 1);

} catch (PDOException $e) {
    echo "Erreur : " . $e->getMessage();
}
?>
