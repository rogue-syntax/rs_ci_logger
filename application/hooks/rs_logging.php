<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

require_once('/PathTo/rs_activity_TX.php');

/**
 * RS LOGGING:
 * Intercept incoming requests and log them to a structured logging class 'RSLog'
 * 
 * To implement:
 * 1 ) Place this file in application/hooks
 * 2 ) config/hooks.php must register the following hooks:
 * 
 * $hook['post_controller_constructor'][] = array(
 * 'class'    => 'RSLogging',  
 * 'function' => 'logRequestPre',
 * 'filename' => 'RS_logging.php',  
 * 'filepath' => 'hooks
 * );
 * 
 * $hook['post_controller'][] = array(
 * 'class'    => 'RSLogging',  
 * 'function' => 'logRequestPost', 
 * 'filename' => 'RS_logging.php',  
 * 'filepath' => 'hooks'
 * );
 * 
 * 3 ) Require this file once in index.php just ahead of codeigniter.php, 
 * so that the exception, shutdown, and error handlers in this file take precidence over the ones in Common.php
 * ( an alternative is to rename the exception, shutdown, and error handlers here, 
 *  and edit the defined a custom error handlers in codeigniter.php line 132 to call them )
 * 
 * RSLogging->logRequestPre 
 * 	-	logs the data at hand at the post_controller_constructor lifecycle hook
 * 	- 	adds query data from the queries called post_controller to the log
 * 	- 	the registered shutdown and exception handlers append and query errors, prcess errors, or exceptions thrown to the log data,
 * 	- 	finally, the log data is written to temp file and sent to persistant storage
 *  - 	the RSLog log data is able to be interpredted into viewable summary data in the RSActivityTx.php library 
 * 
 * Lifecycle: Request shutdown, error and exception callbacks are overridden here to pass the logged data through to wherever they need to go
 * Examples here are:
 * 	-	Insert to database table ( MYSql example )
 * 	-	Log to file ( Simple example )
 * 	-	Simple stream processing with PHP. 
 * 	-	RSActivityTX
 * 	-	Included in this repo is the RSActivityTX and ActLogTranslate classes, which processes the forwarded log data by mapping handler functions to the log's [route][controller] => ()
 * 	-	i.e. for a request to site.com/reports/monthlyreport, the data is handled like ActLogTranslate->controller['reports']['monthlyreport'] ( RSLog data to pocess )
 * 	-	Ideally for processing a large mapping of logs, this procesing work should be offloaded to some other thread / server / instance etc., preferably something pre compiled to accomodate a large handler mapping structure
 * 	-	The RSActivityTX class is the desired data processing outcome, and the ActLogTranslate class holds the mappings of translation handlers.
 * 	
 * 	-	TODO: The functions RSLog->onShutdown and shutdownWriter are essentially duplicate processes, because on some request termination handlers the Code Igniter instance is no longer availble.
 * 		TODO: Replace RSLog->onShutdown with just shutdownWriter if possible
 * 	-	The log processing logic is called at the the end of the RSLog->onShutdown and shutdownWriter procedures like this:
 * 	-	$alt = new ActLogTranslate(true);
 *	-	$alt->transl($log);
 * 
 */

/**
 * Class RSLog
 * This class is used to log the request and response of the API.
 * @property string $logId - The ID of the log.
 * @property int $type - The type of the log.
 * @property string $uri - The URI of the request.
 * @property string $sessionId - The id of the session.
 * @property array $uriVars - The URI of the request vars.
 * @property array $post - The $_POST data.
 * @property array $inputStream - The $_POST data.
 * @property string $ipAddr - The IP address of the requester.
 * @property string $userEmail - The email of the user.
 * @property string $userID - The ID of the user.
 * @property int $time - The time of the request.
 * @property array $queryTimes - The query execution times.
 * @property array $queries - The queries.
 * @property array $dbError - The database error.
 * @property array $procError - The procedure error.
 * @property array $exception - The exception.
 */

class RSLog {
	public static $TYPE_REQ = 0;
	public static $TYPE_ACT = 1;
	public static $TYPE_ERR = 2;
	public function toJSON() {
		return json_encode($this);
	}

	public function fromJSON($json) {
		$obj = json_decode($json);
		$this->type = $obj->type;
		$this->logId = $obj->logId;
		$this->sessionId = $obj->sessionId;
		$this->uri = $obj->uri;
		$this->uriVars = explode("/", $this->uri);
		$this->ctrlFile = $obj->ctrlFile;
		$this->ctrlEP = $obj->ctrlEP;
		$this->mainRsrc = $obj->mainRsrc;
		$this->post = $obj->post;
		$this->inputStream = $obj->inputStream;
		$this->ipAddr = $obj->ipAddr;
		$this->userEmail = $obj->userEmail;
		$this->userID = $obj->userID;
		$this->time = $obj->time;
		$this->queryTimes = $obj->queryTimes;
		$this->queries = $obj->queries;
		$this->dbError = $obj->dbError;
		$this->procError = $obj->procError;
		$this->exception = $obj->exception;
		unset($obj);
	}


	/**
	 *@property int The type of the log.
	 */
	public $type = 0;

	/**
	 * @property string The ID of the log.
	 */
	public $logId = 0;

	/**
	 * @property string The URI of the request.
	 */
	public $uri = "";

	/**
	 * @property string The session ID of the request.
	 */
	public $sessionId = "";
	/**
	 * @property array The URI of the request vars.
	 */
	public $uriVars = array();
	/**
	 * @property string The Controller file
	 */
	public $ctrlFile = "";
	/**
	 * @property string The Controller endpoint
	 */
	public $ctrlEP = "";
	/**
	 * @property string The Main resource if called out
	 */
	public $mainRsrc = "";
	/**
	 * @property array The $_POST data.
	 */
	public $post = array();

	/**
	 * @property string The raw input stream.
	 */
	public $inputStream = "";

	/**
	 * @property string The IP address of the requester.
	 */
	public $ipAddr = "";

	/**
	 * @property string The email of the user.
	 */
	public $userEmail = "";

	/**
	 * @property string The ID of the user.
	 */
	public $userID = "";

	/**
	 * @property	int The time of the request.
	 */
	public $time = 0;

	/**
	 * @property array query execution times.
	 */
	public $queryTimes = array();

	/**
	 * @property array The queries.
	 */
	public $queries = array();

	/**
	 * @property string attemp to get last inserted id as string
	 */
	public $lastID = "";
	/**
	 *@property array The database error. Has keys "type”, “message”, “file” and “line?
	 */
	public $dbError = array();

	/**
	 * @property array The procedure error. Has keys "type”, “message”, “file” and “line?
	 */
	public $procError = array();

	/**
	 *@property Exception The exception.
	 */
	public $exception = null;
}





/**
 * Class RSLogging
 * This class is used to log the request and response of the API.
 * @property RSLog $LOG The log object.
 * @property array $noLogID The list of user ID's activity that are not logged.
 * @property array $noLogIP The list of IP addresses that are not logged.
 * @property array $noLogPost The list of post data that are not logged.
 * @property CI_Controller $CI The CI instance.
 * @method void saveToMySqlDB(RSLog $log) example log handler on request shutdown
 * @method void logRequestPre() called from CI pre request hook, marshales most of the pretinent req data
 * @method void logRequestPost() called from CI post request hook
 * @method void onShutdown() called from PHP registered shutdown function
 */
class RSLogging {
	/**
	 * @property array The list of user ID's activity that are not logged.
	 */
	public $noLogID = array();

	/**
	 * @property array The list of IP addresses that are not logged.
	 */
	public $noLogIP = array();

	/**
	 * @property array If these $_POST or $_GETkeys are set === to "1", do not log.
	 * !List the disallowed terms you want to trigger logging prevention here
	 * i.e. do not log request that post password=xxxx
	 */
	public $noLogPost = array("pw", "password");

	/**
	 * @property object The CI instance.
	 */
	public $CI;

	/**
	 * @property RSLog The log object.
	 */
	public $LOG;


	function __construct() {
		if (ENVIRONMENT === 'production') {
			$this->noLogID = array(0, 1);
			//$this->noLogIP = array("74.111.112.57");
		}
		$this->CI = &get_instance();
		/*
		*!Dynamically appending "RSLogging" property to Code Igniter instance
		*So our RSLog instance can carry through the request lifecycle
		*/
		$this->CI->RSLogging = NULL;
	}


	public function saveToMySqlDB() {

		$uriVals = json_encode($this->LOG->uriVars, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		$postVals = json_encode($this->LOG->post, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		$queryTimes = json_encode($this->LOG->queryTimes);
		$queries = json_encode($this->LOG->queries, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		if ($this->LOG->dbError["code"] == 0) {
			$dbErr = null;
		} else {
			$dbErr = json_encode($this->LOG->dbError, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}

		if ($this->LOG->procError === null) {
			$procErr = null;
		} else {
			$procErr = json_encode($this->LOG->procError, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}

		if ($this->LOG->exception === null) {
			$exErr = null;
		} else {
			$exErr = json_encode($this->LOG->exception, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}


		$conn = mysqli_connect($this->CI->db->hostname, $this->CI->db->username, $this->CI->db->password, $this->CI->db->database, "3306");
		$query = mysqli_prepare($conn, "CALL saveRSLog( ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
		$query->bind_param(
			"isssssssssssisssss",
			$this->LOG->type,
			$this->LOG->logId,
			$this->LOG->uri,
			$uriVals,
			$this->LOG->ctrlFile,
			$this->LOG->ctrlEP,
			$this->LOG->mainRsrc,
			$postVals,
			$this->LOG->inputStream,
			$this->LOG->ipAddr,
			$this->LOG->userEmail,
			$this->LOG->userID,
			$this->LOG->time,
			$queries,
			$queryTimes,
			$dbErr,
			$procErr,
			$exErr
		);

		$success = $query->execute();
		if (!$success) {
			$this->logQuerySaveError($query->error, $query);
		}
	}

	/**
	 * Logs the request on the CI post_controller_constructor hook i.e when the controller is instantiated and CI is available.
	 */
	public function logRequestPre() {

		$uriVars = explode("/", uri_string());

		/**
		 * filter out undsired data part one
		 */
		if (in_array($this->CI->input->ip_address(), $this->noLogIP)) {
			return;
		}
		if (in_array($this->CI->session->userData('id'), $this->noLogIP)) {
			return;
		}

		/**
		 * filter out undsired data part two
		 */
		for ($i = 0; $i < count($this->noLogPost); $i++) {
			if (isset($_GET[$this->noLogPost[$i]])) {
				return;
			} else if (isset($_POST[$this->noLogPost[$i]])) {
				return;
			}
		}

		/**
		 * Dynamically bind this to the CI instance
		 */
		$this->CI->RSLogging = $this;

		$this->LOG = new RSLog();
		if (sizeof($_POST) > 0 || count($uriVars) >= 3) {
			$this->LOG->type = RSLog::$TYPE_ACT;
		} else {
			$this->LOG->type = RSLog::$TYPE_REQ;
		}

		$this->LOG->logId = uniqid() . "" . time() . "" . bin2hex(openssl_random_pseudo_bytes(5));

		$this->LOG->sessionId = $this->CI->session->session_id;

		$this->LOG->uri = uri_string();

		$this->LOG->uriVars = $uriVars;

		$this->LOG->ctrlFile = $uriVars[0];

		if (count($uriVars) > 1) {
			$this->LOG->ctrlEP = $uriVars[1];
		}

		if (count($uriVars) > 2) {
			$this->LOG->mainRsrc = $uriVars[2];
		}

		$this->LOG->post = $_POST;

		foreach ($this->LOG->post as $key => &$value) {
			$this->LOG->post[$key] = urldecode($value);
		}

		$this->LOG->inputStream = urldecode($this->CI->input->raw_input_stream);

		$this->LOG->ipAddr = $this->CI->input->ip_address();

		$this->LOG->userEmail = $this->CI->session->userData('email');

		$this->LOG->userID = $this->CI->session->userData('id');

		$this->LOG->time = time();

		/**
		 * Dynamically bind the log data to the CI instance
		 * Log data should be available to controllers throughout the rest of the request lifecycle
		 * so other controllers can wite to the log data, or pull out the logId for reference, etc.
		 */
		$this->CI->REQ_LOG = $this->LOG;
	}

	/**
	 * Appends the logs with query data on the post_system hook.
	 */
	public function logRequestPost() {
		if ($this->LOG !== NULL) {
			$this->LOG->queries = $this->CI->db->queries;
			$this->LOG->queryTimes = $this->CI->db->query_times;
		}
	}

	/**
	 * Appends the logs with any error data and appends them on the CI RS_shutdown_handler and RS_exception_handler.
	 */
	public function onShutdown() {
		$this->LOG->queries = $this->CI->db->queries;
		$this->LOG->dbError = $this->CI->db->error();
		$this->LOG->procError = error_get_last();

		if ($this->LOG->dbError['code'] !== 0 || $this->LOG->procError !== null || $this->LOG->exception !== null) {
			$this->LOG->type = RSLog::$TYPE_ERR;
		};

		//raw test logs example
		$fp = fopen('/var/www/logToSomeFile.txt', 'a');
		fwrite($fp, json_encode($this->LOG) . "\n");
		fclose($fp);

		//save log to DB example
		$this->saveToMySqlDB();
	}
}


function logQuerySaveError($msg, $sql, $log) {
	$fp = fopen('/var/www/log_save_error.txt', 'a');
	$e = new stdClass();
	$e->err = $msg;
	$e->sql = $sql;
	$e->log = $log;
	fwrite($fp, json_encode($e) . "\n");
	fclose($fp);
}

/**
 * @param RSLog $log
 * An implementation of handling the log that inserts it as a record into a MYSQL database table
 */
function saveLog($log) {

	$targetTable = "RSLogs_" . date("Y-m");

	$uriVals = json_encode($log->uriVars);
	$postVals = json_encode($log->post);
	$queryTimes = json_encode($log->queryTimes);
	$queries = json_encode($log->queries);

	if ($log->dbError["code"] == 0) {
		$dbErr = null;
	} else {
		$dbErr = json_encode($log->queries, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	}

	if ($log->procError === null) {
		$procErr = null;
	} else {
		$procErr = json_encode($log->procError, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	}

	if ($log->exception === null) {
		$exErr = null;
	} else {
		$exErr = json_encode($log->exception, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	}

	$conn = mysqli_connect($this->CI->db->hostname, $this->CI->db->username, $this->CI->db->password, $this->CI->db->database, "3306");
	$query = mysqli_prepare($conn, "CALL saveRSLog( ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
	$query->bind_param(
		"issssssssssssisssss",
		$log->type,
		$log->logId,
		$log->sessionId,
		$log->uri,
		$uriVals,
		$log->ctrlFile,
		$log->ctrlEP,
		$log->mainRsrc,
		$postVals,
		$log->inputStream,
		$log->ipAddr,
		$log->userEmail,
		$log->userID,
		$log->time,
		$queries,
		$queryTimes,
		$dbErr,
		$procErr,
		$exErr
	);

	$success = $query->execute();
	if (!$success) {
		logQuerySaveError($query->error, $query, $log);
	}

	$alt = new ActLogTranslate(true);
	$alt->transl($log);
}

/**
 * @param RSLog $log
 * Handles log data when the request lifecycle comes to an end
 */
function shutdownWriter($log) {
	if (ENVIRONMENT == 'development') {
		//EXAMPLE of log to file:
		$fp = fopen('/var/www/logtest.txt', 'a');
		fwrite($fp, json_encode($log) . "\n");
		fclose($fp);
	}
	//EXAMPLE of log to database:
	saveLog($log);
}


/**
 * The following event handlers overwrite the ones in CI index.php
 * 
 */


/**
 * @param String $severity
 * @param String $message
 * @param String $filepath
 * @param String $line
 * These are the fatal error arguments passed in from the code igniter exception / error handlers
 */
function _logFatalError($severity, $message, $filepath, $line) {

	$log = new RSLog();
	$log->post = $_POST;
	$log->time = time();
	$log->uri = $_SERVER['REQUEST_URI'];
	$log->uriVars = explode("/", $_SERVER['REQUEST_URI']);
	$log->logId = uniqid() . "" . time() . "" . bin2hex(openssl_random_pseudo_bytes(5));
	$log->inputStream = urldecode(file_get_contents("php://input"));
	$log->ipAddr = $_SERVER["REMOTE_ADDR"];
	$log->type = RSLog::$TYPE_ERR;
	$err['type'] = $severity;
	$err['message'] = $message;
	$err['file'] = $filepath;
	$err['line'] = $line;
	$log->exception = $err;
	shutdownWriter($log);
}


/**
 * @param String $severity
 * @param String $message
 * @param String $filepath
 * @param String $line
 * These are the fatal error arguments passed in from the code igniter exception / error handlers
 */
function _error_handler($severity, $message, $filepath, $line) {

	$is_error = (((E_ERROR | E_COMPILE_ERROR | E_CORE_ERROR | E_USER_ERROR) & $severity) === $severity);

	// When an error occurred, set the status header to '500 Internal Server Error'
	// to indicate to the client something went wrong.
	// This can't be done within the $_error->show_php_error method because
	// it is only called when the display_errors flag is set (which isn't usually
	// the case in a production environment) or when errors are ignored because
	// they are above the error_reporting threshold.
	if ($is_error) {
		set_status_header(500);
	}

	// Should we ignore the error? We'll get the current error_reporting
	// level and add its bits with the severity bits to find out.
	if (($severity & error_reporting()) !== $severity) {
		return;
	}

	$_error = &load_class('Exceptions', 'core');
	$_error->log_exception($severity, $message, $filepath, $line);

	// Should we display the error?
	if (str_ireplace(array('off', 'none', 'no', 'false', 'null'), '', ini_get('display_errors'))) {
		$_error->show_php_error($severity, $message, $filepath, $line);
	}

	// If the error is fatal, the execution of the script should be stopped because
	// errors can't be recovered from. Halting the script conforms with PHP's
	// default error handling. See http://www.php.net/manual/en/errorfunc.constants.php
	if ($is_error) {
		exit(1); // EXIT_ERROR
	}
}


// ------------------------------------------------------------------------


/**
 * Exception Handler
 *
 * Sends uncaught exceptions to the logger and displays them
 * only if display_errors is On so that they don't show up in
 * production environments.
 *
 * @param	Exception	$exception
 * @return	void
 */
function _exception_handler($exception) {
	$_error = &load_class('Exceptions', 'core');
	$err = array();
	$err['type'] = "Exception";
	$err['message'] = $exception->getMessage();
	$err['file'] = $exception->getFile();
	$err['line'] = $exception->getLine();
	$CI = &get_instance();
	if ($CI->RSLogging) {
		$CI->RSLogging->LOG->exception = $err;
		$CI->RSLogging->LOG->type = RSLog::$TYPE_ERR;
		$CI->RSLogging->onShutdown();
	}
	// Should we display the error?
	if (str_ireplace(array('off', 'none', 'no', 'false', 'null'), '', ini_get('display_errors'))) {
		$_error->show_exception($exception);
	}
	exit(1); // EXIT_ERROR
}


// ------------------------------------------------------------------------


/**
 * Shutdown Handler
 *
 * This is the shutdown handler that is declared at the top
 * of CodeIgniter.php. The main reason we use this is to simulate
 * a complete custom exception handler.
 *
 * E_STRICT is purposivly neglected because such events may have
 * been caught. Duplication or none? None is preferred for now.
 *
 * @link	http://insomanic.me.uk/post/229851073/php-trick-catching-fatal-errors-e-error-with-a
 * @return	void
 */
function _shutdown_handler() {
	$last_error = error_get_last();
	if (
		$last_error !== NULL &&
		($last_error['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_COMPILE_WARNING))
	) {
		_logFatalError($last_error['type'], $last_error['message'], $last_error['file'], $last_error['line']);

		_error_handler($last_error['type'], $last_error['message'], $last_error['file'], $last_error['line']);
	} else {
		$CI = &get_instance();
		if ($CI->RSLogging) {
			$CI->RSLogging->LOG->dbError = $CI->db->error();
			$CI->RSLogging->LOG->procError = error_get_last();

			if ($CI->RSLogging->LOG->dbError['code'] !== 0 || $CI->RSLogging->LOG->procError !== null || $CI->RSLogging->LOG->exception !== null) {
				$CI->RSLogging->LOG->type = RSLog::$TYPE_ERR;
			};
			shutdownWriter($CI->RSLogging->LOG);
		}
	}
}
