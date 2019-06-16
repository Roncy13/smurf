<?php
define("SITE_ROOT", str_replace("/api/Seeders", "",getcwd()));
define("API_MODEL", SITE_ROOT."/api/core/Model.php");
define("MODELS", SITE_ROOT."/api/Models/");

require SITE_ROOT.'/api/Libraries/Faker-master/src/autoload.php';
require SITE_ROOT.'/api/core/db.php';
?>