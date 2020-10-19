# CorbeauPerdu\ErrorHandler\ErrorHandler Class
ErrorHandler Class, initially written by K.D. (<a href="https://codereview.stackexchange.com/questions/222650/php-error-handler-class">https://codereview.stackexchange.com/questions/222650/php-error-handler-class</a>) and modified by myself quite a bit for added functionnality.

**@requires PHPMailer https://github.com/PHPMailer/PHPMailer** if you want the ErrorHandler to use PHPMailer to send out emails, instead of using the internal SMTP server.

With this error handler you can:

<ul>
<li>Set a PHP error handler</li>
<li>Set a PHP exception handler</li>
<li>Register a shutdown function that'll process uncaught errors</li>
<li>Choose if to log the error to file and choose file to log it to</li>
<li>Send the error to an email</li>
<li>Add a custom error 500 page</li>
<li>Set a custom general error message if no error page is set</li>
<li>Send the error html to a JSON response under the name 'error500Html',<br/>instead of printing to the browser!</li>
<li>Log/Email an error $ex to admins, without displaying an error page</li>
</ul>


Requires the following to be defined in ErrorHandlerConfig.php before usage:

<pre>
namespace CorbeauPerdu\ErrorHandler;

define(__NAMESPACE__ . '\APP_NAME', 'Test App');
define(__NAMESPACE__ . '\APP_SUPPORT_EMAIL', 'support@testapp.com');
define(__NAMESPACE__ . '\DEBUG_MODE', true);
define(__NAMESPACE__ . '\LOG_ERRORS', true);
define(__NAMESPACE__ . '\SEND_ERROR_EMAILS', true);
define(__NAMESPACE__ . '\SEND_ERROR_EMAILS_WITH_PHPMAILER', true);
define(__NAMESPACE__ . '\ERROR_REPORTING_EMAIL', 'security@testapp.com');
define(__NAMESPACE__ . '\ERROR_LOG_PATH', $_SERVER['DOCUMENT_ROOT'].'/../logs/error_log.log');
define(__NAMESPACE__ . '\ERROR_PAGE_PATH', $_SERVER['DOCUMENT_ROOT'].'/error500.php');
define(__NAMESPACE__ . '\PUBLIC_ERROR_MESSAGE', 'Looks like there was an error. We are already looking in to it!');

and if using PHPMailer:

define(__NAMESPACE__ . '\SMTP_DEBUG', 0);
define(__NAMESPACE__ . '\SMTP_AUTH', true);
define(__NAMESPACE__ . '\SMTP_SECURE', 'tls');
define(__NAMESPACE__ . '\SMTP_HOST', 'smtp.gmail.com');
define(__NAMESPACE__ . '\SMTP_PORT', 587);
define(__NAMESPACE__ . '\SMTP_USERNAME', 'username@gmail.com');
define(__NAMESPACE__ . '\SMTP_PASSWORD', 'password');
</pre>

Then, set the error handler in your pages with:</br>
<code>set_error_handler(array(new ErrorHandler(), 'handleError'));</code>

You can also set the exception handler (not the same!) with:</br>
<code>set_exception_handler(array ( new ErrorHandler(), 'handleException' ));</code>

You can also register a shutdown function to catch any other uncaught (except ParseError) errors/exceptions:</br>
<code>register_shutdown_function(array ( new ErrorHandler(), 'handleShutdown' ));</code>

Finally, force Log / Email an error $ex to admins, without displaying an error page:</br>
<code>$uniqErrID = ErrorHandler::logError($ex);</code>
