<?php
if(!defined('MEDIAWIKI')) die;

$dir = __DIR__;
$ext = 'MysqlLikeSearch';

$wgExtensionCredits['other'][] = array(
  'path'            => __FILE__,
  'name'            => $ext,
  'version'         => '0.1',
  'author'          => '[https://github.com/uta uta]',
  'url'             => 'https://github.com/uta/MysqlLikeSearch',
  'descriptionmsg'  => 'mysql-like-search-desc',
  'license-name'    => 'MIT-License',
);

$wgAutoloadClasses[$ext]    = "$dir/classes/$ext.php";
$wgMessagesDirs[$ext]       = "$dir/i18n";
$wgSearchType               = $ext;
$wgDisableSearchUpdate      = true;
$wgDBmysql5                 = true;
