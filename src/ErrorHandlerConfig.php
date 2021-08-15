<?php

namespace CorbeauPerdu\ErrorHandler;

/* App settings
 ===============================================*/

//App name
define(__NAMESPACE__ . '\APP_NAME', 'Test App');

//Support email
define(__NAMESPACE__ . '\APP_SUPPORT_EMAIL', 'support@testapp.com');

/* Error handling settings (used in ErrorHandler.php)
 ===============================================*/

//Set debug mode: report full error message in html error page? 
//Can be overwritten with ErrorHandler's constructor!!
define(__NAMESPACE__ . '\DEBUG_MODE', true);

//Log errors
define(__NAMESPACE__ . '\LOG_ERRORS', true);

//Send error reports to email
define(__NAMESPACE__ . '\SEND_ERROR_EMAILS', false);

//Send error reports to email
define(__NAMESPACE__ . '\SEND_ERROR_EMAILS_WITH_PHPMAILER', true);

//Email to send the error reports to
define(__NAMESPACE__ . '\ERROR_REPORTING_EMAIL', 'security@testapp.com');

//Path to the error log file
define(__NAMESPACE__ . '\ERROR_LOG_PATH', $_SERVER['DOCUMENT_ROOT'].'/../logs/error_log.log');

//Path to the '500' error page
define(__NAMESPACE__ . '\ERROR_PAGE_PATH', __DIR__ . '/error500.php');

//Default error message (if no error page is set or found)
define(__NAMESPACE__ . '\PUBLIC_ERROR_MESSAGE', 'Looks like there was an error. We are already looking into it!');

/* PHPMailer configurations
 ===============================================*/

define(__NAMESPACE__ . '\SMTP_DEBUG', 0);
define(__NAMESPACE__ . '\SMTP_AUTH', true);
define(__NAMESPACE__ . '\SMTP_SECURE', 'tls');
define(__NAMESPACE__ . '\SMTP_PORT', 587);
define(__NAMESPACE__ . '\SMTP_HOST', 'smtp.gmail.com');
define(__NAMESPACE__ . '\SMTP_USERNAME', 'username');
define(__NAMESPACE__ . '\SMTP_PASSWORD', 'password');

?>
