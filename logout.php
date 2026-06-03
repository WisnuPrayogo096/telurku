<?php
require_once 'config.php';

logoutUser($conn);
header("Location: login");
exit();
