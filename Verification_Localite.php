<?php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

function envoyer($data) {
    echo "data: " . json_encode($data) . "\n\n";
    ob_flush(); flush();
}

function nettoyer($texte) {
    $texte = mb_strtolower($texte, 'UTF-8');
    $texte = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texte);
    return preg_replace('/[^a-z ]/', '', $texte);
}

$paysId = isset($_GET['pays']) && is_numeric($_GET['pays']) ? intval($_GET['pays']) : null;
$pdo = new PDO("mysql:host=localhost;dbname=c5basedistribtest;charset=utf8", 'root', '');
$sql = "SELECT LO_COMPTEUR, LO_LOCALITE FROM Localite WHERE LO_LOCALITE NOT LIKE '%DIVERS%'";
if ($paysId) $sql .= " AND LO_PAYS = " . $pdo->quote($paysId);

$localites = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
$total = count($localites);
$index = 0;

$f = fopen("localites_invalides.csv", "w");
fputcsv($f, ['ID', 'Localité', 'Résultat API']);

foreach ($localites as $loc) {
    $index++;
    envoyer(['total' => $total, 'index' => $index]);

    $id = $loc['LO_COMPTEUR'];
    $nom = $loc['LO_LOCALITE'];
    $url = "https://nominatim.openstreetmap.org/search?q=" . urlencode($nom) . "&format=json&accept-language=fr";
    $ctx = stream_context_create(['http' => ['header' => "User-Agent: LocaliteVerif/1.0\r\n"]]);
    $json = @file_get_contents($url, false, $ctx);
    $data = json_decode($json, true);
    $apiNom = $data[0]['display_name'] ?? null;
    $apiCourt = $apiNom ? explode(',', $apiNom)[0] : null;

    if (!$apiCourt || nettoyer($apiCourt) !== nettoyer($nom)) {
        fputcsv($f, [$id, $nom, $apiCourt]);
        envoyer(['id' => $id, 'nom' => $nom, 'api' => $apiCourt]);
    }

    usleep(250000); // pour éviter d’être bloqué
}
fclose($f);
envoyer("[FIN]");
