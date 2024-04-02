# rs_ci_logger
An end to end request, query, and error logger and request log event processor for Code Igniter V3

/application/hooks/rs_logging.php:
  Intercept incoming requests and log them to a structured logging class 'RSLog'
  
  To implement:
  1 ) Place this file in application/hooks
  2 ) config/hooks.php must register the following hooks:
  
  $hook['post_controller_constructor'][] = array(
  'class'    => 'RSLogging',  
  'function' => 'logRequestPre',
  'filename' => 'RS_logging.php',  
  'filepath' => 'hooks
  );
  
  $hook['post_controller'][] = array(
  'class'    => 'RSLogging',  
  'function' => 'logRequestPost', 
  'filename' => 'RS_logging.php',  
  'filepath' => 'hooks'
  );
  
  3 ) Require this file once in index.php just ahead of codeigniter.php, 
  so that the exception, shutdown, and error handlers in this file take precidence over the ones in Common.php
  ( an alternative is to rename the exception, shutdown, and error handlers here, 
   and edit the defined a custom error handlers in codeigniter.php line 132 to call them )
  
  RSLogging->logRequestPre 
  	-	logs the data at hand at the post_controller_constructor lifecycle hook
  	- 	adds query data from the queries called post_controller to the log
  	- 	the registered shutdown and exception handlers append and query errors, prcess errors, or exceptions thrown to the log data,
  	- 	finally, the log data is written to temp file and sent to persistant storage
   - 	the RSLog log data is able to be interpredted into viewable summary data in the RSActivityTx.php library 
  
  Lifecycle: Request shutdown, error and exception callbacks are overridden here to pass the logged data through to wherever they need to go
  Examples here are:
  	-	Insert to database table ( MYSql example )
  	-	Log to file ( Simple example )
  	-	Simple stream processing with PHP. 
  	-	RSActivityTX
  	-	Included in this repo is the RSActivityTX and ActLogTranslate classes, which processes the forwarded log data by mapping handler functions to the log's [route][controller] => ()
  	-	i.e. for a request to site.com/reports/monthlyreport, the data is handled like ActLogTranslate->controller['reports']['monthlyreport'] ( RSLog data to pocess )
  	-	Ideally for processing a large mapping of logs, this procesing work should be offloaded to some other thread / server / instance etc., preferably something pre compiled to accomodate a large handler mapping structure
  	-	The RSActivityTX class is the desired data processing outcome, and the ActLogTranslate class holds the mappings of translation handlers.
  	
  	-	TODO: The functions RSLog->onShutdown and shutdownWriter are essentially duplicate processes, because on some request termination handlers the Code Igniter instance is no longer availble.
  		TODO: Replace RSLog->onShutdown with just shutdownWriter if possible
  	-	The log processing logic is called at the the end of the RSLog->onShutdown and shutdownWriter procedures like this:
  	-	$alt = new ActLogTranslate(true);
 	-	$alt->transl($log);
