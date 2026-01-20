<?php
require_once 'src/Session.php';

Session::start();
Session::destroy();
header('Location: login.php');
exit;
