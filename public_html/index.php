<?php
session_start();

require_once __DIR__ . '/../application/service/service.php';

$view_model = ftss_read_view_model();
$page_title = $view_model['app_name'];

ob_start();
include __DIR__ . '/../application/templates/pages/home.php';
$page_content = ob_get_clean();

include __DIR__ . '/../application/templates/layouts/base.php';
