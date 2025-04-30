<?php
$host = 'localhost';
$dbname = 'c5basedistribtest';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    echo "✅ Connexion réussie à la base de données.<br><br>";

    function getCountryNameFromISO($isoCode) {
        $isoCode = trim($isoCode);
        $url = "https://restcountries.com/v3.1/alpha/{$isoCode}";
        $response = @file_get_contents($url);
        if ($response === FALSE) return null;
        $countryData = json_decode($response, true);
        return $countryData[0]['name']['common'] ?? null;
    }

    function handleSpecialCases($isoCode) {
        return $isoCode === 'FO' ? 'Îles Féroé' : null;
    }

    $sql = "SELECT LO_COMPTEUR, LO_DEPARTEMENT FROM Localite WHERE LO_PAYS = 0";
    $stmt = $pdo->query($sql);
    $localites = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $compteurMAJ = 0;
    $csvData = [["LO_COMPTEUR", "LO_DEPARTEMENT", "NOM_PAYS", "CODE_ISO", "ID_PAYS"]];

    foreach ($localites as $loc) {
        $id = $loc['LO_COMPTEUR'];
        $dep = trim($loc['LO_DEPARTEMENT']);

        if (empty($dep)) continue;

        if (is_numeric($dep)) {
            $countryName = 'France';
            $isoCode = 'FR';
            $newPays = 1;
        } else {
            $isoCode = strtoupper(trim($dep));
            $countryName = handleSpecialCases($isoCode) ?? getCountryNameFromISO($isoCode);
            if ($countryName === null) {
                echo " Code ISO '$isoCode' introuvable pour ID $id<br>";
                continue;
            }

            $stmtPays = $pdo->prepare("SELECT PA_COMPTEUR FROM Pays WHERE PA_ISO = :iso LIMIT 1");
            $stmtPays->execute(['iso' => $isoCode]);
            $rowPays = $stmtPays->fetch(PDO::FETCH_ASSOC);

            if ($rowPays) {
                $newPays = $rowPays['PA_COMPTEUR'];
            } else {
                echo "Ajout du pays '$countryName' avec le code ISO '$isoCode'...<br>";
                $stmtInsertPays = $pdo->prepare("INSERT INTO Pays (PA_ISO, PA_NOM) VALUES (:iso, :name)");
                $stmtInsertPays->execute(['iso' => $isoCode, 'name' => $countryName]);
                $newPays = $pdo->lastInsertId();
            }
        }

        $update = $pdo->prepare("UPDATE Localite SET LO_PAYS = :pays WHERE LO_COMPTEUR = :id");
        $update->execute(['pays' => $newPays, 'id' => $id]);
        $compteurMAJ++;

        // Ajouter la ligne au CSV
        $csvData[] = [$id, $dep, $countryName, $isoCode, $newPays];
    }

    echo "<br>Mise à jour terminée : $compteurMAJ localité(s) modifiée(s).<br>";

    // Création du fichier CSV
    $fileName = "export_localites_" . date("Ymd_His") . ".csv";
    $filePath = __DIR__ . '/' . $fileName;
    $fp = fopen($filePath, 'w');

    foreach ($csvData as $row) {
        fputcsv($fp, $row, ';');
    }
    fclose($fp);

    echo "<br>Fichier CSV généré : <a href='$fileName' download>Télécharger le fichier</a>";

} catch (PDOException $e) {
    echo "Erreur : " . $e->getMessage();
}
?>
