<?php

namespace CorbeauPerdu\ErrorHandler;

// Include config files
$ROOT = __DIR__ . '/../../../';
require_once ( $ROOT . '/include/PHPMailer/Exception.php' );
require_once ( $ROOT . '/include/PHPMailer/PHPMailer.php' );
require_once ( $ROOT . '/include/PHPMailer/SMTP.php' );
require_once ( 'ErrorHandlerConfig.php' );

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * ErrorHandler Class
 * 
 * MIT License
 * 
 * Copyright (c) 2020 Patrick Roy
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * @requires PHPMailer https://github.com/PHPMailer/PHPMailer
 *
 * With this error handler you can:
 *
 * - Set a PHP error handler
 * - Set a PHP exception handler
 * - Register a shutdown function that'll process uncaught errors
 * - Choose to log the error to file and choose file to log it to
 * - Send the error to an email if enabled
 * - Add a custom error page
 * - Set a custom general error message if no error page is set
 * - Log/Email an error $ex to admins only, without displaying an error page
 *
 * Requires the following to be defined in ErrorHandlerConfig.php before usage:
 * 
 * namespace CorbeauPerdu\ErrorHandler;
 *
 * define(__NAMESPACE__ . '\APP_NAME', 'Test App');
 * define(__NAMESPACE__ . '\APP_SUPPORT_EMAIL', 'support@testapp.com');
 * define(__NAMESPACE__ . '\DEBUG_MODE', true);
 * define(__NAMESPACE__ . '\LOG_ERRORS', true);
 * define(__NAMESPACE__ . '\SEND_ERROR_EMAILS', true);
 * define(__NAMESPACE__ . '\SEND_ERROR_EMAILS_WITH_PHPMAILER', true);
 * define(__NAMESPACE__ . '\ERROR_REPORTING_EMAIL', 'security@testapp.com');
 * define(__NAMESPACE__ . '\ERROR_LOG_PATH', $_SERVER['DOCUMENT_ROOT'].'/../logs/error_log.log');
 * define(__NAMESPACE__ . '\ERROR_PAGE_PATH', $_SERVER['DOCUMENT_ROOT'].'/error500.php');
 * define(__NAMESPACE__ . '\PUBLIC_ERROR_MESSAGE', 'Looks like there was an error. We are already looking in to it!');
 * 
 * and if using PHPMailer:
 * 
 * define(__NAMESPACE__ . '\SMTP_DEBUG', 0);
 * define(__NAMESPACE__ . '\SMTP_AUTH', true);
 * define(__NAMESPACE__ . '\SMTP_SECURE', 'tls');
 * define(__NAMESPACE__ . '\SMTP_HOST', 'smtp.gmail.com');
 * define(__NAMESPACE__ . '\SMTP_PORT', 587);
 * define(__NAMESPACE__ . '\SMTP_USERNAME', 'username');
 * define(__NAMESPACE__ . '\SMTP_PASSWORD', 'password');
 *  
 * Then, set the error handler in your pages with:
 * set_error_handler(array(new ErrorHandler(), 'handleError'));
 * 
 * You can also set the exception handler (not the same!) with:
 * set_exception_handler(array ( new ErrorHandler(), 'handleException' ));
 * 
 * You can also register a shutdown function to catch any other uncaught errors/exceptions:
 * register_shutdown_function(array ( new ErrorHandler(), 'handleShutdown' ));
 *    @todo: not convinced it works as it should (i.e. doesn't catch ParseErrors). Would need further testing ?
 *    @todo: see how https://github.com/ArtisticPhoenix/Shutdown/blob/master/src/evo/shutdown/Shutdown.php does it!
 * 
 * Log / Email an error $ex to admins only, without displaying an error page:
 * $uniqErrID = ErrorHandler::logError($ex);

 * Notes: this class originaly came from a person named 'K.D' in StackExchange.
 * 
 * Last Modified : 2020/05/28 by PRoy - First release, with the following mods to original K.D.'s version:
 *                                    - Added handleException() to be used as exception handler
 *                                    - Added handleShutdown() to be registred as a shutdown function: will check if they were any uncaught errors when script ends
 *                                    - Added genErrID(): every logged errors will have a unique ID. This is the only thing users should be provided with! Admins can look up the errors with this ID
 *                                    - Added getClientIP() to log client's IP address
 *                                    - Added getThisPageFullURL() to log referer that produced the error
 *                                    - Added logError() to Log / Email an error $ex to admins only, without displaying an error page 
 *                                    - Added stringToWeb() to htmlentities the error messages and stacktraces, and replace \n with <br> tags
 *                                    - Customized style of HTML some more in getErrorString()
 *                                    - sendToEmail() can either send the email with PHPMailer or the system's php.ini SMTP configs
 *                                    - Constants are now defined in the classe's namespace!
 *                                    - Probably some other minor things...
 *
 *                 2020/07/18 by PRoy - Added a private $errorPageInJSON defined from constructor to be used in loadErrorPage(): 
 *                                      if set, error page is sent through a JSON response named 'error500Html' ! 
 *                 2020/11/23 by PRoy - Added ability to override debug config in constructor; 
 *                                      Added possibility to pass an array of extra logging fields (i.e. user id), passed to constructor.
 *                                      IMPORTANT: if using extra fields, then the array should ALSO be passed in logError() !!
 *
 * @author K.D. https://codereview.stackexchange.com/questions/222650/php-error-handler-class
 *              https://codereview.stackexchange.com/users/201435/k-d
 *              
 * @author Patrick Roy <ravenlost2@gmail.com>
 * 
 * @todo Add ability to add extra values to be printed in the error log/mail: array('key'=>'value') (i.e. 'userid' => $_SESSION['userid'])
 *              
 */
class ErrorHandler
{
  //Set default class properties
  private $debugMode = false;
  private $logErrors = false;
  private $sendEmail = false;
  private $sendEmailWithPHPMailer = false;
  private $securityEmail = null;
  private $publicErrorMessage = "Looks like there was an error. We are already looking into it!";
  private $appName = null;
  private $appSupportEmail = null;
  private $errorPageInJSON = false;
  private $errorLogPath = __DIR__ . '/../../logs/error_log.log';
  private $errorPagePath = __DIR__ . '/../error500.php';
  private $errorDetailsHeaderStyle = 'font-weight: bold;';
  private $failedToEmailMsgStyle = 'color: #dc3545 !important;';
  private $extraLoggingFields = null;

  /**
   * Constructor - Sets up the class settings
   * @param boolean $errorPageInJSON send out error page in a JSON response
   * @param boolean $debugMode set debug mode: report full error message in html error page? If null, then checks in config file for constant DEBUG_MODE, else defaults to false
   * @param array $extraLoggingFields set an array of extra fields to log into array log and email<br/>
   *                                  it's passed by reference, in case one might want to change these values dynamically as your their script it running!  
   */
  public function __construct(bool $errorPageInJSON = false, $debugMode = null, &$extraLoggingFields = null)
  {
    // update class properties from defined constants if they are set
    $this->debugMode = $debugMode ?? ( defined(__NAMESPACE__ . '\DEBUG_MODE') ? DEBUG_MODE : $this->debugMode );
    $this->logErrors = ( defined(__NAMESPACE__ . '\LOG_ERRORS') ? LOG_ERRORS : $this->logErrors );
    $this->sendEmail = ( defined(__NAMESPACE__ . '\SEND_ERROR_EMAILS') ? SEND_ERROR_EMAILS : $this->sendEmail );
    $this->securityEmail = ( defined(__NAMESPACE__ . '\ERROR_REPORTING_EMAIL') && filter_var(ERROR_REPORTING_EMAIL, FILTER_VALIDATE_EMAIL) ? ERROR_REPORTING_EMAIL : $this->securityEmail );
    $this->publicErrorMessage = ( defined(__NAMESPACE__ . '\PUBLIC_ERROR_MESSAGE') ? PUBLIC_ERROR_MESSAGE : $this->publicErrorMessage );
    $this->appName = ( defined(__NAMESPACE__ . '\APP_NAME') ? APP_NAME : $this->appName );
    $this->appSupportEmail = ( defined(__NAMESPACE__ . '\APP_SUPPORT_EMAIL') && filter_var(APP_SUPPORT_EMAIL, FILTER_VALIDATE_EMAIL) ? APP_SUPPORT_EMAIL : $this->appSupportEmail );
    $this->errorLogPath = ( defined(__NAMESPACE__ . '\ERROR_LOG_PATH') ? ERROR_LOG_PATH : $this->errorLogPath );
    $this->errorPagePath = ( defined(__NAMESPACE__ . '\ERROR_PAGE_PATH') ? ERROR_PAGE_PATH : $this->errorPagePath );
    $this->errorPageInJSON = $errorPageInJSON;

    // set extra logging fields
    $this->extraLoggingFields = &$extraLoggingFields;

    // if using PHPMailer
    $this->sendEmailWithPHPMailer = ( defined(__NAMESPACE__ . '\SEND_ERROR_EMAILS_WITH_PHPMAILER') ? SEND_ERROR_EMAILS_WITH_PHPMAILER : $this->sendEmailWithPHPMailer );
    $this->SMTPDebug = ( defined(__NAMESPACE__ . '\SMTP_DEBUG') ? SMTP_DEBUG : null );
    $this->SMTPAuth = ( defined(__NAMESPACE__ . '\SMTP_AUTH') ? SMTP_AUTH : null );
    $this->SMTPSecure = ( defined(__NAMESPACE__ . '\SMTP_SECURE') ? SMTP_SECURE : null );
    $this->SMTPPort = ( defined(__NAMESPACE__ . '\SMTP_PORT') ? SMTP_PORT : null );
    $this->SMTPHost = ( defined(__NAMESPACE__ . '\SMTP_HOST') ? SMTP_HOST : null );
    $this->SMTPUsername = ( defined(__NAMESPACE__ . '\SMTP_USERNAME') ? SMTP_USERNAME : null );
    $this->SMTPPassword = ( defined(__NAMESPACE__ . '\SMTP_PASSWORD') ? SMTP_PASSWORD : null );
  }

  /**
   * Log / Email an error $ex to admins only, without displaying an error page 
   * @param Exception $ex
   * @param array $extraLoggingFields set an array of extra fields to log
   * @return string Error ID used in logs
   */
  public static function logError($ex, $extraLoggingFields = null)
  {
    $eh = new self(false, null, $extraLoggingFields);
    $errid = $eh->genErrID();
    $eh->handleException($ex, $errid, false);
    unset($eh);

    return $errid;
  }

  /**
   * Handle Errors: used by set_error_handler()
   * @param integer $errno
   * @param string $errstr
   * @param string $errfile
   * @param integer $errline
   */
  public function handleError($errno, $errstr, $errfile = false, $errline = false)
  {
    //Save the error data
    $this->errno = $errno;
    $this->errstr = $errstr;
    $this->errfile = $errfile;
    $this->errline = $errline;
    $this->errid = $this->genErrID(); // generate uniq id the user will get (he'll pass this ID to the administrator, and in turn the admin will find the error log with it!

    $this->referer = $this->getThisPageFullURL();

    //Get the actual base filename this error occurred in
    $link_array = explode('/', $this->errfile);
    $this->failedOnFile = end($link_array);

    //If email reporting is enabled
    if ( $this->sendEmail && $this->securityEmail )
    {
      $this->sendToEmail();
    }

    //If logging errors is enabled
    if ( $this->logErrors )
    {
      $this->saveToLog();
    }

    //Load the error page
    $this->loadErrorPage();
  }

  /**
   * Handle Exceptions: used by set_exception_handler()
   */
  public function handleException($ex, string $errid = null, bool $loadErrorPage = true)
  {
    $this->ex = $ex;
    $this->errid = $errid ?? $this->genErrID();
    $this->referer = $this->getThisPageFullURL();

    //If email reporting is enabled
    if ( $this->sendEmail && $this->securityEmail )
    {
      $this->sendToEmail();
    }

    //If logging errors is enabled
    if ( $this->logErrors )
    {
      $this->saveToLog();
    }

    //Load the error page
    if ( $loadErrorPage ) $this->loadErrorPage();
  }

  /**
   * Handle Exceptions: used by register_shutdown_function()
   */
  public function handleShutdown()
  {
    $last_error = error_get_last();

    if ( ( $last_error !== null ) and ( in_array($last_error['type'], Array ( E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR, E_CORE_WARNING, E_COMPILE_WARNING, E_PARSE )) ) )
    {
      $this->DontShowBacktrace = true;
      $this->handleError($last_error['type'], $last_error['message'], $last_error['file'], $last_error['line']);
    }
  }

  /**
   * Save the error to a log
   */
  private function saveToLog()
  {
    // Get the error string
    $errorString = $this->getErrorString(false);
    $errorString .= '------------------------------------------' . PHP_EOL; // log seperator

    // Save the error to the log
    error_log($errorString, 3, $this->errorLogPath);
  }

  /**
   * Send the error to the set email
   */
  private function sendToEmail()
  {
    if ( $this->sendEmailWithPHPMailer )
    {
      // if need specific SMTP server setting from outside (i.e. google)
      try
      {
        $mail = new PHPMailer(true);
        $mail->IsSMTP();
        $mail->Mailer = "smtp";

        $mail->SMTPDebug = $this->SMTPDebug;
        $mail->SMTPAuth = $this->SMTPAuth;
        $mail->SMTPSecure = $this->SMTPSecure;
        $mail->Port = $this->SMTPPort;
        $mail->Host = $this->SMTPHost;
        $mail->Username = $this->SMTPUsername;
        $mail->Password = $this->SMTPPassword;

        $mail->IsHTML(true);
        $mail->AddAddress($this->securityEmail, $this->appName . ' Security');
        $mail->SetFrom($this->securityEmail, $this->appName . ' Security');
        $mail->AddReplyTo($this->securityEmail, $this->appName . ' Security');
        $mail->Subject = $this->appName . ' Error ID: ' . $this->errid;

        $mail->MsgHTML($this->getErrorString());

        $mail->Send();
      }
      catch ( Exception $ex )
      {
        $this->failedToEmailMsg = $mail->ErrorInfo;
      }
    }

    // if using smtp mail server from server's php.ini setting:
    else
    {
      $headers = "MIME-Version: 1.0" . "\r\n";
      $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
      $headers .= "From: " . $this->appName . " Security <" . $this->securityEmail . ">\r\n";
      $headers .= "Reply-To: " . $this->securityEmail . "" . "\r\n";

      //Send the email
      if ( mail($this->securityEmail, $this->appName . ' Error ID: ' . $this->errid, $this->getErrorString(), $headers) === false )
      {
        $this->failedToEmailMsg = 'Is there a proper SMTP server running?';
      }
    }
  }

  /**
   * Load the error page
   */
  private function loadErrorPage()
  {
    // If there is no error page path or the file doesnt exist, output a message
    if ( ( ! $this->errorPagePath ) || ( ! file_exists($this->errorPagePath) ) )
    {
      // Output a general error message
      $error500Html = '';
      $error500Html .= $this->publicErrorMessage . '<br/><br/>';
      $error500Html .= '<span style="' . $this->errorDetailsHeaderStyle . '">Error ID:</span> ' . $this->errid . '<br/><br/>';

      // If debug mode is enabled, output the error
      if ( $this->debugMode )
      {
        $error500Html .= $this->getErrorString(true, false, true, false, false);
      }

      $error500Html .= '<span style="' . $this->errorDetailsHeaderStyle . '">Referrer:</span> <a href="' . $this->referer . '">' . $this->referer . '</a>';

      // If email failed to send, append to error message
      if ( ( $this->debugMode ) and isset($this->failedToEmailMsg) ) $error500Html .= '<br/><br/><span style="' . $this->failedToEmailMsgStyle . '">** Failed to send email to administrators: ' . $this->failedToEmailMsg . '</span>';

      // send error page in JSON response if coming from POST?
      if ( ( ! empty($_POST) ) and ( $this->errorPageInJSON ) )
      {
        exit(json_encode([  'error500Html' => $error500Html ]));
      }
      else
      {
        echo $error500Html;
      }
    }
    /*  If there is an error page path and it exists, include it
     *  The file itself has access to the error string, it will
     *  output it if debug mode is enabled.
     *  Custom error files can be used by definining the config ERROR_PAGE_PATH */
    else
    {
      // send error page in JSON response if coming from POST?
      if ( ( ! empty($_POST) ) and ( $this->errorPageInJSON ) )
      {
        // start output buffering
        ob_start();
        include ( $this->errorPagePath );
        $error500Html = ob_get_clean(); // send generated error page into variable!

        exit(json_encode([  'error500Html' => $error500Html ]));
      }
      else
      {
        include ( $this->errorPagePath );
      }
    }

    // exit the application after printing the error page
    exit();
  }

  /**
   * Set up the error string to be logged, emailed and printed out to error page
   * @param bool $outputForHTML
   * @param bool $inclErrorID
   * @param bool $inclStacktrace
   * @param bool $inclReferer
   * @param bool $inclSendMailStatus
   * @return string
   */
  private function getErrorString(bool $outputForHTML = true, bool $inclErrorID = true, bool $inclStacktrace = true, bool $inclReferer = true, bool $inclSendMailStatus = true)
  {
    $errorString = null;
    $separator = PHP_EOL;

    // ------------------------------------
    //Error comes from exception object
    if ( isset($this->ex) )
    {
      if ( $outputForHTML )
      {
        $separator = '<br/>';

        $errorString = '<span style="' . $this->errorDetailsHeaderStyle . '">' . get_class($this->ex) . '</span> on ' . date('j M Y - g:i:s A (T)', time()) . ':' . $separator;
        if ( $inclErrorID ) $errorString .= '<span style="' . $this->errorDetailsHeaderStyle . '" >Error ID:</span> ' . $this->errid . $separator;
        $errorString .= '<span style="' . $this->errorDetailsHeaderStyle . '">File:</span> ' . $this->ex->getFile() . ' (Line: ' . $this->ex->getLine() . ')' . $separator;
        $errorString .= '<span style="' . $this->errorDetailsHeaderStyle . '">Message:</span> ' . $this->stringToWeb($this->ex->getMessage(), true) . $separator;

        if ( $inclStacktrace ) $errorString .= '<span style="' . $this->errorDetailsHeaderStyle . '">Stacktrace:</span> ' . $separator . $this->stringToWeb($this->ex->getTraceAsString(), true) . $separator . $separator;
        if ( $inclReferer ) $errorString .= '<span style="' . $this->errorDetailsHeaderStyle . '">Referrer:</span> ' . '<a href="' . $this->referer . '">' . $this->referer . '</a>' . $separator;
        $errorString .= '<span style="' . $this->errorDetailsHeaderStyle . '">Client IP:</span> ' . ( $this->getClientIP() ?: 'UNKNOWN' ) . $separator;

        //Get extra fields to log  
        if ( isset($this->extraLoggingFields) )
        {
          foreach ( $this->extraLoggingFields as $field => $value )
          {
            $errorString .= '<span style="' . $this->errorDetailsHeaderStyle . '">' . $field . ':</span> ' . $this->stringToWeb($value, true) . $separator;
          }
        }

        //If email failed to send, append to error message
        if ( ( $inclSendMailStatus ) and isset($this->failedToEmailMsg) ) $errorString .= '<span style="' . $this->failedToEmailMsgStyle . '">** Failed to send email to administrators: ' . $this->failedToEmailMsg . '</span>' . $separator;
      }
      else
      {
        $errorString = '' . get_class($this->ex) . ' on ' . date('j M Y - g:i:s A (T)', time()) . ':' . $separator;
        if ( $inclErrorID ) $errorString .= 'Error ID: ' . $this->errid . $separator;
        $errorString .= 'File: ' . $this->ex->getFile() . ' (Line: ' . $this->ex->getLine() . ')' . $separator;
        $errorString .= 'Message: ' . $this->ex->getMessage() . $separator;

        if ( $inclStacktrace ) $errorString .= 'Stacktrace: ' . $separator . $this->ex->getTraceAsString() . $separator;
        if ( $inclReferer ) $errorString .= 'Referrer: ' . $this->referer . $separator;
        $errorString .= 'Client IP: ' . ( $this->getClientIP() ?: 'UNKNOWN' ) . $separator;

        //Get extra fields to log
        if ( isset($this->extraLoggingFields) )
        {
          foreach ( $this->extraLoggingFields as $field => $value )
          {
            $errorString .= "$field: $value" . $separator;
          }
        }

        //If email failed to send, append to error message
        if ( ( $inclSendMailStatus ) and isset($this->failedToEmailMsg) ) $errorString .= '** Failed to send email to administrators: ' . $this->failedToEmailMsg . $separator;
      }
    }

    // ------------------------------------
    //Error is a normal PHP error
    else
    {
      //Switch between the error numbers and set up the error type variable
      switch ( $this->errno )
      {
        case E_NOTICE:
        case E_USER_NOTICE:
        case E_DEPRECATED:
        case E_USER_DEPRECATED:
        case E_STRICT:
          $errorType = "NOTICE";
          break;

        case E_WARNING:
        case E_USER_WARNING:
          $errorType = "WARNING";
          break;

        case E_ERROR:
        case E_USER_ERROR:
        case E_RECOVERABLE_ERROR:
          $errorType = "FATAL";
          break;

        default:
          $errorType = "UNKNOWN";
      }

      if ( $outputForHTML )
      {
        $separator = '<br/>';

        $errorString = '<span style="' . $this->errorDetailsHeaderStyle . '">' . $errorType . ' [' . $this->errno . ']</span> on ' . date('j M Y - g:i:s A (T)', time()) . ':' . $separator;
        if ( $inclErrorID ) $errorString .= '<span style="' . $this->errorDetailsHeaderStyle . '" >Error ID:</span> ' . $this->errid . $separator;
        $errorString .= '<span style="' . $this->errorDetailsHeaderStyle . '">File:</span> ' . $this->errfile . ' (Line: ' . $this->errline . ')' . $separator;

        if ( isset($this->DontShowBacktrace) )
        {
          $erstr = str_replace('Stack trace:', '<span style="' . $this->errorDetailsHeaderStyle . '">Stack trace:</span>', $this->stringToWeb($this->errstr, true));
          $errorString .= 'Message: ' . $erstr . $separator;
        }
        else
        {
          $errorString .= '<span style="' . $this->errorDetailsHeaderStyle . '">Message:</span> ' . $this->stringToWeb($this->errstr, true) . $separator;
          $errorString .= '<span style="' . $this->errorDetailsHeaderStyle . '">Backtrace:</span> ' . $this->backTraceError() . $separator;
        }

        //if ( ! isset($this->DontShowBacktrace) ) $errorString .= '<span style="' . $this->errorDetailsHeaderStyle . '">Backtrace:</span> ' . $this->backTraceError() . $separator;
        if ( $inclReferer ) $errorString .= '<span style="' . $this->errorDetailsHeaderStyle . '">Referrer:</span> <a href="' . $this->referer . '">' . $this->referer . '</a>' . $separator;
        $errorString .= '<span style="' . $this->errorDetailsHeaderStyle . '">Client IP:</span> ' . ( $this->getClientIP() ?: 'UNKNOWN' ) . $separator;

        //Get extra fields to log
        if ( isset($this->extraLoggingFields) )
        {
          foreach ( $this->extraLoggingFields as $field => $value )
          {
            $errorString .= '<span style="' . $this->errorDetailsHeaderStyle . '">' . $field . ':</span> ' . $this->stringToWeb($value, true) . $separator;
          }
        }

        //If email failed to send, append to error message
        if ( ( $inclSendMailStatus ) and isset($this->failedToEmailMsg) ) $errorString .= '<span style="' . $this->failedToEmailMsgStyle . '">** Failed to send email to administrators: ' . $this->failedToEmailMsg . '</span>' . $separator;
      }
      else
      {
        $errorString = $errorType . ' [' . $this->errno . '] on ' . date('j M Y - g:i:s A (T)', time()) . ':' . $separator;
        if ( $inclErrorID ) $errorString .= 'Error ID: ' . $this->errid . $separator;
        $errorString .= 'File: ' . $this->errfile . ' (Line: ' . $this->errline . ')' . $separator;
        $errorString .= 'Message: ' . $this->errstr . $separator;
        if ( ! isset($this->DontShowBacktrace) ) $errorString .= 'Backtrace: ' . $this->backTraceError() . $separator;
        if ( $inclReferer ) $errorString .= 'Referrer: ' . $this->referer . $separator;
        $errorString .= 'Client IP: ' . ( $this->getClientIP() ?: 'UNKNOWN' ) . $separator;

        //Get extra fields to log
        if ( isset($this->extraLoggingFields) )
        {
          foreach ( $this->extraLoggingFields as $field => $value )
          {
            $errorString .= "$field: $value" . $separator;
          }
        }

        //If email failed to send, append to error message
        if ( ( $inclSendMailStatus ) and isset($this->failedToEmailMsg) ) $errorString .= '** Failed to send email to administrators: ' . $this->failedToEmailMsg . $separator;
      }
    }

    //Return the error string
    return $errorString;
  }

  /**
   * Function to back trace the error
   * @return string
   * @see https://www.php.net/manual/en/function.debug-backtrace.php
   */
  private function backTraceError()
  {
    //Set up backtrace variables
    $backtraceStarted = null;
    $rawBacktrace = debug_backtrace();
    $cleanBacktrace = $backtraceSeparator = '';
    $i = 0;

    //Loop through the backtrace
    foreach ( $rawBacktrace as $a_key => $a_value )
    {
      //If a file or line is not set, skip this iteration
      if ( ! isset($a_value['file']) || ! isset($a_value['line']) )
      {
        continue;
      }

      //Start saving the backtrace from the file the error occurred in, skip the rest
      if ( ! isset($backtraceStarted) && basename($a_value['file']) != $this->failedOnFile )
      {
        continue;
      }
      else
      {
        $backtraceStarted = true;
      }

      //Add this file to the backtrace
      $cleanBacktrace .= $backtraceSeparator . basename($a_value['file']) . ' [' . $a_value['line'] . ']';

      //Set the separator for the next iteration
      $backtraceSeparator = ' < ';

      //Increment the counter
      $i ++;
    }

    //Return the backtrace
    return $cleanBacktrace;
  }

  /**
   * getThisPageFullURL()
   * Builds this page's URL to set referrer
   * @return string full url address of the running script
   */
  private function getThisPageFullURL()
  {
    return 'http' . ( ! empty($_SERVER['HTTPS']) ? 's' : '' ) . '://' . $_SERVER['SERVER_NAME'] . $_SERVER['PHP_SELF'] . ( ( empty($_GET) ) ? '' : '?' . http_build_query($_GET) );
  }

  /**
   * getClientIP()
   * Retrieve a client's best known IP address
   * @return string|NULL
   */
  private function getClientIP()
  {
    if ( isset($_SERVER["HTTP_CF_CONNECTING_IP"]) )
    {
      $_SERVER['REMOTE_ADDR'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
    }

    foreach ( array ( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR' ) as $key )
    {
      if ( array_key_exists($key, $_SERVER) )
      {
        foreach ( explode(',', $_SERVER[$key]) as $ip )
        {
          $ip = trim($ip);

          //if ( filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false )
          if ( filter_var($ip, FILTER_VALIDATE_IP) !== false )
          {
            return $ip;
          }
        }
      }
    }

    return null;
  }

  /**
   * stringToWeb()
   * Format a string for web output
   * @param string $value
   * @param bool $replaceLineBreaks $replaceLineBreaks replace '\n' with '<br/>'
   * @return string
   */
  private function stringToWeb(string $value, bool $replaceLineBreaks = true)
  {
    $value = htmlentities($value, ENT_QUOTES);
    //if ( $replaceLineBreaks ) $value = str_replace(PHP_EOL, '<br/>', $value);
    if ( $replaceLineBreaks ) $value = nl2br($value);
    return $value;
  }

  /**
   * genErrID()
   * Get a uniq error ID
   * @return string
   */
  private function genErrID()
  {
    return uniqid();
  }
}

?>
