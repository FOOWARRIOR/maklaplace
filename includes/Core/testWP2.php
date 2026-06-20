<?php
echo 'Before define';
define( 'ABSPATH', 'C:\Users\Yazid\Local Sites\01\app\public\wp-content\' );
echo 'After define';
require_once 'C:\Users\Yazid\Local Sites\01\app\public\wp-content\plugins\maklaplace\includes\autoload.php';
echo 'After require_once';
 = new MaklaPlace\Admin\AdminModule(null, null);
echo 'After instantiation';
