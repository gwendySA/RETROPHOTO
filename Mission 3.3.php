<?php
$host = 'localhost';
$dbname = 'c5basedistribtest';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    echo "✅ Connexion réussie à la base de données.<br><br>";

    // Fonction pour récupérer le nom du pays à partir du code ISO via l'API RestCountries
    function getCountryNameFromISO($isoCode) {
        $isoCode = trim($isoCode); // Enlever les espaces autour du code ISO

        // API RestCountries pour récupérer les informations du pays par le code ISO
        $url = "https://restcountries.com/v3.1/alpha/{$isoCode}";

        // Utilisation de file_get_contents() pour obtenir les données JSON depuis l'API
        $response = file_get_contents($url);

        if ($response === FALSE) {
            return null; // Si la récupération échoue, retourner null
        }

        $countryData = json_decode($response, true);

        // Si les données sont valides, retourner le nom du pays
        if (isset($countryData[0]['name']['common'])) {
            return $countryData[0]['name']['common'];
        }

        return null; // Si le pays n'est pas trouvé, retourner null
    }

    // Ajouter le traitement spécifique pour "FO" (Îles Féroé)
    function handleSpecialCases($isoCode) {
        if ($isoCode === 'FO') {
            return 'Îles Féroé';  // Si "FO", on retourne "Îles Féroé"
        }
        return null; // Aucun traitement spécial pour d'autres codes ISO
    }

    // Sélectionner les localités où LO_PAYS = 0 (pas de pays renseigné)
    $sql = "SELECT LO_COMPTEUR, LO_DEPARTEMENT FROM Localite WHERE LO_PAYS = 0";
    $stmt = $pdo->query($sql);
    $localites = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Traiter les localités
    $compteurMAJ = 0;
    foreach ($localites as $loc) {
        $id = $loc['LO_COMPTEUR'];
        $dep = trim($loc['LO_DEPARTEMENT']); // Enlever les espaces au début et à la fin

        // Ignorer les départements vides ou non pertinents
        if (empty($dep)) {
            continue;
        }

        // Vérifier si LO_DEPARTEMENT est un nombre
        if (is_numeric($dep)) {
            // Si LO_DEPARTEMENT est un numéro, on suppose que c'est un département français
            $countryName = 'France';
            $isoCode = 'FR'; // Code ISO de la France
            $newPays = 1; // Associer à l'ID 1 pour la France
        } else {
            // Si ce n'est pas un numéro, chercher un pays en utilisant l'API avec le code ISO
            $isoCode = strtoupper(trim($dep)); // Assurer que le code ISO soit en majuscules

            // Traiter les cas spéciaux comme "FO"
            $countryName = handleSpecialCases($isoCode);
            if (!$countryName) {
                $countryName = getCountryNameFromISO($isoCode); // Récupérer le nom du pays via l'API
            }

            // Si le pays n'est pas trouvé, on l'ignore
            if ($countryName === null) {
                echo "❌ Code ISO '$isoCode' introuvable pour ID $id<br>";
                continue;
            }

            // Vérifier si le pays existe déjà dans la table Pays
            $stmtPays = $pdo->prepare("SELECT PA_COMPTEUR FROM Pays WHERE PA_ISO = :iso LIMIT 1");
            $stmtPays->execute(['iso' => $isoCode]);
            $rowPays = $stmtPays->fetch(PDO::FETCH_ASSOC);

            if ($rowPays) {
                // Si le pays existe déjà, utiliser son ID
                $newPays = $rowPays['PA_COMPTEUR'];
            } else {
                // Si le pays n'existe pas, l'ajouter
                echo "Ajout du pays '$countryName' avec le code ISO '$isoCode' à la base de données...<br>";

                // Insérer le pays dans la table Pays
                $stmtInsertPays = $pdo->prepare("INSERT INTO Pays (PA_ISO, PA_NOM) VALUES (:iso, :name)");
                $stmtInsertPays->execute([
                    'iso' => $isoCode,
                    'name' => $countryName
                ]);

                // Récupérer l'ID du pays nouvellement ajouté
                $newPays = $pdo->lastInsertId();
            }
        }

        // Mise à jour de la table Localite avec le pays trouvé ou France (ID 1)
        $update = $pdo->prepare("UPDATE Localite SET LO_PAYS = :pays WHERE LO_COMPTEUR = :id");
        $update->execute([
            'pays' => $newPays,
            'id' => $id
        ]);

        $compteurMAJ++;
    }

    echo "<br>✅ Mise à jour terminée : $compteurMAJ localité(s) modifiée(s).<br>";

} catch (PDOException $e) {
    echo "❌ Erreur de connexion ou requête : " . $e->getMessage();
}
?>
