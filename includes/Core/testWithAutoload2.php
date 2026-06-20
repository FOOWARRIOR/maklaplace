<?php
echo 'Before require_once';
 = 'C:\Users\Yazid\Local Sites\01\app\public\wp-content\plugins\maklaplace\vendor/autoload.php';
echo 'File exists: ' . var_export(file_exists(), true);
 = require_once ;
echo 'After require_once: ' . var_export(, true);
