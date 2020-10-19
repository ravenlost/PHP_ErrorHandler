<?php

/**
 * This page is included within the ErrorHandler.php script. It has access to all of it's properties with $this keyword!
 */

// deny direct access to this page (it should be included within ErrorHandler.php!)
if ( strpos($_SERVER['REQUEST_URI'], basename(__FILE__)) !== false )
{
  header('HTTP/1.0 403 Forbidden');
  exit();
}
?>

<!doctype html>
<html>
<head>
</head>
<body>

	<h1>Error!</h1>
	<hr/>
	<p><?=$this->getErrorString()?></p>

	<!-- You can change the default args to getErrorString() to have better control of the output,
     and then by using seperately the following properties to your liking:

     $this->errid             // the uniq error id
     $this->ex                // if error comes from an exception, this will be set with all associated Exception methods
     $this->referer           // page url on which error occured
     $this->failedToEmailMsg  // if emailing error to admins fails, this will hold the error message mail failure

     and any other properties in class ErrorHandler.php
  -->

</body>
</html>
