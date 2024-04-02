# rs_ci_logger
An end to end request, query, and error logger and request log event processor for Code Igniter V3


# 1. /application/hooks/rs_logging.php:
Intercept incoming requests and log them to a structured logging class 'RSLog'

To implement:
  1. rs_logging.php goes in application/hooks
  2. config/hooks.php must register the following hooks :
  ```
  $hook['post_controller_constructor'][] = array(
  'class'    => 'RSLogging',  
  'function' => 'logRequestPre',
  'filename' => 'rs_logging.php',  
  'filepath' => 'hooks
  );
  
  $hook['post_controller'][] = array(
  'class'    => 'RSLogging',  
  'function' => 'logRequestPost', 
  'filename' => 'ps_logging.php',  
  'filepath' => 'hooks'
  );
  ```

So the logRequestPre and logRequestPost methods of the  are tied to the CI request lifecycle
  
3. Require rs_logging.php file once in index.php just ahead of codeigniter.php, 
  so that the exception, shutdown, and error handlers in this file take precedence over the ones in Common.php
  ```
  /*
* --------------------------------------------------------------------
* LOAD THE BOOTSTRAP FILE
* --------------------------------------------------------------------
*
* And away we go...
*/
require_once('application/hooks/rs_logging.php');
require_once BASEPATH.'core/CodeIgniter.php';
 ```
	
>  an alternative is to rename the exception, shutdown, and error handlers here, 
   and edit the defined a custom error handlers in codeigniter.php line 132 to call them.
   The main thing is that code igniter must use the exception, shutdown, and error handlers as defined in 			   rs_logging.php so that logging captures everything post exception, shutdown, and error.



### Notable Features	
RSLogging->logRequestPre 
  - Logs the data at hand at the post_controller_constructor lifecycle hook
  -  Adds query data from the queries called post_controller to the log
  -  Dynamically mounts the logger object and log data to the Code Igniter instance so it can be interacted with elsewhere throughout the request, by endpoint controllers or wherever the CI instance is available 
  -  The registered shutdown and exception handlers append and query errors, process errors, or exceptions thrown to the log data, and send the log data on its merry way
  	- 	Its merry way can include being written to temp file and sent to persistent storage ( do what you will with it ).
  	-  And best of all,
   - 	It can be processed the rs_activity-tx.php library 
  
### Handling the log data
  Included examples here are:
  - Insert to database table ( MySQL example )
  - Log to file ( Simple example )
  - Simple stream processing with PHP: Included in this repository is the RSActivityTX and ActLogTranslate classes, which processes the forwarded log data by mapping handler functions to the log's route and controller, i.e. map[route][controller] = handler(RSlog $logData). 
  - These log event processing handlers are loaded into a controller mapping in the constructor of the ActLogTranslate class:
 ```
 //Report generations and downloads from contoller 'Reports/generate_invoice_report' and 'Reports/vendor_report'
//map them to the handlers ActivityLogHandler::INVOICE_REPORT and ActivityLogHandler::VENDOR_REPORT
//using ConstantKeyClass::KEYNAME in lieu of 'random_strings'
$this->controller[ControllerKeys::REPORTS] = array(
	ActKeys::INVOICE_REPORT => function ($RSLog) {
		return  ActivityLogHandler::INVOICE_REPORT($RSLog);
	},
	ActKeys::VENDOR_REPORT => function ($RSLog) {
		return  ActivityLogHandler::VENDOR_REPORT($RSLog);
	}
);
```

 -  These log event processing handlers and are dispatched by calling ActLogTranslate->transl(RSLog $logData)
 -	Within rs_activity_tx.php, the RSActivityTX class is used to define the desired data processing outcome, the ActLogTranslate class is the actual processing engine, and the processing handlers are defined as static functions  ActLogTranslate::PROCESSING_HANDLER(RSLog $logData)
  	-	Ideally for processing a large mapping of logs, this procesing work should be offloaded to some other thread / server / instance etc., preferably something pre compiled to accomodate a large handler mapping structure
  
 - The log processing logic is called at the the end of the RSLog->onShutdown and shutdownWriter procedures like this:
  	```
  	$alt = new ActLogTranslate(true);
 	$alt->transl($log);
 	```
  
  ### TODO	
  -	The functions RSLog->onShutdown and shutdownWriter are essentially duplicate processes, because on some request termination handlers the Code Igniter instance is no longer availble.
  - So look into replacing RSLog->onShutdown with just shutdownWriter if possible
