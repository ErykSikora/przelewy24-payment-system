<?php

# CMS

$config['dbms']             = 'mysqli';
$config['db_hostname']      = 'localhost';
$config['db_username']      = 'XXXX';
$config['db_password']      = 'XXXX';
$config['db_name']          = 'XXXX';
$config['db_prefix']        = 'cms_';
$config['timezone']         = 'Europe/Warsaw';

$config['url_rewriting']    = 'mod_rewrite';
$config['use_hierarchy']    = true;
$config['showbase']         = false;

# SYSTEM

$system['db_name']          = 'XYZ_api';
$system['show_errors']      = false;
$system['parish_link']      = 'wesprzyj';
$system['url']              = 'https://YOURDOMAIN.pl/';
$system['api']              = 'https://api.YOURDOMAIN.pl/';
$system['token']            = 'XXXX'; //zdefiniowany w api\app\Providers\AuthServiceProvider.php

# API - PREFIX

$prefix['parish']           = 'api/parishes/';

# PRZELEWY24 - REGISTER FORM

$przelewy['merchant_id']    = '000000';

?>