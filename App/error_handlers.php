<?php

error_reporting(E_WARNING);

/**
 * You can obviously implement your own error handling strategy and code any way you'd like. But some important points: 
 * 
 * 1. We want a daemon to be very resilient and hard to crash, but when it does crash, we need it to crash loudly. Silent
 * failures are my biggest fear. 
 * 
 * 2. You should implement your error handlers as close to line 1 of your app as possible. 
 * 
 * 3. You should implement an error handler, exception handler, and a global shutdown handler. 
 */

/**
 * Override the PHP error handler while still respecting the error_reporting, display_errors and log_errors ini settings
 * 
 * @param $errno
 * @param $errstr
 * @param $errfile
 * @param $errline
 * @return boolean
 */
function daemon_error($errno, $errstr, $errfile, $errline) 
{
	// Respect the error_reporting Level
	if(($errno & error_reporting()) == 0) 
		return;
	
	$is_fatal = false;
		
    switch ($errno) {
        case E_NOTICE:
        case E_USER_NOTICE:
            $errors = 'Notice';
            break;
        case E_WARNING:
        case E_USER_WARNING:
            $errors = 'Warning';
            break;
        case E_ERROR:
        case E_USER_ERROR:
        	$is_fatal = true;
            $errors = 'Fatal Error';
            break;
        default:
            $errors = 'Unknown';
            break;
	}
	
	$message = sprintf('PHP %s: %s in %s on line %d', $errors, $errstr, $errfile, $errline);
	
    if (ini_get('display_errors'))
    	echo "\n", $message, "\n";

    if (ini_get('log_errors'))
        error_log($message);
        
    if ($is_fatal) {
    	foreach(email_on_error() as $email_address)
			mail($email_address, 'Fatal Daemon Error', $message);    	
    	
    	exit(1);
    }	
	
    return true;
}

/**
 * When the process ends check to make sure it wasn't caused by an un-handled error. 
 * This will help us catch nearly all types of php errors. 
 * @return void
 */
function daemon_shutdown_function() 
{
    $error = error_get_last();
    
    if (is_array($error) && isset($error['type']) == false)
    	return;
    
    switch($error['type'])
    {
    	case E_ERROR:
    	case E_PARSE:
    	case E_CORE_ERROR:
    	case E_CORE_WARNING:
    	case E_COMPILE_ERROR:
    		
			daemon_error($error['type'], $error['message'], $error['file'], $error['line']);
    }
}

set_error_handler('daemon_error');
register_shutdown_function('daemon_shutdown_function');