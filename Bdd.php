<?php
class Bdd {
    private static ?PDO $pdo = null;

    public static function getConnexion(): PDO {
        if (self::$pdo === null) {
            $hôte = 'localhost';
            $base = 'c5basedistribtest';
            $utilisateur = 'root';
            $motDePasse = '';

            try {
                self::$pdo = new PDO("mysql:host=$hôte;dbname=$base;charset=utf8", $utilisateur, $motDePasse);
                self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                echo "✅ Connexion réussie à la base de données.<br><br>";
            } catch (PDOException $e) {
                die("Erreur de connexion : " . $e->getMessage());
            }
        }
        return self::$pdo;
    }
}
?>
