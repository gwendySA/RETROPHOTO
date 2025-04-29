<?php
require_once 'Bdd.php';

class Localite {
    private PDO $bdd;

    public function __construct() {
        $this->bdd = Bdd::getConnexion();
    }

    public function exporterDoublonsLocalites(): void {
        echo "<h3>🔍 Doublons stricts de localités dans même département et même pays :</h3>";

        $csv = fopen("homonymes_stricts.csv", "w");
        if (!$csv) {
            die("Impossible d'ouvrir le fichier CSV.");
        }

        fputcsv($csv, ['LO_COMPTEUR', 'Nom', 'Département', 'Pays']);

        $requete = "
            SELECT LO_LOCALITE, LO_DEPARTEMENT, LO_PAYS, COUNT(*) as nb
            FROM Localite
            WHERE LO_LOCALITE NOT LIKE '%DIVERS%'
            GROUP BY LO_LOCALITE, LO_DEPARTEMENT, LO_PAYS
            HAVING nb > 1
        ";
        $résultat = $this->bdd->query($requete);
        $doublons = $résultat->fetchAll(PDO::FETCH_ASSOC);

        foreach ($doublons as $groupe) {
            $nom = $groupe['LO_LOCALITE'];
            $dep = $groupe['LO_DEPARTEMENT'];
            $pays = $groupe['LO_PAYS'];

            echo "<strong>" . htmlspecialchars($nom) . "</strong> : {$groupe['nb']} doublons<br>";

            $stmt = $this->bdd->prepare("
                SELECT LO_COMPTEUR, LO_LOCALITE, LO_DEPARTEMENT, LO_PAYS
                FROM Localite
                WHERE LO_LOCALITE = :nom AND LO_DEPARTEMENT = :dep AND LO_PAYS = :pays
            ");
            $stmt->execute([
                'nom' => $nom,
                'dep' => $dep,
                'pays' => $pays
            ]);

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $loc) {
                echo "- ID {$loc['LO_COMPTEUR']} | Département: {$loc['LO_DEPARTEMENT']} | Pays: {$loc['LO_PAYS']}<br>";
                fputcsv($csv, [$loc['LO_COMPTEUR'], $loc['LO_LOCALITE'], $loc['LO_DEPARTEMENT'], $loc['LO_PAYS']]);
            }
            echo "<br>";
        }

        fclose($csv);
        echo "<br>Export CSV terminé dans <strong>homonymes_stricts.csv</strong><br>";
    }
}
?>
