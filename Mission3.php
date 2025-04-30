<?php
require_once 'Localite.php';
require_once 'Pays.php';

//$localite = new Localite();
//$localite->exporterDoublonsLocalites();


$pays = new AnalysePays();
$pays->comparerLocalites();
?>
