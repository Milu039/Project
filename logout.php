<?php
session_start();

/* Remove all session variables */
$_SESSION = [];

/* Destroy session */
session_destroy();

/* Redirect back to login */
header("Location: loginpage.php");
exit();
