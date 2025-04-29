<?php
require_once 'Bdd.php';

class AnalysePays {
    private PDO $bdd;
    private array $languages;

    public function __construct() {
        $this->bdd = Bdd::getConnexion();
        $this->languages = $this->chargerLanguesPays();
        $this->verifierColonne();
    }

    private function verifierColonne(): void {
        $verif = $this->bdd->query("SHOW COLUMNS FROM Localite LIKE 'test_nom_etranger'");
        if ($verif->rowCount() === 0) {
            $this->bdd->exec("ALTER TABLE Localite ADD COLUMN test_nom_etranger VARCHAR(255) DEFAULT NULL");
            echo "📦 Colonne 'test_nom_etranger' ajoutée à la table Localite.<br>";
        }
    }

    private function chargerLanguesPays(): array {
        return [ 'ZA'=>'en','AL'=>'sq','AD'=>'ca','AO'=>'pt','AI'=>'en','AG'=>'en','SA'=>'ar','AR'=>'es',
            'AW'=>'nl','AU'=>'en','AZ'=>'az','BS'=>'en','BH'=>'ar','BD'=>'bn','BB'=>'en','BY'=>'be','BE'=>'nl',
            'BZ'=>'es','BM'=>'en','BT'=>'dz','BO'=>'es','BA'=>'bs','BW'=>'en','BR'=>'pt','BN'=>'ms','BG'=>'bg',
            'BI'=>'rn','KH'=>'km','CM'=>'fr','CA'=>'en','CV'=>'pt','CL'=>'es','CN'=>'zh','CO'=>'es','CI'=>'fr',
            'HR'=>'hr','CU'=>'es','DK'=>'da','DJ'=>'ar','DM'=>'en','EG'=>'ar','AE'=>'ar','EC'=>'es','EE'=>'et',
            'ET'=>'am','FI'=>'fi','FR'=>'fr','GA'=>'fr','GM'=>'en','GE'=>'ka','GH'=>'en','GR'=>'el','GT'=>'es',
            'GN'=>'fr','GY'=>'en','HK'=>'zh','HN'=>'es','HU'=>'hu','ID'=>'id','IN'=>'hi','IR'=>'fa','IQ'=>'ar',
            'IE'=>'en','IL'=>'he','IS'=>'is','IT'=>'it','JM'=>'en','JP'=>'ja','JO'=>'ar','KZ'=>'kk','KE'=>'en',
            'KR'=>'ko','KW'=>'ar','LA'=>'lo','LB'=>'ar','LI'=>'de','LT'=>'lt','LU'=>'lb','LV'=>'lv','LY'=>'ar',
            'MA'=>'ar','MC'=>'fr','MD'=>'ro','ME'=>'sr','MG'=>'mg','MK'=>'mk','MM'=>'my','MN'=>'mn','MO'=>'zh',
            'MR'=>'ar','MT'=>'mt','MU'=>'en','MX'=>'es','MY'=>'ms','MZ'=>'pt','NA'=>'en','NE'=>'fr','NG'=>'en',
            'NI'=>'es','NL'=>'nl','NO'=>'no','NP'=>'ne','NZ'=>'en','OM'=>'ar','PA'=>'es','PE'=>'es','PG'=>'en',
            'PH'=>'tl','PK'=>'ur','PL'=>'pl','PT'=>'pt','PY'=>'es','QA'=>'ar','RO'=>'ro','RS'=>'sr','RU'=>'ru',
            'RW'=>'rw','SA'=>'ar','SD'=>'ar','SE'=>'sv','SG'=>'en','SI'=>'sl','SK'=>'sk','SN'=>'fr','SO'=>'so',
            'SR'=>'nl','SV'=>'es','SY'=>'ar','TH'=>'th','TJ'=>'tg','TL'=>'tet','TM'=>'tk','TN'=>'ar','TR'=>'tr',
            'TW'=>'zh','TZ'=>'sw','UA'=>'uk','UG'=>'en','US'=>'en','UY'=>'es','UZ'=>'uz','VE'=>'es','VN'=>'vi',
            'YE'=>'ar','ZA'=>'en','ZM'=>'en','ZW'=>'en'
        ];
    }

    private function langueDepuisISO(string $iso): string {
        return $this->languages[$iso] ?? 'fr';
    }

    private function translittererSiNécessaire(string $texte): string {
        if (preg_match('/[^\x20-\x7E]/', $texte)) {
            $trans = Transliterator::create('Any-Latin; Latin-ASCII;');
            return $trans->transliterate($texte);
        }
        return $texte;
    }

    private function nettoyerTexte(string $texte): string {
        $texte = mb_strtolower($texte, 'UTF-8');
        $texte = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texte);
        return preg_replace('/[^a-z ]/', '', $texte);
    }

    private function extrairePremièrePartie(string $texte): string {
        return trim(explode(',', $texte)[0]);
    }

    private function obtenirNomLocalViaAPI(string $nom, string $lang): ?string {
        $query = urlencode($nom);
        $url = "https://nominatim.openstreetmap.org/search?q=$query&format=json&accept-language=$lang";

        $contexte = stream_context_create([
            "http" => ["header" => "User-Agent: AnalyseLocalites/1.0\r\n"]
        ]);
        $réponse = @file_get_contents($url, false, $contexte);

        if (!$réponse) return null;

        $données = json_decode($réponse, true);
        if (!empty($données[0]['display_name'])) {
            return $this->translittererSiNécessaire($this->extrairePremièrePartie($données[0]['display_name']));
        }

        return null;
    }

    public function comparerLocalites(): void {
        echo "<h3>🌍 Localités avec nom local différent :</h3>";

        $csv = fopen('localites_diff.csv', 'w');
        if (!$csv) die("❌ Impossible d'ouvrir le fichier CSV.");
        fputcsv($csv, ['Nom Base', 'Nom Local', 'Pays ID']);

        $résultats = $this->bdd->query("SELECT LO_LOCALITE, LO_PAYS FROM Localite WHERE LO_PAYS NOT IN (0, 1)");
        foreach ($résultats as $ligne) {
            $nomBase = $ligne['LO_LOCALITE'];
            $paysID = $ligne['LO_PAYS'];

            if (stripos($nomBase, 'divers') !== false) continue;

            $stmtPays = $this->bdd->prepare("SELECT PA_ISO FROM Pays WHERE PA_COMPTEUR = :id LIMIT 1");
            $stmtPays->execute(['id' => $paysID]);
            $pays = $stmtPays->fetch(PDO::FETCH_ASSOC);

            if (!$pays) continue;

            $lang = $this->langueDepuisISO($pays['PA_ISO']);
            $nomLocal = $this->obtenirNomLocalViaAPI($nomBase, $lang);

            if (!$nomLocal) continue;

            $baseClean = $this->nettoyerTexte($nomBase);
            $localClean = $this->nettoyerTexte($nomLocal);

            if ($baseClean !== $localClean) {
                echo "<strong>$nomBase</strong> ➜ localisé : " . htmlspecialchars($nomLocal) . "<br>";
                fputcsv($csv, [$nomBase, $nomLocal, $paysID]);

                $update = $this->bdd->prepare("
                    UPDATE Localite 
                    SET test_nom_etranger = :nom 
                    WHERE LO_LOCALITE = :loc AND LO_PAYS = :pays
                ");
                $update->execute([
                    'nom' => $nomLocal,
                    'loc' => $nomBase,
                    'pays' => $paysID
                ]);
            }
        }

        fclose($csv);
        echo "<br>✅ Export terminé dans <strong>localites_diff.csv</strong><br>";
    }
}
?>
