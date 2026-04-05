<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
session_start();
$_SESSION['admin_id'] = 1;
require_once 'd:\laragon\www\COPCSDM\api\ocr_status.php';
