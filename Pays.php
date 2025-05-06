<?php
$host = 'localhost';
$dbname = 'c5basedistribtest';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    echo "✅ Connexion réussie à la base de données.<br><br>";

    if (!class_exists('Transliterator')) {
        die("❌ L'extension PHP intl n'est pas activée !");
    }

    // 🔧 MODIF : Ajout de l'ID du pays en paramètre
    $idPaysCible = 2;    if (!$idPaysCible) {
        die("❌ ID de pays manquant ou invalide.");
    }

    // 🔣 Fonctions de nettoyage et transformation
    function cleanString($str) {
        $str = mb_strtolower($str, 'UTF-8');
        $str = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str);
        return preg_replace('/[^a-z ]/', '', $str);
    }

    function getFirstPart($str) {
        $parts = explode(',', $str);
        return trim($parts[0]);
    }

    function transliterateIfNeeded($text) {
        if (preg_match('/[^\x20-\x7E]/', $text)) {
            $trans = Transliterator::create('Any-Latin; Latin-ASCII;');
            return $trans->transliterate($text);
        }
        return $text;
    }

    function getCountryLanguageByISO($isoCode) {
        $languages = [ 'ZA'=>'en','AL'=>'sq','AD'=>'ca','AO'=>'pt','AI'=>'en','AG'=>'en','SA'=>'ar','AR'=>'es',
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
        return isset($languages[$isoCode]) ? $languages[$isoCode] : 'fr';
    }

    function getLocalNameFromAPI($nom, $lang = 'fr') {
        $query = urlencode($nom);
        $url = "https://nominatim.openstreetmap.org/search?q=$query&format=json&accept-language=$lang";

        $opts = [
            "http" => [
                "header" => "User-Agent: LocalisationCheckScript/1.0\r\n"
            ]
        ];
        $context = stream_context_create($opts);
        $response = @file_get_contents($url, false, $context);
        $data = json_decode($response, true);

        if (!empty($data) && isset($data[0]['display_name'])) {
            $localName = getFirstPart($data[0]['display_name']);
            return transliterateIfNeeded($localName);
        }

        return null;
    }

    // 🧾 Fichier CSV
    $csvFile = fopen('localites_diff.csv', 'w');
    if (!$csvFile) die("❌ Impossible d'ouvrir le fichier CSV.");
    fputcsv($csvFile, ['ID Localite', 'ID Pays', 'Nom Base', 'Nom Local', 'Nouveau Nom Base']);

    echo "<h3>🌍 Localités du pays ID $idPaysCible avec nom local différent :</h3>";

    // 🔧 MODIF : Requête ciblée sur un seul pays
    $sql = "SELECT LO_COMPTEUR, LO_LOCALITE, LO_PAYS FROM Localite WHERE LO_PAYS = :idPays";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['idPays' => $idPaysCible]);

    $localitesAmettreAJour = [];

    foreach ($stmt as $row) {
        $nomBase = $row['LO_LOCALITE'];
        $paysID = $row['LO_PAYS'];

        // Ignorer les noms contenant "DIVERS"
        if (stripos($nomBase, 'divers') !== false) continue;

        $stmtCountry = $pdo->prepare("SELECT PA_ISO FROM Pays WHERE PA_COMPTEUR = :paysID LIMIT 1");
        $stmtCountry->execute(['paysID' => $paysID]);
        $countryRow = $stmtCountry->fetch(PDO::FETCH_ASSOC);

        $languePays = $countryRow ? getCountryLanguageByISO($countryRow['PA_ISO']) : 'fr';
        $nomLocalOrig = getLocalNameFromAPI($nomBase, $languePays);

        $nomBaseClean = cleanString($nomBase);
        $nomLocalOrigClean = cleanString($nomLocalOrig);

        if ($nomLocalOrig && $nomLocalOrigClean !== $nomBaseClean) {
            $nomAvecTraduction = $nomBase . ' (' . $nomLocalOrig . ')';

            fputcsv($csvFile, [$row['LO_COMPTEUR'], $paysID, $nomBase, $nomLocalOrig, $nomAvecTraduction]);

            $localitesAmettreAJour[] = [
                'id' => $row['LO_COMPTEUR'],
                'nomBase' => $nomBase,
                'nouveauNom' => $nomAvecTraduction,
                'paysID' => $paysID
            ];

            echo "<strong>$nomBase</strong> ➜ traduit/localisé en : " . htmlspecialchars($nomLocalOrig) . "<br>";
        }
    }

    fclose($csvFile);
    echo "<br>✅ Résultats enregistrés dans <strong>localites_diff.csv</strong>.";

    // 💾 Mise à jour base de données
    echo "<br><h3>Mise à jour de la base de données...</h3>";

    foreach ($localitesAmettreAJour as $localite) {
        $update = $pdo->prepare("UPDATE Localite SET LO_LOCALITE = :nouveauNom WHERE LO_COMPTEUR = :id");
        $update->execute([
            'nouveauNom' => $localite['nouveauNom'],
            'id' => $localite['id']
        ]);
    }

    echo "<br>✅ Mise à jour terminée.";

} catch (PDOException $e) {
    echo "❌ Erreur de connexion : " . $e->getMessage();
}
?>
