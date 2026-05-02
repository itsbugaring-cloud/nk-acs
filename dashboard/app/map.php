<?php
require_once __DIR__ . '/config/config.php';
requireLogin();

// Topology map feature has been retired from UI.
redirect('/devices.php');

