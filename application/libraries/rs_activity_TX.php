<?php
require_once('/PathTo/rs_logging.php');
/**
 * Script Contents:
 * OldActivityLogger: Interface for working with existing / old activity_log table / arrays
 * OldActivityLogger: Save to old activity logs in mysql db
 * RSLogStrings - string defninitions
 * RSActivity class for summarized view of RSLog logs
 * ActivityLogHander class of static methods for log translation to RSActivity summary object
 *  - These handlers contain the readable multilang (1:english, 2:japanese) strings like activity name and description
 *  - These handlers can be tuned to extrapolate per endpoint specifics like "N # of records downloaded" or "Updated Work Order Line items with item id#123"
 * 	- Descriptiopns can be tailord per topic handler based on RSLog data like queries, URI vars, or POST data
 * 	- A more in depth description object could be added with a second layer of per type translator 
 * 	- i.e. report download type could have a detail array of [FILTER PARAMS] etc.
 * ControllerKeys - constants for referencing controller file names as logged in RSLog->uriVars[0] 
 * ActKeys - constsnts for referencing function enpoints within a controller as logged in RSLog->uriVars[2] 
 * ActLogTranslate - the translation class for ingesting RSLog data and generating RSActivity objects from it via the above mechanisms.
 * 	- Usage: Create an instance of ActLogTranslate and provide its transl method with RSLog data to generate the corresponding RSActivity log summaries
 */


 /**
 * RS Logging: OldActivityLogger
 * Example of a paralled record structure that we need to integrate the RSLog data with
 */
class OldActivityLogger{
	/**
	 * @var string
	 */
	public $action = "";
	/**
	 * @var string
	 */
	public $action_by = "";
	/**
	 * @var date("Y-m-d H:i:s")
	 */
	public $action_time = "";
	/**
	 * @var int
	 */
	public $primary_id = 0;
	/**
	 * @var int
	 */
	public $module_job_id = 0;
	/**
	 * @var	string
	 */
	public $module_name = "";
	/**
	 * @var string
	 */
	public $table_name = "";
	/**
	 * @var string json string of data
	 */
	public $modify_array = "";
	/**
	 * @var string description
	 */
	public $remarks = "";
	/**
	 * @var string id from RSLog
	 */
	public $rsLogId = "";

	/**
	 * @var string session ID
	 */
	public $sessionId = "";

	public function __construct(){

	}

}

/**
 * Mock function to handle data post processing
 * I.E. send to database, service, filesystem, event queue, etc
 * @param OldActivityLogger
 */
function saveOldActivityLog( $oldLog ){
	$log_array =(array)$oldLog;
	//someSaveProcudure($log_array, "activity_logs");
}

/**
 * String Definitions for this file
 */
class RSLogStrings {
    const COMPLETED = array( 1=> "Completed", 2=>"完了");
	const IN_PROGRESS = array( 1=> "In Progress", 2=>"進行中");
    const TYPE_REPORT_DL = array( 1=> "Report Download", 2=>"レポートのダウンロード");
	const TYPE_VENDOR_UPDATE = array( 1=> "Vendor Update", 2=>"ベンダーのアップデート");
}

/**
 * data field names 
 */
class RSLogIDTitles {
    const VENDOR_ID = array( 1=> "Vendor ID", 2=>"ベンダーID");
}

/**
 * constants for referencing controller file names as logged in RSLog->uriVars[0] 
 * Our example controllers will be called 'Reports' and 'Vendors'
 */
class ControllerKeys {
	const REPORTS = "Reports";
	const VENDORS = "Vendors";
}

/**
 * constants for referencing endpoint function names as logged in RSLog->uriVars[1] 
 * Our example endpoints will be:
 * 	-	Reports/generate_invoice_report 
 * 	-	Reports/vendor_report
 * 	-	Vendors/addVendor
 * 	-	Vendors/editVendor
 */
class ActKeys {
	//REPORTS
	const INVOICE_REPORT = "generate_invoice_report";
	const VENDOR_REPORT = "vendor_report";

	//VENDORS
	const ADD_VENDOR = "addVendor";
	const EDIT_VENDOR = "editVendor";

}


/**
 * @property array $name - the activity name
 * @property array $type - the activity type
 * @property array $description - the activity description
 * @property string $Controller - the controller file called	
 * @property string $atTime - the time the activity was logged
 * @property string $userEmail - the email of the user
 * @property string $state - the activity state, RsActivityLookup::IN_PROGRESS or RsActivityLookup::COMPLETED
 * @property string $logId - the log ID
 * @property RSLog $RSLog - the log object
 */
class RsActivity {
	
	/**
	 * @var array The activity readable name, 1 for English, 2 for Japanese.
	 */
	public $name = array();
	/**
	 *@var array The activity readable type, 1 for English, 2 for Japanese.
	 */
	public $type = array();
	/**
	 *@var array The activity readable description, 1 for English, 2 for Japanese.
	 */
	public $description = array();

    public $atTime = "";

    public $userEmail = "";
	/**
	 * @var string The activity state, RsActivityLookup::IN_PROGRESS or RsActivityLookup::COMPLETED.
	 */
	public $state = RSLogStrings::IN_PROGRESS;
	/**
	 * @var string The log ID.
	 */
    public $logId = "";
	/**
	 * @var RSLog The log object.
	 */
	public $RSLog = null;
	
}



class ActivityLogHandler {

	/**
	 * @param OldActivityLogger $rsActivity
	 */
	public static function doOldLogger($RsActivity){
		$oldLog = new OldActivityLogger();
		$oldLog->action = $RsActivity->name[2] ." | ".$RsActivity->name[1] ;
		$oldLog->remarks = $RsActivity->description[2]  ." | ".$RsActivity->description[1] ;
		$oldLog->action_time = date("Y-m-d H:i:s");
		$oldLog->action_by = $RsActivity->RSLog->userID;
		$oldLog->modify_array = json_encode($RsActivity->RSLog->post, JSON_UNESCAPED_UNICODE);
		$oldLog->module_name = "Interior_RM";
		$oldLog->module_job_id = 0;
		$oldLog->primary_id = 0;
		$oldLog->table_name = "";
		$oldLog->rsLogId = $RsActivity->RSLog->logId;
		$oldLog->sessionId = $RsActivity->RSLog->sessionId;
		saveOldActivityLog($oldLog);
	}

	/** 
	 * process add vendor event by its log data
	 * @param RSLog $RSLog
	 * @return RsActivity
	 */
	public static function ADD_VENDOR($RSLog) {
		$RsActivity = new RsActivity();
        $RsActivity->logId = $RSLog->logId;
	    $RsActivity->name = array( 
            1=> "Added Vendor",
            2=> "ベンダーを追加しました"
        );
        $RsActivity->type = array(
            1=> RSLogStrings::TYPE_VENDOR_UPDATE
        );

		$vn =  $RSLog->post['VendorNameWasSentInPostRequest'];
        $RsActivity->description = array(
			
            1=> $RSLog->userEmail." added Vendor ".$vn."",
            2=> $RSLog->userEmail." ベンダーを追加しました ".$vn."",
        );
        $RsActivity->atTime = $RSLog->time;
        $RsActivity->userEmail = $RSLog->userEmail;
        $RsActivity->state =  RSLogStrings::IN_PROGRESS;
		$RsActivity->RSLog = $RSLog;
		return $RsActivity; 
	}

	/** 
	 * process edit vendor event by its log data
	 * @param RSLog $RSLog
	 * @return RsActivity
	 */
	public static function EDIT_VENDOR($RSLog) {
		$RsActivity = new RsActivity();
        $RsActivity->logId = $RSLog->logId;
	    $RsActivity->name = array( 
            1=> "Edited Vendor",
            2=> "編集されたベンダー"
        );
        $RsActivity->type = array(
            1=> RSLogStrings::TYPE_VENDOR_UPDATE
        );
		
        $RsActivity->description = array(
            1=> $RSLog->userEmail." edited Vendor #".$RSLog->mainRsrc.": ".$RSLog->post['VendorNameWasSentInPostRequest'],
            2=> $RSLog->userEmail." 編集されたベンダー #".$RSLog->mainRsrc.": ".$RSLog->post['VendorNameWasSentInPostRequest']
        );
        $RsActivity->atTime = $RSLog->time;
        $RsActivity->userEmail = $RSLog->userEmail;
        $RsActivity->state =  RSLogStrings::IN_PROGRESS;
		$RsActivity->RSLog = $RSLog;

		return $RsActivity; 
	}


	/**
	 * process invoice report event by its log data
	 * @param RSLog $RSLog
	 * @return RsActivity $RsActivity
	 */
	public static function INVOICE_REPORT($RSLog) {
		$RsActivity = new RsActivity();
        $RsActivity->logId = $RSLog->logId;
	    $RsActivity->name = array( 
            1=> "Invoice Report Downloaded",
            2=> "請求書レポートがダウンロードされました"
        );
        $RsActivity->type = array(
            1=> RSLogStrings::TYPE_REPORT_DL
        );
        $RsActivity->description = array(
            1=> "Invoice Report Downloaded by ".$RSLog->userEmail."",
            2=> "請求書レポートのダウンロード者 ".$RSLog->userEmail.""
        );
        $RsActivity->atTime = $RSLog->time;
        $RsActivity->userEmail = $RSLog->userEmail;
        $RsActivity->state =  RSLogStrings::IN_PROGRESS;
		$RsActivity->RSLog = $RSLog;
		return $RsActivity; 
	}




	/**
	* process vendor report event by its log data
	 * @param RSLog $RSLog
	 * @return RsActivity $RsActivity
	 */
	public static function VENDOR_REPORT($RSLog) {
		$RsActivity = new RsActivity();
        $RsActivity->logId = $RSLog->logId;
	    $RsActivity->name = array( 
            1=> "Vendor Report Downloaded",
            2=> "ベンダーレポートがダウンロードされました"
        );
        $RsActivity->type = array(
            1=> RSLogStrings::TYPE_REPORT_DL
        );
        $RsActivity->description = array(
            1=> "Vendor Report Downloaded by ".$RSLog->userEmail."",
            2=> "ベンダーレポートのダウンロード元 ".$RSLog->userEmail.""
        );
        $RsActivity->atTime = $RSLog->time;
        $RsActivity->userEmail = $RSLog->userEmail;
        $RsActivity->state =  RSLogStrings::IN_PROGRESS;
		$RsActivity->RSLog = $RSLog;
		return $RsActivity; 
	}

}



/**
 * Class to lookup activities based on the log
 * Instantiate where activity log summary is needed
 * @example $actTx = new ActLogTranslate();
 * 			$activity = $actTx->lookup($RSLog);
 */
class ActLogTranslate {

	public $doOldLoggerBool = false;

	/**
	 * @var array $controller A 2d KV array of controller file names to function endpoint names to transation handlers that will be called to translate the log
	 * @example array( 'ctrlfile1'=>['epFunc1'=>handler, 'epFunc2'=>handler], 'ctrlfile2'=>['epFunc1'=>handler])
	 * 	
	 */
	public $controller = array();

	/**
	 * Translate the log to an RsActivity object
	 * Called from rs_logging.php to feed log data into processing handlers
	 * Resolves log event handlers with the "doOldLogger" example funtion
	 * but the processed data can handled however needed here i.e. send to service, further event queue system, etc. 
	 * 
	 * @param RSLog $rsLog
	 * @return RsActivity
	 */
    public function transl( $rsLog ){	
		if( array_key_exists($rsLog->uriVars[0], $this->controller )){
			if( array_key_exists($rsLog->uriVars[1], $this->controller[$rsLog->uriVars[0]] )){
				try{
					$rsActivity =  $this->controller[$rsLog->uriVars[0]][$rsLog->uriVars[1]]($rsLog);
					if ( $this->doOldLoggerBool ){
						//!Handle the RsActivity result of the log event processing here
						ActivityLogHandler::doOldLogger($rsActivity);
					}
					return $rsActivity;
				}catch(Exception $e){
					ActLogTranslate::logTranslError("Error translating MXLog", $e);
				}
			}
		}
		return NULL;
    }

	/**
	 * Example simple error handler, just writes to a local file
	 * @param string $msg - user defined error message
	 * @param Exception $err - a thrown exception
	 */
	public static function logTranslError($msg, $err) {
		$fp = fopen('/SomeFilePath/log_transl_error.txt', 'a');
		$e = new stdClass();
		$e->msg = $msg;
		$e->err = $err;
		fwrite($fp, json_encode($e) . "\n");
		fclose($fp);
	}
    
	/**
	 * Constructor for Activity Log Translations
	 * - !Register your log processing logic here
	 * - set a kv array on $this->controller with the endpoint controller file name that will be read from RSLog->uriVars[0]
	 * - its keys should be controller function endpoints that will be read from  RSLog->uriVars[1]
	 * - each of these values should be an anonymous function that accepts a RSLog object and returns an RsActivity object
	 * - our bank of handler functions for this will be static methods in the ActivityLogHandler class
	 * @param bool $shouldDoOldLogger - true if this is being used to propagate logs to old logger at capture time, flase if this is just a translator
	 */
    public function __construct( $shouldDoOldLogger = false){
		$this->doOldLoggerBool = $shouldDoOldLogger;

		//Report generations and downloads from contoller 'Reports/generate_invoice_report' and 'Reports/vendor_report'
		//map them to the handlers ActivityLogHandler::INVOICE_REPORT and ActivityLogHandler::VENDOR_REPORT
        $this->controller[ControllerKeys::REPORTS] = array(

			ActKeys::INVOICE_REPORT => function ($RSLog) {
				return ActivityLogHandler::INVOICE_REPORT($RSLog);
			},
			ActKeys::VENDOR_REPORT => function ($RSLog) {
				return ActivityLogHandler::VENDOR_REPORT($RSLog);
			}
        );

		//Report generations and downloads from contoller 'Vendors/addVendor' and 'Vendors/editVendor'
		//map them to the handlers ActivityLogHandler::ADD_VENDOR and ActivityLogHandler::EDIT_VENDOR
		$this->controller[ControllerKeys::VENDORS] = array(
			ActKeys::ADD_VENDOR  => function ($RSLog) {
				return ActivityLogHandler::ADD_VENDOR($RSLog);
			},
			ActKeys::EDIT_VENDOR  => function ($RSLog) {
				return ActivityLogHandler::EDIT_VENDOR($RSLog);
			},
		);
    }

}

