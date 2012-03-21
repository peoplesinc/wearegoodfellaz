<?PHP

///////Form Installer////////////////////
class FM_FormInstaller extends FM_Module
{
    var $formdata_folder;
    var $formdata_folder_rel;
    var $csv_maker;
    var $file_uploader;
    var $simple_db;

    function FM_FormInstaller()
    {
        $this->formdata_folder="";
        $this->formdata_folder_rel="";
        $this->csv_maker=NULL;
        $this->file_uploader = NULL;
        $this->response_sender = NULL;
        $this->simple_db = NULL;
    }
    
    function addError($error)
    {
        $this->response_sender->addError($error);
    }
        
    function SetCSVMaker(&$csvmkr)
    {
        $this->csv_maker = &$csvmkr;
    }

    function SetFileUploader(&$uploader)
    {
        $this->file_uploader = &$uploader;
    }

    function SetSimpleDB(&$simpledb)
    {
        $this->simple_db = &$simpledb;
    }

    function Process(&$continue)
    {
        $continue=true;
        $globals = new FM_GlobalData($this->config);
        $this->response_sender = new FM_Response($this->config,$this->logger);

        $globals->GetGlobalVars();

        if(isset($globals->post_vars[$this->config->config_update_var]) )
        {
            $this->logger->LogInfo("FM_FormInstaller: config_update_var is set");

            $continue=false;
            if($this->ValidateConfigInput())
            {
                $this->ProcessConfigUpdate();
            }
            
            $this->ShowResponse();
        }
        else
        {
            $this->error_handler->EnableErrorFormMerge();
            if(false == $this->config->installed)
            {
                $continue=false;
                $this->ShowFormNotInstalled();
            }
        }
    }
    
    function ShowResponse()
    {
		if($this->error_handler->IsSysError())
		{
			$this->addError($this->error_handler->GetSysError());
		}
        $this->response_sender->ShowResponse();
    }

    function ValidateConfigInput()
    {
        $ret=false;
        if(!isset($this->config->encr_key) ||
            strlen($this->config->encr_key)<=0)
        {
            $this->addError(E_CONF_FORM_KEY_NOT_SET);
        }
        else
        if(!isset($this->config->form_id) ||
            strlen($this->config->form_id)<=0)
        {
            $this->addError(E_CONF_FORM_ID_NOT_SET);
        }
        else
        if(!isset($this->globaldata->post_vars[$this->config->config_form_id_var]))
        {
            $this->addError(E_CONF_FORM_ID_NOT_SET);
        }
        else
        {
            $form_id = $this->globaldata->post_vars[$this->config->config_form_id_var];
            $form_id = $this->Decrypt($form_id);
            if(strcmp($form_id,$this->config->form_id)!=0)
            {
                $this->addError(E_CONF_FORM_ID_NOT_MATCH);
            }
            else
            {
                $this->logger->LogInfo("FM_FormInstaller:ValidateConfigInput succeeded");
                $ret=true;
            }
        }
        return $ret;
    }

    function Decrypt($str)
    {
        return sfm_crypt_decrypt($str,$this->config->encr_key);
    }

    function ProcessConfigUpdate()
    {
        $cmd = $this->globaldata->post_vars[$this->config->config_update_var];
        
        $this->logger->LogInfo("ProcessConfigUpdate: command $cmd");

        $cmd = $this->Decrypt($cmd);

        $this->logger->LogInfo("ProcessConfigUpdate: command decrypted $cmd");

        $val = "";
        if(isset($this->globaldata->post_vars[$this->config->config_update_val]))
        {
            $val = $this->globaldata->post_vars[$this->config->config_update_val];
            $val = $this->Decrypt($val);
        }

        $this->logger->LogInfo("ProcessConfigUpdate: $cmd:$val");

        $this->process_command($cmd,$val);
    }

    function ShowFormNotInstalled()
    {
        $this->logger->LogInfo("FM_FormInstaller: showing form not installed");
        $this->DisplayError(E_CONF_FORM_NOT_INSTALLED_TITLE,E_CONF_FORM_NOT_INSTALLED);
    }

    function DisplayError($title,$error_str)
    {
        echo "<HTML><HEAD><TITLE>$title</TITLE></HEAD><BODY><H1>$title</H1>$error_str</BODY>";
    }

    function process_command($cmd,$val)
    {
        switch($cmd)
        {
            case "qry_install":
            {
                $this->QryInstall($val);
            }
            break;

            case "data_file_list":
            { 
                $this->csv_maker->get_data_file_list($this->response_sender);
            }
            break;

            case "get_file":
            {
                if(strlen($val)<=0)
                {
                    $this->addError(E_CONF_FILE_NAME_NOTSET);
                }
                else
                {
                    $this->csv_maker->get_file($val,$this->response_sender);
                }
            }
            break;

            case "rem_file":
            {
                if(strlen($val)<=0)
                {
                    $this->addError(E_CONF_FILE_NAME_NOTSET);
                }
                else
                {
                    $this->rem_file($val);
                }                
            }
            break;

            case "log_file":
            {
                $this->get_log_file();
            }
            break;

            case "get_attachment_list":
            {
                if(is_null($this->file_uploader))
                {
                    $this->addError(E_ERR_NO_FILE_UPLOAD_OBJ_SET);
                    return false;
                }

                $this->file_uploader->get_attachment_list($this->response_sender);
            }
            break;

            case "get_attachment":
            {
                if(strlen($val)<=0)
                {
                    $this->addError(E_CONF_FILE_NAME_NOTSET);
                }
                else
                {
                    $this->file_uploader->get_attachment($val,$this->response_sender);
                }                
            }
            break;

			case "ping":
			{
				$this->Ping();
			}
			break;

			case "db_get_rec_count":
			{
                  $this->simple_db->GetRecCount($this->response_sender);
			}
			break;

			case "db_get_recs":
			{
                  $this->simple_db->GetDBRecs($this->response_sender,$val);
			}
			break;
        }//switch($cmd)
    }

	function Ping()
	{
		$this->logger->LogInfo("FM_FormInstaller: Ping");
		$installed="no";
	    if(true == $this->config->installed)
        {
			$installed="yes";
		}
		$this->response_sender->appendResponse("is_installed",$installed);
	}

    function QryInstall($folder_proposed)
    {
        $formdata_folder = "";
        $this->logger->LogInfo("FM_FormInstaller: QryInstall");
        if(true == $this->config->installed)
        {
            $this->logger->LogInfo("FM_FormInstaller: QryInstall already installed.");
        }   
        else
        {
			if(!isset($folder_proposed) || strlen($folder_proposed)<=0)
			{
				$root_folder = $this->get_root_path();

				$this->logger->LogInfo("FM_FormInstaller: QryInstall root_folder: $root_folder");

				$formdata_folder = sfm_make_path($root_folder,"formdata");
			}
			else
			{
				$formdata_folder = $folder_proposed;
			}
        }
                
        if(strlen($formdata_folder)>0)
        {
            $this->response_sender->appendResponse("folder",$formdata_folder);
    
            $result = $this->try_make_formdata_folder($formdata_folder);
            $this->response_sender->appendResultResponse($result);
        }
        else
        {
            $this->response_sender->appendResponse("error_code","unknown");
            $this->logger->LogError("FM_FormInstaller: QryInstall: formdata_folder value is empty.");
        }
    }


    function get_log_file()
    {
		$log_file_path=$this->logger->get_log_file_path();

        $this->logger->LogInfo("FM_FormInstaller: getting log file log_file_path:$log_file_path");
        
        $this->response_sender->SetNeedToRemoveHeader();
        
        return $this->response_sender->load_file_contents($log_file_path);
    }

    function rem_file($val)
    {
        $parts = explode('/',$val);
        $filename=$parts[0];
        $is_attachment='n';
        if(isset($parts[1]))
        {
            $is_attachment=$parts[1];
        }
        $this->logger->LogInfo("FM_FormInstaller: rem_file $filename");

        $base_folder = $this->getFormDataFolder();
        if($is_attachment == 'a')
        {
            $this->logger->LogInfo("FM_FormInstaller: rem_file - is attachment");
            if(is_null($this->file_uploader))
            {
                $this->logger->LogError("FM_FormInstaller: rem_file file_uploader not set");
                $this->addError(E_ERR_NO_FILE_UPLOAD_OBJ_SET);
                return false;
            }
            $base_folder = $this->file_uploader->get_uploadfiles_folder();
        }

        $filename = trim($filename);
        if(strlen($filename)>0)
        {
          
          $filepath = sfm_make_path($base_folder,$filename);
          $success=false;
          if(unlink($filepath))
          {
            $this->appendResponse("result","success");
            $this->logger->LogInfo("FM_FormInstaller: rem_file removed file $filepath");
            $success=true;
          }
          $this->response_sender->appendResultResponse($success);
        }
    }
    
    function try_make_formdata_folder($folder_path)
    {
        $this->logger->LogInfo(" try_make_formdata_folder $folder_path");
        return  $this->make_formdata_folder($folder_path);
    }

    function get_root_path()
    {
        $script_url = $this->globaldata->server_vars['SCRIPT_NAME'];
        $script_file_path = $this->globaldata->server_vars['SCRIPT_FILENAME'];

        $this->logger->LogInfo("script_url $script_url\n script_file_path $script_file_path");
        
        $url_parts = split('/',$script_url);
        $path_parts = split('[/\\]',$script_file_path);

        $nu = count($url_parts);
        $np = count($path_parts);
        for($i=0;$i<$nu;$i++)
        {
            $iu = $nu-$i-1;
            $ip = $np-$i-1;
            if(strcasecmp($url_parts[$iu],$path_parts[$ip])!=0)
            {
                break;
            }
        }
        $arr_root_path = array_slice($path_parts,0,$np - $i-1);

        $root_path = implode("/",$arr_root_path);

        return $root_path;
    }

    function make_formdata_folder($folder)
    {
        if(!file_exists($folder))
        {
            if(!@mkdir($folder))
            {
                $this->logger->LogInfo(" mkdir $folder failed");
                $error_str = sprintf(E_CONF_ERROR_MAKE_FORMDATA_FOLDER,$folder);
                $this->addError($error_str);
                return false;
            }
            else
            {
                $this->logger->LogInfo(" mkdir $folder succeeded");
            }
        }
        if(!$this->test_folder_write($folder))
        {
            $this->logger->LogInfo(" test_folder_write $folder failed");
            $error_str = sprintf(E_CONF_ERROR_WRITE_FORMDATA_FOLDER,$folder);
            $this->addError($error_str);
            return false;
        }
        else
        {
            $this->logger->LogInfo(" test_folder_write $folder succeeded");
        }
        return true;
    }

    function test_folder_write($folder)
    {
        $file_path = sfm_make_path($folder,$this->formname.".log");
        $i=1;
        while(file_exists($file_path) && $i < 100)
        {
            $file_path = sfm_make_path($folder,$this->formname."$i.log");
            $i++;
        }
        if($i>=100){ return false; }

        $fp = @fopen($file_path,"w");
        if(!$fp)
        {
            return false;
        }
        $data_line ="Form processor installed.";

        if(!fwrite($fp,$data_line))
        {
          fclose($fp);
          return false;
        }
        fclose($fp);

        $read_back = file_get_contents($file_path);

        $ret=true;
        if(strcmp($read_back,$data_line) != 0)
        {
            $ret=false;
        }
        unlink($file_path);
        return true;
    }
}


class FM_Response
{
    var $error_str;
    var $response;
    var $encr_response;
    var $extra_headers;
    var $sfm_headers;

    function FM_Response(&$config,&$logger)
    {
        $this->error_str="";
        $this->response="";
        $this->encr_response=true;
        $this->extra_headers = array();
        $this->sfm_headers = array();
        $this->logger = &$logger;
		$this->config = &$config;
    }

    function addError($error)
    {
        $this->error_str .= $error;
        $this->error_str .= "\n";
    }

    function straighten_val($val)
    {
        $ret = str_replace("\n","\\n",$val);
        return $ret;
    }

    function appendResponse($name,$val)
    {
        $this->response .= "$name: ".$this->straighten_val($val);
        $this->response .= "\n";
    }

    function appendResultResponse($is_success)
    {
        if($is_success)
        {
            $this->appendResponse("result","success");
        }
        else
        {
            $this->appendResponse("result","failed");
        }
    }
    
    function SetEncrypt($encrypt)
    {
        $this->encr_response = $encrypt;
    }

    function AddResponseHeader($name,$val,$replace=false)
    {
        $header = "$name: $val";
        $this->extra_headers[$header] = $replace;
    }

    function AddSFMHeader($option)
    {
        $this->sfm_headers[$option]=1;
    }


    function SetNeedToRemoveHeader()
    {
        $this->AddSFMHeader('remove-header-footer');
    }

    function ShowResponse()
    {
        $err=false;

        if(strlen($this->error_str)>0) 
        {
            $err=true;
            $this->appendResponse("error",$this->error_str);
            $this->AddSFMHeader('sforms-error');
            $log_str = sprintf("FM_FormInstaller: reporting error:%s",$this->error_str);
            $this->logger->LogError($log_str);
        }
        
        $resp="";
        if(($this->encr_response || true == $err) && 
           (false == $this->config->sys_debug_mode))
        {
            $this->AddResponseHeader('Content-type','application/sforms-e');

            $resp = $this->Encrypt($this->response);
        }
        else
        {
            $resp = $this->response;
        }

        $cust_header = "SFM_COMM_HEADER_START{\n";
        foreach($this->sfm_headers as $sfm_header => $flag)
        {
             $cust_header .=  $sfm_header."\n";
        }
        $cust_header .= "}SFM_COMM_HEADER_END\n";

        $resp = $cust_header.$resp;

        $this->AddResponseHeader('pragma','no-cache',/*replace*/true);
		$this->AddResponseHeader('cache-control','no-cache');        
        $this->AddResponseHeader('Content-Length',strlen($resp));

        foreach($this->extra_headers as $header_str => $replace)
        {
            $this->logger->LogInfo("adding header $header_str");
            header($header_str, false);
        }


        print($resp);
        if(true == $this->config->sys_debug_mode)
        {
            $this->logger->print_log();
        }
    }

    function Encrypt($str)
    {
        //echo " Encrypt $str ";
        //$blowfish = new Crypt_Blowfish($this->config->encr_key);
        $blowfish =& Crypt_Blowfish::factory('ecb');
        $blowfish->setKey($this->config->encr_key);

        $encr = $blowfish->encrypt($str);
        $retdata = bin2hex($encr);
        return $retdata;
    }

    function load_file_contents($filepath)
    {
        $filename = basename($filepath);

        $this->encr_response=false;
        $this->logger->LogInfo("load_file_contents filepath: $filepath");

        $fp = fopen($filepath,"r");

        if(!$fp)
        {
            $err = sprintf(E_CONF_FAIL_OPEN_FILE,$filepath);
            $this->addError($err);
            return false;
        }

        $this->AddResponseHeader('Content-Disposition',"attachment; filename=\"$filename\"");

        $this->response = file_get_contents($filepath);
        
        return true;
    }
    
    function SetResponse($response)
    {
        $this->response = $response;
    }
}


/**
 * PEAR, the PHP Extension and Application Repository
 *
 * PEAR class and PEAR_Error class
 *
 * PHP versions 4 and 5
 *
 * LICENSE: This source file is subject to version 3.0 of the PHP license
 * that is available through the world-wide-web at the following URI:
 * http://www.php.net/license/3_0.txt.  If you did not receive a copy of
 * the PHP License and are unable to obtain it through the web, please
 * send a note to license@php.net so we can mail you a copy immediately.
 *
 * @category   pear
 * @package    PEAR
 * @author     Sterling Hughes <sterling@php.net>
 * @author     Stig Bakken <ssb@php.net>
 * @author     Tomas V.V.Cox <cox@idecnet.com>
 * @author     Greg Beaver <cellog@php.net>
 * @copyright  1997-2008 The PHP Group
 * @license    http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version    CVS: $Id: PEAR.php,v 1.1 2009/01/28 07:34:12 Prasanth Exp $
 * @link       http://pear.php.net/package/PEAR
 * @since      File available since Release 0.1
 */

/**#@+
 * ERROR constants
 */
define('PEAR_ERROR_RETURN',     1);
define('PEAR_ERROR_PRINT',      2);
define('PEAR_ERROR_TRIGGER',    4);
define('PEAR_ERROR_DIE',        8);
define('PEAR_ERROR_CALLBACK',  16);
/**
 * WARNING: obsolete
 * @deprecated
 */
define('PEAR_ERROR_EXCEPTION', 32);
/**#@-*/
define('PEAR_ZE2', (function_exists('version_compare') &&
                    version_compare(zend_version(), "2-dev", "ge")));

if (substr(PHP_OS, 0, 3) == 'WIN') {
    define('OS_WINDOWS', true);
    define('OS_UNIX',    false);
    define('PEAR_OS',    'Windows');
} else {
    define('OS_WINDOWS', false);
    define('OS_UNIX',    true);
    define('PEAR_OS',    'Unix'); // blatant assumption
}

// instant backwards compatibility
if (!defined('PATH_SEPARATOR')) {
    if (OS_WINDOWS) {
        define('PATH_SEPARATOR', ';');
    } else {
        define('PATH_SEPARATOR', ':');
    }
}

$GLOBALS['_PEAR_default_error_mode']     = PEAR_ERROR_RETURN;
$GLOBALS['_PEAR_default_error_options']  = E_USER_NOTICE;
$GLOBALS['_PEAR_destructor_object_list'] = array();
$GLOBALS['_PEAR_shutdown_funcs']         = array();
$GLOBALS['_PEAR_error_handler_stack']    = array();

@ini_set('track_errors', true);

/**
 * Base class for other PEAR classes.  Provides rudimentary
 * emulation of destructors.
 *
 * If you want a destructor in your class, inherit PEAR and make a
 * destructor method called _yourclassname (same name as the
 * constructor, but with a "_" prefix).  Also, in your constructor you
 * have to call the PEAR constructor: $this->PEAR();.
 * The destructor method will be called without parameters.  Note that
 * at in some SAPI implementations (such as Apache), any output during
 * the request shutdown (in which destructors are called) seems to be
 * discarded.  If you need to get any debug information from your
 * destructor, use error_log(), syslog() or something similar.
 *
 * IMPORTANT! To use the emulated destructors you need to create the
 * objects by reference: $obj =& new PEAR_child;
 *
 * @category   pear
 * @package    PEAR
 * @author     Stig Bakken <ssb@php.net>
 * @author     Tomas V.V. Cox <cox@idecnet.com>
 * @author     Greg Beaver <cellog@php.net>
 * @copyright  1997-2006 The PHP Group
 * @license    http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version    Release: 1.7.2
 * @link       http://pear.php.net/package/PEAR
 * @see        PEAR_Error
 * @since      Class available since PHP 4.0.2
 * @link        http://pear.php.net/manual/en/core.pear.php#core.pear.pear
 */
class PEAR
{
    // {{{ properties

    /**
     * Whether to enable internal debug messages.
     *
     * @var     bool
     * @access  private
     */
    var $_debug = false;

    /**
     * Default error mode for this object.
     *
     * @var     int
     * @access  private
     */
    var $_default_error_mode = null;

    /**
     * Default error options used for this object when error mode
     * is PEAR_ERROR_TRIGGER.
     *
     * @var     int
     * @access  private
     */
    var $_default_error_options = null;

    /**
     * Default error handler (callback) for this object, if error mode is
     * PEAR_ERROR_CALLBACK.
     *
     * @var     string
     * @access  private
     */
    var $_default_error_handler = '';

    /**
     * Which class to use for error objects.
     *
     * @var     string
     * @access  private
     */
    var $_error_class = 'PEAR_Error';

    /**
     * An array of expected errors.
     *
     * @var     array
     * @access  private
     */
    var $_expected_errors = array();

    // }}}

    // {{{ constructor

    /**
     * Constructor.  Registers this object in
     * $_PEAR_destructor_object_list for destructor emulation if a
     * destructor object exists.
     *
     * @param string $error_class  (optional) which class to use for
     *        error objects, defaults to PEAR_Error.
     * @access public
     * @return void
     */
    function PEAR($error_class = null)
    {
        $classname = strtolower(get_class($this));
        if ($this->_debug) {
            print "PEAR constructor called, class=$classname\n";
        }
        if ($error_class !== null) {
            $this->_error_class = $error_class;
        }
        while ($classname && strcasecmp($classname, "pear")) {
            $destructor = "_$classname";
            if (method_exists($this, $destructor)) {
                global $_PEAR_destructor_object_list;
                $_PEAR_destructor_object_list[] = &$this;
                if (!isset($GLOBALS['_PEAR_SHUTDOWN_REGISTERED'])) {
                    register_shutdown_function("_PEAR_call_destructors");
                    $GLOBALS['_PEAR_SHUTDOWN_REGISTERED'] = true;
                }
                break;
            } else {
                $classname = get_parent_class($classname);
            }
        }
    }

    // }}}
    // {{{ destructor

    /**
     * Destructor (the emulated type of...).  Does nothing right now,
     * but is included for forward compatibility, so subclass
     * destructors should always call it.
     *
     * See the note in the class desciption about output from
     * destructors.
     *
     * @access public
     * @return void
     */
    function _PEAR() {
        if ($this->_debug) {
            printf("PEAR destructor called, class=%s\n", strtolower(get_class($this)));
        }
    }

    // }}}
    // {{{ getStaticProperty()

    /**
    * If you have a class that's mostly/entirely static, and you need static
    * properties, you can use this method to simulate them. Eg. in your method(s)
    * do this: $myVar = &PEAR::getStaticProperty('myclass', 'myVar');
    * You MUST use a reference, or they will not persist!
    *
    * @access public
    * @param  string $class  The calling classname, to prevent clashes
    * @param  string $var    The variable to retrieve.
    * @return mixed   A reference to the variable. If not set it will be
    *                 auto initialised to NULL.
    */
    function &getStaticProperty($class, $var)
    {
        static $properties;
        if (!isset($properties[$class])) {
            $properties[$class] = array();
        }
        if (!array_key_exists($var, $properties[$class])) {
            $properties[$class][$var] = null;
        }
        return $properties[$class][$var];
    }

    // }}}
    // {{{ registerShutdownFunc()

    /**
    * Use this function to register a shutdown method for static
    * classes.
    *
    * @access public
    * @param  mixed $func  The function name (or array of class/method) to call
    * @param  mixed $args  The arguments to pass to the function
    * @return void
    */
    function registerShutdownFunc($func, $args = array())
    {
        // if we are called statically, there is a potential
        // that no shutdown func is registered.  Bug #6445
        if (!isset($GLOBALS['_PEAR_SHUTDOWN_REGISTERED'])) {
            register_shutdown_function("_PEAR_call_destructors");
            $GLOBALS['_PEAR_SHUTDOWN_REGISTERED'] = true;
        }
        $GLOBALS['_PEAR_shutdown_funcs'][] = array($func, $args);
    }

    // }}}
    // {{{ isError()

    /**
     * Tell whether a value is a PEAR error.
     *
     * @param   mixed $data   the value to test
     * @param   int   $code   if $data is an error object, return true
     *                        only if $code is a string and
     *                        $obj->getMessage() == $code or
     *                        $code is an integer and $obj->getCode() == $code
     * @access  public
     * @return  bool    true if parameter is an error
     */
    function isError($data, $code = null)
    {
        if (is_a($data, 'PEAR_Error')) {
            if (is_null($code)) {
                return true;
            } elseif (is_string($code)) {
                return $data->getMessage() == $code;
            } else {
                return $data->getCode() == $code;
            }
        }
        return false;
    }

    // }}}
    // {{{ setErrorHandling()

    /**
     * Sets how errors generated by this object should be handled.
     * Can be invoked both in objects and statically.  If called
     * statically, setErrorHandling sets the default behaviour for all
     * PEAR objects.  If called in an object, setErrorHandling sets
     * the default behaviour for that object.
     *
     * @param int $mode
     *        One of PEAR_ERROR_RETURN, PEAR_ERROR_PRINT,
     *        PEAR_ERROR_TRIGGER, PEAR_ERROR_DIE,
     *        PEAR_ERROR_CALLBACK or PEAR_ERROR_EXCEPTION.
     *
     * @param mixed $options
     *        When $mode is PEAR_ERROR_TRIGGER, this is the error level (one
     *        of E_USER_NOTICE, E_USER_WARNING or E_USER_ERROR).
     *
     *        When $mode is PEAR_ERROR_CALLBACK, this parameter is expected
     *        to be the callback function or method.  A callback
     *        function is a string with the name of the function, a
     *        callback method is an array of two elements: the element
     *        at index 0 is the object, and the element at index 1 is
     *        the name of the method to call in the object.
     *
     *        When $mode is PEAR_ERROR_PRINT or PEAR_ERROR_DIE, this is
     *        a printf format string used when printing the error
     *        message.
     *
     * @access public
     * @return void
     * @see PEAR_ERROR_RETURN
     * @see PEAR_ERROR_PRINT
     * @see PEAR_ERROR_TRIGGER
     * @see PEAR_ERROR_DIE
     * @see PEAR_ERROR_CALLBACK
     * @see PEAR_ERROR_EXCEPTION
     *
     * @since PHP 4.0.5
     */

    function setErrorHandling($mode = null, $options = null)
    {
        if (isset($this) && is_a($this, 'PEAR')) {
            $setmode     = &$this->_default_error_mode;
            $setoptions  = &$this->_default_error_options;
        } else {
            $setmode     = &$GLOBALS['_PEAR_default_error_mode'];
            $setoptions  = &$GLOBALS['_PEAR_default_error_options'];
        }

        switch ($mode) {
            case PEAR_ERROR_EXCEPTION:
            case PEAR_ERROR_RETURN:
            case PEAR_ERROR_PRINT:
            case PEAR_ERROR_TRIGGER:
            case PEAR_ERROR_DIE:
            case null:
                $setmode = $mode;
                $setoptions = $options;
                break;

            case PEAR_ERROR_CALLBACK:
                $setmode = $mode;
                // class/object method callback
                if (is_callable($options)) {
                    $setoptions = $options;
                } else {
                    trigger_error("invalid error callback", E_USER_WARNING);
                }
                break;

            default:
                trigger_error("invalid error mode", E_USER_WARNING);
                break;
        }
    }

    // }}}
    // {{{ expectError()

    /**
     * This method is used to tell which errors you expect to get.
     * Expected errors are always returned with error mode
     * PEAR_ERROR_RETURN.  Expected error codes are stored in a stack,
     * and this method pushes a new element onto it.  The list of
     * expected errors are in effect until they are popped off the
     * stack with the popExpect() method.
     *
     * Note that this method can not be called statically
     *
     * @param mixed $code a single error code or an array of error codes to expect
     *
     * @return int     the new depth of the "expected errors" stack
     * @access public
     */
    function expectError($code = '*')
    {
        if (is_array($code)) {
            array_push($this->_expected_errors, $code);
        } else {
            array_push($this->_expected_errors, array($code));
        }
        return sizeof($this->_expected_errors);
    }

    // }}}
    // {{{ popExpect()

    /**
     * This method pops one element off the expected error codes
     * stack.
     *
     * @return array   the list of error codes that were popped
     */
    function popExpect()
    {
        return array_pop($this->_expected_errors);
    }

    // }}}
    // {{{ _checkDelExpect()

    /**
     * This method checks unsets an error code if available
     *
     * @param mixed error code
     * @return bool true if the error code was unset, false otherwise
     * @access private
     * @since PHP 4.3.0
     */
    function _checkDelExpect($error_code)
    {
        $deleted = false;

        foreach ($this->_expected_errors AS $key => $error_array) {
            if (in_array($error_code, $error_array)) {
                unset($this->_expected_errors[$key][array_search($error_code, $error_array)]);
                $deleted = true;
            }

            // clean up empty arrays
            if (0 == count($this->_expected_errors[$key])) {
                unset($this->_expected_errors[$key]);
            }
        }
        return $deleted;
    }

    // }}}
    // {{{ delExpect()

    /**
     * This method deletes all occurences of the specified element from
     * the expected error codes stack.
     *
     * @param  mixed $error_code error code that should be deleted
     * @return mixed list of error codes that were deleted or error
     * @access public
     * @since PHP 4.3.0
     */
    function delExpect($error_code)
    {
        $deleted = false;

        if ((is_array($error_code) && (0 != count($error_code)))) {
            // $error_code is a non-empty array here;
            // we walk through it trying to unset all
            // values
            foreach($error_code as $key => $error) {
                if ($this->_checkDelExpect($error)) {
                    $deleted =  true;
                } else {
                    $deleted = false;
                }
            }
            return $deleted ? true : PEAR::raiseError("The expected error you submitted does not exist"); // IMPROVE ME
        } elseif (!empty($error_code)) {
            // $error_code comes alone, trying to unset it
            if ($this->_checkDelExpect($error_code)) {
                return true;
            } else {
                return PEAR::raiseError("The expected error you submitted does not exist"); // IMPROVE ME
            }
        } else {
            // $error_code is empty
            return PEAR::raiseError("The expected error you submitted is empty"); // IMPROVE ME
        }
    }

    // }}}
    // {{{ raiseError()

    /**
     * This method is a wrapper that returns an instance of the
     * configured error class with this object's default error
     * handling applied.  If the $mode and $options parameters are not
     * specified, the object's defaults are used.
     *
     * @param mixed $message a text error message or a PEAR error object
     *
     * @param int $code      a numeric error code (it is up to your class
     *                  to define these if you want to use codes)
     *
     * @param int $mode      One of PEAR_ERROR_RETURN, PEAR_ERROR_PRINT,
     *                  PEAR_ERROR_TRIGGER, PEAR_ERROR_DIE,
     *                  PEAR_ERROR_CALLBACK, PEAR_ERROR_EXCEPTION.
     *
     * @param mixed $options If $mode is PEAR_ERROR_TRIGGER, this parameter
     *                  specifies the PHP-internal error level (one of
     *                  E_USER_NOTICE, E_USER_WARNING or E_USER_ERROR).
     *                  If $mode is PEAR_ERROR_CALLBACK, this
     *                  parameter specifies the callback function or
     *                  method.  In other error modes this parameter
     *                  is ignored.
     *
     * @param string $userinfo If you need to pass along for example debug
     *                  information, this parameter is meant for that.
     *
     * @param string $error_class The returned error object will be
     *                  instantiated from this class, if specified.
     *
     * @param bool $skipmsg If true, raiseError will only pass error codes,
     *                  the error message parameter will be dropped.
     *
     * @access public
     * @return object   a PEAR error object
     * @see PEAR::setErrorHandling
     * @since PHP 4.0.5
     */
    function &raiseError($message = null,
                         $code = null,
                         $mode = null,
                         $options = null,
                         $userinfo = null,
                         $error_class = null,
                         $skipmsg = false)
    {
        // The error is yet a PEAR error object
        if (is_object($message)) {
            $code        = $message->getCode();
            $userinfo    = $message->getUserInfo();
            $error_class = $message->getType();
            $message->error_message_prefix = '';
            $message     = $message->getMessage();
        }

        if (isset($this) && isset($this->_expected_errors) && sizeof($this->_expected_errors) > 0 && sizeof($exp = end($this->_expected_errors))) {
            if ($exp[0] == "*" ||
                (is_int(reset($exp)) && in_array($code, $exp)) ||
                (is_string(reset($exp)) && in_array($message, $exp))) {
                $mode = PEAR_ERROR_RETURN;
            }
        }
        // No mode given, try global ones
        if ($mode === null) {
            // Class error handler
            if (isset($this) && isset($this->_default_error_mode)) {
                $mode    = $this->_default_error_mode;
                $options = $this->_default_error_options;
            // Global error handler
            } elseif (isset($GLOBALS['_PEAR_default_error_mode'])) {
                $mode    = $GLOBALS['_PEAR_default_error_mode'];
                $options = $GLOBALS['_PEAR_default_error_options'];
            }
        }

        if ($error_class !== null) {
            $ec = $error_class;
        } elseif (isset($this) && isset($this->_error_class)) {
            $ec = $this->_error_class;
        } else {
            $ec = 'PEAR_Error';
        }
        if (intval(PHP_VERSION) < 5) {
            // little non-eval hack to fix bug #12147
            /*PJ: include 'PEAR/FixPHP5PEARWarnings.php';*/
            return $a;
        }
        if ($skipmsg) {
            $a = new $ec($code, $mode, $options, $userinfo);
        } else {
            $a = new $ec($message, $code, $mode, $options, $userinfo);
        }
        return $a;
    }

    // }}}
    // {{{ throwError()

    /**
     * Simpler form of raiseError with fewer options.  In most cases
     * message, code and userinfo are enough.
     *
     * @param string $message
     *
     */
    function &throwError($message = null,
                         $code = null,
                         $userinfo = null)
    {
        if (isset($this) && is_a($this, 'PEAR')) {
            $a = &$this->raiseError($message, $code, null, null, $userinfo);
            return $a;
        } else {
            $a = &PEAR::raiseError($message, $code, null, null, $userinfo);
            return $a;
        }
    }

    // }}}
    function staticPushErrorHandling($mode, $options = null)
    {
        $stack = &$GLOBALS['_PEAR_error_handler_stack'];
        $def_mode    = &$GLOBALS['_PEAR_default_error_mode'];
        $def_options = &$GLOBALS['_PEAR_default_error_options'];
        $stack[] = array($def_mode, $def_options);
        switch ($mode) {
            case PEAR_ERROR_EXCEPTION:
            case PEAR_ERROR_RETURN:
            case PEAR_ERROR_PRINT:
            case PEAR_ERROR_TRIGGER:
            case PEAR_ERROR_DIE:
            case null:
                $def_mode = $mode;
                $def_options = $options;
                break;

            case PEAR_ERROR_CALLBACK:
                $def_mode = $mode;
                // class/object method callback
                if (is_callable($options)) {
                    $def_options = $options;
                } else {
                    trigger_error("invalid error callback", E_USER_WARNING);
                }
                break;

            default:
                trigger_error("invalid error mode", E_USER_WARNING);
                break;
        }
        $stack[] = array($mode, $options);
        return true;
    }

    function staticPopErrorHandling()
    {
        $stack = &$GLOBALS['_PEAR_error_handler_stack'];
        $setmode     = &$GLOBALS['_PEAR_default_error_mode'];
        $setoptions  = &$GLOBALS['_PEAR_default_error_options'];
        array_pop($stack);
        list($mode, $options) = $stack[sizeof($stack) - 1];
        array_pop($stack);
        switch ($mode) {
            case PEAR_ERROR_EXCEPTION:
            case PEAR_ERROR_RETURN:
            case PEAR_ERROR_PRINT:
            case PEAR_ERROR_TRIGGER:
            case PEAR_ERROR_DIE:
            case null:
                $setmode = $mode;
                $setoptions = $options;
                break;

            case PEAR_ERROR_CALLBACK:
                $setmode = $mode;
                // class/object method callback
                if (is_callable($options)) {
                    $setoptions = $options;
                } else {
                    trigger_error("invalid error callback", E_USER_WARNING);
                }
                break;

            default:
                trigger_error("invalid error mode", E_USER_WARNING);
                break;
        }
        return true;
    }

    // {{{ pushErrorHandling()

    /**
     * Push a new error handler on top of the error handler options stack. With this
     * you can easily override the actual error handler for some code and restore
     * it later with popErrorHandling.
     *
     * @param mixed $mode (same as setErrorHandling)
     * @param mixed $options (same as setErrorHandling)
     *
     * @return bool Always true
     *
     * @see PEAR::setErrorHandling
     */
    function pushErrorHandling($mode, $options = null)
    {
        $stack = &$GLOBALS['_PEAR_error_handler_stack'];
        if (isset($this) && is_a($this, 'PEAR')) {
            $def_mode    = &$this->_default_error_mode;
            $def_options = &$this->_default_error_options;
        } else {
            $def_mode    = &$GLOBALS['_PEAR_default_error_mode'];
            $def_options = &$GLOBALS['_PEAR_default_error_options'];
        }
        $stack[] = array($def_mode, $def_options);

        if (isset($this) && is_a($this, 'PEAR')) {
            $this->setErrorHandling($mode, $options);
        } else {
            PEAR::setErrorHandling($mode, $options);
        }
        $stack[] = array($mode, $options);
        return true;
    }

    // }}}
    // {{{ popErrorHandling()

    /**
    * Pop the last error handler used
    *
    * @return bool Always true
    *
    * @see PEAR::pushErrorHandling
    */
    function popErrorHandling()
    {
        $stack = &$GLOBALS['_PEAR_error_handler_stack'];
        array_pop($stack);
        list($mode, $options) = $stack[sizeof($stack) - 1];
        array_pop($stack);
        if (isset($this) && is_a($this, 'PEAR')) {
            $this->setErrorHandling($mode, $options);
        } else {
            PEAR::setErrorHandling($mode, $options);
        }
        return true;
    }

    // }}}
    // {{{ loadExtension()

    /**
    * OS independant PHP extension load. Remember to take care
    * on the correct extension name for case sensitive OSes.
    *
    * @param string $ext The extension name
    * @return bool Success or not on the dl() call
    */
    function loadExtension($ext)
    {
        if (!extension_loaded($ext)) {
            // if either returns true dl() will produce a FATAL error, stop that
            if ((ini_get('enable_dl') != 1) || (ini_get('safe_mode') == 1)) {
                return false;
            }
            if (OS_WINDOWS) {
                $suffix = '.dll';
            } elseif (PHP_OS == 'HP-UX') {
                $suffix = '.sl';
            } elseif (PHP_OS == 'AIX') {
                $suffix = '.a';
            } elseif (PHP_OS == 'OSX') {
                $suffix = '.bundle';
            } else {
                $suffix = '.so';
            }
            return @dl('php_'.$ext.$suffix) || @dl($ext.$suffix);
        }
        return true;
    }

    // }}}
}

// {{{ _PEAR_call_destructors()

function _PEAR_call_destructors()
{
    global $_PEAR_destructor_object_list;
    if (is_array($_PEAR_destructor_object_list) &&
        sizeof($_PEAR_destructor_object_list))
    {
        reset($_PEAR_destructor_object_list);
        if (PEAR::getStaticProperty('PEAR', 'destructlifo')) {
            $_PEAR_destructor_object_list = array_reverse($_PEAR_destructor_object_list);
        }
        while (list($k, $objref) = each($_PEAR_destructor_object_list)) {
            $classname = get_class($objref);
            while ($classname) {
                $destructor = "_$classname";
                if (method_exists($objref, $destructor)) {
                    $objref->$destructor();
                    break;
                } else {
                    $classname = get_parent_class($classname);
                }
            }
        }
        // Empty the object list to ensure that destructors are
        // not called more than once.
        $_PEAR_destructor_object_list = array();
    }

    // Now call the shutdown functions
    if (is_array($GLOBALS['_PEAR_shutdown_funcs']) AND !empty($GLOBALS['_PEAR_shutdown_funcs'])) {
        foreach ($GLOBALS['_PEAR_shutdown_funcs'] as $value) {
            call_user_func_array($value[0], $value[1]);
        }
    }
}

// }}}
/**
 * Standard PEAR error class for PHP 4
 *
 * This class is supserseded by {@link PEAR_Exception} in PHP 5
 *
 * @category   pear
 * @package    PEAR
 * @author     Stig Bakken <ssb@php.net>
 * @author     Tomas V.V. Cox <cox@idecnet.com>
 * @author     Gregory Beaver <cellog@php.net>
 * @copyright  1997-2006 The PHP Group
 * @license    http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version    Release: 1.7.2
 * @link       http://pear.php.net/manual/en/core.pear.pear-error.php
 * @see        PEAR::raiseError(), PEAR::throwError()
 * @since      Class available since PHP 4.0.2
 */
class PEAR_Error
{
    // {{{ properties

    var $error_message_prefix = '';
    var $mode                 = PEAR_ERROR_RETURN;
    var $level                = E_USER_NOTICE;
    var $code                 = -1;
    var $message              = '';
    var $userinfo             = '';
    var $backtrace            = null;

    // }}}
    // {{{ constructor

    /**
     * PEAR_Error constructor
     *
     * @param string $message  message
     *
     * @param int $code     (optional) error code
     *
     * @param int $mode     (optional) error mode, one of: PEAR_ERROR_RETURN,
     * PEAR_ERROR_PRINT, PEAR_ERROR_DIE, PEAR_ERROR_TRIGGER,
     * PEAR_ERROR_CALLBACK or PEAR_ERROR_EXCEPTION
     *
     * @param mixed $options   (optional) error level, _OR_ in the case of
     * PEAR_ERROR_CALLBACK, the callback function or object/method
     * tuple.
     *
     * @param string $userinfo (optional) additional user/debug info
     *
     * @access public
     *
     */
    function PEAR_Error($message = 'unknown error', $code = null,
                        $mode = null, $options = null, $userinfo = null)
    {
        if ($mode === null) {
            $mode = PEAR_ERROR_RETURN;
        }
        $this->message   = $message;
        $this->code      = $code;
        $this->mode      = $mode;
        $this->userinfo  = $userinfo;
        if (!PEAR::getStaticProperty('PEAR_Error', 'skiptrace')) {
            $this->backtrace = debug_backtrace();
            if (isset($this->backtrace[0]) && isset($this->backtrace[0]['object'])) {
                unset($this->backtrace[0]['object']);
            }
        }
        if ($mode & PEAR_ERROR_CALLBACK) {
            $this->level = E_USER_NOTICE;
            $this->callback = $options;
        } else {
            if ($options === null) {
                $options = E_USER_NOTICE;
            }
            $this->level = $options;
            $this->callback = null;
        }
        if ($this->mode & PEAR_ERROR_PRINT) {
            if (is_null($options) || is_int($options)) {
                $format = "%s";
            } else {
                $format = $options;
            }
            printf($format, $this->getMessage());
        }
        if ($this->mode & PEAR_ERROR_TRIGGER) {
            trigger_error($this->getMessage(), $this->level);
        }
        if ($this->mode & PEAR_ERROR_DIE) {
            $msg = $this->getMessage();
            if (is_null($options) || is_int($options)) {
                $format = "%s";
                if (substr($msg, -1) != "\n") {
                    $msg .= "\n";
                }
            } else {
                $format = $options;
            }
            die(sprintf($format, $msg));
        }
        if ($this->mode & PEAR_ERROR_CALLBACK) {
            if (is_callable($this->callback)) {
                call_user_func($this->callback, $this);
            }
        }
        if ($this->mode & PEAR_ERROR_EXCEPTION) {
            trigger_error("PEAR_ERROR_EXCEPTION is obsolete, use class PEAR_Exception for exceptions", E_USER_WARNING);
            eval('$e = new Exception($this->message, $this->code);throw($e);');
        }
    }

    // }}}
    // {{{ getMode()

    /**
     * Get the error mode from an error object.
     *
     * @return int error mode
     * @access public
     */
    function getMode() {
        return $this->mode;
    }

    // }}}
    // {{{ getCallback()

    /**
     * Get the callback function/method from an error object.
     *
     * @return mixed callback function or object/method array
     * @access public
     */
    function getCallback() {
        return $this->callback;
    }

    // }}}
    // {{{ getMessage()


    /**
     * Get the error message from an error object.
     *
     * @return  string  full error message
     * @access public
     */
    function getMessage()
    {
        return ($this->error_message_prefix . $this->message);
    }


    // }}}
    // {{{ getCode()

    /**
     * Get error code from an error object
     *
     * @return int error code
     * @access public
     */
     function getCode()
     {
        return $this->code;
     }

    // }}}
    // {{{ getType()

    /**
     * Get the name of this error/exception.
     *
     * @return string error/exception name (type)
     * @access public
     */
    function getType()
    {
        return get_class($this);
    }

    // }}}
    // {{{ getUserInfo()

    /**
     * Get additional user-supplied information.
     *
     * @return string user-supplied information
     * @access public
     */
    function getUserInfo()
    {
        return $this->userinfo;
    }

    // }}}
    // {{{ getDebugInfo()

    /**
     * Get additional debug information supplied by the application.
     *
     * @return string debug information
     * @access public
     */
    function getDebugInfo()
    {
        return $this->getUserInfo();
    }

    // }}}
    // {{{ getBacktrace()

    /**
     * Get the call backtrace from where the error was generated.
     * Supported with PHP 4.3.0 or newer.
     *
     * @param int $frame (optional) what frame to fetch
     * @return array Backtrace, or NULL if not available.
     * @access public
     */
    function getBacktrace($frame = null)
    {
        if (defined('PEAR_IGNORE_BACKTRACE')) {
            return null;
        }
        if ($frame === null) {
            return $this->backtrace;
        }
        return $this->backtrace[$frame];
    }

    // }}}
    // {{{ addUserInfo()

    function addUserInfo($info)
    {
        if (empty($this->userinfo)) {
            $this->userinfo = $info;
        } else {
            $this->userinfo .= " ** $info";
        }
    }

    // }}}
    // {{{ toString()
    function __toString()
    {
        return $this->getMessage();
    }
    // }}}
    // {{{ toString()

    /**
     * Make a string representation of this object.
     *
     * @return string a string with an object summary
     * @access public
     */
    function toString() {
        $modes = array();
        $levels = array(E_USER_NOTICE  => 'notice',
                        E_USER_WARNING => 'warning',
                        E_USER_ERROR   => 'error');
        if ($this->mode & PEAR_ERROR_CALLBACK) {
            if (is_array($this->callback)) {
                $callback = (is_object($this->callback[0]) ?
                    strtolower(get_class($this->callback[0])) :
                    $this->callback[0]) . '::' .
                    $this->callback[1];
            } else {
                $callback = $this->callback;
            }
            return sprintf('[%s: message="%s" code=%d mode=callback '.
                           'callback=%s prefix="%s" info="%s"]',
                           strtolower(get_class($this)), $this->message, $this->code,
                           $callback, $this->error_message_prefix,
                           $this->userinfo);
        }
        if ($this->mode & PEAR_ERROR_PRINT) {
            $modes[] = 'print';
        }
        if ($this->mode & PEAR_ERROR_TRIGGER) {
            $modes[] = 'trigger';
        }
        if ($this->mode & PEAR_ERROR_DIE) {
            $modes[] = 'die';
        }
        if ($this->mode & PEAR_ERROR_RETURN) {
            $modes[] = 'return';
        }
        return sprintf('[%s: message="%s" code=%d mode=%s level=%s '.
                       'prefix="%s" info="%s"]',
                       strtolower(get_class($this)), $this->message, $this->code,
                       implode("|", $modes), $levels[$this->level],
                       $this->error_message_prefix,
                       $this->userinfo);
    }

    // }}}
}

/*
 * Local Variables:
 * mode: php
 * tab-width: 4
 * c-basic-offset: 4
 * End:
 */


///////Validator//////////////////////
class ValidatorObj
{
   var $variable_name;
   var $validator_string;
   var $error_string;
    var $condition;
}

class FM_CustomValidator 
{
   function DoValidate(&$formars,&$error_hash)
   {
      return true;
   }
}

class FM_FormValidator extends FM_Module
{
   var $validator_array;
    var $errors;
    var $show_all_errors_together;
    var $error_hash;
   var $custom_validators;
   
   function FM_FormValidator()
   {
      $this->validator_array = array();
        $this->show_all_errors_together=true;
        $this->error_hash = array();
      $this->custom_validators=array();
   }
   
   function AddCustomValidator(&$customv)
   {
      array_push_ref($this->custom_validators,$customv);
   }

   function addValidation($variable,$validator,$error,$condition="")
   {
      $validator_obj = new ValidatorObj();
      $validator_obj->variable_name = $variable;
      $validator_obj->validator_string = $validator;
      $validator_obj->error_string = $error;
      $validator_obj->condition = $condition;
      array_push($this->validator_array,$validator_obj);
   }
    
    function Process(&$continue)
    {
      $bResult = true;

      if(false == $this->ValidateForm($this->formvars))
      {
         $bResult = false;
      }

      if(count($this->custom_validators) > 0)
      {
         foreach( $this->custom_validators as $custom_val)
         {
            if(false == $custom_val->DoValidate($this->formvars,$this->error_hash))
            {
               $bResult = false;
            }
         }
      }

      if(false ==  $this->ext_module->DoValidate($this->formvars,$this->error_hash))
      {
          $bResult = false;
      }

      if(!$bResult)
      {
         $continue=false;
         $this->error_handler->ShowInputError($this->error_hash,$this->formname);
         return false;        
      }
      else
      {
         $this->logger->LogInfo("Validations succeeded");
         $continue=true;
         return true;
      }
    }

   function ValidateForm($form_variables)
   {
      $bret = true;

      $error_string="";
      $error_to_display = "";

        $vcount = count($this->validator_array);
        $this->logger->LogInfo("Validating form data: number of validations: $vcount" );

      foreach($this->validator_array as $val_obj)
      {
         if(!$this->ValidateObject($val_obj,$form_variables,$error_string))
         {
            $bret = false;
                $this->error_hash[$val_obj->variable_name] = $error_string;
            if(false == $this->show_all_errors_together)
            {
               break;   
            }
         }
      }
      return $bret;
   }

   function getErrors()
   {
      return $this->errors;
   }

   function ValidateObject($validatorobj,$formvariables,&$error_string)
   {
      $bret = true;

        //validation condition
        if(isset($validatorobj->condition) &&
            strlen($validatorobj->condition)>0)
        {
            if(false == $this->ValidateCondition(
                $validatorobj->condition,$formvariables))
            {
                return true;
            }
        }

      $splitted = explode("=",$validatorobj->validator_string);
      $command = $splitted[0];
      $command_value = '';

      if(isset($splitted[1]) && strlen($splitted[1])>0)
      {
         $command_value = $splitted[1];
      }

      $default_error_message="";
      
      $input_value ="";

      if(isset($formvariables[$validatorobj->variable_name]))
      {
       $input_value = $formvariables[$validatorobj->variable_name];
      }

      $extra_info="";

      $bret = $this->ValidateCommand($command,$command_value,$input_value,
                           $default_error_message,
                           $validatorobj->variable_name,
                           $extra_info,
                           $formvariables);

      
      if(false == $bret)
      {
         if(isset($validatorobj->error_string) &&
            strlen($validatorobj->error_string)>0)
         {
            $error_string = $validatorobj->error_string;
         }
         else
         {
            $error_string = $default_error_message;
         }
         if(strlen($extra_info)>0)
         {
            $error_string .= "\n".$extra_info;
         }

      }//if
      return $bret;
   }
      
    function ValidateCondition($condn,$formvariables)
    {
        if(!preg_match("/([a-z_A-Z]*)\(([a-zA-Z0-9_]*),\"(.*)\"\)/",$condn,$res))
        {
            return true;
        }
        $type = strtolower(trim($res[1]));
        $arg1 = trim($res[2]);
        $arg2 = trim($res[3]);
        $bret=false;
        
        switch($type)
        {
            case "is_selected_radio":
            case "isequal":
            {
                if(isset($formvariables[$arg1]) &&
                  strcasecmp($formvariables[$arg1],$arg2)==0 )
                {
                    $bret=true;
                }
                break;
            }//case
            case "ischecked_single":
            {
                if(isset($formvariables[$arg1]))
                {
                    $bret=true;
                }
                break;
            }
            case "contains":
            {
                if(isset($formvariables[$arg1]) &&
                  stristr($formvariables[$arg1],$arg2) !== FALSE )
                {
                    $bret=true;
                }                
                break;
            }
            case "greaterthan":
            {
                if(isset($formvariables[$arg1]) &&
                  floatval($formvariables[$arg1]) > floatval($arg2))
                {
                    $bret=true;
                }                
                break;
            }
            case "lessthan":
            {
                if(isset($formvariables[$arg1]) &&
                  floatval($formvariables[$arg1]) < floatval($arg2))
                {
                    $bret=true;
                }                
                break;                
            }
            case "is_not_checked_single":
            {
                if(!isset($formvariables[$arg1]) )
                {
                    $bret=true;
                }
                break;
            }
            case "is_not_selected_radio":
            {
                if(!isset($formvariables[$arg1]) ||
                  strcasecmp($formvariables[$arg1],$arg2) !=0 )
                {
                    $bret=true;
                }
                break;
            }
            case "is_selected_list_item":
            case "is_checked_group":
            {
                if(isset($formvariables[$arg1]))
                {
                    if(is_array($formvariables[$arg1]))
                    {
                        foreach($formvariables[$arg1] as $chk)
                        {
                            if(strcasecmp($chk,$arg2)==0)
                            {
                                $bret=true;break;
                            }
                        }//foreach
                    }
                    else
                    {
                        if(strcasecmp($formvariables[$arg1],$arg2)==0)
                        {
                            $bret=true;break;
                        }                        
                    }//else
                }
                break;
            }//case]
        case "is_not_selected_list_item":
        case "is_not_checked_group":
            {
                $bret=true;
                if(isset($formvariables[$arg1]))
                {
                    if(is_array($formvariables[$arg1]))
                    {
                        foreach($formvariables[$arg1] as $chk)
                        {
                            if(strcasecmp($chk,$arg2)==0)
                            {
                                $bret=false;break;
                            }
                        }//foreach
                    }
                    else
                    {
                        if(strcasecmp($formvariables[$arg1],$arg2)==0)
                        {
                            $bret=false;break;
                        }                        
                    }//else
                }
                break;
            }//case
            case 'is_empty':
            {
                if(!isset($formvariables[$arg1]))
                {
                    $bret=true;
                }
                else
                {
                    $tmp_arg=trim($formvariables[$arg1]);
                    if(empty($tmp_arg))
                    {
                        $bret=true;
                    }
                }
                break;
            }
            case 'is_not_empty':
            {
                if(isset($formvariables[$arg1]))
                {
                    $tmp_arg=trim($formvariables[$arg1]);
                    if(!empty($tmp_arg))
                    {
                        $bret=true;
                    }                    
                }
                break;
            }

        }//switch

        return $bret;
    }

   function validate_req($input_value, &$default_error_message,$variable_name)
   {
       $bret = true;
         if(!isset($input_value))
        {
            $bret=false;
        }
        else
        {
            $input_value = trim($input_value);

            if(strlen($input_value) <=0)
          {
             $bret=false;
          }

            $type = $this->config->element_info->GetType($variable_name);
            if("datepicker" == $type)
            {
                $date_obj = new FM_DateObj($this->formvars,$this->config,$this->logger);
                if(!$date_obj->GetDateFieldInStdForm($variable_name))
                {
                    $bret=false;
                }
            }
        }   
        if(!$bret)
        {
            $default_error_message = sprintf(E_VAL_REQUIRED_VALUE,$variable_name);
        }

       return $bret; 
   }

   function validate_maxlen($input_value,$max_len,$variable_name,&$extra_info,&$default_error_message)
   {
      $bret = true;
      if(isset($input_value) )
      {
         $input_length = strlen($input_value);
         if($input_length > $max_len)
         {
            $bret=false;
            //$extra_info = sprintf(E_VAL_MAXLEN_EXTRA_INFO,$max_len,$input_length);
            $default_error_message = sprintf(E_VAL_MAXLEN_EXCEEDED,$variable_name);
         }
      }
      return $bret;
   }

   function validate_minlen($input_value,$min_len,$variable_name,&$extra_info,&$default_error_message)
   {
      $bret = true;
      if(isset($input_value) )
      {
         $input_length = strlen($input_value);
         if($input_length < $min_len)
         {
            $bret=false;
            //$extra_info = sprintf(E_VAL_MINLEN_EXTRA_INFO,$min_len,$input_length);
            $default_error_message = sprintf(E_VAL_MINLEN_CHECK_FAILED,$min_len,$variable_name);
         }
      }
      return $bret;
   }

   function test_datatype($input_value,$reg_exp)
   {
      if(preg_match($reg_exp,$input_value))
      {
         return true;
      }
      return false;
   }
   function validate_email($email) 
   {
      return preg_match("/^[_\.0-9a-zA-Z-]+@([0-9a-zA-Z][0-9a-zA-Z-]+\.)+[a-zA-Z]{2,6}$/i", $email);
   }

    function make_number($input_value)
    {
        return str_replace(",","",$input_value);
    }

   function validate_for_numeric_input($input_value,&$validation_success,&$extra_info)
   {
      
      $more_validations=true;
      $validation_success = true;
      if(strlen($input_value)>0)
      {
         
         if(false == is_numeric($input_value))
         {
            $extra_info = E_VAL_NUMERIC_VALUE_EXPECTED;
            $validation_success = false;
            $more_validations=false;
         }
      }
      else
      {
         $more_validations=false;
      }
      return $more_validations;
   }

   function validate_lessthan($command_value,$input_value,
                $variable_name,&$default_error_message,&$extra_info )
   {
      $bret = true;
        $input_value = $this->make_number($input_value);
      if(false == $this->validate_for_numeric_input($input_value,
                                    $bret,$extra_info))
      {
         return $bret;
      }
      if($bret)
      {
         $lessthan = doubleval($command_value);
         $float_inputval = doubleval($input_value);
         if($float_inputval >= $lessthan)
         {
            $default_error_message = sprintf(E_VAL_LESSTHAN_CHECK_FAILED,
                              $lessthan,
                              $variable_name);
            $bret = false;
         }//if
      }
      return $bret ;
   }
   function validate_greaterthan($command_value,$input_value,$variable_name,&$default_error_message,&$extra_info )
   {
      $bret = true;
        $input_value = $this->make_number($input_value);
      if(false == $this->validate_for_numeric_input($input_value,$bret,$extra_info))
      {
         return $bret;
      }
      if($bret)
      {
         $greaterthan = doubleval($command_value);
         $float_inputval = doubleval($input_value);
         if($float_inputval <= $greaterthan)
         {
            $default_error_message = sprintf(E_VAL_GREATERTHAN_CHECK_FAILED,
                              $greaterthan,
                              $variable_name);
            $bret = false;
         }//if
      }
      return $bret ;
   }

    function validate_select($input_value,$command_value,&$default_error_message,$variable_name)
    {
       $bret=false;
      if(is_array($input_value))
      {
         foreach($input_value as $value)
         {
            if($value == $command_value)
            {
               $bret=true;
               break;
            }
         }
      }
      else
      {
         if($command_value == $input_value)
         {
            $bret=true;
         }
      }
        if(false == $bret)
        {
            $default_error_message = sprintf(E_VAL_SHOULD_SEL_CHECK_FAILED,
                                            $command_value,$variable_name);
        }
       return $bret;
    }

   function validate_dontselect($input_value,$command_value,&$default_error_message,$variable_name)
   {
      $bret=true;
      if(is_array($input_value))
      {
         foreach($input_value as $value)
         {
            if($value == $command_value)
            {
               $bret=false;
               $default_error_message = sprintf(E_VAL_DONTSEL_CHECK_FAILED,$variable_name);
               break;
            }
         }
      }
      else
      {
         if($command_value == $input_value)
         {
            $bret=false;
            $default_error_message = sprintf(E_VAL_DONTSEL_CHECK_FAILED,$variable_name);
         }
      }
     return $bret;
   }

   function ValidateComparison($input_value,$formvariables,
         $command_value,&$extra_info,&$default_error_message,$variable_name,$command)
   {
      $bret = true;
      if(isset($input_value) &&
      isset($formvariables[$command_value]))
      {
            $input_value = $this->make_number($input_value);
            $valueOther = $this->make_number($formvariables[$command_value]);

         if(true == $this->validate_for_numeric_input($input_value,$bret,$extra_info) &&
            true == $this->validate_for_numeric_input($valueOther,$bret,$extra_info))
         {
            $valueThis  = doubleval($input_value);
            $valueOther = doubleval($valueOther);
            switch($command)
            {
              case "ltelmnt":
                        {
                           if($valueThis >= $valueOther)
                           {
                              $bret = false;
                              $default_error_message = sprintf(E_VAL_LTELMNT_CHECK_FAILED,$variable_name,$command_value);
                           }
                           break;
                        }
              case "leelmnt":
                        {
                           if($valueThis > $valueOther)
                           {
                              $bret = false;
                              $default_error_message = sprintf(E_VAL_LEELMNT_CHECK_FAILED,$variable_name,$command_value);
                           }
                           break;
                        }
              case "gtelmnt":
                        {
                           if($valueThis <= $valueOther)
                           {
                              $bret = false;
                              $default_error_message = sprintf(E_VAL_GTELMNT_CHECK_FAILED,$variable_name,$command_value);
                           }
                           break;
                        }
              case "geelmnt":
                        {
                           if($valueThis < $valueOther)
                           {
                              $bret = false;
                              $default_error_message = sprintf(E_VAL_GEELMNT_CHECK_FAILED,$variable_name,$command_value);
                           }
                           break;                           
                        }

              
            }//switch
         }
      }
      return $bret;
   }

   function ValidateCommand($command,$command_value,$input_value,&$default_error_message,$variable_name,&$extra_info,$formvariables)
   {
      $bret=true;
      switch($command)
      {
         case 'req':
                  {
                     $bret = $this->validate_req($input_value, $default_error_message,$variable_name);
                     break;
                  }

         case 'maxlen':
                  {
                     $max_len = intval($command_value);
                     $bret = $this->validate_maxlen($input_value,$max_len,$variable_name,
                                    $extra_info,$default_error_message);
                     break;
                  }

         case 'minlen':
                  {
                     $min_len = intval($command_value);
                     $bret = $this->validate_minlen($input_value,$min_len,$variable_name,
                                 $extra_info,$default_error_message);
                     break;
                  }

         case 'alnum':
                  {
                     $bret= $this->test_datatype($input_value,"/^[A-Za-z0-9]*$/");
                     if(false == $bret)
                     {
                        $default_error_message = sprintf(E_VAL_ALNUM_CHECK_FAILED,$variable_name);
                     }
                     break;
                  }

         case 'alnum_s':
                  {
                     $bret= $this->test_datatype($input_value,"/^[A-Za-z0-9\s]*$/");
                     if(false == $bret)
                     {
                        $default_error_message = sprintf(E_VAL_ALNUM_S_CHECK_FAILED,$variable_name);
                     }
                     break;
                  }

         case 'num':
            case 'numeric':
                  {
                     if(isset($input_value) && strlen($input_value)>0)
                     {
                        if(!preg_match("/^[\-\+]?[\d\,]*\.?[\d]*$/",$input_value))
                        {
                           $bret=false;
                           $default_error_message = sprintf(E_VAL_NUM_CHECK_FAILED,$variable_name);
                        }
                     }
                     break;
                  }

         case 'alpha': 
                  {
                     $bret= $this->test_datatype($input_value,"/^[A-Za-z]*$/");
                     if(false == $bret)
                     {
                        $default_error_message = sprintf(E_VAL_ALPHA_CHECK_FAILED,$variable_name);
                     }
                     break;
                  }
         case 'alpha_s':
                  {
                     $bret= $this->test_datatype($input_value,"/^[A-Za-z\s]*$/");
                     if(false == $bret)
                     {
                        $default_error_message = sprintf(E_VAL_ALPHA_S_CHECK_FAILED,$variable_name);
                     }
                     break;
                  }
         case 'email':
                  {
                     if(isset($input_value) && strlen($input_value)>0)
                     {
                        $bret= $this->validate_email($input_value);
                        if(false == $bret)
                        {
                           $default_error_message = E_VAL_EMAIL_CHECK_FAILED;
                        }
                     }
                     break;
                  }
         case "lt": 
         case "lessthan": 
                  {
                     $bret = $this->validate_lessthan($command_value,
                                       $input_value,
                                       $variable_name,
                                       $default_error_message,
                                       $extra_info );
                     break;
                  }
         case "gt": 
         case "greaterthan": 
                  {
                     $bret = $this->validate_greaterthan($command_value,
                                       $input_value,
                                       $variable_name,
                                       $default_error_message,
                                       $extra_info );
                     break;
                  }

         case "regexp":
                  {
                     if(isset($input_value) && strlen($input_value)>0)
                     {
                        if(!preg_match("$command_value",$input_value))
                        {
                           $bret=false;
                           $default_error_message = sprintf(E_VAL_REGEXP_CHECK_FAILED,$variable_name);
                        }
                     }
                     break;
                  }
        case "dontselect": 
        case "dontselectchk":
          case "dontselectradio":
                  {
                     $bret = $this->validate_dontselect($input_value,
                                                $command_value,
                                                $default_error_message,
                                                $variable_name);
                      break;
                  }//case

          case "shouldselchk":
          case "selectradio":
                      {
                            $bret = $this->validate_select($input_value,
                            $command_value,
                            $default_error_message,
                            $variable_name);
                            break;
                      }//case

        case "selmin":
                  {
                     $min_count = intval($command_value);
                            $bret = false;

                     if(is_array($input_value))
                            {
                         $bret = (count($input_value) >= $min_count )?true:false;
                            }
                     else
                     {
                                if(isset($input_value) && !empty($input_value) && $min_count == 1)
                                {
                                    $bret = true;
                                }
                     }
                            if(!$bret)
                            {
                        $default_error_message = sprintf(E_VAL_SELMIN_CHECK_FAILED,
                                            $min_count,$variable_name);

                            }

                     break;
                  }//case
        case "selmax":
                  {
                     $max_count = intval($command_value);

                     if(isset($input_value))
                            {
                         $bret = (count($input_value) > $max_count )?false:true;
                            }

                     break;
                  }//case
       case "selone":
                  {
                     if(false == isset($input_value)||
                        strlen($input_value)<=0)
                     {
                        $bret= false;
                        $default_error_message = sprintf(E_VAL_SELONE_CHECK_FAILED,$variable_name);
                     }
                     break;
                  }
       case "eqelmnt":
                  {

                     if(isset($formvariables[$command_value]) &&
                        strcmp($input_value,$formvariables[$command_value])==0 )
                     {
                        $bret=true;
                     }
                     else
                     {
                        $bret= false;
                        $default_error_message = sprintf(E_VAL_EQELMNT_CHECK_FAILED,$variable_name,$command_value);
                     }
                  break;
                  }
        case "ltelmnt":
        case "leelmnt":
        case "gtelmnt":
        case "geelmnt":
                  {
                     $bret= $this->ValidateComparison($input_value,$formvariables,
                              $command_value,$extra_info,$default_error_message,
                              $variable_name,
                              $command);
                  break;
                  }
        case "neelmnt":
                  {
                     if(isset($formvariables[$command_value]) &&
                        strcmp($input_value,$formvariables[$command_value]) !=0 )
                     {
                        $bret=true;
                     }
                     else
                     {
                        $bret= false;
                        $default_error_message = sprintf(E_VAL_NEELMNT_CHECK_FAILED,$variable_name,$command_value);
                     }
                     break;
                  }
          case "req_file":
          case "max_filesize":
          case "file_extn":
                  {
                            $bret= $this->ValidateFileUpload($variable_name,
                                    $command,
                                    $command_value,
                                    $default_error_message);
                            break;
                        }

          case "after_date":
                        {
                            $bret = $this->ValidateDate($command_value,$variable_name,false);
                        }
                        break;

          case "before_date":
                        {
                            $bret = $this->ValidateDate($command_value,$variable_name,true);
                        }
                        break;
       
      }//switch
      return $bret;
   }//validdate command

    function ValidateDate($command_value,$variable_name,$before)
    {
        $bret = true;
        $date_obj = new FM_DateObj($this->formvars,$this->config,$this->logger);

        $date_other = $date_obj->GetOtherDate($command_value);

        $date_this  = $date_obj->GetDateFieldInStdForm($variable_name);

        if(empty($this->formvars[$variable_name]))
        {
            $bret=true;
        }
        else
        if(!$date_other || !$date_this)
        {
            $this->logger->LogError("Invalid date received. ".$this->formvars[$variable_name]);
            $bret=false;
        }
        else
        {
            $this->logger->LogInfo(" before_date validation this:$date_this other:$date_other");

            $bret = $before ? strcmp($date_this,$date_other) < 0: strcmp($date_this,$date_other)>0;
        } 
        if(!$bret)
        {
            $this->logger->LogInfo("$variable_name: Date validation failed");
        }
        return $bret;
    }

    function ValidateFileUpload($variable_name,$command,$command_value,&$default_error_message)
    {
        $bret=true;

        if(NULL == $this->globaldata->files ||
            !isset($this->globaldata->files))
        {
            if(isset($this->formvars[$this->config->confirm_file_list_var]))
            {
                $this->logger->LogInfo("Validator: confirm_file_list_var is set. skipping file validations");
                return true;
            }
            $default_error_message = 
                sprintf(E_VAL_REQ_FILE_INPUT,$variable_name);
            return false;
        }
        switch($command)
      {
        case "req_file":
         { 
               if(!isset($this->globaldata->files[$variable_name])||
                  !isset($this->globaldata->files[$variable_name]['size'])||
                  $this->globaldata->files[$variable_name]['size'] <= 0
                  ) 
                {
                    $bret=false;
                    $default_error_message = 
                        sprintf(E_VAL_REQ_FILE_INPUT,$variable_name);
                }//if
               break;
            }//case
        case "max_filesize":
         { 
               if(isset($this->globaldata->files[$variable_name]))
               {
                    $maxsize = intval(doubleval($command_value)*1024);
                    if( $this->globaldata->files[$variable_name]['size'] > $maxsize ||
                        @filesize($this->globaldata->files[$variable_name]['tmp_name']) > $maxsize) 
                    {
                        $bret=false;
                        $default_error_message = 
                            sprintf(E_VAL_FILESIZE_EXCEEDED,$variable_name,$maxsize);
                    }//if
                }//if
               break;
            }//case max_filesize

         case "file_extn":
            {
               if(isset($this->globaldata->files[$variable_name]) &&
                  strlen($this->globaldata->files[$variable_name]['name'])>0)
               {
                    $orig_filename = $this->globaldata->files[$variable_name]['name'];
                    $parts = pathinfo($orig_filename);
                    $file_extn = $parts["extension"];
                    $allowed_extns=explode(";",$command_value);
                    $found=false;
                    foreach($allowed_extns as $extn)
                    {
                        if(strcasecmp($file_extn,$extn)==0)
                        {
                            $found=true;
                        }
                    }
                    if(!$found)
                    {
                        $bret=false;
                        $default_error_message = 
                            sprintf(E_VAL_EXTN_NOTALLOWED,$variable_name,$command_value);
                    }
               }
               break;
            }
         
        }//switch
        return $bret;
    }//function
}//FM_FormValidator


/**
 *  PEAR's Mail:: interface.
 *
 * PHP versions 4 and 5
 *
 * LICENSE:
 *
 * Copyright (c) 2002-2007, Richard Heyes
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 * o Redistributions of source code must retain the above copyright
 *   notice, this list of conditions and the following disclaimer.
 * o Redistributions in binary form must reproduce the above copyright
 *   notice, this list of conditions and the following disclaimer in the
 *   documentation and/or other materials provided with the distribution.
 * o The names of the authors may not be used to endorse or promote
 *   products derived from this software without specific prior written
 *   permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @category    Mail
 * @package     Mail
 * @author      Chuck Hagenbuch <chuck@horde.org>
 * @copyright   1997-2010 Chuck Hagenbuch
 * @license     http://opensource.org/licenses/bsd-license.php New BSD License
 * @version     CVS: $Id: Mail.php,v 1.2.4.1 2011/02/02 15:49:12 Prasanth Exp $
 * @link        http://pear.php.net/package/Mail/
 */

/*PJ: require_once 'PEAR.php';*/

/**
 * PEAR's Mail:: interface. Defines the interface for implementing
 * mailers under the PEAR hierarchy, and provides supporting functions
 * useful in multiple mailer backends.
 *
 * @access public
 * @version $Revision: 1.2.4.1 $
 * @package Mail
 */
class Mail
{
    /**
     * Line terminator used for separating header lines.
     * @var string
     */
    var $sep = "\r\n";

    /**
     * Provides an interface for generating Mail:: objects of various
     * types
     *
     * @param string $driver The kind of Mail:: object to instantiate.
     * @param array  $params The parameters to pass to the Mail:: object.
     * @return object Mail a instance of the driver class or if fails a PEAR Error
     * @access public
     */
    function &factory($driver, $params = array())
    {
        $driver = strtolower($driver);
        /*PJ: @include_once 'Mail/' . $driver . '.php';*/
        $class = 'Mail_' . $driver;
        if (class_exists($class)) {
            $mailer = new $class($params);
            return $mailer;
        } else {
            return PEAR::raiseError('Unable to find class for driver ' . $driver);
        }
    }

    /**
     * Implements Mail::send() function using php's built-in mail()
     * command.
     *
     * @param mixed $recipients Either a comma-seperated list of recipients
     *              (RFC822 compliant), or an array of recipients,
     *              each RFC822 valid. This may contain recipients not
     *              specified in the headers, for Bcc:, resending
     *              messages, etc.
     *
     * @param array $headers The array of headers to send with the mail, in an
     *              associative array, where the array key is the
     *              header name (ie, 'Subject'), and the array value
     *              is the header value (ie, 'test'). The header
     *              produced from those values would be 'Subject:
     *              test'.
     *
     * @param string $body The full text of the message body, including any
     *               Mime parts, etc.
     *
     * @return mixed Returns true on success, or a PEAR_Error
     *               containing a descriptive error message on
     *               failure.
     *
     * @access public
     * @deprecated use Mail_mail::send instead
     */
    function send($recipients, $headers, $body)
    {
        if (!is_array($headers)) {
            return PEAR::raiseError('$headers must be an array');
        }

        $result = $this->_sanitizeHeaders($headers);
        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }

        // if we're passed an array of recipients, implode it.
        if (is_array($recipients)) {
            $recipients = implode(', ', $recipients);
        }

        // get the Subject out of the headers array so that we can
        // pass it as a seperate argument to mail().
        $subject = '';
        if (isset($headers['Subject'])) {
            $subject = $headers['Subject'];
            unset($headers['Subject']);
        }

        // flatten the headers out.
        list(, $text_headers) = Mail::prepareHeaders($headers);

        return mail($recipients, $subject, $body, $text_headers);
    }

    /**
     * Sanitize an array of mail headers by removing any additional header
     * strings present in a legitimate header's value.  The goal of this
     * filter is to prevent mail injection attacks.
     *
     * @param array $headers The associative array of headers to sanitize.
     *
     * @access private
     */
    function _sanitizeHeaders(&$headers)
    {
        foreach ($headers as $key => $value) {
            $headers[$key] =
                preg_replace('=((<CR>|<LF>|0x0A/%0A|0x0D/%0D|\\n|\\r)\S).*=i',
                             null, $value);
        }
    }

    /**
     * Take an array of mail headers and return a string containing
     * text usable in sending a message.
     *
     * @param array $headers The array of headers to prepare, in an associative
     *              array, where the array key is the header name (ie,
     *              'Subject'), and the array value is the header
     *              value (ie, 'test'). The header produced from those
     *              values would be 'Subject: test'.
     *
     * @return mixed Returns false if it encounters a bad address,
     *               otherwise returns an array containing two
     *               elements: Any From: address found in the headers,
     *               and the plain text version of the headers.
     * @access private
     */
    function prepareHeaders($headers)
    {
        $lines = array();
        $from = null;

        foreach ($headers as $key => $value) {
            if (strcasecmp($key, 'From') === 0) {
                /*PJ: include_once 'Mail/RFC822.php';*/
                $parser = new Mail_RFC822();
                $addresses = $parser->parseAddressList($value, 'localhost', false);
                if (is_a($addresses, 'PEAR_Error')) {
                    return $addresses;
                }

                $from = $addresses[0]->mailbox . '@' . $addresses[0]->host;

                // Reject envelope From: addresses with spaces.
                if (strstr($from, ' ')) {
                    return false;
                }

                $lines[] = $key . ': ' . $value;
            } elseif (strcasecmp($key, 'Received') === 0) {
                $received = array();
                if (is_array($value)) {
                    foreach ($value as $line) {
                        $received[] = $key . ': ' . $line;
                    }
                }
                else {
                    $received[] = $key . ': ' . $value;
                }
                // Put Received: headers at the top.  Spam detectors often
                // flag messages with Received: headers after the Subject:
                // as spam.
                $lines = array_merge($received, $lines);
            } else {
                // If $value is an array (i.e., a list of addresses), convert
                // it to a comma-delimited string of its elements (addresses).
                if (is_array($value)) {
                    $value = implode(', ', $value);
                }
                $lines[] = $key . ': ' . $value;
            }
        }

        return array($from, join($this->sep, $lines));
    }

    /**
     * Take a set of recipients and parse them, returning an array of
     * bare addresses (forward paths) that can be passed to sendmail
     * or an smtp server with the rcpt to: command.
     *
     * @param mixed Either a comma-seperated list of recipients
     *              (RFC822 compliant), or an array of recipients,
     *              each RFC822 valid.
     *
     * @return mixed An array of forward paths (bare addresses) or a PEAR_Error
     *               object if the address list could not be parsed.
     * @access private
     */
    function parseRecipients($recipients)
    {
        /*PJ: include_once 'Mail/RFC822.php';*/

        // if we're passed an array, assume addresses are valid and
        // implode them before parsing.
        if (is_array($recipients)) {
            $recipients = implode(', ', $recipients);
        }

        // Parse recipients, leaving out all personal info. This is
        // for smtp recipients, etc. All relevant personal information
        // should already be in the headers.
        $addresses = Mail_RFC822::parseAddressList($recipients, 'localhost', false);

        // If parseAddressList() returned a PEAR_Error object, just return it.
        if (is_a($addresses, 'PEAR_Error')) {
            return $addresses;
        }

        $recipients = array();
        if (is_array($addresses)) {
            foreach ($addresses as $ob) {
                $recipients[] = $ob->mailbox . '@' . $ob->host;
            }
        }

        return $recipients;
    }

}
/**
 * RFC 822 Email address list validation Utility
 *
 * PHP versions 4 and 5
 *
 * LICENSE:
 *
 * Copyright (c) 2001-2010, Richard Heyes
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 * o Redistributions of source code must retain the above copyright
 *   notice, this list of conditions and the following disclaimer.
 * o Redistributions in binary form must reproduce the above copyright
 *   notice, this list of conditions and the following disclaimer in the
 *   documentation and/or other materials provided with the distribution.
 * o The names of the authors may not be used to endorse or promote
 *   products derived from this software without specific prior written
 *   permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @category    Mail
 * @package     Mail
 * @author      Richard Heyes <richard@phpguru.org>
 * @author      Chuck Hagenbuch <chuck@horde.org
 * @copyright   2001-2010 Richard Heyes
 * @license     http://opensource.org/licenses/bsd-license.php New BSD License
 * @version     CVS: $Id: RFC822.php,v 1.2 2010/03/19 05:37:27 Prasanth Exp $
 * @link        http://pear.php.net/package/Mail/
 */

/**
 * RFC 822 Email address list validation Utility
 *
 * What is it?
 *
 * This class will take an address string, and parse it into it's consituent
 * parts, be that either addresses, groups, or combinations. Nested groups
 * are not supported. The structure it returns is pretty straight forward,
 * and is similar to that provided by the imap_rfc822_parse_adrlist(). Use
 * print_r() to view the structure.
 *
 * How do I use it?
 *
 * $address_string = 'My Group: "Richard" <richard@localhost> (A comment), ted@example.com (Ted Bloggs), Barney;';
 * $structure = Mail_RFC822::parseAddressList($address_string, 'example.com', true)
 * print_r($structure);
 *
 * @author  Richard Heyes <richard@phpguru.org>
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @version $Revision: 1.2 $
 * @license BSD
 * @package Mail
 */
class Mail_RFC822 {

    /**
     * The address being parsed by the RFC822 object.
     * @var string $address
     */
    var $address = '';

    /**
     * The default domain to use for unqualified addresses.
     * @var string $default_domain
     */
    var $default_domain = 'localhost';

    /**
     * Should we return a nested array showing groups, or flatten everything?
     * @var boolean $nestGroups
     */
    var $nestGroups = true;

    /**
     * Whether or not to validate atoms for non-ascii characters.
     * @var boolean $validate
     */
    var $validate = true;

    /**
     * The array of raw addresses built up as we parse.
     * @var array $addresses
     */
    var $addresses = array();

    /**
     * The final array of parsed address information that we build up.
     * @var array $structure
     */
    var $structure = array();

    /**
     * The current error message, if any.
     * @var string $error
     */
    var $error = null;

    /**
     * An internal counter/pointer.
     * @var integer $index
     */
    var $index = null;

    /**
     * The number of groups that have been found in the address list.
     * @var integer $num_groups
     * @access public
     */
    var $num_groups = 0;

    /**
     * A variable so that we can tell whether or not we're inside a
     * Mail_RFC822 object.
     * @var boolean $mailRFC822
     */
    var $mailRFC822 = true;

    /**
    * A limit after which processing stops
    * @var int $limit
    */
    var $limit = null;

    /**
     * Sets up the object. The address must either be set here or when
     * calling parseAddressList(). One or the other.
     *
     * @access public
     * @param string  $address         The address(es) to validate.
     * @param string  $default_domain  Default domain/host etc. If not supplied, will be set to localhost.
     * @param boolean $nest_groups     Whether to return the structure with groups nested for easier viewing.
     * @param boolean $validate        Whether to validate atoms. Turn this off if you need to run addresses through before encoding the personal names, for instance.
     *
     * @return object Mail_RFC822 A new Mail_RFC822 object.
     */
    function Mail_RFC822($address = null, $default_domain = null, $nest_groups = null, $validate = null, $limit = null)
    {
        if (isset($address))        $this->address        = $address;
        if (isset($default_domain)) $this->default_domain = $default_domain;
        if (isset($nest_groups))    $this->nestGroups     = $nest_groups;
        if (isset($validate))       $this->validate       = $validate;
        if (isset($limit))          $this->limit          = $limit;
    }

    /**
     * Starts the whole process. The address must either be set here
     * or when creating the object. One or the other.
     *
     * @access public
     * @param string  $address         The address(es) to validate.
     * @param string  $default_domain  Default domain/host etc.
     * @param boolean $nest_groups     Whether to return the structure with groups nested for easier viewing.
     * @param boolean $validate        Whether to validate atoms. Turn this off if you need to run addresses through before encoding the personal names, for instance.
     *
     * @return array A structured array of addresses.
     */
    function parseAddressList($address = null, $default_domain = null, $nest_groups = null, $validate = null, $limit = null)
    {
        if (!isset($this) || !isset($this->mailRFC822)) {
            $obj = new Mail_RFC822($address, $default_domain, $nest_groups, $validate, $limit);
            return $obj->parseAddressList();
        }

        if (isset($address))        $this->address        = $address;
        if (isset($default_domain)) $this->default_domain = $default_domain;
        if (isset($nest_groups))    $this->nestGroups     = $nest_groups;
        if (isset($validate))       $this->validate       = $validate;
        if (isset($limit))          $this->limit          = $limit;

        $this->structure  = array();
        $this->addresses  = array();
        $this->error      = null;
        $this->index      = null;

        // Unfold any long lines in $this->address.
        $this->address = preg_replace('/\r?\n/', "\r\n", $this->address);
        $this->address = preg_replace('/\r\n(\t| )+/', ' ', $this->address);

        while ($this->address = $this->_splitAddresses($this->address));

        if ($this->address === false || isset($this->error)) {
            /*PJ: require_once 'PEAR.php';*/
            return PEAR::raiseError($this->error);
        }

        // Validate each address individually.  If we encounter an invalid
        // address, stop iterating and return an error immediately.
        foreach ($this->addresses as $address) {
            $valid = $this->_validateAddress($address);

            if ($valid === false || isset($this->error)) {
               /*PJ: require_once 'PEAR.php';*/
                return PEAR::raiseError($this->error);
            }

            if (!$this->nestGroups) {
                $this->structure = array_merge($this->structure, $valid);
            } else {
                $this->structure[] = $valid;
            }
        }

        return $this->structure;
    }

    /**
     * Splits an address into separate addresses.
     *
     * @access private
     * @param string $address The addresses to split.
     * @return boolean Success or failure.
     */
    function _splitAddresses($address)
    {
        if (!empty($this->limit) && count($this->addresses) == $this->limit) {
            return '';
        }

        if ($this->_isGroup($address) && !isset($this->error)) {
            $split_char = ';';
            $is_group   = true;
        } elseif (!isset($this->error)) {
            $split_char = ',';
            $is_group   = false;
        } elseif (isset($this->error)) {
            return false;
        }

        // Split the string based on the above ten or so lines.
        $parts  = explode($split_char, $address);
        $string = $this->_splitCheck($parts, $split_char);

        // If a group...
        if ($is_group) {
            // If $string does not contain a colon outside of
            // brackets/quotes etc then something's fubar.

            // First check there's a colon at all:
            if (strpos($string, ':') === false) {
                $this->error = 'Invalid address: ' . $string;
                return false;
            }

            // Now check it's outside of brackets/quotes:
            if (!$this->_splitCheck(explode(':', $string), ':')) {
                return false;
            }

            // We must have a group at this point, so increase the counter:
            $this->num_groups++;
        }

        // $string now contains the first full address/group.
        // Add to the addresses array.
        $this->addresses[] = array(
                                   'address' => trim($string),
                                   'group'   => $is_group
                                   );

        // Remove the now stored address from the initial line, the +1
        // is to account for the explode character.
        $address = trim(substr($address, strlen($string) + 1));

        // If the next char is a comma and this was a group, then
        // there are more addresses, otherwise, if there are any more
        // chars, then there is another address.
        if ($is_group && substr($address, 0, 1) == ','){
            $address = trim(substr($address, 1));
            return $address;

        } elseif (strlen($address) > 0) {
            return $address;

        } else {
            return '';
        }

        // If you got here then something's off
        return false;
    }

    /**
     * Checks for a group at the start of the string.
     *
     * @access private
     * @param string $address The address to check.
     * @return boolean Whether or not there is a group at the start of the string.
     */
    function _isGroup($address)
    {
        // First comma not in quotes, angles or escaped:
        $parts  = explode(',', $address);
        $string = $this->_splitCheck($parts, ',');

        // Now we have the first address, we can reliably check for a
        // group by searching for a colon that's not escaped or in
        // quotes or angle brackets.
        if (count($parts = explode(':', $string)) > 1) {
            $string2 = $this->_splitCheck($parts, ':');
            return ($string2 !== $string);
        } else {
            return false;
        }
    }

    /**
     * A common function that will check an exploded string.
     *
     * @access private
     * @param array $parts The exloded string.
     * @param string $char  The char that was exploded on.
     * @return mixed False if the string contains unclosed quotes/brackets, or the string on success.
     */
    function _splitCheck($parts, $char)
    {
        $string = $parts[0];

        for ($i = 0; $i < count($parts); $i++) {
            if ($this->_hasUnclosedQuotes($string)
                || $this->_hasUnclosedBrackets($string, '<>')
                || $this->_hasUnclosedBrackets($string, '[]')
                || $this->_hasUnclosedBrackets($string, '()')
                || substr($string, -1) == '\\') {
                if (isset($parts[$i + 1])) {
                    $string = $string . $char . $parts[$i + 1];
                } else {
                    $this->error = 'Invalid address spec. Unclosed bracket or quotes';
                    return false;
                }
            } else {
                $this->index = $i;
                break;
            }
        }

        return $string;
    }

    /**
     * Checks if a string has unclosed quotes or not.
     *
     * @access private
     * @param string $string  The string to check.
     * @return boolean  True if there are unclosed quotes inside the string,
     *                  false otherwise.
     */
    function _hasUnclosedQuotes($string)
    {
        $string = trim($string);
        $iMax = strlen($string);
        $in_quote = false;
        $i = $slashes = 0;

        for (; $i < $iMax; ++$i) {
            switch ($string[$i]) {
            case '\\':
                ++$slashes;
                break;

            case '"':
                if ($slashes % 2 == 0) {
                    $in_quote = !$in_quote;
                }
                // Fall through to default action below.

            default:
                $slashes = 0;
                break;
            }
        }

        return $in_quote;
    }

    /**
     * Checks if a string has an unclosed brackets or not. IMPORTANT:
     * This function handles both angle brackets and square brackets;
     *
     * @access private
     * @param string $string The string to check.
     * @param string $chars  The characters to check for.
     * @return boolean True if there are unclosed brackets inside the string, false otherwise.
     */
    function _hasUnclosedBrackets($string, $chars)
    {
        $num_angle_start = substr_count($string, $chars[0]);
        $num_angle_end   = substr_count($string, $chars[1]);

        $this->_hasUnclosedBracketsSub($string, $num_angle_start, $chars[0]);
        $this->_hasUnclosedBracketsSub($string, $num_angle_end, $chars[1]);

        if ($num_angle_start < $num_angle_end) {
            $this->error = 'Invalid address spec. Unmatched quote or bracket (' . $chars . ')';
            return false;
        } else {
            return ($num_angle_start > $num_angle_end);
        }
    }

    /**
     * Sub function that is used only by hasUnclosedBrackets().
     *
     * @access private
     * @param string $string The string to check.
     * @param integer &$num    The number of occurences.
     * @param string $char   The character to count.
     * @return integer The number of occurences of $char in $string, adjusted for backslashes.
     */
    function _hasUnclosedBracketsSub($string, &$num, $char)
    {
        $parts = explode($char, $string);
        for ($i = 0; $i < count($parts); $i++){
            if (substr($parts[$i], -1) == '\\' || $this->_hasUnclosedQuotes($parts[$i]))
                $num--;
            if (isset($parts[$i + 1]))
                $parts[$i + 1] = $parts[$i] . $char . $parts[$i + 1];
        }

        return $num;
    }

    /**
     * Function to begin checking the address.
     *
     * @access private
     * @param string $address The address to validate.
     * @return mixed False on failure, or a structured array of address information on success.
     */
    function _validateAddress($address)
    {
        $is_group = false;
        $addresses = array();

        if ($address['group']) {
            $is_group = true;

            // Get the group part of the name
            $parts     = explode(':', $address['address']);
            $groupname = $this->_splitCheck($parts, ':');
            $structure = array();

            // And validate the group part of the name.
            if (!$this->_validatePhrase($groupname)){
                $this->error = 'Group name did not validate.';
                return false;
            } else {
                // Don't include groups if we are not nesting
                // them. This avoids returning invalid addresses.
                if ($this->nestGroups) {
                    $structure = new stdClass;
                    $structure->groupname = $groupname;
                }
            }

            $address['address'] = ltrim(substr($address['address'], strlen($groupname . ':')));
        }

        // If a group then split on comma and put into an array.
        // Otherwise, Just put the whole address in an array.
        if ($is_group) {
            while (strlen($address['address']) > 0) {
                $parts       = explode(',', $address['address']);
                $addresses[] = $this->_splitCheck($parts, ',');
                $address['address'] = trim(substr($address['address'], strlen(end($addresses) . ',')));
            }
        } else {
            $addresses[] = $address['address'];
        }

        // Check that $addresses is set, if address like this:
        // Groupname:;
        // Then errors were appearing.
        if (!count($addresses)){
            $this->error = 'Empty group.';
            return false;
        }

        // Trim the whitespace from all of the address strings.
        array_map('trim', $addresses);

        // Validate each mailbox.
        // Format could be one of: name <geezer@domain.com>
        //                         geezer@domain.com
        //                         geezer
        // ... or any other format valid by RFC 822.
        for ($i = 0; $i < count($addresses); $i++) {
            if (!$this->validateMailbox($addresses[$i])) {
                if (empty($this->error)) {
                    $this->error = 'Validation failed for: ' . $addresses[$i];
                }
                return false;
            }
        }

        // Nested format
        if ($this->nestGroups) {
            if ($is_group) {
                $structure->addresses = $addresses;
            } else {
                $structure = $addresses[0];
            }

        // Flat format
        } else {
            if ($is_group) {
                $structure = array_merge($structure, $addresses);
            } else {
                $structure = $addresses;
            }
        }

        return $structure;
    }

    /**
     * Function to validate a phrase.
     *
     * @access private
     * @param string $phrase The phrase to check.
     * @return boolean Success or failure.
     */
    function _validatePhrase($phrase)
    {
        // Splits on one or more Tab or space.
        $parts = preg_split('/[ \\x09]+/', $phrase, -1, PREG_SPLIT_NO_EMPTY);

        $phrase_parts = array();
        while (count($parts) > 0){
            $phrase_parts[] = $this->_splitCheck($parts, ' ');
            for ($i = 0; $i < $this->index + 1; $i++)
                array_shift($parts);
        }

        foreach ($phrase_parts as $part) {
            // If quoted string:
            if (substr($part, 0, 1) == '"') {
                if (!$this->_validateQuotedString($part)) {
                    return false;
                }
                continue;
            }

            // Otherwise it's an atom:
            if (!$this->_validateAtom($part)) return false;
        }

        return true;
    }

    /**
     * Function to validate an atom which from rfc822 is:
     * atom = 1*<any CHAR except specials, SPACE and CTLs>
     *
     * If validation ($this->validate) has been turned off, then
     * validateAtom() doesn't actually check anything. This is so that you
     * can split a list of addresses up before encoding personal names
     * (umlauts, etc.), for example.
     *
     * @access private
     * @param string $atom The string to check.
     * @return boolean Success or failure.
     */
    function _validateAtom($atom)
    {
        if (!$this->validate) {
            // Validation has been turned off; assume the atom is okay.
            return true;
        }

        // Check for any char from ASCII 0 - ASCII 127
        if (!preg_match('/^[\\x00-\\x7E]+$/i', $atom, $matches)) {
            return false;
        }

        // Check for specials:
        if (preg_match('/[][()<>@,;\\:". ]/', $atom)) {
            return false;
        }

        // Check for control characters (ASCII 0-31):
        if (preg_match('/[\\x00-\\x1F]+/', $atom)) {
            return false;
        }

        return true;
    }

    /**
     * Function to validate quoted string, which is:
     * quoted-string = <"> *(qtext/quoted-pair) <">
     *
     * @access private
     * @param string $qstring The string to check
     * @return boolean Success or failure.
     */
    function _validateQuotedString($qstring)
    {
        // Leading and trailing "
        $qstring = substr($qstring, 1, -1);

        // Perform check, removing quoted characters first.
        return !preg_match('/[\x0D\\\\"]/', preg_replace('/\\\\./', '', $qstring));
    }

    /**
     * Function to validate a mailbox, which is:
     * mailbox =   addr-spec         ; simple address
     *           / phrase route-addr ; name and route-addr
     *
     * @access public
     * @param string &$mailbox The string to check.
     * @return boolean Success or failure.
     */
    function validateMailbox(&$mailbox)
    {
        // A couple of defaults.
        $phrase  = '';
        $comment = '';
        $comments = array();

        // Catch any RFC822 comments and store them separately.
        $_mailbox = $mailbox;
        while (strlen(trim($_mailbox)) > 0) {
            $parts = explode('(', $_mailbox);
            $before_comment = $this->_splitCheck($parts, '(');
            if ($before_comment != $_mailbox) {
                // First char should be a (.
                $comment    = substr(str_replace($before_comment, '', $_mailbox), 1);
                $parts      = explode(')', $comment);
                $comment    = $this->_splitCheck($parts, ')');
                $comments[] = $comment;

                // +2 is for the brackets
                $_mailbox = substr($_mailbox, strpos($_mailbox, '('.$comment)+strlen($comment)+2);
            } else {
                break;
            }
        }

        foreach ($comments as $comment) {
            $mailbox = str_replace("($comment)", '', $mailbox);
        }

        $mailbox = trim($mailbox);

        // Check for name + route-addr
        if (substr($mailbox, -1) == '>' && substr($mailbox, 0, 1) != '<') {
            $parts  = explode('<', $mailbox);
            $name   = $this->_splitCheck($parts, '<');

            $phrase     = trim($name);
            $route_addr = trim(substr($mailbox, strlen($name.'<'), -1));

            if ($this->_validatePhrase($phrase) === false || ($route_addr = $this->_validateRouteAddr($route_addr)) === false) {
                return false;
            }

        // Only got addr-spec
        } else {
            // First snip angle brackets if present.
            if (substr($mailbox, 0, 1) == '<' && substr($mailbox, -1) == '>') {
                $addr_spec = substr($mailbox, 1, -1);
            } else {
                $addr_spec = $mailbox;
            }

            if (($addr_spec = $this->_validateAddrSpec($addr_spec)) === false) {
                return false;
            }
        }

        // Construct the object that will be returned.
        $mbox = new stdClass();

        // Add the phrase (even if empty) and comments
        $mbox->personal = $phrase;
        $mbox->comment  = isset($comments) ? $comments : array();

        if (isset($route_addr)) {
            $mbox->mailbox = $route_addr['local_part'];
            $mbox->host    = $route_addr['domain'];
            $route_addr['adl'] !== '' ? $mbox->adl = $route_addr['adl'] : '';
        } else {
            $mbox->mailbox = $addr_spec['local_part'];
            $mbox->host    = $addr_spec['domain'];
        }

        $mailbox = $mbox;
        return true;
    }

    /**
     * This function validates a route-addr which is:
     * route-addr = "<" [route] addr-spec ">"
     *
     * Angle brackets have already been removed at the point of
     * getting to this function.
     *
     * @access private
     * @param string $route_addr The string to check.
     * @return mixed False on failure, or an array containing validated address/route information on success.
     */
    function _validateRouteAddr($route_addr)
    {
        // Check for colon.
        if (strpos($route_addr, ':') !== false) {
            $parts = explode(':', $route_addr);
            $route = $this->_splitCheck($parts, ':');
        } else {
            $route = $route_addr;
        }

        // If $route is same as $route_addr then the colon was in
        // quotes or brackets or, of course, non existent.
        if ($route === $route_addr){
            unset($route);
            $addr_spec = $route_addr;
            if (($addr_spec = $this->_validateAddrSpec($addr_spec)) === false) {
                return false;
            }
        } else {
            // Validate route part.
            if (($route = $this->_validateRoute($route)) === false) {
                return false;
            }

            $addr_spec = substr($route_addr, strlen($route . ':'));

            // Validate addr-spec part.
            if (($addr_spec = $this->_validateAddrSpec($addr_spec)) === false) {
                return false;
            }
        }

        if (isset($route)) {
            $return['adl'] = $route;
        } else {
            $return['adl'] = '';
        }

        $return = array_merge($return, $addr_spec);
        return $return;
    }

    /**
     * Function to validate a route, which is:
     * route = 1#("@" domain) ":"
     *
     * @access private
     * @param string $route The string to check.
     * @return mixed False on failure, or the validated $route on success.
     */
    function _validateRoute($route)
    {
        // Split on comma.
        $domains = explode(',', trim($route));

        foreach ($domains as $domain) {
            $domain = str_replace('@', '', trim($domain));
            if (!$this->_validateDomain($domain)) return false;
        }

        return $route;
    }

    /**
     * Function to validate a domain, though this is not quite what
     * you expect of a strict internet domain.
     *
     * domain = sub-domain *("." sub-domain)
     *
     * @access private
     * @param string $domain The string to check.
     * @return mixed False on failure, or the validated domain on success.
     */
    function _validateDomain($domain)
    {
        // Note the different use of $subdomains and $sub_domains
        $subdomains = explode('.', $domain);

        while (count($subdomains) > 0) {
            $sub_domains[] = $this->_splitCheck($subdomains, '.');
            for ($i = 0; $i < $this->index + 1; $i++)
                array_shift($subdomains);
        }

        foreach ($sub_domains as $sub_domain) {
            if (!$this->_validateSubdomain(trim($sub_domain)))
                return false;
        }

        // Managed to get here, so return input.
        return $domain;
    }

    /**
     * Function to validate a subdomain:
     *   subdomain = domain-ref / domain-literal
     *
     * @access private
     * @param string $subdomain The string to check.
     * @return boolean Success or failure.
     */
    function _validateSubdomain($subdomain)
    {
        if (preg_match('|^\[(.*)]$|', $subdomain, $arr)){
            if (!$this->_validateDliteral($arr[1])) return false;
        } else {
            if (!$this->_validateAtom($subdomain)) return false;
        }

        // Got here, so return successful.
        return true;
    }

    /**
     * Function to validate a domain literal:
     *   domain-literal =  "[" *(dtext / quoted-pair) "]"
     *
     * @access private
     * @param string $dliteral The string to check.
     * @return boolean Success or failure.
     */
    function _validateDliteral($dliteral)
    {
        return !preg_match('/(.)[][\x0D\\\\]/', $dliteral, $matches) && $matches[1] != '\\';
    }

    /**
     * Function to validate an addr-spec.
     *
     * addr-spec = local-part "@" domain
     *
     * @access private
     * @param string $addr_spec The string to check.
     * @return mixed False on failure, or the validated addr-spec on success.
     */
    function _validateAddrSpec($addr_spec)
    {
        $addr_spec = trim($addr_spec);

        // Split on @ sign if there is one.
        if (strpos($addr_spec, '@') !== false) {
            $parts      = explode('@', $addr_spec);
            $local_part = $this->_splitCheck($parts, '@');
            $domain     = substr($addr_spec, strlen($local_part . '@'));

        // No @ sign so assume the default domain.
        } else {
            $local_part = $addr_spec;
            $domain     = $this->default_domain;
        }

        if (($local_part = $this->_validateLocalPart($local_part)) === false) return false;
        if (($domain     = $this->_validateDomain($domain)) === false) return false;

        // Got here so return successful.
        return array('local_part' => $local_part, 'domain' => $domain);
    }

    /**
     * Function to validate the local part of an address:
     *   local-part = word *("." word)
     *
     * @access private
     * @param string $local_part
     * @return mixed False on failure, or the validated local part on success.
     */
    function _validateLocalPart($local_part)
    {
        $parts = explode('.', $local_part);
        $words = array();

        // Split the local_part into words.
        while (count($parts) > 0){
            $words[] = $this->_splitCheck($parts, '.');
            for ($i = 0; $i < $this->index + 1; $i++) {
                array_shift($parts);
            }
        }

        // Validate each word.
        foreach ($words as $word) {
            // If this word contains an unquoted space, it is invalid. (6.2.4)
            if (strpos($word, ' ') && $word[0] !== '"')
            {
                return false;
            }

            if ($this->_validatePhrase(trim($word)) === false) return false;
        }

        // Managed to get here, so return the input.
        return $local_part;
    }

    /**
     * Returns an approximate count of how many addresses are in the
     * given string. This is APPROXIMATE as it only splits based on a
     * comma which has no preceding backslash. Could be useful as
     * large amounts of addresses will end up producing *large*
     * structures when used with parseAddressList().
     *
     * @param  string $data Addresses to count
     * @return int          Approximate count
     */
    function approximateCount($data)
    {
        return count(preg_split('/(?<!\\\\),/', $data));
    }

    /**
     * This is a email validating function separate to the rest of the
     * class. It simply validates whether an email is of the common
     * internet form: <user>@<domain>. This can be sufficient for most
     * people. Optional stricter mode can be utilised which restricts
     * mailbox characters allowed to alphanumeric, full stop, hyphen
     * and underscore.
     *
     * @param  string  $data   Address to check
     * @param  boolean $strict Optional stricter mode
     * @return mixed           False if it fails, an indexed array
     *                         username/domain if it matches
     */
    function isValidInetAddress($data, $strict = false)
    {
        $regex = $strict ? '/^([.0-9a-z_+-]+)@(([0-9a-z-]+\.)+[0-9a-z]{2,})$/i' : '/^([*+!.&#$|\'\\%\/0-9a-z^_`{}=?~:-]+)@(([0-9a-z-]+\.)+[0-9a-z]{2,})$/i';
        if (preg_match($regex, trim($data), $matches)) {
            return array($matches[1], $matches[2]);
        } else {
            return false;
        }
    }

}
/**
 * The Mail_Mime class is used to create MIME E-mail messages
 *
 * The Mail_Mime class provides an OO interface to create MIME
 * enabled email messages. This way you can create emails that
 * contain plain-text bodies, HTML bodies, attachments, inline
 * images and specific headers.
 *
 * Compatible with PHP versions 4 and 5
 *
 * LICENSE: This LICENSE is in the BSD license style.
 * Copyright (c) 2002-2003, Richard Heyes <richard@phpguru.org>
 * Copyright (c) 2003-2006, PEAR <pear-group@php.net>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or
 * without modification, are permitted provided that the following
 * conditions are met:
 *
 * - Redistributions of source code must retain the above copyright
 *   notice, this list of conditions and the following disclaimer.
 * - Redistributions in binary form must reproduce the above copyright
 *   notice, this list of conditions and the following disclaimer in the
 *   documentation and/or other materials provided with the distribution.
 * - Neither the name of the authors, nor the names of its contributors 
 *   may be used to endorse or promote products derived from this 
 *   software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF
 * THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @category  Mail
 * @package   Mail_Mime
 * @author    Richard Heyes  <richard@phpguru.org>
 * @author    Tomas V.V. Cox <cox@idecnet.com>
 * @author    Cipriano Groenendal <cipri@php.net>
 * @author    Sean Coates <sean@php.net>
 * @author    Aleksander Machniak <alec@php.net>
 * @copyright 2003-2006 PEAR <pear-group@php.net>
 * @license   http://www.opensource.org/licenses/bsd-license.php BSD License
 * @version   CVS: $Id: mime.php,v 1.2 2010/03/19 05:37:26 Prasanth Exp $
 * @link      http://pear.php.net/package/Mail_mime
 *
 *            This class is based on HTML Mime Mail class from
 *            Richard Heyes <richard@phpguru.org> which was based also
 *            in the mime_mail.class by Tobias Ratschiller <tobias@dnet.it>
 *            and Sascha Schumann <sascha@schumann.cx>
 */


/**
 * require PEAR
 *
 * This package depends on PEAR to raise errors.
 */
/*PJ: require_once 'PEAR.php';*/

/**
 * require Mail_mimePart
 *
 * Mail_mimePart contains the code required to
 * create all the different parts a mail can
 * consist of.
 */
/*PJ: require_once 'Mail/mimePart.php';*/


/**
 * The Mail_Mime class provides an OO interface to create MIME
 * enabled email messages. This way you can create emails that
 * contain plain-text bodies, HTML bodies, attachments, inline
 * images and specific headers.
 *
 * @category  Mail
 * @package   Mail_Mime
 * @author    Richard Heyes  <richard@phpguru.org>
 * @author    Tomas V.V. Cox <cox@idecnet.com>
 * @author    Cipriano Groenendal <cipri@php.net>
 * @author    Sean Coates <sean@php.net>
 * @copyright 2003-2006 PEAR <pear-group@php.net>
 * @license   http://www.opensource.org/licenses/bsd-license.php BSD License
 * @version   Release: @package_version@
 * @link      http://pear.php.net/package/Mail_mime
 */
class Mail_mime
{
    /**
     * Contains the plain text part of the email
     *
     * @var string
     * @access private
     */
    var $_txtbody;

    /**
     * Contains the html part of the email
     *
     * @var string
     * @access private
     */
    var $_htmlbody;

    /**
     * list of the attached images
     *
     * @var array
     * @access private
     */
    var $_html_images = array();

    /**
     * list of the attachements
     *
     * @var array
     * @access private
     */
    var $_parts = array();

    /**
     * Headers for the mail
     *
     * @var array
     * @access private
     */
    var $_headers = array();

    /**
     * Build parameters
     *
     * @var array
     * @access private
     */
    var $_build_params = array(
        // What encoding to use for the headers
        // Options: quoted-printable or base64
        'head_encoding' => 'quoted-printable',
        // What encoding to use for plain text
        // Options: 7bit, 8bit, base64, or quoted-printable
        'text_encoding' => 'quoted-printable',
        // What encoding to use for html
        // Options: 7bit, 8bit, base64, or quoted-printable
        'html_encoding' => 'quoted-printable',
        // The character set to use for html
        'html_charset'  => 'ISO-8859-1',
        // The character set to use for text
        'text_charset'  => 'ISO-8859-1',
        // The character set to use for headers
        'head_charset'  => 'ISO-8859-1',
        // End-of-line sequence
        'eol'           => "\r\n",
        // Delay attachment files IO until building the message
        'delay_file_io' => false
    );

    /**
     * Constructor function
     *
     * @param mixed $params Build parameters that change the way the email
     *                      is built. Should be an associative array.
     *                      See $_build_params.
     *
     * @return void
     * @access public
     */
    function Mail_mime($params = array())
    {
        // Backward-compatible EOL setting
        if (is_string($params)) {
            $this->_build_params['eol'] = $params;
        } else if (defined('MAIL_MIME_CRLF') && !isset($params['eol'])) {
            $this->_build_params['eol'] = MAIL_MIME_CRLF;
        }

        // Update build parameters
        if (!empty($params) && is_array($params)) {
            while (list($key, $value) = each($params)) {
                $this->_build_params[$key] = $value;
            }
        }
    }

    /**
     * Set build parameter value
     *
     * @param string $name  Parameter name
     * @param string $value Parameter value
     *
     * @return void
     * @access public
     * @since 1.6.0
     */
    function setParam($name, $value)
    {
        $this->_build_params[$name] = $value;
    }

    /**
     * Get build parameter value
     *
     * @param string $name Parameter name
     *
     * @return mixed Parameter value
     * @access public
     * @since 1.6.0
     */
    function getParam($name)
    {
        return isset($this->_build_params[$name]) ? $this->_build_params[$name] : null;
    }

    /**
     * Accessor function to set the body text. Body text is used if
     * it's not an html mail being sent or else is used to fill the
     * text/plain part that emails clients who don't support
     * html should show.
     *
     * @param string $data   Either a string or
     *                       the file name with the contents
     * @param bool   $isfile If true the first param should be treated
     *                       as a file name, else as a string (default)
     * @param bool   $append If true the text or file is appended to
     *                       the existing body, else the old body is
     *                       overwritten
     *
     * @return mixed         True on success or PEAR_Error object
     * @access public
     */
    function setTXTBody($data, $isfile = false, $append = false)
    {
        if (!$isfile) {
            if (!$append) {
                $this->_txtbody = $data;
            } else {
                $this->_txtbody .= $data;
            }
        } else {
            $cont = $this->_file2str($data);
            if (PEAR::isError($cont)) {
                return $cont;
            }
            if (!$append) {
                $this->_txtbody = $cont;
            } else {
                $this->_txtbody .= $cont;
            }
        }
        return true;
    }

    /**
     * Get message text body
     *
     * @return string Text body
     * @access public
     * @since 1.6.0
     */
    function getTXTBody()
    {
        return $this->_txtbody;
    }

    /**
     * Adds a html part to the mail.
     *
     * @param string $data   Either a string or the file name with the
     *                       contents
     * @param bool   $isfile A flag that determines whether $data is a
     *                       filename, or a string(false, default)
     *
     * @return bool          True on success
     * @access public
     */
    function setHTMLBody($data, $isfile = false)
    {
        if (!$isfile) {
            $this->_htmlbody = $data;
        } else {
            $cont = $this->_file2str($data);
            if (PEAR::isError($cont)) {
                return $cont;
            }
            $this->_htmlbody = $cont;
        }

        return true;
    }

    /**
     * Get message HTML body
     *
     * @return string HTML body
     * @access public
     * @since 1.6.0
     */
    function getHTMLBody()
    {
        return $this->_htmlbody;
    }

    /**
     * Adds an image to the list of embedded images.
     *
     * @param string $file       The image file name OR image data itself
     * @param string $c_type     The content type
     * @param string $name       The filename of the image.
     *                           Only used if $file is the image data.
     * @param bool   $isfile     Whether $file is a filename or not.
     *                           Defaults to true
     * @param string $content_id Desired Content-ID of MIME part
     *                           Defaults to generated unique ID
     *
     * @return bool          True on success
     * @access public
     */
    function addHTMLImage($file,
        $c_type='application/octet-stream',
        $name = '',
        $isfile = true,
        $content_id = null
    ) {
        $bodyfile = null;

        if ($isfile) {
            // Don't load file into memory
            if ($this->_build_params['delay_file_io']) {
                $filedata = null;
                $bodyfile = $file;
            } else {
                if (PEAR::isError($filedata = $this->_file2str($file))) {
                    return $filedata;
                }
            }
            $filename = ($name ? $name : $file);
        } else {
            $filedata = $file;
            $filename = $name;
        }

        if (!$content_id) {
            $content_id = md5(uniqid(time()));
        }

        $this->_html_images[] = array(
            'body'      => $filedata,
            'body_file' => $bodyfile,
            'name'      => $filename,
            'c_type'    => $c_type,
            'cid'       => $content_id
        );

        return true;
    }

    /**
     * Adds a file to the list of attachments.
     *
     * @param string $file        The file name of the file to attach
     *                            OR the file contents itself
     * @param string $c_type      The content type
     * @param string $name        The filename of the attachment
     *                            Only use if $file is the contents
     * @param bool   $isfile      Whether $file is a filename or not
     *                            Defaults to true
     * @param string $encoding    The type of encoding to use.
     *                            Defaults to base64.
     *                            Possible values: 7bit, 8bit, base64, 
     *                            or quoted-printable.
     * @param string $disposition The content-disposition of this file
     *                            Defaults to attachment.
     *                            Possible values: attachment, inline.
     * @param string $charset     The character set used in the filename
     *                            of this attachment.
     * @param string $language    The language of the attachment
     * @param string $location    The RFC 2557.4 location of the attachment
     * @param string $n_encoding  Encoding for attachment name (Content-Type)
     *                            By default filenames are encoded using RFC2231 method
     *                            Here you can set RFC2047 encoding (quoted-printable
     *                            or base64) instead
     * @param string $f_encoding  Encoding for attachment filename (Content-Disposition)
     *                            See $n_encoding description
     * @param string $description Content-Description header
     *
     * @return mixed              True on success or PEAR_Error object
     * @access public
     */
    function addAttachment($file,
        $c_type      = 'application/octet-stream',
        $name        = '',
        $isfile      = true,
        $encoding    = 'base64',
        $disposition = 'attachment',
        $charset     = '',
        $language    = '',
        $location    = '',
        $n_encoding  = null,
        $f_encoding  = null,
        $description = ''
    ) {
        $bodyfile = null;

        if ($isfile) {
            // Don't load file into memory
            if ($this->_build_params['delay_file_io']) {
                $filedata = null;
                $bodyfile = $file;
            } else {
                if (PEAR::isError($filedata = $this->_file2str($file))) {
                    return $filedata;
                }
            }
            // Force the name the user supplied, otherwise use $file
            $filename = ($name ? $name : $file);
        } else {
            $filedata = $file;
            $filename = $name;
        }

        if (!strlen($filename)) {
            $msg = "The supplied filename for the attachment can't be empty";
            $err = PEAR::raiseError($msg);
            return $err;
        }
        $filename = $this->_basename($filename);

        $this->_parts[] = array(
            'body'        => $filedata,
            'body_file'   => $bodyfile,
            'name'        => $filename,
            'c_type'      => $c_type,
            'encoding'    => $encoding,
            'charset'     => $charset,
            'language'    => $language,
            'location'    => $location,
            'disposition' => $disposition,
            'description' => $description,
            'name_encoding'     => $n_encoding,
            'filename_encoding' => $f_encoding
        );

        return true;
    }

    /**
     * Get the contents of the given file name as string
     *
     * @param string $file_name Path of file to process
     *
     * @return string           Contents of $file_name
     * @access private
     */
    function &_file2str($file_name)
    {
        // Check state of file and raise an error properly
        if (!file_exists($file_name)) {
            $err = PEAR::raiseError('File not found: ' . $file_name);
            return $err;
        }
        if (!is_file($file_name)) {
            $err = PEAR::raiseError('Not a regular file: ' . $file_name);
            return $err;
        }
        if (!is_readable($file_name)) {
            $err = PEAR::raiseError('File is not readable: ' . $file_name);
            return $err;
        }

        // Temporarily reset magic_quotes_runtime and read file contents
        if ($magic_quote_setting = get_magic_quotes_runtime()) {
            @ini_set('magic_quotes_runtime', 0);
        }
        $cont = file_get_contents($file_name);
        if ($magic_quote_setting) {
            @ini_set('magic_quotes_runtime', $magic_quote_setting);
        }

        return $cont;
    }

    /**
     * Adds a text subpart to the mimePart object and
     * returns it during the build process.
     *
     * @param mixed  &$obj The object to add the part to, or
     *                     null if a new object is to be created.
     * @param string $text The text to add.
     *
     * @return object      The text mimePart object
     * @access private
     */
    function &_addTextPart(&$obj, $text)
    {
        $params['content_type'] = 'text/plain';
        $params['encoding']     = $this->_build_params['text_encoding'];
        $params['charset']      = $this->_build_params['text_charset'];
        $params['eol']          = $this->_build_params['eol'];

        if (is_object($obj)) {
            $ret = $obj->addSubpart($text, $params);
            return $ret;
        } else {
            $ret = new Mail_mimePart($text, $params);
            return $ret;
        }
    }

    /**
     * Adds a html subpart to the mimePart object and
     * returns it during the build process.
     *
     * @param mixed &$obj The object to add the part to, or
     *                    null if a new object is to be created.
     *
     * @return object     The html mimePart object
     * @access private
     */
    function &_addHtmlPart(&$obj)
    {
        $params['content_type'] = 'text/html';
        $params['encoding']     = $this->_build_params['html_encoding'];
        $params['charset']      = $this->_build_params['html_charset'];
        $params['eol']          = $this->_build_params['eol'];

        if (is_object($obj)) {
            $ret = $obj->addSubpart($this->_htmlbody, $params);
            return $ret;
        } else {
            $ret = new Mail_mimePart($this->_htmlbody, $params);
            return $ret;
        }
    }

    /**
     * Creates a new mimePart object, using multipart/mixed as
     * the initial content-type and returns it during the
     * build process.
     *
     * @return object The multipart/mixed mimePart object
     * @access private
     */
    function &_addMixedPart()
    {
        $params                 = array();
        $params['content_type'] = 'multipart/mixed';
        $params['eol']          = $this->_build_params['eol'];

        // Create empty multipart/mixed Mail_mimePart object to return
        $ret = new Mail_mimePart('', $params);
        return $ret;
    }

    /**
     * Adds a multipart/alternative part to a mimePart
     * object (or creates one), and returns it during
     * the build process.
     *
     * @param mixed &$obj The object to add the part to, or
     *                    null if a new object is to be created.
     *
     * @return object     The multipart/mixed mimePart object
     * @access private
     */
    function &_addAlternativePart(&$obj)
    {
        $params['content_type'] = 'multipart/alternative';
        $params['eol']          = $this->_build_params['eol'];

        if (is_object($obj)) {
            return $obj->addSubpart('', $params);
        } else {
            $ret = new Mail_mimePart('', $params);
            return $ret;
        }
    }

    /**
     * Adds a multipart/related part to a mimePart
     * object (or creates one), and returns it during
     * the build process.
     *
     * @param mixed &$obj The object to add the part to, or
     *                    null if a new object is to be created
     *
     * @return object     The multipart/mixed mimePart object
     * @access private
     */
    function &_addRelatedPart(&$obj)
    {
        $params['content_type'] = 'multipart/related';
        $params['eol']          = $this->_build_params['eol'];

        if (is_object($obj)) {
            return $obj->addSubpart('', $params);
        } else {
            $ret = new Mail_mimePart('', $params);
            return $ret;
        }
    }

    /**
     * Adds an html image subpart to a mimePart object
     * and returns it during the build process.
     *
     * @param object &$obj  The mimePart to add the image to
     * @param array  $value The image information
     *
     * @return object       The image mimePart object
     * @access private
     */
    function &_addHtmlImagePart(&$obj, $value)
    {
        $params['content_type'] = $value['c_type'];
        $params['encoding']     = 'base64';
        $params['disposition']  = 'inline';
        $params['dfilename']    = $value['name'];
        $params['cid']          = $value['cid'];
        $params['body_file']    = $value['body_file'];
        $params['eol']          = $this->_build_params['eol'];

        if (!empty($value['name_encoding'])) {
            $params['name_encoding'] = $value['name_encoding'];
        }
        if (!empty($value['filename_encoding'])) {
            $params['filename_encoding'] = $value['filename_encoding'];
        }

        $ret = $obj->addSubpart($value['body'], $params);
        return $ret;
    }

    /**
     * Adds an attachment subpart to a mimePart object
     * and returns it during the build process.
     *
     * @param object &$obj  The mimePart to add the image to
     * @param array  $value The attachment information
     *
     * @return object       The image mimePart object
     * @access private
     */
    function &_addAttachmentPart(&$obj, $value)
    {
        $params['eol']          = $this->_build_params['eol'];
        $params['dfilename']    = $value['name'];
        $params['encoding']     = $value['encoding'];
        $params['content_type'] = $value['c_type'];
        $params['body_file']    = $value['body_file'];
        $params['disposition']  = isset($value['disposition']) ? 
                                  $value['disposition'] : 'attachment';
        if ($value['charset']) {
            $params['charset'] = $value['charset'];
        }
        if ($value['language']) {
            $params['language'] = $value['language'];
        }
        if ($value['location']) {
            $params['location'] = $value['location'];
        }
        if (!empty($value['name_encoding'])) {
            $params['name_encoding'] = $value['name_encoding'];
        }
        if (!empty($value['filename_encoding'])) {
            $params['filename_encoding'] = $value['filename_encoding'];
        }
        if (!empty($value['description'])) {
            $params['description'] = $value['description'];
        }

        $ret = $obj->addSubpart($value['body'], $params);
        return $ret;
    }

    /**
     * Returns the complete e-mail, ready to send using an alternative
     * mail delivery method. Note that only the mailpart that is made
     * with Mail_Mime is created. This means that,
     * YOU WILL HAVE NO TO: HEADERS UNLESS YOU SET IT YOURSELF 
     * using the $headers parameter!
     * 
     * @param string $separation The separation between these two parts.
     * @param array  $params     The Build parameters passed to the
     *                           &get() function. See &get for more info.
     * @param array  $headers    The extra headers that should be passed
     *                           to the &headers() function.
     *                           See that function for more info.
     * @param bool   $overwrite  Overwrite the existing headers with new.
     *
     * @return mixed The complete e-mail or PEAR error object
     * @access public
     */
    function getMessage($separation = null, $params = null, $headers = null,
        $overwrite = false
    ) {
        if ($separation === null) {
            $separation = $this->_build_params['eol'];
        }

        $body = $this->get($params);

        if (PEAR::isError($body)) {
            return $body;
        }

        $head = $this->txtHeaders($headers, $overwrite);
        $mail = $head . $separation . $body;
        return $mail;
    }

    /**
     * Returns the complete e-mail body, ready to send using an alternative
     * mail delivery method.
     * 
     * @param array $params The Build parameters passed to the
     *                      &get() function. See &get for more info.
     *
     * @return mixed The e-mail body or PEAR error object
     * @access public
     * @since 1.6.0
     */
    function getMessageBody($params = null)
    {
        return $this->get($params, null, true);
    }

    /**
     * Writes (appends) the complete e-mail into file.
     * 
     * @param string $filename  Output file location
     * @param array  $params    The Build parameters passed to the
     *                          &get() function. See &get for more info.
     * @param array  $headers   The extra headers that should be passed
     *                          to the &headers() function.
     *                          See that function for more info.
     * @param bool   $overwrite Overwrite the existing headers with new.
     *
     * @return mixed True or PEAR error object
     * @access public
     * @since 1.6.0
     */
    function saveMessage($filename, $params = null, $headers = null, $overwrite = false)
    {
        // Check state of file and raise an error properly
        if (file_exists($filename) && !is_writable($filename)) {
            $err = PEAR::raiseError('File is not writable: ' . $filename);
            return $err;
        }

        // Temporarily reset magic_quotes_runtime and read file contents
        if ($magic_quote_setting = get_magic_quotes_runtime()) {
            @ini_set('magic_quotes_runtime', 0);
        }

        if (!($fh = fopen($filename, 'ab'))) {
            $err = PEAR::raiseError('Unable to open file: ' . $filename);
            return $err;
        }

        // Write message headers into file (skipping Content-* headers)
        $head = $this->txtHeaders($headers, $overwrite, true);
        if (fwrite($fh, $head) === false) {
            $err = PEAR::raiseError('Error writing to file: ' . $filename);
            return $err;
        }

        fclose($fh);

        if ($magic_quote_setting) {
            @ini_set('magic_quotes_runtime', $magic_quote_setting);
        }

        // Write the rest of the message into file
        $res = $this->get($params, $filename);

        return $res ? $res : true;
    }

    /**
     * Writes (appends) the complete e-mail body into file.
     * 
     * @param string $filename Output file location
     * @param array  $params   The Build parameters passed to the
     *                         &get() function. See &get for more info.
     *
     * @return mixed True or PEAR error object
     * @access public
     * @since 1.6.0
     */
    function saveMessageBody($filename, $params = null)
    {
        // Check state of file and raise an error properly
        if (file_exists($filename) && !is_writable($filename)) {
            $err = PEAR::raiseError('File is not writable: ' . $filename);
            return $err;
        }

        // Temporarily reset magic_quotes_runtime and read file contents
        if ($magic_quote_setting = get_magic_quotes_runtime()) {
            @ini_set('magic_quotes_runtime', 0);
        }

        if (!($fh = fopen($filename, 'ab'))) {
            $err = PEAR::raiseError('Unable to open file: ' . $filename);
            return $err;
        }

        // Write the rest of the message into file
        $res = $this->get($params, $filename, true);

        return $res ? $res : true;
    }

    /**
     * Builds the multipart message from the list ($this->_parts) and
     * returns the mime content.
     *
     * @param array    $params    Build parameters that change the way the email
     *                            is built. Should be associative. See $_build_params.
     * @param resource $filename  Output file where to save the message instead of
     *                            returning it
     * @param boolean  $skip_head True if you want to return/save only the message
     *                            without headers
     *
     * @return mixed The MIME message content string, null or PEAR error object
     * @access public
     */
    function &get($params = null, $filename = null, $skip_head = false)
    {
        if (isset($params)) {
            while (list($key, $value) = each($params)) {
                $this->_build_params[$key] = $value;
            }
        }

        if (isset($this->_headers['From'])) {
            // Bug #11381: Illegal characters in domain ID
            if (preg_match("|(@[0-9a-zA-Z\-\.]+)|", $this->_headers['From'], $matches)) {
                $domainID = $matches[1];
            } else {
                $domainID = "@localhost";
            }
            foreach ($this->_html_images as $i => $img) {
                $this->_html_images[$i]['cid']
                    = $this->_html_images[$i]['cid'] . $domainID;
            }
        }

        if (count($this->_html_images) && isset($this->_htmlbody)) {
            foreach ($this->_html_images as $key => $value) {
                $regex   = array();
                $regex[] = '#(\s)((?i)src|background|href(?-i))\s*=\s*(["\']?)' .
                            preg_quote($value['name'], '#') . '\3#';
                $regex[] = '#(?i)url(?-i)\(\s*(["\']?)' .
                            preg_quote($value['name'], '#') . '\1\s*\)#';

                $rep   = array();
                $rep[] = '\1\2=\3cid:' . $value['cid'] .'\3';
                $rep[] = 'url(\1cid:' . $value['cid'] . '\1)';

                $this->_htmlbody = preg_replace($regex, $rep, $this->_htmlbody);
                $this->_html_images[$key]['name']
                    = $this->_basename($this->_html_images[$key]['name']);
            }
        }

        $this->_checkParams();

        $null        = null;
        $attachments = count($this->_parts)                 ? true : false;
        $html_images = count($this->_html_images)           ? true : false;
        $html        = strlen($this->_htmlbody)             ? true : false;
        $text        = (!$html && strlen($this->_txtbody))  ? true : false;

        switch (true) {
        case $text && !$attachments:
            $message =& $this->_addTextPart($null, $this->_txtbody);
            break;

        case !$text && !$html && $attachments:
            $message =& $this->_addMixedPart();
            for ($i = 0; $i < count($this->_parts); $i++) {
                $this->_addAttachmentPart($message, $this->_parts[$i]);
            }
            break;

        case $text && $attachments:
            $message =& $this->_addMixedPart();
            $this->_addTextPart($message, $this->_txtbody);
            for ($i = 0; $i < count($this->_parts); $i++) {
                $this->_addAttachmentPart($message, $this->_parts[$i]);
            }
            break;

        case $html && !$attachments && !$html_images:
            if (isset($this->_txtbody)) {
                $message =& $this->_addAlternativePart($null);
                $this->_addTextPart($message, $this->_txtbody);
                $this->_addHtmlPart($message);
            } else {
                $message =& $this->_addHtmlPart($null);
            }
            break;

        case $html && !$attachments && $html_images:
            // * Content-Type: multipart/alternative;
            //    * text
            //    * Content-Type: multipart/related;
            //       * html
            //       * image...
            if (isset($this->_txtbody)) {
                $message =& $this->_addAlternativePart($null);
                $this->_addTextPart($message, $this->_txtbody);

                $ht =& $this->_addRelatedPart($message);
                $this->_addHtmlPart($ht);
                for ($i = 0; $i < count($this->_html_images); $i++) {
                    $this->_addHtmlImagePart($ht, $this->_html_images[$i]);
                }
            } else {
                // * Content-Type: multipart/related;
                //    * html
                //    * image...
                $message =& $this->_addRelatedPart($null);
                $this->_addHtmlPart($message);
                for ($i = 0; $i < count($this->_html_images); $i++) {
                    $this->_addHtmlImagePart($message, $this->_html_images[$i]);
                }
            }
            /*
            // #13444, #9725: the code below was a non-RFC compliant hack
            // * Content-Type: multipart/related;
            //    * Content-Type: multipart/alternative;
            //        * text
            //        * html
            //    * image...
            $message =& $this->_addRelatedPart($null);
            if (isset($this->_txtbody)) {
                $alt =& $this->_addAlternativePart($message);
                $this->_addTextPart($alt, $this->_txtbody);
                $this->_addHtmlPart($alt);
            } else {
                $this->_addHtmlPart($message);
            }
            for ($i = 0; $i < count($this->_html_images); $i++) {
                $this->_addHtmlImagePart($message, $this->_html_images[$i]);
            }
            */
            break;

        case $html && $attachments && !$html_images:
            $message =& $this->_addMixedPart();
            if (isset($this->_txtbody)) {
                $alt =& $this->_addAlternativePart($message);
                $this->_addTextPart($alt, $this->_txtbody);
                $this->_addHtmlPart($alt);
            } else {
                $this->_addHtmlPart($message);
            }
            for ($i = 0; $i < count($this->_parts); $i++) {
                $this->_addAttachmentPart($message, $this->_parts[$i]);
            }
            break;

        case $html && $attachments && $html_images:
            $message =& $this->_addMixedPart();
            if (isset($this->_txtbody)) {
                $alt =& $this->_addAlternativePart($message);
                $this->_addTextPart($alt, $this->_txtbody);
                $rel =& $this->_addRelatedPart($alt);
            } else {
                $rel =& $this->_addRelatedPart($message);
            }
            $this->_addHtmlPart($rel);
            for ($i = 0; $i < count($this->_html_images); $i++) {
                $this->_addHtmlImagePart($rel, $this->_html_images[$i]);
            }
            for ($i = 0; $i < count($this->_parts); $i++) {
                $this->_addAttachmentPart($message, $this->_parts[$i]);
            }
            break;

        }

        if (!isset($message)) {
            $ret = null;
            return $ret;
        }
        
        // Use saved boundary
        if (!empty($this->_build_params['boundary'])) {
            $boundary = $this->_build_params['boundary'];
        } else {
            $boundary = null;
        }

        // Write output to file
        if ($filename) {
            // Append mimePart message headers and body into file
            $headers = $message->encodeToFile($filename, $boundary, $skip_head);
            if (PEAR::isError($headers)) {
                return $headers;
            }
            $this->_headers = array_merge($this->_headers, $headers);
            $ret = null;
            return $ret;
        } else {
            $output = $message->encode($boundary, $skip_head);
            if (PEAR::isError($output)) {
                return $output;
            }
            $this->_headers = array_merge($this->_headers, $output['headers']);
            $body = $output['body'];
            return $body;
        }
    }

    /**
     * Returns an array with the headers needed to prepend to the email
     * (MIME-Version and Content-Type). Format of argument is:
     * $array['header-name'] = 'header-value';
     *
     * @param array $xtra_headers Assoc array with any extra headers (optional)
     * @param bool  $overwrite    Overwrite already existing headers.
     * @param bool  $skip_content Don't return content headers: Content-Type,
     *                            Content-Disposition and Content-Transfer-Encoding
     * 
     * @return array              Assoc array with the mime headers
     * @access public
     */
    function &headers($xtra_headers = null, $overwrite = false, $skip_content = false)
    {
        // Add mime version header
        $headers['MIME-Version'] = '1.0';

        // Content-Type and Content-Transfer-Encoding headers should already
        // be present if get() was called, but we'll re-set them to make sure
        // we got them when called before get() or something in the message
        // has been changed after get() [#14780]
        if (!$skip_content) {
            $headers += $this->_contentHeaders();
        }

        if (!empty($xtra_headers)) {
            $headers = array_merge($headers, $xtra_headers);
        }

        if ($overwrite) {
            $this->_headers = array_merge($this->_headers, $headers);
        } else {
            $this->_headers = array_merge($headers, $this->_headers);
        }

        $headers = $this->_headers;

        if ($skip_content) {
            unset($headers['Content-Type']);
            unset($headers['Content-Transfer-Encoding']);
            unset($headers['Content-Disposition']);
        }

        $encodedHeaders = $this->_encodeHeaders($headers);
        return $encodedHeaders;
    }

    /**
     * Get the text version of the headers
     * (usefull if you want to use the PHP mail() function)
     *
     * @param array $xtra_headers Assoc array with any extra headers (optional)
     * @param bool  $overwrite    Overwrite the existing headers with new.
     * @param bool  $skip_content Don't return content headers: Content-Type,
     *                            Content-Disposition and Content-Transfer-Encoding
     *
     * @return string             Plain text headers
     * @access public
     */
    function txtHeaders($xtra_headers = null, $overwrite = false, $skip_content = false)
    {
        $headers = $this->headers($xtra_headers, $overwrite, $skip_content);

        // Place Received: headers at the beginning of the message
        // Spam detectors often flag messages with it after the Subject: as spam
        if (isset($headers['Received'])) {
            $received = $headers['Received'];
            unset($headers['Received']);
            $headers = array('Received' => $received) + $headers;
        }

        $ret = '';
        $eol = $this->_build_params['eol'];

        foreach ($headers as $key => $val) {
            if (is_array($val)) {
                foreach ($val as $value) {
                    $ret .= "$key: $value" . $eol;
                }
            } else {
                $ret .= "$key: $val" . $eol;
            }
        }

        return $ret;
    }

    /**
     * Sets the Subject header
     *
     * @param string $subject String to set the subject to.
     *
     * @return void
     * @access public
     */
    function setSubject($subject)
    {
        $this->_headers['Subject'] = $subject;
    }

    /**
     * Set an email to the From (the sender) header
     *
     * @param string $email The email address to use
     *
     * @return void
     * @access public
     */
    function setFrom($email)
    {
        $this->_headers['From'] = $email;
    }

    /**
     * Add an email to the Cc (carbon copy) header
     * (multiple calls to this method are allowed)
     *
     * @param string $email The email direction to add
     *
     * @return void
     * @access public
     */
    function addCc($email)
    {
        if (isset($this->_headers['Cc'])) {
            $this->_headers['Cc'] .= ", $email";
        } else {
            $this->_headers['Cc'] = $email;
        }
    }

    /**
     * Add an email to the Bcc (blank carbon copy) header
     * (multiple calls to this method are allowed)
     *
     * @param string $email The email direction to add
     *
     * @return void
     * @access public
     */
    function addBcc($email)
    {
        if (isset($this->_headers['Bcc'])) {
            $this->_headers['Bcc'] .= ", $email";
        } else {
            $this->_headers['Bcc'] = $email;
        }
    }

    /**
     * Since the PHP send function requires you to specify
     * recipients (To: header) separately from the other
     * headers, the To: header is not properly encoded.
     * To fix this, you can use this public method to 
     * encode your recipients before sending to the send
     * function
     *
     * @param string $recipients A comma-delimited list of recipients
     *
     * @return string            Encoded data
     * @access public
     */
    function encodeRecipients($recipients)
    {
        $input = array("To" => $recipients);
        $retval = $this->_encodeHeaders($input);
        return $retval["To"] ;
    }

    /**
     * Encodes headers as per RFC2047
     *
     * @param array $input  The header data to encode
     * @param array $params Extra build parameters
     *
     * @return array        Encoded data
     * @access private
     */
    function _encodeHeaders($input, $params = array())
    {
        $build_params = $this->_build_params;
        while (list($key, $value) = each($params)) {
            $build_params[$key] = $value;
        }

        foreach ($input as $hdr_name => $hdr_value) {
            if (is_array($hdr_value)) {
                foreach ($hdr_value as $idx => $value) {
                    $input[$hdr_name][$idx] = $this->encodeHeader(
                        $hdr_name, $value,
                        $build_params['head_charset'], $build_params['head_encoding']
                    );
                }
            } else {
                $input[$hdr_name] = $this->encodeHeader(
                    $hdr_name, $hdr_value,
                    $build_params['head_charset'], $build_params['head_encoding']
                );
            }
        }

        return $input;
    }

    /**
     * Encodes a header as per RFC2047
     *
     * @param string $name     The header name
     * @param string $value    The header data to encode
     * @param string $charset  Character set name
     * @param string $encoding Encoding name (base64 or quoted-printable)
     *
     * @return string          Encoded header data (without a name)
     * @access public
     * @since 1.5.3
     */
    function encodeHeader($name, $value, $charset, $encoding)
    {
        return Mail_mimePart::encodeHeader(
            $name, $value, $charset, $encoding, $this->_build_params['eol']
        );
    }

    /**
     * Get file's basename (locale independent) 
     *
     * @param string $filename Filename
     *
     * @return string          Basename
     * @access private
     */
    function _basename($filename)
    {
        // basename() is not unicode safe and locale dependent
        if (stristr(PHP_OS, 'win') || stristr(PHP_OS, 'netware')) {
            return preg_replace('/^.*[\\\\\\/]/', '', $filename);
        } else {
            return preg_replace('/^.*[\/]/', '', $filename);
        }
    }

    /**
     * Get Content-Type and Content-Transfer-Encoding headers of the message
     *
     * @return array Headers array
     * @access private
     */
    function _contentHeaders()
    {
        $attachments = count($this->_parts)                 ? true : false;
        $html_images = count($this->_html_images)           ? true : false;
        $html        = strlen($this->_htmlbody)             ? true : false;
        $text        = (!$html && strlen($this->_txtbody))  ? true : false;
        $headers     = array();

        // See get()
        switch (true) {
        case $text && !$attachments:
            $headers['Content-Type'] = 'text/plain';
            break;

        case !$text && !$html && $attachments:
        case $text && $attachments:
        case $html && $attachments && !$html_images:
        case $html && $attachments && $html_images:
            $headers['Content-Type'] = 'multipart/mixed';
            break;

        case $html && !$attachments && !$html_images && isset($this->_txtbody):
        case $html && !$attachments && $html_images && isset($this->_txtbody):
            $headers['Content-Type'] = 'multipart/alternative';
            break;

        case $html && !$attachments && !$html_images && !isset($this->_txtbody):
            $headers['Content-Type'] = 'text/html';
            break;

        case $html && !$attachments && $html_images && !isset($this->_txtbody):
            $headers['Content-Type'] = 'multipart/related';
            break;

        default:
            return $headers;
        }

        $this->_checkParams();

        $eol = !empty($this->_build_params['eol'])
            ? $this->_build_params['eol'] : "\r\n";

        if ($headers['Content-Type'] == 'text/plain') {
            // single-part message: add charset and encoding
            $headers['Content-Type']
                .= ";$eol charset=" . $this->_build_params['text_charset'];
            $headers['Content-Transfer-Encoding']
                = $this->_build_params['text_encoding'];
        } else if ($headers['Content-Type'] == 'text/html') {
            // single-part message: add charset and encoding
            $headers['Content-Type']
                .= ";$eol charset=" . $this->_build_params['html_charset'];
            $headers['Content-Transfer-Encoding']
                = $this->_build_params['html_encoding'];
        } else {
            // multipart message: add charset and boundary
            if (!empty($this->_build_params['boundary'])) {
                $boundary = $this->_build_params['boundary'];
            } else if (!empty($this->_headers['Content-Type'])
                && preg_match('/boundary="([^"]+)"/', $this->_headers['Content-Type'], $m)
            ) {
                $boundary = $m[1];
            } else {
                $boundary = '=_' . md5(rand() . microtime());
            }

            $this->_build_params['boundary'] = $boundary;
            $headers['Content-Type'] .= ";$eol boundary=\"$boundary\"";
        }

        return $headers;
    }

    /**
     * Validate and set build parameters
     *
     * @return void
     * @access private
     */
    function _checkParams()
    {
        $encodings = array('7bit', '8bit', 'base64', 'quoted-printable');

        $this->_build_params['text_encoding']
            = strtolower($this->_build_params['text_encoding']);
        $this->_build_params['html_encoding']
            = strtolower($this->_build_params['html_encoding']);

        if (!in_array($this->_build_params['text_encoding'], $encodings)) {
            $this->_build_params['text_encoding'] = '7bit';
        }
        if (!in_array($this->_build_params['html_encoding'], $encodings)) {
            $this->_build_params['html_encoding'] = '7bit';
        }

        // text body
        if ($this->_build_params['text_encoding'] == '7bit'
            && !preg_match('/ascii/i', $this->_build_params['text_charset'])
            && preg_match('/[^\x00-\x7F]/', $this->_txtbody)
        ) {
            $this->_build_params['text_encoding'] = 'quoted-printable';
        }
        // html body
        if ($this->_build_params['html_encoding'] == '7bit'
            && !preg_match('/ascii/i', $this->_build_params['html_charset'])
            && preg_match('/[^\x00-\x7F]/', $this->_htmlbody)
        ) {
            $this->_build_params['html_encoding'] = 'quoted-printable';
        }
    }

} // End of class
/**
 * The Mail_mimePart class is used to create MIME E-mail messages
 *
 * This class enables you to manipulate and build a mime email
 * from the ground up. The Mail_Mime class is a userfriendly api
 * to this class for people who aren't interested in the internals
 * of mime mail.
 * This class however allows full control over the email.
 *
 * Compatible with PHP versions 4 and 5
 *
 * LICENSE: This LICENSE is in the BSD license style.
 * Copyright (c) 2002-2003, Richard Heyes <richard@phpguru.org>
 * Copyright (c) 2003-2006, PEAR <pear-group@php.net>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or
 * without modification, are permitted provided that the following
 * conditions are met:
 *
 * - Redistributions of source code must retain the above copyright
 *   notice, this list of conditions and the following disclaimer.
 * - Redistributions in binary form must reproduce the above copyright
 *   notice, this list of conditions and the following disclaimer in the
 *   documentation and/or other materials provided with the distribution.
 * - Neither the name of the authors, nor the names of its contributors 
 *   may be used to endorse or promote products derived from this 
 *   software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF
 * THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @category  Mail
 * @package   Mail_Mime
 * @author    Richard Heyes  <richard@phpguru.org>
 * @author    Cipriano Groenendal <cipri@php.net>
 * @author    Sean Coates <sean@php.net>
 * @author    Aleksander Machniak <alec@php.net>
 * @copyright 2003-2006 PEAR <pear-group@php.net>
 * @license   http://www.opensource.org/licenses/bsd-license.php BSD License
 * @version   CVS: $Id: mimePart.php,v 1.2 2010/03/19 05:37:27 Prasanth Exp $
 * @link      http://pear.php.net/package/Mail_mime
 */


/**
 * The Mail_mimePart class is used to create MIME E-mail messages
 *
 * This class enables you to manipulate and build a mime email
 * from the ground up. The Mail_Mime class is a userfriendly api
 * to this class for people who aren't interested in the internals
 * of mime mail.
 * This class however allows full control over the email.
 *
 * @category  Mail
 * @package   Mail_Mime
 * @author    Richard Heyes  <richard@phpguru.org>
 * @author    Cipriano Groenendal <cipri@php.net>
 * @author    Sean Coates <sean@php.net>
 * @author    Aleksander Machniak <alec@php.net>
 * @copyright 2003-2006 PEAR <pear-group@php.net>
 * @license   http://www.opensource.org/licenses/bsd-license.php BSD License
 * @version   Release: @package_version@
 * @link      http://pear.php.net/package/Mail_mime
 */
class Mail_mimePart
{
    /**
    * The encoding type of this part
    *
    * @var string
    * @access private
    */
    var $_encoding;

    /**
    * An array of subparts
    *
    * @var array
    * @access private
    */
    var $_subparts;

    /**
    * The output of this part after being built
    *
    * @var string
    * @access private
    */
    var $_encoded;

    /**
    * Headers for this part
    *
    * @var array
    * @access private
    */
    var $_headers;

    /**
    * The body of this part (not encoded)
    *
    * @var string
    * @access private
    */
    var $_body;

    /**
    * The location of file with body of this part (not encoded)
    *
    * @var string
    * @access private
    */
    var $_body_file;

    /**
    * The end-of-line sequence
    *
    * @var string
    * @access private
    */
    var $_eol = "\r\n";

    /**
    * Constructor.
    *
    * Sets up the object.
    *
    * @param string $body   The body of the mime part if any.
    * @param array  $params An associative array of optional parameters:
    *     content_type      - The content type for this part eg multipart/mixed
    *     encoding          - The encoding to use, 7bit, 8bit,
    *                         base64, or quoted-printable
    *     cid               - Content ID to apply
    *     disposition       - Content disposition, inline or attachment
    *     dfilename         - Filename parameter for content disposition
    *     description       - Content description
    *     charset           - Character set to use
    *     name_encoding     - Encoding for attachment name (Content-Type)
    *                         By default filenames are encoded using RFC2231
    *                         Here you can set RFC2047 encoding (quoted-printable
    *                         or base64) instead
    *     filename_encoding - Encoding for attachment filename (Content-Disposition)
    *                         See 'name_encoding'
    *     eol               - End of line sequence. Default: "\r\n"
    *     body_file         - Location of file with part's body (instead of $body)
    *
    * @access public
    */
    function Mail_mimePart($body = '', $params = array())
    {
        if (!empty($params['eol'])) {
            $this->_eol = $params['eol'];
        } else if (defined('MAIL_MIMEPART_CRLF')) { // backward-copat.
            $this->_eol = MAIL_MIMEPART_CRLF;
        }

        $c_type = array();
        $c_disp = array();
        foreach ($params as $key => $value) {
            switch ($key) {
            case 'content_type':
                $c_type['type'] = $value;
                break;

            case 'encoding':
                $this->_encoding = $value;
                $headers['Content-Transfer-Encoding'] = $value;
                break;

            case 'cid':
                $headers['Content-ID'] = '<' . $value . '>';
                break;

            case 'disposition':
                $c_disp['disp'] = $value;
                break;

            case 'dfilename':
                $c_disp['filename'] = $value;
                $c_type['name'] = $value;
                break;

            case 'description':
                $headers['Content-Description'] = $value;
                break;

            case 'charset':
                $c_type['charset'] = $value;
                $c_disp['charset'] = $value;
                break;

            case 'language':
                $c_type['language'] = $value;
                $c_disp['language'] = $value;
                break;

            case 'location':
                $headers['Content-Location'] = $value;
                break;

            case 'body_file':
                $this->_body_file = $value;
                break;
            }
        }

        // Content-Type
        if (isset($c_type['type'])) {
            $headers['Content-Type'] = $c_type['type'];
            if (isset($c_type['name'])) {
                $headers['Content-Type'] .= ';' . $this->_eol;
                $headers['Content-Type'] .= $this->_buildHeaderParam(
                    'name', $c_type['name'], 
                    isset($c_type['charset']) ? $c_type['charset'] : 'US-ASCII', 
                    isset($c_type['language']) ? $c_type['language'] : null,
                    isset($params['name_encoding']) ?  $params['name_encoding'] : null
                );
            }
            if (isset($c_type['charset'])) {
                $headers['Content-Type']
                    .= ';' . $this->_eol . " charset={$c_type['charset']}";
            }
        }

        // Content-Disposition
        if (isset($c_disp['disp'])) {
            $headers['Content-Disposition'] = $c_disp['disp'];
            if (isset($c_disp['filename'])) {
                $headers['Content-Disposition'] .= ';' . $this->_eol;
                $headers['Content-Disposition'] .= $this->_buildHeaderParam(
                    'filename', $c_disp['filename'], 
                    isset($c_disp['charset']) ? $c_disp['charset'] : 'US-ASCII', 
                    isset($c_disp['language']) ? $c_disp['language'] : null,
                    isset($params['filename_encoding']) ?  $params['filename_encoding'] : null
                );
            }
        }

        if (!empty($headers['Content-Description'])) {
            $headers['Content-Description'] = $this->encodeHeader(
                'Content-Description', $headers['Content-Description'],
                isset($c_type['charset']) ? $c_type['charset'] : 'US-ASCII',
                isset($params['name_encoding']) ?  $params['name_encoding'] : 'quoted-printable',
                $this->_eol
            );
        }

        // Default content-type
        if (!isset($headers['Content-Type'])) {
            $headers['Content-Type'] = 'text/plain';
        }

        // Default encoding
        if (!isset($this->_encoding)) {
            $this->_encoding = '7bit';
        }

        // Assign stuff to member variables
        $this->_encoded  = array();
        $this->_headers  = $headers;
        $this->_body     = $body;
    }

    /**
     * Encodes and returns the email. Also stores
     * it in the encoded member variable
     *
     * @param string $boundary Pre-defined boundary string
     *
     * @return An associative array containing two elements,
     *         body and headers. The headers element is itself
     *         an indexed array. On error returns PEAR error object.
     * @access public
     */
    function encode($boundary=null)
    {
        $encoded =& $this->_encoded;

        if (count($this->_subparts)) {
            $boundary = $boundary ? $boundary : '=_' . md5(rand() . microtime());
            $eol = $this->_eol;

            $this->_headers['Content-Type'] .= ";$eol boundary=\"$boundary\"";

            $encoded['body'] = ''; 

            for ($i = 0; $i < count($this->_subparts); $i++) {
                $encoded['body'] .= '--' . $boundary . $eol;
                $tmp = $this->_subparts[$i]->encode();
                if (PEAR::isError($tmp)) {
                    return $tmp;
                }
                foreach ($tmp['headers'] as $key => $value) {
                    $encoded['body'] .= $key . ': ' . $value . $eol;
                }
                $encoded['body'] .= $eol . $tmp['body'] . $eol;
            }

            $encoded['body'] .= '--' . $boundary . '--' . $eol;

        } else if ($this->_body) {
            $encoded['body'] = $this->_getEncodedData($this->_body, $this->_encoding);
        } else if ($this->_body_file) {
            // Temporarily reset magic_quotes_runtime for file reads and writes
            if ($magic_quote_setting = get_magic_quotes_runtime()) {
                @ini_set('magic_quotes_runtime', 0);
            }
            $body = $this->_getEncodedDataFromFile($this->_body_file, $this->_encoding);
            if ($magic_quote_setting) {
                @ini_set('magic_quotes_runtime', $magic_quote_setting);
            }

            if (PEAR::isError($body)) {
                return $body;
            }
            $encoded['body'] = $body;
        } else {
            $encoded['body'] = '';
        }

        // Add headers to $encoded
        $encoded['headers'] =& $this->_headers;

        return $encoded;
    }

    /**
     * Encodes and saves the email into file. File must exist.
     * Data will be appended to the file.
     *
     * @param string  $filename  Output file location
     * @param string  $boundary  Pre-defined boundary string
     * @param boolean $skip_head True if you don't want to save headers
     *
     * @return array An associative array containing message headers
     *               or PEAR error object
     * @access public
     * @since 1.6.0
     */
    function encodeToFile($filename, $boundary=null, $skip_head=false)
    {
        if (file_exists($filename) && !is_writable($filename)) {
            $err = PEAR::raiseError('File is not writeable: ' . $filename);
            return $err;
        }

        if (!($fh = fopen($filename, 'ab'))) {
            $err = PEAR::raiseError('Unable to open file: ' . $filename);
            return $err;
        }

        // Temporarily reset magic_quotes_runtime for file reads and writes
        if ($magic_quote_setting = get_magic_quotes_runtime()) {
            @ini_set('magic_quotes_runtime', 0);
        }

        $res = $this->_encodePartToFile($fh, $boundary, $skip_head);

        fclose($fh);

        if ($magic_quote_setting) {
            @ini_set('magic_quotes_runtime', $magic_quote_setting);
        }

        return PEAR::isError($res) ? $res : $this->_headers;
    }

    /**
     * Encodes given email part into file
     *
     * @param string  $fh        Output file handle
     * @param string  $boundary  Pre-defined boundary string
     * @param boolean $skip_head True if you don't want to save headers
     *
     * @return array True on sucess or PEAR error object
     * @access private
     */
    function _encodePartToFile($fh, $boundary=null, $skip_head=false)
    {
        $eol = $this->_eol;

        if (count($this->_subparts)) {
            $boundary = $boundary ? $boundary : '=_' . md5(rand() . microtime());
            $this->_headers['Content-Type'] .= ";$eol boundary=\"$boundary\"";
        }

        if (!$skip_head) {
            foreach ($this->_headers as $key => $value) {
                fwrite($fh, $key . ': ' . $value . $eol);
            }
            $f_eol = $eol;
        } else {
            $f_eol = '';
        }

        if (count($this->_subparts)) {
            for ($i = 0; $i < count($this->_subparts); $i++) {
                fwrite($fh, $f_eol . '--' . $boundary . $eol);
                $res = $this->_subparts[$i]->_encodePartToFile($fh);
                if (PEAR::isError($res)) {
                    return $res;
                }
                $f_eol = $eol;
            }

            fwrite($fh, $eol . '--' . $boundary . '--' . $eol);

        } else if ($this->_body) {
            fwrite($fh, $f_eol . $this->_getEncodedData($this->_body, $this->_encoding));
        } else if ($this->_body_file) {
            fwrite($fh, $f_eol);
            $res = $this->_getEncodedDataFromFile(
                $this->_body_file, $this->_encoding, $fh
            );
            if (PEAR::isError($res)) {
                return $res;
            }
        }

        return true;
    }

    /**
     * Adds a subpart to current mime part and returns
     * a reference to it
     *
     * @param string $body   The body of the subpart, if any.
     * @param array  $params The parameters for the subpart, same
     *                       as the $params argument for constructor.
     *
     * @return Mail_mimePart A reference to the part you just added. It is
     *                       crucial if using multipart/* in your subparts that
     *                       you use =& in your script when calling this function,
     *                       otherwise you will not be able to add further subparts.
     * @access public
     */
    function &addSubpart($body, $params)
    {
        $this->_subparts[] = new Mail_mimePart($body, $params);
        return $this->_subparts[count($this->_subparts) - 1];
    }

    /**
     * Returns encoded data based upon encoding passed to it
     *
     * @param string $data     The data to encode.
     * @param string $encoding The encoding type to use, 7bit, base64,
     *                         or quoted-printable.
     *
     * @return string
     * @access private
     */
    function _getEncodedData($data, $encoding)
    {
        switch ($encoding) {
        case 'quoted-printable':
            return $this->_quotedPrintableEncode($data);
            break;

        case 'base64':
            return rtrim(chunk_split(base64_encode($data), 76, $this->_eol));
            break;

        case '8bit':
        case '7bit':
        default:
            return $data;
        }
    }

    /**
     * Returns encoded data based upon encoding passed to it
     *
     * @param string   $filename Data file location
     * @param string   $encoding The encoding type to use, 7bit, base64,
     *                           or quoted-printable.
     * @param resource $fh       Output file handle. If set, data will be
     *                           stored into it instead of returning it
     *
     * @return string Encoded data or PEAR error object
     * @access private
     */
    function _getEncodedDataFromFile($filename, $encoding, $fh=null)
    {
        if (!is_readable($filename)) {
            $err = PEAR::raiseError('Unable to read file: ' . $filename);
            return $err;
        }

        if (!($fd = fopen($filename, 'rb'))) {
            $err = PEAR::raiseError('Could not open file: ' . $filename);
            return $err;
        }

        $data = '';

        switch ($encoding) {
        case 'quoted-printable':
            while (!feof($fd)) {
                $buffer = $this->_quotedPrintableEncode(fgets($fd));
                if ($fh) {
                    fwrite($fh, $buffer);
                } else {
                    $data .= $buffer;
                }
            }
            break;

        case 'base64':
            while (!feof($fd)) {
                // Should read in a multiple of 57 bytes so that
                // the output is 76 bytes per line. Don't use big chunks
                // because base64 encoding is memory expensive
                $buffer = fread($fd, 57 * 9198); // ca. 0.5 MB
                $buffer = base64_encode($buffer);
                $buffer = chunk_split($buffer, 76, $this->_eol);
                if (feof($fd)) {
                    $buffer = rtrim($buffer);
                }

                if ($fh) {
                    fwrite($fh, $buffer);
                } else {
                    $data .= $buffer;
                }
            }
            break;

        case '8bit':
        case '7bit':
        default:
            while (!feof($fd)) {
                $buffer = fread($fd, 1048576); // 1 MB
                if ($fh) {
                    fwrite($fh, $buffer);
                } else {
                    $data .= $buffer;
                }
            }
        }

        fclose($fd);

        if (!$fh) {
            return $data;
        }
    }

    /**
     * Encodes data to quoted-printable standard.
     *
     * @param string $input    The data to encode
     * @param int    $line_max Optional max line length. Should
     *                         not be more than 76 chars
     *
     * @return string Encoded data
     *
     * @access private
     */
    function _quotedPrintableEncode($input , $line_max = 76)
    {
        $eol = $this->_eol;
        /*
        // imap_8bit() is extremely fast, but doesn't handle properly some characters
        if (function_exists('imap_8bit') && $line_max == 76) {
            $input = preg_replace('/\r?\n/', "\r\n", $input);
            $input = imap_8bit($input);
            if ($eol != "\r\n") {
                $input = str_replace("\r\n", $eol, $input);
            }
            return $input;
        }
        */
        $lines  = preg_split("/\r?\n/", $input);
        $escape = '=';
        $output = '';

        while (list($idx, $line) = each($lines)) {
            $newline = '';
            $i = 0;

            while (isset($line[$i])) {
                $char = $line[$i];
                $dec  = ord($char);
                $i++;

                if (($dec == 32) && (!isset($line[$i]))) {
                    // convert space at eol only
                    $char = '=20';
                } elseif ($dec == 9 && isset($line[$i])) {
                    ; // Do nothing if a TAB is not on eol
                } elseif (($dec == 61) || ($dec < 32) || ($dec > 126)) {
                    $char = $escape . sprintf('%02X', $dec);
                } elseif (($dec == 46) && (($newline == '')
                    || ((strlen($newline) + strlen("=2E")) >= $line_max))
                ) {
                    // Bug #9722: convert full-stop at bol,
                    // some Windows servers need this, won't break anything (cipri)
                    // Bug #11731: full-stop at bol also needs to be encoded
                    // if this line would push us over the line_max limit.
                    $char = '=2E';
                }

                // Note, when changing this line, also change the ($dec == 46)
                // check line, as it mimics this line due to Bug #11731
                // EOL is not counted
                if ((strlen($newline) + strlen($char)) >= $line_max) {
                    // soft line break; " =\r\n" is okay
                    $output  .= $newline . $escape . $eol;
                    $newline  = '';
                }
                $newline .= $char;
            } // end of for
            $output .= $newline . $eol;
            unset($lines[$idx]);
        }
        // Don't want last crlf
        $output = substr($output, 0, -1 * strlen($eol));
        return $output;
    }

    /**
     * Encodes the paramater of a header.
     *
     * @param string $name      The name of the header-parameter
     * @param string $value     The value of the paramter
     * @param string $charset   The characterset of $value
     * @param string $language  The language used in $value
     * @param string $encoding  Parameter encoding. If not set, parameter value
     *                          is encoded according to RFC2231
     * @param int    $maxLength The maximum length of a line. Defauls to 75
     *
     * @return string
     *
     * @access private
     */
    function _buildHeaderParam($name, $value, $charset=null, $language=null,
        $encoding=null, $maxLength=75
    ) {
        // RFC 2045:
        // value needs encoding if contains non-ASCII chars or is longer than 78 chars
        if (!preg_match('#[^\x20-\x7E]#', $value)) {
            $token_regexp = '#([^\x21,\x23-\x27,\x2A,\x2B,\x2D'
                . ',\x2E,\x30-\x39,\x41-\x5A,\x5E-\x7E])#';
            if (!preg_match($token_regexp, $value)) {
                // token
                if (strlen($name) + strlen($value) + 3 <= $maxLength) {
                    return " {$name}={$value}";
                }
            } else {
                // quoted-string
                $quoted = addcslashes($value, '\\"');
                if (strlen($name) + strlen($quoted) + 5 <= $maxLength) {
                    return " {$name}=\"{$quoted}\"";
                }
            }
        }

        // RFC2047: use quoted-printable/base64 encoding
        if ($encoding == 'quoted-printable' || $encoding == 'base64') {
            return $this->_buildRFC2047Param($name, $value, $charset, $encoding);
        }

        // RFC2231:
        $encValue = preg_replace_callback(
            '/([^\x21,\x23,\x24,\x26,\x2B,\x2D,\x2E,\x30-\x39,\x41-\x5A,\x5E-\x7E])/',
            array($this, '_encodeReplaceCallback'), $value
        );
        $value = "$charset'$language'$encValue";

        $header = " {$name}*={$value}";
        if (strlen($header) <= $maxLength) {
            return $header;
        }

        $preLength = strlen(" {$name}*0*=");
        $maxLength = max(16, $maxLength - $preLength - 3);
        $maxLengthReg = "|(.{0,$maxLength}[^\%][^\%])|";

        $headers = array();
        $headCount = 0;
        while ($value) {
            $matches = array();
            $found = preg_match($maxLengthReg, $value, $matches);
            if ($found) {
                $headers[] = " {$name}*{$headCount}*={$matches[0]}";
                $value = substr($value, strlen($matches[0]));
            } else {
                $headers[] = " {$name}*{$headCount}*={$value}";
                $value = '';
            }
            $headCount++;
        }

        $headers = implode(';' . $this->_eol, $headers);
        return $headers;
    }

    /**
     * Encodes header parameter as per RFC2047 if needed
     *
     * @param string $name      The parameter name
     * @param string $value     The parameter value
     * @param string $charset   The parameter charset
     * @param string $encoding  Encoding type (quoted-printable or base64)
     * @param int    $maxLength Encoded parameter max length. Default: 76
     *
     * @return string Parameter line
     * @access private
     */
    function _buildRFC2047Param($name, $value, $charset,
        $encoding='quoted-printable', $maxLength=76
    ) {
        // WARNING: RFC 2047 says: "An 'encoded-word' MUST NOT be used in
        // parameter of a MIME Content-Type or Content-Disposition field",
        // but... it's supported by many clients/servers
        $quoted = '';

        if ($encoding == 'base64') {
            $value = base64_encode($value);
            $prefix = '=?' . $charset . '?B?';
            $suffix = '?=';

            // 2 x SPACE, 2 x '"', '=', ';'
            $add_len = strlen($prefix . $suffix) + strlen($name) + 6;
            $len = $add_len + strlen($value);

            while ($len > $maxLength) { 
                // We can cut base64-encoded string every 4 characters
                $real_len = floor(($maxLength - $add_len) / 4) * 4;
                $_quote = substr($value, 0, $real_len);
                $value = substr($value, $real_len);

                $quoted .= $prefix . $_quote . $suffix . $this->_eol . ' ';
                $add_len = strlen($prefix . $suffix) + 4; // 2 x SPACE, '"', ';'
                $len = strlen($value) + $add_len;
            }
            $quoted .= $prefix . $value . $suffix;

        } else {
            // quoted-printable
            $value = $this->encodeQP($value);
            $prefix = '=?' . $charset . '?Q?';
            $suffix = '?=';

            // 2 x SPACE, 2 x '"', '=', ';'
            $add_len = strlen($prefix . $suffix) + strlen($name) + 6;
            $len = $add_len + strlen($value);

            while ($len > $maxLength) {
                $length = $maxLength - $add_len;
                // don't break any encoded letters
                if (preg_match("/^(.{0,$length}[^\=][^\=])/", $value, $matches)) {
                    $_quote = $matches[1];
                }

                $quoted .= $prefix . $_quote . $suffix . $this->_eol . ' ';
                $value = substr($value, strlen($_quote));
                $add_len = strlen($prefix . $suffix) + 4; // 2 x SPACE, '"', ';'
                $len = strlen($value) + $add_len;
            }

            $quoted .= $prefix . $value . $suffix;
        }

        return " {$name}=\"{$quoted}\"";
    }

    /**
     * Encodes a header as per RFC2047
     *
     * @param string $name     The header name
     * @param string $value    The header data to encode
     * @param string $charset  Character set name
     * @param string $encoding Encoding name (base64 or quoted-printable)
     * @param string $eol      End-of-line sequence. Default: "\r\n"
     *
     * @return string          Encoded header data (without a name)
     * @access public
     * @since 1.6.1
     */
    function encodeHeader($name, $value, $charset='ISO-8859-1',
        $encoding='quoted-printable', $eol="\r\n"
    ) {
        // Structured headers
        $comma_headers = array(
            'from', 'to', 'cc', 'bcc', 'sender', 'reply-to',
            'resent-from', 'resent-to', 'resent-cc', 'resent-bcc',
            'resent-sender', 'resent-reply-to',
            'return-receipt-to', 'disposition-notification-to',
        );
        $other_headers = array(
            'references', 'in-reply-to', 'message-id', 'resent-message-id',
        );

        $name = strtolower($name);

        if (in_array($name, $comma_headers)) {
            $separator = ',';
        } else if (in_array($name, $other_headers)) {
            $separator = ' ';
        }

        if (!$charset) {
            $charset = 'ISO-8859-1';
        }

        // Structured header (make sure addr-spec inside is not encoded)
        if (!empty($separator)) {
            $parts = Mail_mimePart::_explodeQuotedString($separator, $value);
            $value = '';

            foreach ($parts as $part) {
                $part = preg_replace('/\r?\n[\s\t]*/', $eol . ' ', $part);
                $part = trim($part);

                if (!$part) {
                    continue;
                }
                if ($value) {
                    $value .= $separator==',' ? $separator.' ' : ' ';
                } else {
                    $value = $name . ': ';
                }

                // let's find phrase (name) and/or addr-spec
                if (preg_match('/^<\S+@\S+>$/', $part)) {
                    $value .= $part;
                } else if (preg_match('/^\S+@\S+$/', $part)) {
                    // address without brackets and without name
                    $value .= $part;
                } else if (preg_match('/<*\S+@\S+>*$/', $part, $matches)) {
                    // address with name (handle name)
                    $address = $matches[0];
                    $word = str_replace($address, '', $part);
                    $word = trim($word);
                    // check if phrase requires quoting
                    if ($word) {
                        // non-ASCII: require encoding
                        if (preg_match('#([\x80-\xFF]){1}#', $word)) {
                            if ($word[0] == '"' && $word[strlen($word)-1] == '"') {
                                // de-quote quoted-string, encoding changes
                                // string to atom
                                $search = array("\\\"", "\\\\");
                                $replace = array("\"", "\\");
                                $word = str_replace($search, $replace, $word);
                                $word = substr($word, 1, -1);
                            }
                            // find length of last line
                            if (($pos = strrpos($value, $eol)) !== false) {
                                $last_len = strlen($value) - $pos;
                            } else {
                                $last_len = strlen($value);
                            }
                            $word = Mail_mimePart::encodeHeaderValue(
                                $word, $charset, $encoding, $last_len, $eol
                            );
                        } else if (($word[0] != '"' || $word[strlen($word)-1] != '"')
                            && preg_match('/[\(\)\<\>\\\.\[\]@,;:"]/', $word)
                        ) {
                            // ASCII: quote string if needed
                            $word = '"'.addcslashes($word, '\\"').'"';
                        }
                    }
                    $value .= $word.' '.$address;
                } else {
                    // addr-spec not found, don't encode (?)
                    $value .= $part;
                }

                // RFC2822 recommends 78 characters limit, use 76 from RFC2047
                $value = wordwrap($value, 76, $eol . ' ');
            }

            // remove header name prefix (there could be EOL too)
            $value = preg_replace(
                '/^'.$name.':('.preg_quote($eol, '/').')* /', '', $value
            );

        } else {
            // Unstructured header
            // non-ASCII: require encoding
            if (preg_match('#([\x80-\xFF]){1}#', $value)) {
                if ($value[0] == '"' && $value[strlen($value)-1] == '"') {
                    // de-quote quoted-string, encoding changes
                    // string to atom
                    $search = array("\\\"", "\\\\");
                    $replace = array("\"", "\\");
                    $value = str_replace($search, $replace, $value);
                    $value = substr($value, 1, -1);
                }
                $value = Mail_mimePart::encodeHeaderValue(
                    $value, $charset, $encoding, strlen($name) + 2, $eol
                );
            } else if (strlen($name.': '.$value) > 78) {
                // ASCII: check if header line isn't too long and use folding
                $value = preg_replace('/\r?\n[\s\t]*/', $eol . ' ', $value);
                $tmp = wordwrap($name.': '.$value, 78, $eol . ' ');
                $value = preg_replace('/^'.$name.':\s*/', '', $tmp);
                // hard limit 998 (RFC2822)
                $value = wordwrap($value, 998, $eol . ' ', true);
            }
        }

        return $value;
    }

    /**
     * Explode quoted string
     *
     * @param string $delimiter Delimiter expression string for preg_match()
     * @param string $string    Input string
     *
     * @return array            String tokens array
     * @access private
     */
    function _explodeQuotedString($delimiter, $string)
    {
        $result = array();
        $strlen = strlen($string);

        for ($q=$p=$i=0; $i < $strlen; $i++) {
            if ($string[$i] == "\""
                && (empty($string[$i-1]) || $string[$i-1] != "\\")
            ) {
                $q = $q ? false : true;
            } else if (!$q && preg_match("/$delimiter/", $string[$i])) {
                $result[] = substr($string, $p, $i - $p);
                $p = $i + 1;
            }
        }

        $result[] = substr($string, $p);
        return $result;
    }

    /**
     * Encodes a header value as per RFC2047
     *
     * @param string $value      The header data to encode
     * @param string $charset    Character set name
     * @param string $encoding   Encoding name (base64 or quoted-printable)
     * @param int    $prefix_len Prefix length. Default: 0
     * @param string $eol        End-of-line sequence. Default: "\r\n"
     *
     * @return string            Encoded header data
     * @access public
     * @since 1.6.1
     */
    function encodeHeaderValue($value, $charset, $encoding, $prefix_len=0, $eol="\r\n")
    {
        if ($encoding == 'base64') {
            // Base64 encode the entire string
            $value = base64_encode($value);

            // Generate the header using the specified params and dynamicly 
            // determine the maximum length of such strings.
            // 75 is the value specified in the RFC.
            $prefix = '=?' . $charset . '?B?';
            $suffix = '?=';
            $maxLength = 75 - strlen($prefix . $suffix) - 2;
            $maxLength1stLine = $maxLength - $prefix_len;

            // We can cut base4 every 4 characters, so the real max
            // we can get must be rounded down.
            $maxLength = $maxLength - ($maxLength % 4);
            $maxLength1stLine = $maxLength1stLine - ($maxLength1stLine % 4);

            $cutpoint = $maxLength1stLine;
            $value_out = $value;
            $output = '';
            while ($value_out) {
                // Split translated string at every $maxLength
                $part = substr($value_out, 0, $cutpoint);
                $value_out = substr($value_out, $cutpoint);
                $cutpoint = $maxLength;
                // RFC 2047 specifies that any split header should
                // be seperated by a CRLF SPACE. 
                if ($output) {
                    $output .= $eol . ' ';
                }
                $output .= $prefix . $part . $suffix;
            }
            $value = $output;
        } else {
            // quoted-printable encoding has been selected
            $value = Mail_mimePart::encodeQP($value);

            // Generate the header using the specified params and dynamicly 
            // determine the maximum length of such strings.
            // 75 is the value specified in the RFC.
            $prefix = '=?' . $charset . '?Q?';
            $suffix = '?=';
            $maxLength = 75 - strlen($prefix . $suffix) - 3;
            $maxLength1stLine = $maxLength - $prefix_len;
            $maxLength = $maxLength - 1;

            // This regexp will break QP-encoded text at every $maxLength
            // but will not break any encoded letters.
            $reg1st = "|(.{0,$maxLength1stLine}[^\=][^\=])|";
            $reg2nd = "|(.{0,$maxLength}[^\=][^\=])|";

            $value_out = $value;
            $realMax = $maxLength1stLine + strlen($prefix . $suffix);
            if (strlen($value_out) >= $realMax) {
                // Begin with the regexp for the first line.
                $reg = $reg1st;
                $output = '';
                while ($value_out) {
                    // Split translated string at every $maxLength
                    // But make sure not to break any translated chars.
                    $found = preg_match($reg, $value_out, $matches);

                    // After this first line, we need to use a different
                    // regexp for the first line.
                    $reg = $reg2nd;

                    // Save the found part and encapsulate it in the
                    // prefix & suffix. Then remove the part from the
                    // $value_out variable.
                    if ($found) {
                        $part = $matches[0];
                        $len = strlen($matches[0]);
                        $value_out = substr($value_out, $len);
                    } else {
                        $part = $value_out;
                        $value_out = "";
                    }

                    // RFC 2047 specifies that any split header should 
                    // be seperated by a CRLF SPACE
                    if ($output) {
                        $output .= $eol . ' ';
                    }
                    $output .= $prefix . $part . $suffix;
                }
                $value_out = $output;
            } else {
                $value_out = $prefix . $value_out . $suffix;
            }
            $value = $value_out;
        }

        return $value;
    }

    /**
     * Encodes the given string using quoted-printable
     *
     * @param string $str String to encode
     *
     * @return string     Encoded string
     * @access public
     * @since 1.6.0
     */
    function encodeQP($str)
    {
        // Replace all special characters used by the encoder
        $search  = array('=',   '_',   '?',   ' ');
        $replace = array('=3D', '=5F', '=3F', '_');
        $str = str_replace($search, $replace, $str);

        // Replace all extended characters (\x80-xFF) with their
        // ASCII values.
        return preg_replace_callback(
            '/([\x80-\xFF])/', array('Mail_mimePart', '_qpReplaceCallback'), $str
        );
    }

    /**
     * Callback function to replace extended characters (\x80-xFF) with their
     * ASCII values (RFC2047: quoted-printable)
     *
     * @param array $matches Preg_replace's matches array
     *
     * @return string        Encoded character string
     * @access private
     */
    function _qpReplaceCallback($matches)
    {
        return sprintf('=%02X', ord($matches[1]));
    }

    /**
     * Callback function to replace extended characters (\x80-xFF) with their
     * ASCII values (RFC2231)
     *
     * @param array $matches Preg_replace's matches array
     *
     * @return string        Encoded character string
     * @access private
     */
    function _encodeReplaceCallback($matches)
    {
        return sprintf('%%%02X', ord($matches[1]));
    }

} // End of class
//
// +----------------------------------------------------------------------+
// | PHP Version 4                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2003 The PHP Group                                |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.02 of the PHP license,      |
// | that is bundled with this package in the file LICENSE, and is        |
// | available at through the world-wide-web at                           |
// | http://www.php.net/license/2_02.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Authors: Chuck Hagenbuch <chuck@horde.org>                           |
// |          Jon Parise <jon@php.net>                                    |
// +----------------------------------------------------------------------+

/** Error: Failed to create a Net_SMTP object */
define('PEAR_MAIL_SMTP_ERROR_CREATE', 10000);

/** Error: Failed to connect to SMTP server */
define('PEAR_MAIL_SMTP_ERROR_CONNECT', 10001);

/** Error: SMTP authentication failure */
define('PEAR_MAIL_SMTP_ERROR_AUTH', 10002);

/** Error: No From: address has been provided */
define('PEAR_MAIL_SMTP_ERROR_FROM', 10003);

/** Error: Failed to set sender */
define('PEAR_MAIL_SMTP_ERROR_SENDER', 10004);

/** Error: Failed to add recipient */
define('PEAR_MAIL_SMTP_ERROR_RECIPIENT', 10005);

/** Error: Failed to send data */
define('PEAR_MAIL_SMTP_ERROR_DATA', 10006);

/**
 * SMTP implementation of the PEAR Mail interface. Requires the Net_SMTP class.
 * @access public
 * @package Mail
 * @version $Revision: 1.1.8.1 $
 */
class Mail_smtp extends Mail {

    /**
     * SMTP connection object.
     *
     * @var object
     * @access private
     */
    var $_smtp = null;

    /**
     * The SMTP host to connect to.
     * @var string
     */
    var $host = 'localhost';

    /**
     * The port the SMTP server is on.
     * @var integer
     */
    var $port = 25;

    /**
     * Should SMTP authentication be used?
     *
     * This value may be set to true, false or the name of a specific
     * authentication method.
     *
     * If the value is set to true, the Net_SMTP package will attempt to use
     * the best authentication method advertised by the remote SMTP server.
     *
     * @var mixed
     */
    var $auth = false;

    /**
     * The username to use if the SMTP server requires authentication.
     * @var string
     */
    var $username = '';

    /**
     * The password to use if the SMTP server requires authentication.
     * @var string
     */
    var $password = '';

    /**
     * Hostname or domain that will be sent to the remote SMTP server in the
     * HELO / EHLO message.
     *
     * @var string
     */
    var $localhost = 'localhost';

    /**
     * SMTP connection timeout value.  NULL indicates no timeout.
     *
     * @var integer
     */
    var $timeout = null;

    /**
     * Whether to use VERP or not. If not a boolean, the string value
     * will be used as the VERP separators.
     *
     * @var mixed boolean or string
     */
    var $verp = false;

    /**
     * Turn on Net_SMTP debugging?
     *
     * @var boolean $debug
     */
    var $debug = false;

    /**
     * Indicates whether or not the SMTP connection should persist over
     * multiple calls to the send() method.
     *
     * @var boolean
     */
    var $persist = false;

    /**
     * Constructor.
     *
     * Instantiates a new Mail_smtp:: object based on the parameters
     * passed in. It looks for the following parameters:
     *     host        The server to connect to. Defaults to localhost.
     *     port        The port to connect to. Defaults to 25.
     *     auth        SMTP authentication.  Defaults to none.
     *     username    The username to use for SMTP auth. No default.
     *     password    The password to use for SMTP auth. No default.
     *     localhost   The local hostname / domain. Defaults to localhost.
     *     timeout     The SMTP connection timeout. Defaults to none.
     *     verp        Whether to use VERP or not. Defaults to false.
     *     debug       Activate SMTP debug mode? Defaults to false.
     *     persist     Should the SMTP connection persist?
     *
     * If a parameter is present in the $params array, it replaces the
     * default.
     *
     * @param array Hash containing any parameters different from the
     *              defaults.
     * @access public
     */
    function Mail_smtp($params)
    {
        if (isset($params['host'])) $this->host = $params['host'];
        if (isset($params['port'])) $this->port = $params['port'];
        if (isset($params['auth'])) $this->auth = $params['auth'];
        if (isset($params['username'])) $this->username = $params['username'];
        if (isset($params['password'])) $this->password = $params['password'];
        if (isset($params['localhost'])) $this->localhost = $params['localhost'];
        if (isset($params['timeout'])) $this->timeout = $params['timeout'];
        if (isset($params['verp'])) $this->verp = $params['verp'];
        if (isset($params['debug'])) $this->debug = (boolean)$params['debug'];
        if (isset($params['persist'])) $this->persist = (boolean)$params['persist'];

        register_shutdown_function(array(&$this, '_Mail_smtp'));
    }

    /**
     * Destructor implementation to ensure that we disconnect from any
     * potentially-alive persistent SMTP connections.
     */
    function _Mail_smtp()
    {
        $this->disconnect();
    }

    /**
     * Implements Mail::send() function using SMTP.
     *
     * @param mixed $recipients Either a comma-seperated list of recipients
     *              (RFC822 compliant), or an array of recipients,
     *              each RFC822 valid. This may contain recipients not
     *              specified in the headers, for Bcc:, resending
     *              messages, etc.
     *
     * @param array $headers The array of headers to send with the mail, in an
     *              associative array, where the array key is the
     *              header name (e.g., 'Subject'), and the array value
     *              is the header value (e.g., 'test'). The header
     *              produced from those values would be 'Subject:
     *              test'.
     *
     * @param string $body The full text of the message body, including any
     *               Mime parts, etc.
     *
     * @return mixed Returns true on success, or a PEAR_Error
     *               containing a descriptive error message on
     *               failure.
     * @access public
     */
    function send($recipients, $headers, $body)
    {
       /*PJ include_once 'Net/SMTP.php';*/

        /* If we don't already have an SMTP object, create one. */
        if (is_object($this->_smtp) === false) {
            $this->_smtp = new Net_SMTP($this->host, $this->port,
                                         $this->localhost);

            /* If we still don't have an SMTP object at this point, fail. */
            if (is_object($this->_smtp) === false) {
                return PEAR::raiseError('Failed to create a Net_SMTP object',
                                        PEAR_MAIL_SMTP_ERROR_CREATE);
            }

            /* Configure the SMTP connection. */
            if ($this->debug) {
                $this->_smtp->setDebug(true);
            }

            /* Attempt to connect to the configured SMTP server. */
            if (PEAR::isError($res = $this->_smtp->connect($this->timeout))) {
                $error = $this->_error('Failed to connect to ' .
                                       $this->host . ':' . $this->port,
                                       $res);
                return PEAR::raiseError($error, PEAR_MAIL_SMTP_ERROR_CONNECT);
            }

            /* Attempt to authenticate if authentication has been enabled. */
            if ($this->auth) {
                $method = is_string($this->auth) ? $this->auth : '';

                if (PEAR::isError($res = $this->_smtp->auth($this->username,
                                                            $this->password,
                                                            $method))) {
                    $error = $this->_error("$method authentication failure",
                                           $res);
                    $this->_smtp->rset();
                    return PEAR::raiseError($error, PEAR_MAIL_SMTP_ERROR_AUTH);
                }
            }
        }

        $this->_sanitizeHeaders($headers);
        $headerElements = $this->prepareHeaders($headers);
        if (PEAR::isError($headerElements)) {
            $this->_smtp->rset();
            return $headerElements;
        }
        list($from, $textHeaders) = $headerElements;

        /* Since few MTAs are going to allow this header to be forged
         * unless it's in the MAIL FROM: exchange, we'll use
         * Return-Path instead of From: if it's set. */
        if (!empty($headers['Return-Path'])) {
            $from = $headers['Return-Path'];
        }

        if (!isset($from)) {
            $this->_smtp->rset();
            return PEAR::raiseError('No From: address has been provided',
                                    PEAR_MAIL_SMTP_ERROR_FROM);
        }

        $args['verp'] = $this->verp;
        if (PEAR::isError($res = $this->_smtp->mailFrom($from, $args))) {
            $error = $this->_error("Failed to set sender: $from", $res);
            $this->_smtp->rset();
            return PEAR::raiseError($error, PEAR_MAIL_SMTP_ERROR_SENDER);
        }

        $recipients = $this->parseRecipients($recipients);
        if (PEAR::isError($recipients)) {
            $this->_smtp->rset();
            return $recipients;
        }

        foreach ($recipients as $recipient) {
            if (PEAR::isError($res = $this->_smtp->rcptTo($recipient))) {
                $error = $this->_error("Failed to add recipient: $recipient",
                                       $res);
                $this->_smtp->rset();
                return PEAR::raiseError($error, PEAR_MAIL_SMTP_ERROR_RECIPIENT);
            }
        }

        /* Send the message's headers and the body as SMTP data. */
        if (PEAR::isError($res = $this->_smtp->data($textHeaders . "\r\n\r\n" . $body))) {
            $error = $this->_error('Failed to send data', $res);
            $this->_smtp->rset();
            return PEAR::raiseError($error, PEAR_MAIL_SMTP_ERROR_DATA);
        }

        /* If persistent connections are disabled, destroy our SMTP object. */
        if ($this->persist === false) {
            $this->disconnect();
        }

        return true;
    }

    /**
     * Disconnect and destroy the current SMTP connection.
     *
     * @return boolean True if the SMTP connection no longer exists.
     *
     * @since  1.1.9
     * @access public
     */
    function disconnect()
    {
        /* If we have an SMTP object, disconnect and destroy it. */
        if (is_object($this->_smtp) && $this->_smtp->disconnect()) {
            $this->_smtp = null;
        }

        /* We are disconnected if we no longer have an SMTP object. */
        return ($this->_smtp === null);
    }

    /**
     * Build a standardized string describing the current SMTP error.
     *
     * @param string $text  Custom string describing the error context.
     * @param object $error Reference to the current PEAR_Error object.
     *
     * @return string       A string describing the current SMTP error.
     *
     * @since  1.1.7
     * @access private
     */
    function _error($text, &$error)
    {
        /* Split the SMTP response into a code and a response string. */
        list($code, $response) = $this->_smtp->getResponse();

        /* Build our standardized error string. */
        $msg = $text;
        $msg .= ' [SMTP: ' . $error->getMessage();
        $msg .= " (code: $code, response: $response)]";

        return $msg;
    }

}
//
// +----------------------------------------------------------------------+
// | PHP Version 4                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2003 The PHP Group                                |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.0 of the PHP license,       |
// | that is bundled with this package in the file LICENSE, and is        |
// | available at through the world-wide-web at                           |
// | http://www.php.net/license/2_02.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Authors: Stig Bakken <ssb@php.net>                                   |
// |          Chuck Hagenbuch <chuck@horde.org>                           |
// +----------------------------------------------------------------------+
//
// $Id: Socket.php,v 1.1 2009/01/28 07:34:14 Prasanth Exp $

/*PJ require_once 'PEAR.php';*/

define('NET_SOCKET_READ',  1);
define('NET_SOCKET_WRITE', 2);
define('NET_SOCKET_ERROR', 4);

/**
 * Generalized Socket class.
 *
 * @version 1.1
 * @author Stig Bakken <ssb@php.net>
 * @author Chuck Hagenbuch <chuck@horde.org>
 */
class Net_Socket extends PEAR {

    /**
     * Socket file pointer.
     * @var resource $fp
     */
    var $fp = null;

    /**
     * Whether the socket is blocking. Defaults to true.
     * @var boolean $blocking
     */
    var $blocking = true;

    /**
     * Whether the socket is persistent. Defaults to false.
     * @var boolean $persistent
     */
    var $persistent = false;

    /**
     * The IP address to connect to.
     * @var string $addr
     */
    var $addr = '';

    /**
     * The port number to connect to.
     * @var integer $port
     */
    var $port = 0;

    /**
     * Number of seconds to wait on socket connections before assuming
     * there's no more data. Defaults to no timeout.
     * @var integer $timeout
     */
    var $timeout = false;

    /**
     * Number of bytes to read at a time in readLine() and
     * readAll(). Defaults to 2048.
     * @var integer $lineLength
     */
    var $lineLength = 2048;

    /**
     * Connect to the specified port. If called when the socket is
     * already connected, it disconnects and connects again.
     *
     * @param string  $addr        IP address or host name.
     * @param integer $port        TCP port number.
     * @param boolean $persistent  (optional) Whether the connection is
     *                             persistent (kept open between requests
     *                             by the web server).
     * @param integer $timeout     (optional) How long to wait for data.
     * @param array   $options     See options for stream_context_create.
     *
     * @access public
     *
     * @return boolean | PEAR_Error  True on success or a PEAR_Error on failure.
     */
    function connect($addr, $port = 0, $persistent = null, $timeout = null, $options = null)
    {
        if (is_resource($this->fp)) {
            @fclose($this->fp);
            $this->fp = null;
        }

        if (!$addr) {
            return $this->raiseError('$addr cannot be empty');
        } elseif (strspn($addr, '.0123456789') == strlen($addr) ||
                  strstr($addr, '/') !== false) {
            $this->addr = $addr;
        } else {
            $this->addr = @gethostbyname($addr);
        }

        $this->port = $port % 65536;

        if ($persistent !== null) {
            $this->persistent = $persistent;
        }

        if ($timeout !== null) {
            $this->timeout = $timeout;
        }

        $openfunc = $this->persistent ? 'pfsockopen' : 'fsockopen';
        $errno = 0;
        $errstr = '';
        $old_track_errors = @ini_set('track_errors', 1);
        if ($options && function_exists('stream_context_create')) {
            if ($this->timeout) {
                $timeout = $this->timeout;
            } else {
                $timeout = 0;
            }
            $context = stream_context_create($options);

            // Since PHP 5 fsockopen doesn't allow context specification
            if (function_exists('stream_socket_client')) {
                $flags = $this->persistent ? STREAM_CLIENT_PERSISTENT : STREAM_CLIENT_CONNECT;
                $addr = $this->addr . ':' . $this->port;
                $fp = stream_socket_client($addr, $errno, $errstr, $timeout, $flags, $context);
            } else {
                $fp = @$openfunc($this->addr, $this->port, $errno, $errstr, $timeout, $context);
            }
        } else {
            if ($this->timeout) {
                $fp = @$openfunc($this->addr, $this->port, $errno, $errstr, $this->timeout);
            } else {
                $fp = @$openfunc($this->addr, $this->port, $errno, $errstr);
            }
        }

        if (!$fp) {
            if ($errno == 0 && isset($php_errormsg)) {
                $errstr = $php_errormsg;
            }
            @ini_set('track_errors', $old_track_errors);
            return $this->raiseError($errstr, $errno);
        }

        @ini_set('track_errors', $old_track_errors);
        $this->fp = $fp;

        return $this->setBlocking($this->blocking);
    }

    /**
     * Disconnects from the peer, closes the socket.
     *
     * @access public
     * @return mixed true on success or a PEAR_Error instance otherwise
     */
    function disconnect()
    {
        if (!is_resource($this->fp)) {
            return $this->raiseError('not connected');
        }

        @fclose($this->fp);
        $this->fp = null;
        return true;
    }

    /**
     * Find out if the socket is in blocking mode.
     *
     * @access public
     * @return boolean  The current blocking mode.
     */
    function isBlocking()
    {
        return $this->blocking;
    }

    /**
     * Sets whether the socket connection should be blocking or
     * not. A read call to a non-blocking socket will return immediately
     * if there is no data available, whereas it will block until there
     * is data for blocking sockets.
     *
     * @param boolean $mode  True for blocking sockets, false for nonblocking.
     * @access public
     * @return mixed true on success or a PEAR_Error instance otherwise
     */
    function setBlocking($mode)
    {
        if (!is_resource($this->fp)) {
            return $this->raiseError('not connected');
        }

        $this->blocking = $mode;
        socket_set_blocking($this->fp, $this->blocking);
        return true;
    }

    /**
     * Sets the timeout value on socket descriptor,
     * expressed in the sum of seconds and microseconds
     *
     * @param integer $seconds  Seconds.
     * @param integer $microseconds  Microseconds.
     * @access public
     * @return mixed true on success or a PEAR_Error instance otherwise
     */
    function setTimeout($seconds, $microseconds)
    {
        if (!is_resource($this->fp)) {
            return $this->raiseError('not connected');
        }

        return socket_set_timeout($this->fp, $seconds, $microseconds);
    }

    /**
     * Sets the file buffering size on the stream.
     * See php's stream_set_write_buffer for more information.
     *
     * @param integer $size     Write buffer size.
     * @access public
     * @return mixed on success or an PEAR_Error object otherwise
     */
    function setWriteBuffer($size)
    {
        if (!is_resource($this->fp)) {
            return $this->raiseError('not connected');
        }

        $returned = stream_set_write_buffer($this->fp, $size);
        if ($returned == 0) {
            return true;
        }
        return $this->raiseError('Cannot set write buffer.');
    }

    /**
     * Returns information about an existing socket resource.
     * Currently returns four entries in the result array:
     *
     * <p>
     * timed_out (bool) - The socket timed out waiting for data<br>
     * blocked (bool) - The socket was blocked<br>
     * eof (bool) - Indicates EOF event<br>
     * unread_bytes (int) - Number of bytes left in the socket buffer<br>
     * </p>
     *
     * @access public
     * @return mixed Array containing information about existing socket resource or a PEAR_Error instance otherwise
     */
    function getStatus()
    {
        if (!is_resource($this->fp)) {
            return $this->raiseError('not connected');
        }

        return socket_get_status($this->fp);
    }

    /**
     * Get a specified line of data
     *
     * @access public
     * @return $size bytes of data from the socket, or a PEAR_Error if
     *         not connected.
     */
    function gets($size)
    {
        if (!is_resource($this->fp)) {
            return $this->raiseError('not connected');
        }

        return @fgets($this->fp, $size);
    }

    /**
     * Read a specified amount of data. This is guaranteed to return,
     * and has the added benefit of getting everything in one fread()
     * chunk; if you know the size of the data you're getting
     * beforehand, this is definitely the way to go.
     *
     * @param integer $size  The number of bytes to read from the socket.
     * @access public
     * @return $size bytes of data from the socket, or a PEAR_Error if
     *         not connected.
     */
    function read($size)
    {
        if (!is_resource($this->fp)) {
            return $this->raiseError('not connected');
        }

        return @fread($this->fp, $size);
    }

    /**
     * Write a specified amount of data.
     *
     * @param string  $data       Data to write.
     * @param integer $blocksize  Amount of data to write at once.
     *                            NULL means all at once.
     *
     * @access public
     * @return mixed If the socket is not connected, returns an instance of PEAR_Error
     *               If the write succeeds, returns the number of bytes written
     *               If the write fails, returns false.
     */
    function write($data, $blocksize = null)
    {
        if (!is_resource($this->fp)) {
            return $this->raiseError('not connected');
        }

        if (is_null($blocksize) && !OS_WINDOWS) {
            return @fwrite($this->fp, $data);
        } else {
            if (is_null($blocksize)) {
                $blocksize = 1024;
            }

            $pos = 0;
            $size = strlen($data);
            while ($pos < $size) {
                $written = @fwrite($this->fp, substr($data, $pos, $blocksize));
                if ($written === false) {
                    return false;
                }
                $pos += $written;
            }

            return $pos;
        }
    }

    /**
     * Write a line of data to the socket, followed by a trailing "\r\n".
     *
     * @access public
     * @return mixed fputs result, or an error
     */
    function writeLine($data)
    {
        if (!is_resource($this->fp)) {
            return $this->raiseError('not connected');
        }

        return fwrite($this->fp, $data . "\r\n");
    }

    /**
     * Tests for end-of-file on a socket descriptor.
     *
     * Also returns true if the socket is disconnected.
     *
     * @access public
     * @return bool
     */
    function eof()
    {
        return (!is_resource($this->fp) || feof($this->fp));
    }

    /**
     * Reads a byte of data
     *
     * @access public
     * @return 1 byte of data from the socket, or a PEAR_Error if
     *         not connected.
     */
    function readByte()
    {
        if (!is_resource($this->fp)) {
            return $this->raiseError('not connected');
        }

        return ord(@fread($this->fp, 1));
    }

    /**
     * Reads a word of data
     *
     * @access public
     * @return 1 word of data from the socket, or a PEAR_Error if
     *         not connected.
     */
    function readWord()
    {
        if (!is_resource($this->fp)) {
            return $this->raiseError('not connected');
        }

        $buf = @fread($this->fp, 2);
        return (ord($buf[0]) + (ord($buf[1]) << 8));
    }

    /**
     * Reads an int of data
     *
     * @access public
     * @return integer  1 int of data from the socket, or a PEAR_Error if
     *                  not connected.
     */
    function readInt()
    {
        if (!is_resource($this->fp)) {
            return $this->raiseError('not connected');
        }

        $buf = @fread($this->fp, 4);
        return (ord($buf[0]) + (ord($buf[1]) << 8) +
                (ord($buf[2]) << 16) + (ord($buf[3]) << 24));
    }

    /**
     * Reads a zero-terminated string of data
     *
     * @access public
     * @return string, or a PEAR_Error if
     *         not connected.
     */
    function readString()
    {
        if (!is_resource($this->fp)) {
            return $this->raiseError('not connected');
        }

        $string = '';
        while (($char = @fread($this->fp, 1)) != "\x00")  {
            $string .= $char;
        }
        return $string;
    }

    /**
     * Reads an IP Address and returns it in a dot formatted string
     *
     * @access public
     * @return Dot formatted string, or a PEAR_Error if
     *         not connected.
     */
    function readIPAddress()
    {
        if (!is_resource($this->fp)) {
            return $this->raiseError('not connected');
        }

        $buf = @fread($this->fp, 4);
        return sprintf('%d.%d.%d.%d', ord($buf[0]), ord($buf[1]),
                       ord($buf[2]), ord($buf[3]));
    }

    /**
     * Read until either the end of the socket or a newline, whichever
     * comes first. Strips the trailing newline from the returned data.
     *
     * @access public
     * @return All available data up to a newline, without that
     *         newline, or until the end of the socket, or a PEAR_Error if
     *         not connected.
     */
    function readLine()
    {
        if (!is_resource($this->fp)) {
            return $this->raiseError('not connected');
        }

        $line = '';
        $timeout = time() + $this->timeout;
        while (!feof($this->fp) && (!$this->timeout || time() < $timeout)) {
            $line .= @fgets($this->fp, $this->lineLength);
            if (substr($line, -1) == "\n") {
                return rtrim($line, "\r\n");
            }
        }
        return $line;
    }

    /**
     * Read until the socket closes, or until there is no more data in
     * the inner PHP buffer. If the inner buffer is empty, in blocking
     * mode we wait for at least 1 byte of data. Therefore, in
     * blocking mode, if there is no data at all to be read, this
     * function will never exit (unless the socket is closed on the
     * remote end).
     *
     * @access public
     *
     * @return string  All data until the socket closes, or a PEAR_Error if
     *                 not connected.
     */
    function readAll()
    {
        if (!is_resource($this->fp)) {
            return $this->raiseError('not connected');
        }

        $data = '';
        while (!feof($this->fp)) {
            $data .= @fread($this->fp, $this->lineLength);
        }
        return $data;
    }

    /**
     * Runs the equivalent of the select() system call on the socket
     * with a timeout specified by tv_sec and tv_usec.
     *
     * @param integer $state    Which of read/write/error to check for.
     * @param integer $tv_sec   Number of seconds for timeout.
     * @param integer $tv_usec  Number of microseconds for timeout.
     *
     * @access public
     * @return False if select fails, integer describing which of read/write/error
     *         are ready, or PEAR_Error if not connected.
     */
    function select($state, $tv_sec, $tv_usec = 0)
    {
        if (!is_resource($this->fp)) {
            return $this->raiseError('not connected');
        }

        $read = null;
        $write = null;
        $except = null;
        if ($state & NET_SOCKET_READ) {
            $read[] = $this->fp;
        }
        if ($state & NET_SOCKET_WRITE) {
            $write[] = $this->fp;
        }
        if ($state & NET_SOCKET_ERROR) {
            $except[] = $this->fp;
        }
        if (false === ($sr = stream_select($read, $write, $except, $tv_sec, $tv_usec))) {
            return false;
        }

        $result = 0;
        if (count($read)) {
            $result |= NET_SOCKET_READ;
        }
        if (count($write)) {
            $result |= NET_SOCKET_WRITE;
        }
        if (count($except)) {
            $result |= NET_SOCKET_ERROR;
        }
        return $result;
    }

    /**
     * Turns encryption on/off on a connected socket.
     *
     * @param bool    $enabled  Set this parameter to true to enable encryption
     *                          and false to disable encryption.
     * @param integer $type     Type of encryption. See
     *                          http://se.php.net/manual/en/function.stream-socket-enable-crypto.php for values.
     *
     * @access public
     * @return false on error, true on success and 0 if there isn't enough data and the
     *         user should try again (non-blocking sockets only). A PEAR_Error object
     *         is returned if the socket is not connected
     */
    function enableCrypto($enabled, $type)
    {
        if (version_compare(phpversion(), "5.1.0", ">=")) {
            if (!is_resource($this->fp)) {
                return $this->raiseError('not connected');
            }
            return @stream_socket_enable_crypto($this->fp, $enabled, $type);
        } else {
            return $this->raiseError('Net_Socket::enableCrypto() requires php version >= 5.1.0');
        }
    }

}
/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
// +----------------------------------------------------------------------+
// | PHP Version 4                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2003 The PHP Group                                |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.02 of the PHP license,      |
// | that is bundled with this package in the file LICENSE, and is        |
// | available at through the world-wide-web at                           |
// | http://www.php.net/license/2_02.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Authors: Chuck Hagenbuch <chuck@horde.org>                           |
// |          Jon Parise <jon@php.net>                                    |
// |          Damian Alejandro Fernandez Sosa <damlists@cnba.uba.ar>      |
// +----------------------------------------------------------------------+
//
// $Id: SMTP.php,v 1.2 2010/03/19 05:37:26 Prasanth Exp $

/*PJ: require_once 'PEAR.php';*/
/*PJ: require_once 'Net/Socket.php';*/

/**
 * Provides an implementation of the SMTP protocol using PEAR's
 * Net_Socket:: class.
 *
 * @package Net_SMTP
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @author  Jon Parise <jon@php.net>
 * @author  Damian Alejandro Fernandez Sosa <damlists@cnba.uba.ar>
 *
 * @example basic.php   A basic implementation of the Net_SMTP package.
 */
class Net_SMTP
{
    /**
     * The server to connect to.
     * @var string
     * @access public
     */
    var $host = 'localhost';

    /**
     * The port to connect to.
     * @var int
     * @access public
     */
    var $port = 25;

    /**
     * The value to give when sending EHLO or HELO.
     * @var string
     * @access public
     */
    var $localhost = 'localhost';

    /**
     * List of supported authentication methods, in preferential order.
     * @var array
     * @access public
     */
    var $auth_methods = array('DIGEST-MD5', 'CRAM-MD5', 'LOGIN', 'PLAIN');

    /**
     * Use SMTP command pipelining (specified in RFC 2920) if the SMTP
     * server supports it.
     *
     * When pipeling is enabled, rcptTo(), mailFrom(), sendFrom(),
     * somlFrom() and samlFrom() do not wait for a response from the
     * SMTP server but return immediately.
     *
     * @var bool
     * @access public
     */
    var $pipelining = false;

    /**
     * Number of pipelined commands.
     * @var int
     * @access private
     */
    var $_pipelined_commands = 0;

    /**
     * Should debugging output be enabled?
     * @var boolean
     * @access private
     */
    var $_debug = false;

    /**
     * Debug output handler.
     * @var callback
     * @access private
     */
    var $_debug_handler = null;

    /**
     * The socket resource being used to connect to the SMTP server.
     * @var resource
     * @access private
     */
    var $_socket = null;

    /**
     * The most recent server response code.
     * @var int
     * @access private
     */
    var $_code = -1;

    /**
     * The most recent server response arguments.
     * @var array
     * @access private
     */
    var $_arguments = array();

    /**
     * Stores the SMTP server's greeting string.
     * @var string
     * @access private
     */
    var $_greeting = null;

    /**
     * Stores detected features of the SMTP server.
     * @var array
     * @access private
     */
    var $_esmtp = array();

    /**
     * Instantiates a new Net_SMTP object, overriding any defaults
     * with parameters that are passed in.
     *
     * If you have SSL support in PHP, you can connect to a server
     * over SSL using an 'ssl://' prefix:
     *
     *   // 465 is a common smtps port.
     *   $smtp = new Net_SMTP('ssl://mail.host.com', 465);
     *   $smtp->connect();
     *
     * @param string  $host       The server to connect to.
     * @param integer $port       The port to connect to.
     * @param string  $localhost  The value to give when sending EHLO or HELO.
     * @param boolean $pipeling   Use SMTP command pipelining
     *
     * @access  public
     * @since   1.0
     */
    function Net_SMTP($host = null, $port = null, $localhost = null, $pipelining = false)
    {
        if (isset($host)) {
            $this->host = $host;
        }
        if (isset($port)) {
            $this->port = $port;
        }
        if (isset($localhost)) {
            $this->localhost = $localhost;
        }
        $this->pipelining = $pipelining;

        $this->_socket = new Net_Socket();

        /* Include the Auth_SASL package.  If the package is not
         * available, we disable the authentication methods that
         * depend upon it. */
        /*PJ if ((@include_once 'Auth/SASL.php') === false)
        {
            $pos = array_search('DIGEST-MD5', $this->auth_methods);
            unset($this->auth_methods[$pos]);
            $pos = array_search('CRAM-MD5', $this->auth_methods);
            unset($this->auth_methods[$pos]);
        }*/
    }

    /**
     * Set the value of the debugging flag.
     *
     * @param   boolean $debug      New value for the debugging flag.
     *
     * @access  public
     * @since   1.1.0
     */
    function setDebug($debug, $handler = null)
    {
        $this->_debug = $debug;
        $this->_debug_handler = $handler;
    }

    /**
     * Write the given debug text to the current debug output handler.
     *
     * @param   string  $message    Debug mesage text.
     *
     * @access  private
     * @since   1.3.3
     */
    function _debug($message)
    {
        if ($this->_debug) {
            if ($this->_debug_handler) {
                call_user_func_array($this->_debug_handler,
                                     array(&$this, $message));
            } else {
                echo "DEBUG: $message\n";
            }
        }
    }

    /**
     * Send the given string of data to the server.
     *
     * @param   string  $data       The string of data to send.
     *
     * @return  mixed   True on success or a PEAR_Error object on failure.
     *
     * @access  private
     * @since   1.1.0
     */
    function _send($data)
    {
        $this->_debug("Send: $data");

        $error = $this->_socket->write($data);
        if ($error === false || PEAR::isError($error)) {
            $msg = ($error) ? $error->getMessage() : "unknown error";
            return PEAR::raiseError("Failed to write to socket: $msg");
        }

        return true;
    }

    /**
     * Send a command to the server with an optional string of
     * arguments.  A carriage return / linefeed (CRLF) sequence will
     * be appended to each command string before it is sent to the
     * SMTP server - an error will be thrown if the command string
     * already contains any newline characters. Use _send() for
     * commands that must contain newlines.
     *
     * @param   string  $command    The SMTP command to send to the server.
     * @param   string  $args       A string of optional arguments to append
     *                              to the command.
     *
     * @return  mixed   The result of the _send() call.
     *
     * @access  private
     * @since   1.1.0
     */
    function _put($command, $args = '')
    {
        if (!empty($args)) {
            $command .= ' ' . $args;
        }

        if (strcspn($command, "\r\n") !== strlen($command)) {
            return PEAR::raiseError('Commands cannot contain newlines');
        }

        return $this->_send($command . "\r\n");
    }

    /**
     * Read a reply from the SMTP server.  The reply consists of a response
     * code and a response message.
     *
     * @param   mixed   $valid      The set of valid response codes.  These
     *                              may be specified as an array of integer
     *                              values or as a single integer value.
     * @param   bool    $later      Do not parse the response now, but wait
     *                              until the last command in the pipelined
     *                              command group
     *
     * @return  mixed   True if the server returned a valid response code or
     *                  a PEAR_Error object is an error condition is reached.
     *
     * @access  private
     * @since   1.1.0
     *
     * @see     getResponse
     */
    function _parseResponse($valid, $later = false)
    {
        $this->_code = -1;
        $this->_arguments = array();

        if ($later) {
            $this->_pipelined_commands++;
            return true;
        }

        for ($i = 0; $i <= $this->_pipelined_commands; $i++) {
            while ($line = $this->_socket->readLine()) {
                $this->_debug("Recv: $line");

                /* If we receive an empty line, the connection has been closed. */
                if (empty($line)) {
                    $this->disconnect();
                    return PEAR::raiseError('Connection was unexpectedly closed');
                }

                /* Read the code and store the rest in the arguments array. */
                $code = substr($line, 0, 3);
                $this->_arguments[] = trim(substr($line, 4));

                /* Check the syntax of the response code. */
                if (is_numeric($code)) {
                    $this->_code = (int)$code;
                } else {
                    $this->_code = -1;
                    break;
                }

                /* If this is not a multiline response, we're done. */
                if (substr($line, 3, 1) != '-') {
                    break;
                }
            }
        }

        $this->_pipelined_commands = 0;

        /* Compare the server's response code with the valid code/codes. */
        if (is_int($valid) && ($this->_code === $valid)) {
            return true;
        } elseif (is_array($valid) && in_array($this->_code, $valid, true)) {
            return true;
        }

        return PEAR::raiseError('Invalid response code received from server',
                                $this->_code);
    }

    /**
     * Return a 2-tuple containing the last response from the SMTP server.
     *
     * @return  array   A two-element array: the first element contains the
     *                  response code as an integer and the second element
     *                  contains the response's arguments as a string.
     *
     * @access  public
     * @since   1.1.0
     */
    function getResponse()
    {
        return array($this->_code, join("\n", $this->_arguments));
    }

    /**
     * Return the SMTP server's greeting string.
     *
     * @return  string  A string containing the greeting string, or null if a 
     *                  greeting has not been received.
     *
     * @access  public
     * @since   1.3.3
     */
    function getGreeting()
    {
        return $this->_greeting;
    }

    /**
     * Attempt to connect to the SMTP server.
     *
     * @param   int     $timeout    The timeout value (in seconds) for the
     *                              socket connection.
     * @param   bool    $persistent Should a persistent socket connection
     *                              be used?
     *
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or true on success.
     * @access public
     * @since  1.0
     */
    function connect($timeout = null, $persistent = false)
    {
        $this->_greeting = null;
        $result = $this->_socket->connect($this->host, $this->port,
                                          $persistent, $timeout);
        if (PEAR::isError($result)) {
            return PEAR::raiseError('Failed to connect socket: ' .
                                    $result->getMessage());
        }

        if (PEAR::isError($error = $this->_parseResponse(220))) {
            return $error;
        }

        /* Extract and store a copy of the server's greeting string. */
        list(, $this->_greeting) = $this->getResponse();

        if (PEAR::isError($error = $this->_negotiate())) {
            return $error;
        }

        return true;
    }

    /**
     * Attempt to disconnect from the SMTP server.
     *
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or true on success.
     * @access public
     * @since  1.0
     */
    function disconnect()
    {
        if (PEAR::isError($error = $this->_put('QUIT'))) {
            return $error;
        }
        if (PEAR::isError($error = $this->_parseResponse(221))) {
            return $error;
        }
        if (PEAR::isError($error = $this->_socket->disconnect())) {
            return PEAR::raiseError('Failed to disconnect socket: ' .
                                    $error->getMessage());
        }

        return true;
    }

    /**
     * Attempt to send the EHLO command and obtain a list of ESMTP
     * extensions available, and failing that just send HELO.
     *
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or true on success.
     *
     * @access private
     * @since  1.1.0
     */
    function _negotiate()
    {
        if (PEAR::isError($error = $this->_put('EHLO', $this->localhost))) {
            return $error;
        }

        if (PEAR::isError($this->_parseResponse(250))) {
            /* If we receive a 503 response, we're already authenticated. */
            if ($this->_code === 503) {
                return true;
            }

            /* If the EHLO failed, try the simpler HELO command. */
            if (PEAR::isError($error = $this->_put('HELO', $this->localhost))) {
                return $error;
            }
            if (PEAR::isError($this->_parseResponse(250))) {
                return PEAR::raiseError('HELO was not accepted: ', $this->_code);
            }

            return true;
        }

        foreach ($this->_arguments as $argument) {
            $verb = strtok($argument, ' ');
            $arguments = substr($argument, strlen($verb) + 1,
                                strlen($argument) - strlen($verb) - 1);
            $this->_esmtp[$verb] = $arguments;
        }

        if (!isset($this->_esmtp['PIPELINING'])) {
            $this->pipelining = false;
        }

        return true;
    }

    /**
     * Returns the name of the best authentication method that the server
     * has advertised.
     *
     * @return mixed    Returns a string containing the name of the best
     *                  supported authentication method or a PEAR_Error object
     *                  if a failure condition is encountered.
     * @access private
     * @since  1.1.0
     */
    function _getBestAuthMethod()
    {
        $available_methods = explode(' ', $this->_esmtp['AUTH']);

        foreach ($this->auth_methods as $method) {
            if (in_array($method, $available_methods)) {
                return $method;
            }
        }

        return PEAR::raiseError('No supported authentication methods');
    }

    /**
     * Attempt to do SMTP authentication.
     *
     * @param string The userid to authenticate as.
     * @param string The password to authenticate with.
     * @param string The requested authentication method.  If none is
     *               specified, the best supported method will be used.
     * @param bool   Flag indicating whether or not TLS should be attempted.
     *
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or true on success.
     * @access public
     * @since  1.0
     */
    function auth($uid, $pwd , $method = '', $tls = true)
    {
        /* We can only attempt a TLS connection if one has been requested,
         * we're running PHP 5.1.0 or later, have access to the OpenSSL 
         * extension, are connected to an SMTP server which supports the 
         * STARTTLS extension, and aren't already connected over a secure 
         * (SSL) socket connection. */
        if ($tls && version_compare(PHP_VERSION, '5.1.0', '>=') &&
            extension_loaded('openssl') && isset($this->_esmtp['STARTTLS']) &&
            strncasecmp($this->host, 'ssl://', 6) !== 0) {
            /* Start the TLS connection attempt. */
            if (PEAR::isError($result = $this->_put('STARTTLS'))) {
                return $result;
            }
            if (PEAR::isError($result = $this->_parseResponse(220))) {
                return $result;
            }
            if (PEAR::isError($result = $this->_socket->enableCrypto(true, STREAM_CRYPTO_METHOD_TLS_CLIENT))) {
                return $result;
            } elseif ($result !== true) {
                return PEAR::raiseError('STARTTLS failed');
            }

            /* Send EHLO again to recieve the AUTH string from the
             * SMTP server. */
            $this->_negotiate();
        }

        if (empty($this->_esmtp['AUTH'])) {
            return PEAR::raiseError('SMTP server does not support authentication');
        }

        /* If no method has been specified, get the name of the best
         * supported method advertised by the SMTP server. */
        if (empty($method)) {
            if (PEAR::isError($method = $this->_getBestAuthMethod())) {
                /* Return the PEAR_Error object from _getBestAuthMethod(). */
                return $method;
            }
        } else {
            $method = strtoupper($method);
            if (!in_array($method, $this->auth_methods)) {
                return PEAR::raiseError("$method is not a supported authentication method");
            }
        }

        switch ($method) {
        case 'DIGEST-MD5':
            $result = $this->_authDigest_MD5($uid, $pwd);
            break;

        case 'CRAM-MD5':
            $result = $this->_authCRAM_MD5($uid, $pwd);
            break;

        case 'LOGIN':
            $result = $this->_authLogin($uid, $pwd);
            break;

        case 'PLAIN':
            $result = $this->_authPlain($uid, $pwd);
            break;

        default:
            $result = PEAR::raiseError("$method is not a supported authentication method");
            break;
        }

        /* If an error was encountered, return the PEAR_Error object. */
        if (PEAR::isError($result)) {
            return $result;
        }

        return true;
    }

    /**
     * Authenticates the user using the DIGEST-MD5 method.
     *
     * @param string The userid to authenticate as.
     * @param string The password to authenticate with.
     *
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or true on success.
     * @access private
     * @since  1.1.0
     */
    function _authDigest_MD5($uid, $pwd)
    {
        if (PEAR::isError($error = $this->_put('AUTH', 'DIGEST-MD5'))) {
            return $error;
        }
        /* 334: Continue authentication request */
        if (PEAR::isError($error = $this->_parseResponse(334))) {
            /* 503: Error: already authenticated */
            if ($this->_code === 503) {
                return true;
            }
            return $error;
        }

        $challenge = base64_decode($this->_arguments[0]);
        $digest = &Auth_SASL::factory('digestmd5');
        $auth_str = base64_encode($digest->getResponse($uid, $pwd, $challenge,
                                                       $this->host, "smtp"));

        if (PEAR::isError($error = $this->_put($auth_str))) {
            return $error;
        }
        /* 334: Continue authentication request */
        if (PEAR::isError($error = $this->_parseResponse(334))) {
            return $error;
        }

        /* We don't use the protocol's third step because SMTP doesn't
         * allow subsequent authentication, so we just silently ignore
         * it. */
        if (PEAR::isError($error = $this->_put(''))) {
            return $error;
        }
        /* 235: Authentication successful */
        if (PEAR::isError($error = $this->_parseResponse(235))) {
            return $error;
        }
    }

    /**
     * Authenticates the user using the CRAM-MD5 method.
     *
     * @param string The userid to authenticate as.
     * @param string The password to authenticate with.
     *
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or true on success.
     * @access private
     * @since  1.1.0
     */
    function _authCRAM_MD5($uid, $pwd)
    {
        if (PEAR::isError($error = $this->_put('AUTH', 'CRAM-MD5'))) {
            return $error;
        }
        /* 334: Continue authentication request */
        if (PEAR::isError($error = $this->_parseResponse(334))) {
            /* 503: Error: already authenticated */
            if ($this->_code === 503) {
                return true;
            }
            return $error;
        }

        $challenge = base64_decode($this->_arguments[0]);
        $cram = &Auth_SASL::factory('crammd5');
        $auth_str = base64_encode($cram->getResponse($uid, $pwd, $challenge));

        if (PEAR::isError($error = $this->_put($auth_str))) {
            return $error;
        }

        /* 235: Authentication successful */
        if (PEAR::isError($error = $this->_parseResponse(235))) {
            return $error;
        }
    }

    /**
     * Authenticates the user using the LOGIN method.
     *
     * @param string The userid to authenticate as.
     * @param string The password to authenticate with.
     *
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or true on success.
     * @access private
     * @since  1.1.0
     */
    function _authLogin($uid, $pwd)
    {
        if (PEAR::isError($error = $this->_put('AUTH', 'LOGIN'))) {
            return $error;
        }
        /* 334: Continue authentication request */
        if (PEAR::isError($error = $this->_parseResponse(334))) {
            /* 503: Error: already authenticated */
            if ($this->_code === 503) {
                return true;
            }
            return $error;
        }

        if (PEAR::isError($error = $this->_put(base64_encode($uid)))) {
            return $error;
        }
        /* 334: Continue authentication request */
        if (PEAR::isError($error = $this->_parseResponse(334))) {
            return $error;
        }

        if (PEAR::isError($error = $this->_put(base64_encode($pwd)))) {
            return $error;
        }

        /* 235: Authentication successful */
        if (PEAR::isError($error = $this->_parseResponse(235))) {
            return $error;
        }

        return true;
    }

    /**
     * Authenticates the user using the PLAIN method.
     *
     * @param string The userid to authenticate as.
     * @param string The password to authenticate with.
     *
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or true on success.
     * @access private
     * @since  1.1.0
     */
    function _authPlain($uid, $pwd)
    {
        if (PEAR::isError($error = $this->_put('AUTH', 'PLAIN'))) {
            return $error;
        }
        /* 334: Continue authentication request */
        if (PEAR::isError($error = $this->_parseResponse(334))) {
            /* 503: Error: already authenticated */
            if ($this->_code === 503) {
                return true;
            }
            return $error;
        }

        $auth_str = base64_encode(chr(0) . $uid . chr(0) . $pwd);

        if (PEAR::isError($error = $this->_put($auth_str))) {
            return $error;
        }

        /* 235: Authentication successful */
        if (PEAR::isError($error = $this->_parseResponse(235))) {
            return $error;
        }

        return true;
    }

    /**
     * Send the HELO command.
     *
     * @param string The domain name to say we are.
     *
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or true on success.
     * @access public
     * @since  1.0
     */
    function helo($domain)
    {
        if (PEAR::isError($error = $this->_put('HELO', $domain))) {
            return $error;
        }
        if (PEAR::isError($error = $this->_parseResponse(250))) {
            return $error;
        }

        return true;
    }

    /**
     * Return the list of SMTP service extensions advertised by the server.
     *
     * @return array The list of SMTP service extensions.
     * @access public
     * @since 1.3
     */
    function getServiceExtensions()
    {
        return $this->_esmtp;
    }

    /**
     * Send the MAIL FROM: command.
     *
     * @param string $sender    The sender (reverse path) to set.
     * @param string $params    String containing additional MAIL parameters,
     *                          such as the NOTIFY flags defined by RFC 1891
     *                          or the VERP protocol.
     *
     *                          If $params is an array, only the 'verp' option
     *                          is supported.  If 'verp' is true, the XVERP
     *                          parameter is appended to the MAIL command.  If
     *                          the 'verp' value is a string, the full
     *                          XVERP=value parameter is appended.
     *
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or true on success.
     * @access public
     * @since  1.0
     */
    function mailFrom($sender, $params = null)
    {
        $args = "FROM:<$sender>";

        /* Support the deprecated array form of $params. */
        if (is_array($params) && isset($params['verp'])) {
            /* XVERP */
            if ($params['verp'] === true) {
                $args .= ' XVERP';

            /* XVERP=something */
            } elseif (trim($params['verp'])) {
                $args .= ' XVERP=' . $params['verp'];
            }
        } elseif (is_string($params)) {
            $args .= ' ' . $params;
        }

        if (PEAR::isError($error = $this->_put('MAIL', $args))) {
            return $error;
        }
        if (PEAR::isError($error = $this->_parseResponse(250, $this->pipelining))) {
            return $error;
        }

        return true;
    }

    /**
     * Send the RCPT TO: command.
     *
     * @param string $recipient The recipient (forward path) to add.
     * @param string $params    String containing additional RCPT parameters,
     *                          such as the NOTIFY flags defined by RFC 1891.
     *
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or true on success.
     *
     * @access public
     * @since  1.0
     */
    function rcptTo($recipient, $params = null)
    {
        $args = "TO:<$recipient>";
        if (is_string($params)) {
            $args .= ' ' . $params;
        }

        if (PEAR::isError($error = $this->_put('RCPT', $args))) {
            return $error;
        }
        if (PEAR::isError($error = $this->_parseResponse(array(250, 251), $this->pipelining))) {
            return $error;
        }

        return true;
    }

    /**
     * Quote the data so that it meets SMTP standards.
     *
     * This is provided as a separate public function to facilitate
     * easier overloading for the cases where it is desirable to
     * customize the quoting behavior.
     *
     * @param string $data  The message text to quote. The string must be passed
     *                      by reference, and the text will be modified in place.
     *
     * @access public
     * @since  1.2
     */
    function quotedata(&$data)
    {
        /* Change Unix (\n) and Mac (\r) linefeeds into
         * Internet-standard CRLF (\r\n) linefeeds. */
        $data = preg_replace(array('/(?<!\r)\n/','/\r(?!\n)/'), "\r\n", $data);

        /* Because a single leading period (.) signifies an end to the
         * data, legitimate leading periods need to be "doubled"
         * (e.g. '..'). */
        $data = str_replace("\n.", "\n..", $data);
    }

    /**
     * Send the DATA command.
     *
     * @param mixed $data     The message data, either as a string or an open
     *                        file resource.
     * @param string $headers The message headers.  If $headers is provided,
     *                        $data is assumed to contain only body data.
     *
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or true on success.
     * @access public
     * @since  1.0
     */
    function data($data, $headers = null)
    {
        /* Verify that $data is a supported type. */
        if (!is_string($data) && !is_resource($data)) {
            return PEAR::raiseError('Expected a string or file resource');
        }

        /* RFC 1870, section 3, subsection 3 states "a value of zero
         * indicates that no fixed maximum message size is in force".
         * Furthermore, it says that if "the parameter is omitted no
         * information is conveyed about the server's fixed maximum
         * message size". */
        if (isset($this->_esmtp['SIZE']) && ($this->_esmtp['SIZE'] > 0)) {
            /* Start by considering the size of the optional headers string.  
             * We also account for the addition 4 character "\r\n\r\n"
             * separator sequence. */
            $size = (is_null($headers)) ? 0 : strlen($headers) + 4;

            if (is_resource($data)) {
                $stat = fstat($data);
                if ($stat === false) {
                    return PEAR::raiseError('Failed to get file size');
                }
                $size += $stat['size'];
            } else {
                $size += strlen($data);
            }

            if ($size >= $this->_esmtp['SIZE']) {
                $this->disconnect();
                return PEAR::raiseError('Message size exceeds server limit');
            }
        }

        /* Initiate the DATA command. */
        if (PEAR::isError($error = $this->_put('DATA'))) {
            return $error;
        }
        if (PEAR::isError($error = $this->_parseResponse(354))) {
            return $error;
        }

        /* If we have a separate headers string, send it first. */
        if (!is_null($headers)) {
            $this->quotedata($headers);
            if (PEAR::isError($result = $this->_send($headers . "\r\n\r\n"))) {
                return $result;
            }
        }

        /* Now we can send the message body data. */
        if (is_resource($data)) {
            /* Stream the contents of the file resource out over our socket 
             * connection, line by line.  Each line must be run through the 
             * quoting routine. */
            while ($line = fgets($data, 1024)) {
                $this->quotedata($line);
                if (PEAR::isError($result = $this->_send($line))) {
                    return $result;
                }
            }

            /* Finally, send the DATA terminator sequence. */
            if (PEAR::isError($result = $this->_send("\r\n.\r\n"))) {
                return $result;
            }
        } else {
            /* Just send the entire quoted string followed by the DATA 
             * terminator. */
            $this->quotedata($data);
            if (PEAR::isError($result = $this->_send($data . "\r\n.\r\n"))) {
                return $result;
            }
        }

        /* Verify that the data was successfully received by the server. */
        if (PEAR::isError($error = $this->_parseResponse(250, $this->pipelining))) {
            return $error;
        }

        return true;
    }

    /**
     * Send the SEND FROM: command.
     *
     * @param string The reverse path to send.
     *
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or true on success.
     * @access public
     * @since  1.2.6
     */
    function sendFrom($path)
    {
        if (PEAR::isError($error = $this->_put('SEND', "FROM:<$path>"))) {
            return $error;
        }
        if (PEAR::isError($error = $this->_parseResponse(250, $this->pipelining))) {
            return $error;
        }

        return true;
    }

    /**
     * Backwards-compatibility wrapper for sendFrom().
     *
     * @param string The reverse path to send.
     *
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or true on success.
     *
     * @access      public
     * @since       1.0
     * @deprecated  1.2.6
     */
    function send_from($path)
    {
        return sendFrom($path);
    }

    /**
     * Send the SOML FROM: command.
     *
     * @param string The reverse path to send.
     *
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or true on success.
     * @access public
     * @since  1.2.6
     */
    function somlFrom($path)
    {
        if (PEAR::isError($error = $this->_put('SOML', "FROM:<$path>"))) {
            return $error;
        }
        if (PEAR::isError($error = $this->_parseResponse(250, $this->pipelining))) {
            return $error;
        }

        return true;
    }

    /**
     * Backwards-compatibility wrapper for somlFrom().
     *
     * @param string The reverse path to send.
     *
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or true on success.
     *
     * @access      public
     * @since       1.0
     * @deprecated  1.2.6
     */
    function soml_from($path)
    {
        return somlFrom($path);
    }

    /**
     * Send the SAML FROM: command.
     *
     * @param string The reverse path to send.
     *
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or true on success.
     * @access public
     * @since  1.2.6
     */
    function samlFrom($path)
    {
        if (PEAR::isError($error = $this->_put('SAML', "FROM:<$path>"))) {
            return $error;
        }
        if (PEAR::isError($error = $this->_parseResponse(250, $this->pipelining))) {
            return $error;
        }

        return true;
    }

    /**
     * Backwards-compatibility wrapper for samlFrom().
     *
     * @param string The reverse path to send.
     *
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or true on success.
     *
     * @access      public
     * @since       1.0
     * @deprecated  1.2.6
     */
    function saml_from($path)
    {
        return samlFrom($path);
    }

    /**
     * Send the RSET command.
     *
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or true on success.
     * @access public
     * @since  1.0
     */
    function rset()
    {
        if (PEAR::isError($error = $this->_put('RSET'))) {
            return $error;
        }
        if (PEAR::isError($error = $this->_parseResponse(250, $this->pipelining))) {
            return $error;
        }

        return true;
    }

    /**
     * Send the VRFY command.
     *
     * @param string The string to verify
     *
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or true on success.
     * @access public
     * @since  1.0
     */
    function vrfy($string)
    {
        /* Note: 251 is also a valid response code */
        if (PEAR::isError($error = $this->_put('VRFY', $string))) {
            return $error;
        }
        if (PEAR::isError($error = $this->_parseResponse(array(250, 252)))) {
            return $error;
        }

        return true;
    }

    /**
     * Send the NOOP command.
     *
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or true on success.
     * @access public
     * @since  1.0
     */
    function noop()
    {
        if (PEAR::isError($error = $this->_put('NOOP'))) {
            return $error;
        }
        if (PEAR::isError($error = $this->_parseResponse(250))) {
            return $error;
        }

        return true;
    }

    /**
     * Backwards-compatibility method.  identifySender()'s functionality is
     * now handled internally.
     *
     * @return  boolean     This method always return true.
     *
     * @access  public
     * @since   1.0
     */
    function identifySender()
    {
        return true;
    }

}
// +-----------------------------------------------------------------------+ 
// | Copyright (c) 2002-2003 Richard Heyes                                 | 
// | All rights reserved.                                                  | 
// |                                                                       | 
// | Redistribution and use in source and binary forms, with or without    | 
// | modification, are permitted provided that the following conditions    | 
// | are met:                                                              | 
// |                                                                       | 
// | o Redistributions of source code must retain the above copyright      | 
// |   notice, this list of conditions and the following disclaimer.       | 
// | o Redistributions in binary form must reproduce the above copyright   | 
// |   notice, this list of conditions and the following disclaimer in the | 
// |   documentation and/or other materials provided with the distribution.| 
// | o The names of the authors may not be used to endorse or promote      | 
// |   products derived from this software without specific prior written  | 
// |   permission.                                                         | 
// |                                                                       | 
// | THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS   | 
// | "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT     | 
// | LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR | 
// | A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT  | 
// | OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, | 
// | SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT      | 
// | LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, | 
// | DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY | 
// | THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT   | 
// | (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE | 
// | OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.  | 
// |                                                                       | 
// +-----------------------------------------------------------------------+ 
// | Author: Richard Heyes <richard@php.net>                               | 
// +-----------------------------------------------------------------------+ 
// 
// $Id: Common.php,v 1.1 2009/01/28 07:34:10 Prasanth Exp $

/**
* Common functionality to SASL mechanisms
*
* @author  Richard Heyes <richard@php.net>
* @access  public
* @version 1.0
* @package Auth_SASL
*/

class Auth_SASL_Common
{
    /**
    * Function which implements HMAC MD5 digest
    *
    * @param  string $key  The secret key
    * @param  string $data The data to protect
    * @return string       The HMAC MD5 digest
    */
    function _HMAC_MD5($key, $data)
    {
        if (strlen($key) > 64) {
            $key = pack('H32', md5($key));
        }

        if (strlen($key) < 64) {
            $key = str_pad($key, 64, chr(0));
        }

        $k_ipad = substr($key, 0, 64) ^ str_repeat(chr(0x36), 64);
        $k_opad = substr($key, 0, 64) ^ str_repeat(chr(0x5C), 64);

        $inner  = pack('H32', md5($k_ipad . $data));
        $digest = md5($k_opad . $inner);

        return $digest;
    }
}

// +-----------------------------------------------------------------------+ 
// | Copyright (c) 2002-2003 Richard Heyes                                 | 
// | All rights reserved.                                                  | 
// |                                                                       | 
// | Redistribution and use in source and binary forms, with or without    | 
// | modification, are permitted provided that the following conditions    | 
// | are met:                                                              | 
// |                                                                       | 
// | o Redistributions of source code must retain the above copyright      | 
// |   notice, this list of conditions and the following disclaimer.       | 
// | o Redistributions in binary form must reproduce the above copyright   | 
// |   notice, this list of conditions and the following disclaimer in the | 
// |   documentation and/or other materials provided with the distribution.| 
// | o The names of the authors may not be used to endorse or promote      | 
// |   products derived from this software without specific prior written  | 
// |   permission.                                                         | 
// |                                                                       | 
// | THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS   | 
// | "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT     | 
// | LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR | 
// | A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT  | 
// | OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, | 
// | SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT      | 
// | LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, | 
// | DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY | 
// | THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT   | 
// | (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE | 
// | OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.  | 
// |                                                                       | 
// +-----------------------------------------------------------------------+ 
// | Author: Richard Heyes <richard@php.net>                               | 
// +-----------------------------------------------------------------------+ 
// 
// $Id: CramMD5.php,v 1.1 2009/01/28 07:34:10 Prasanth Exp $

/**
* Implmentation of CRAM-MD5 SASL mechanism
*
* @author  Richard Heyes <richard@php.net>
* @access  public
* @version 1.0
* @package Auth_SASL
*/

/*PJ require_once('Auth/SASL/Common.php');*/

class Auth_SASL_CramMD5 extends Auth_SASL_Common
{
    /**
    * Implements the CRAM-MD5 SASL mechanism
    * This DOES NOT base64 encode the return value,
    * you will need to do that yourself.
    *
    * @param string $user      Username
    * @param string $pass      Password
    * @param string $challenge The challenge supplied by the server.
    *                          this should be already base64_decoded.
    *
    * @return string The string to pass back to the server, of the form
    *                "<user> <digest>". This is NOT base64_encoded.
    */
    function getResponse($user, $pass, $challenge)
    {
        return $user . ' ' . $this->_HMAC_MD5($pass, $challenge);
    }
}

// +-----------------------------------------------------------------------+ 
// | Copyright (c) 2002-2003 Richard Heyes                                 | 
// | All rights reserved.                                                  | 
// |                                                                       | 
// | Redistribution and use in source and binary forms, with or without    | 
// | modification, are permitted provided that the following conditions    | 
// | are met:                                                              | 
// |                                                                       | 
// | o Redistributions of source code must retain the above copyright      | 
// |   notice, this list of conditions and the following disclaimer.       | 
// | o Redistributions in binary form must reproduce the above copyright   | 
// |   notice, this list of conditions and the following disclaimer in the | 
// |   documentation and/or other materials provided with the distribution.| 
// | o The names of the authors may not be used to endorse or promote      | 
// |   products derived from this software without specific prior written  | 
// |   permission.                                                         | 
// |                                                                       | 
// | THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS   | 
// | "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT     | 
// | LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR | 
// | A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT  | 
// | OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, | 
// | SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT      | 
// | LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, | 
// | DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY | 
// | THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT   | 
// | (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE | 
// | OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.  | 
// |                                                                       | 
// +-----------------------------------------------------------------------+ 
// | Author: Richard Heyes <richard@php.net>                               | 
// +-----------------------------------------------------------------------+ 
// 
// $Id: DigestMD5.php,v 1.1 2009/01/28 07:34:10 Prasanth Exp $

/**
* Implmentation of DIGEST-MD5 SASL mechanism
*
* @author  Richard Heyes <richard@php.net>
* @access  public
* @version 1.0
* @package Auth_SASL
*/

/*PJ require_once('Auth/SASL/Common.php');*/

class Auth_SASL_DigestMD5 extends Auth_SASL_Common
{
    /**
    * Provides the (main) client response for DIGEST-MD5
    * requires a few extra parameters than the other
    * mechanisms, which are unavoidable.
    * 
    * @param  string $authcid   Authentication id (username)
    * @param  string $pass      Password
    * @param  string $challenge The digest challenge sent by the server
    * @param  string $hostname  The hostname of the machine you're connecting to
    * @param  string $service   The servicename (eg. imap, pop, acap etc)
    * @param  string $authzid   Authorization id (username to proxy as)
    * @return string            The digest response (NOT base64 encoded)
    * @access public
    */
    function getResponse($authcid, $pass, $challenge, $hostname, $service, $authzid = '')
    {
        $challenge = $this->_parseChallenge($challenge);
        $authzid_string = '';
        if ($authzid != '') {
            $authzid_string = ',authzid="' . $authzid . '"'; 
        }

        if (!empty($challenge)) {
            $cnonce         = $this->_getCnonce();
            $digest_uri     = sprintf('%s/%s', $service, $hostname);
            $response_value = $this->_getResponseValue($authcid, $pass, $challenge['realm'], $challenge['nonce'], $cnonce, $digest_uri, $authzid);

            if ($challenge['realm']) {
                return sprintf('username="%s",realm="%s"' . $authzid_string  .
',nonce="%s",cnonce="%s",nc=00000001,qop=auth,digest-uri="%s",response=%s,maxbuf=%d', $authcid, $challenge['realm'], $challenge['nonce'], $cnonce, $digest_uri, $response_value, $challenge['maxbuf']);
            } else {
                return sprintf('username="%s"' . $authzid_string  . ',nonce="%s",cnonce="%s",nc=00000001,qop=auth,digest-uri="%s",response=%s,maxbuf=%d', $authcid, $challenge['nonce'], $cnonce, $digest_uri, $response_value, $challenge['maxbuf']);
            }
        } else {
            return PEAR::raiseError('Invalid digest challenge');
        }
    }
    
    /**
    * Parses and verifies the digest challenge*
    *
    * @param  string $challenge The digest challenge
    * @return array             The parsed challenge as an assoc
    *                           array in the form "directive => value".
    * @access private
    */
    function _parseChallenge($challenge)
    {
        $tokens = array();
        while (preg_match('/^([a-z-]+)=("[^"]+(?<!\\\)"|[^,]+)/i', $challenge, $matches)) {

            // Ignore these as per rfc2831
            if ($matches[1] == 'opaque' OR $matches[1] == 'domain') {
                $challenge = substr($challenge, strlen($matches[0]) + 1);
                continue;
            }

            // Allowed multiple "realm" and "auth-param"
            if (!empty($tokens[$matches[1]]) AND ($matches[1] == 'realm' OR $matches[1] == 'auth-param')) {
                if (is_array($tokens[$matches[1]])) {
                    $tokens[$matches[1]][] = preg_replace('/^"(.*)"$/', '\\1', $matches[2]);
                } else {
                    $tokens[$matches[1]] = array($tokens[$matches[1]], preg_replace('/^"(.*)"$/', '\\1', $matches[2]));
                }

            // Any other multiple instance = failure
            } elseif (!empty($tokens[$matches[1]])) {
                $tokens = array();
                break;

            } else {
                $tokens[$matches[1]] = preg_replace('/^"(.*)"$/', '\\1', $matches[2]);
            }

            // Remove the just parsed directive from the challenge
            $challenge = substr($challenge, strlen($matches[0]) + 1);
        }

        /**
        * Defaults and required directives
        */
        // Realm
        if (empty($tokens['realm'])) {
            $tokens['realm'] = "";
        }

        // Maxbuf
        if (empty($tokens['maxbuf'])) {
            $tokens['maxbuf'] = 65536;
        }

        // Required: nonce, algorithm
        if (empty($tokens['nonce']) OR empty($tokens['algorithm'])) {
            return array();
        }

        return $tokens;
    }

    /**
    * Creates the response= part of the digest response
    *
    * @param  string $authcid    Authentication id (username)
    * @param  string $pass       Password
    * @param  string $realm      Realm as provided by the server
    * @param  string $nonce      Nonce as provided by the server
    * @param  string $cnonce     Client nonce
    * @param  string $digest_uri The digest-uri= value part of the response
    * @param  string $authzid    Authorization id
    * @return string             The response= part of the digest response
    * @access private
    */    
    function _getResponseValue($authcid, $pass, $realm, $nonce, $cnonce, $digest_uri, $authzid = '')
    {
        if ($authzid == '') {
            $A1 = sprintf('%s:%s:%s', pack('H32', md5(sprintf('%s:%s:%s', $authcid, $realm, $pass))), $nonce, $cnonce);
        } else {
            $A1 = sprintf('%s:%s:%s:%s', pack('H32', md5(sprintf('%s:%s:%s', $authcid, $realm, $pass))), $nonce, $cnonce, $authzid);
        }
        $A2 = 'AUTHENTICATE:' . $digest_uri;
        return md5(sprintf('%s:%s:00000001:%s:auth:%s', md5($A1), $nonce, $cnonce, md5($A2)));
    }

    /**
    * Creates the client nonce for the response
    *
    * @return string  The cnonce value
    * @access private
    */
    function _getCnonce()
    {
        if (file_exists('/dev/urandom') && $fd = @fopen('/dev/urandom', 'r')) {
            return base64_encode(fread($fd, 32));

        } elseif (file_exists('/dev/random') && $fd = @fopen('/dev/random', 'r')) {
            return base64_encode(fread($fd, 32));

        } else {
            $str = '';
            mt_srand((double)microtime()*10000000);
            for ($i=0; $i<32; $i++) {
                $str .= chr(mt_rand(0, 255));
            }
            
            return base64_encode($str);
        }
    }
}

// +-----------------------------------------------------------------------+ 
// | Copyright (c) 2002-2003 Richard Heyes                                 | 
// | All rights reserved.                                                  | 
// |                                                                       | 
// | Redistribution and use in source and binary forms, with or without    | 
// | modification, are permitted provided that the following conditions    | 
// | are met:                                                              | 
// |                                                                       | 
// | o Redistributions of source code must retain the above copyright      | 
// |   notice, this list of conditions and the following disclaimer.       | 
// | o Redistributions in binary form must reproduce the above copyright   | 
// |   notice, this list of conditions and the following disclaimer in the | 
// |   documentation and/or other materials provided with the distribution.| 
// | o The names of the authors may not be used to endorse or promote      | 
// |   products derived from this software without specific prior written  | 
// |   permission.                                                         | 
// |                                                                       | 
// | THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS   | 
// | "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT     | 
// | LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR | 
// | A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT  | 
// | OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, | 
// | SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT      | 
// | LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, | 
// | DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY | 
// | THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT   | 
// | (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE | 
// | OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.  | 
// |                                                                       | 
// +-----------------------------------------------------------------------+ 
// | Author: Richard Heyes <richard@php.net>                               | 
// +-----------------------------------------------------------------------+ 
// 
// $Id: SASL.php,v 1.1 2009/01/28 07:34:10 Prasanth Exp $

/**
* Client implementation of various SASL mechanisms 
*
* @author  Richard Heyes <richard@php.net>
* @access  public
* @version 1.0
* @package Auth_SASL
*/

/*PJ: require_once('PEAR.php');*/

class Auth_SASL
{
    /**
    * Factory class. Returns an object of the request
    * type.
    *
    * @param string $type One of: Anonymous
    *                             Plain
    *                             CramMD5
    *                             DigestMD5
    *                     Types are not case sensitive
    */
    function &factory($type)
    {
        switch (strtolower($type)) {
            case 'anonymous':
                $filename  = 'Auth/SASL/Anonymous.php';
                $classname = 'Auth_SASL_Anonymous';
                break;

            case 'login':
                $filename  = 'Auth/SASL/Login.php';
                $classname = 'Auth_SASL_Login';
                break;

            case 'plain':
                $filename  = 'Auth/SASL/Plain.php';
                $classname = 'Auth_SASL_Plain';
                break;

            case 'crammd5':
                $filename  = 'Auth/SASL/CramMD5.php';
                $classname = 'Auth_SASL_CramMD5';
                break;

            case 'digestmd5':
                $filename  = 'Auth/SASL/DigestMD5.php';
                $classname = 'Auth_SASL_DigestMD5';
                break;

            default:
                return PEAR::raiseError('Invalid SASL mechanism type');
                break;
        }

        /*PJ require_once($filename);*/
        $obj = new $classname();
        return $obj;
    }
}


////////Mailer/////////////////////
class FM_Mailer
{
   var $config;
    var $logger;
    var $attachments;

   function FM_Mailer(&$config,&$logger)
   {
      $this->config = &$config;
      $this->logger = &$logger;
      $this->attachments=array();
   }
   
   function SendTextMail($from,$to,$subject,$mailbody)
   {
      $this->SendMail($from,$to,$subject,$mailbody,false);
   }
   
   function SendHtmlMail($from,$to,$subject,$mailbody)
   {
      $this->SendMail($from,$to,$subject,$mailbody,true);
   }
   
   function SendMail($from,$to,$subject,$mailbody,$htmlformat)
   {
      $real_from='';
      $reply_to='';
      if(true == $this->config->variable_from)
      {
         $real_from = $from;
      }
      else
      {
         $real_from = $this->config->from_addr;
         $reply_to = $from;
      }

      $hdrs = array(
                  'From'    => $real_from,
                  'Sender'  => $real_from,
                  'Subject' => $subject,
                  'To' => $to
                  );

      if(!empty($reply_to))
      {
         $hdrs['Reply-To'] = $reply_to;
      }

      $this->DefinePHPEOL();
      
      $mime = new Mail_mime(PHP_EOL);

      $mailbody = str_replace("\r","",$mailbody);
      $mailbody = str_replace("\n",PHP_EOL,$mailbody);

      if(true == $htmlformat) 
      {
         $mime->setHTMLBody($mailbody);
      }
      else
      {
         $mime->setTxtBody($mailbody);
      }

      foreach($this->attachments as $file)
      {
         $mime->addAttachment($file['filepath'],$file['mime_type']);
      }

      $body =  $mime->get(array(
            'head_encoding'=>'base64',
            'html_encoding'=>'8bit',
            'html_charset'=>'UTF-8',
            'text_charset'=>'UTF-8',
            'head_charset'=>'UTF-8',
            'text_encoding'=>'8bit'
            ));

      if(!$this->CheckHeaders($hdrs))
      {
         $this->logger->LogError("Email to:$to subject:$subject aborted since it failed header validation");
         return;
      }

      $headers = $mime->headers($hdrs);

      $params = array();

      //Email addresses of the form Name<email>
      if (strtoupper(substr(PHP_OS, 0, 3) == 'WIN')) 
      {
         $match = array();
         if(preg_match("/(.*?)<(.*?)>/",$to,$match))
         {
            $to = $match[2];
            $to = trim($to);
         }
      }

      $method = 'mail';

      if($this->config->use_smtp)
      {
         $method = 'smtp';
         $params = array('host'=> $this->config->smtp_host );
         if(!empty($this->config->smtp_uname))
         {
             $params['auth']=true;
             $params['username'] = $this->config->smtp_uname;
             $params['password'] = sfm_crypt_decrypt($this->config->smtp_pwd,
                                         $this->config->encr_key);
         }

         $params['port'] = $this->config->smtp_port;
      }

      $mail_object = &Mail::factory($method,$params);

      if($mail_object)
      {
         $mail_object->send($to, $headers, $body);
      } 
   }  
   

    function CheckHeaders(&$headers)
    {
        foreach ($headers as $key => $value) 
        {
            $value = trim($value);
            $headers[$key] = $value;

            if($this->IsInjected($value))
            {
                $this->logger->LogError("Suspicious email header: $key -> $value. Aborting email attempt");
                return false;
            }
        }
        return true;
    }


    function IsInjected($str) 
    {
       $injections = array('(\n+)',
                   '(\r+)',
                   '(\t+)',
                   '(%0A+)',
                   '(%0D+)',
                   '(%08+)',
                   '(%09+)'
                   );
       $inject = join('|', $injections);
       $inject = "/$inject/i";
       if(preg_match($inject,$str)) 
        {
          return true;
       }
       else 
        {
          return false;
       }
    }


    function AttachFile($filepath,$type)
    {
        $this->attachments[]=array('filepath'=>$filepath,'mime_type'=>$type);
    }
    

    function DefinePHPEOL()
    {
      if (!defined('PHP_EOL')) 
      {
         switch (strtoupper(substr(PHP_OS, 0, 3))) 
         {
         // Windows
         case 'WIN':
             define('PHP_EOL', "\r\n");
             break;

         // Mac
         case 'DAR':
             define('PHP_EOL', "\r");
             break;

         // Unix
         default:
             define('PHP_EOL', "\n");
         }
      }
    }
}

////////ComposedMailSender/////////////
class FM_ComposedMailSender extends FM_Module
{
	var $config;
	var $formvars;
	var $message_subject;
	var $message_body;	
    var $mailer;
	function FM_ComposedMailSender()
	{
        $this->mailer = NULL;
	}
	
    function InitMailer()
    {
        $this->mailer = new FM_Mailer($this->config,$this->logger);
    }

	function ComposeMessage($subj_templ,$mail_templ)
	{
		$ret = false;
        $this->message_subject = $subj_templ;

		$templ_page = $mail_templ;
		if(strlen($templ_page)>0)
		{
			$composer = new FM_PageMerger();
        
			$tmpdatamap = $this->formvars;

			if(true == $this->config->email_format_html)
			{
				convert_html_entities_in_formdata("",$tmpdatamap);
			}
			
            $ret = true;
			if(false == $composer->Merge($templ_page,$tmpdatamap))
            {
                $ret = false;
                $this->logger->LogError("MailComposer: merge failed");
            }
			$this->message_body = $composer->getMessageBody();

            $subj_merge = new FM_PageMerger();
            $subj_merge->Merge($this->message_subject,$this->formvars);
            $this->message_subject = $subj_merge->getMessageBody();
		}
		return $ret;
	}//ComposeMessage	
	
	function SendMail($from,$to)
	{
        if(NULL == $this->mailer)
        {
            $this->logger->LogError("mail composer: not initialized");
            return false;
        }
		if(false== $this->config->email_format_html)
		{
            $this->logger->LogInfo("mail composer: sending text mail");
			$this->mailer->SendTextMail($from,$to,
									$this->message_subject,
									$this->message_body);
		}
		else
		{
            $this->logger->LogInfo("mail composer: sending HTML mail");
			$this->mailer->SendHtmlMail($from,$to,
									$this->message_subject,
									$this->message_body);				
		}	
	}
}//

////////FormDataSender/////////////
class FM_FormDataSender extends FM_ComposedMailSender 
{
    var $mail_subject;
    var $mail_template;
    var $dest_list;
    var $mail_from;
    var $file_upload;
    var $attach_files;
	function FM_FormDataSender($subj="",$templ="",$from="")
	{
        $this->mail_subject=$subj;
        $this->mail_template=$templ;
        $this->dest_list=array();
        $this->mail_from=$from;
        $this->file_upload=NULL;
        $this->attach_files = true;
	}
	
    function SetFileUploader(&$fileUploader)
    {
        $this->file_upload = &$fileUploader;
    }

    function AddToAddr($toaddr)
    {
        array_push($this->dest_list,$toaddr);
    }
    
    function SetAttachFiles($attach_files)
    {
        $this->attach_files = $attach_files;
    }

	function SendFormData()
	{
        $this->InitMailer();

		$this->ComposeMessage($this->mail_subject,
					$this->mail_template);

        if($this->attach_files && NULL != $this->file_upload )
        {
            $this->file_upload->AttachFiles($this->mailer);
        }

        $from_merge = new FM_PageMerger();
        $from_merge->Merge($this->mail_from,$this->formvars);
        $this->mail_from = $from_merge->getMessageBody();
					
		foreach($this->dest_list as $to_address)
		{
            if(!$this->ext_module->BeforeSendingFormSubmissionEMail($to_address,
                        $this->message_subject,$this->message_body))
            {
                $this->logger->LogInfo("Extension module prevented sending email to: $to_address");
                continue;
            }
            $this->logger->LogInfo("sending form data to: $to_address");
            $this->SendMail($this->mail_from,
					$to_address);
		}		
	}

    function Process(&$continue)
    {
        if(strlen($this->mail_template)<=0||
           count($this->dest_list)<=0)
        {
            return false;
        }
        $continue = true;
        $this->SendFormData();
        return true;
    }
}

////////AutoResponseSender/////////////
class FM_AutoResponseSender extends FM_ComposedMailSender
{
    var $subject;
    var $template;
    var $namevar;
    var $emailvar;

	function FM_AutoResponseSender($subj="",$templ="")
	{
        $this->subject = $subj;
        $this->template= $templ;
        $this->namevar= "";
        $this->emailvar= "";
	}	
	
    function SetToVariables($name_var,$email_var)
    {
        $this->namevar= $name_var;
        $this->emailvar= $email_var;
    }

	function SendAutoResponse()
	{
        $name_val = 
            $this->formvars[$this->namevar];

        $email_val = 
            $this->formvars[$this->emailvar];

        $email_val = trim($email_val);

        if(empty($email_val))
        {
            $this->logger->LogError("Email value is empty. Didn't send auto-response");
            return;
        }

        $this->InitMailer();

		$this->ComposeMessage($this->subject,$this->template);

       
		$name_val = trim($name_val);
        
        $to_var ='';

        if(!empty($name_val))
        {
            $to_var = "$name_val<$email_val>";
        }
        else
        {
            $to_var = $email_val;
        }
        
        if(!$this->ext_module->BeforeSendingAutoResponse($to_var,
                        $this->message_subject,$this->message_body))
         {
            $this->logger->LogInfo("The extension module stopped the auto-response to: $to_var");
            return;
         }
         
        $this->logger->LogInfo("sending auto response to: $to_var");

        $this->SendMail($this->config->from_addr,$to_var);
	}

    function Process(&$continue)
    {
        if(strlen($this->template)<=0||
           strlen($this->emailvar)<=0)
        {
            $this->logger->LogError("auto response: template or emailvar is empty!");
            return false;
        }
        $continue = true;
        $this->SendAutoResponse();
        return true;
    }
	
}


class FM_ThankYouPage extends FM_Module
{
    var $page_templ;
    var $redir_url;

    function FM_ThankYouPage($page_templ="")
    {
        $this->page_templ=$page_templ;
        $this->redir_url="";
    }
    
    function Process()
    {
        $ret = true;
		if(false === $this->ext_module->FormSubmitted($this->formvars))
		{
			$ret = false;
		}
		else
        if(strlen($this->page_templ)>0)
        {
            $this->logger->LogInfo("Displaying thank you page");
            $ret = $this->ShowPage();
        }
        else
        if(strlen($this->redir_url)>0)
        {
            $this->logger->LogInfo("Redirecting to thank you URL");
            $ret = $this->Redirect($this->redir_url);
        }
		
        return $ret;
    }

    function SetRedirURL($url)
    {
        $this->redir_url=$url;
    }

    function ShowPage()
    {
        $content = $this->page_templ;
        
		$merge = new FM_PageMerger();
    
		$tmpdatamap = $this->formvars;

		convert_html_entities_in_formdata("",$tmpdatamap);

        $this->ext_module->BeforeThankYouPageDisplay($tmpdatamap);
		
        $page = "<H1>Error!</H1>";
        $ret = true;
        if(false == $merge->Merge($content,$tmpdatamap))
        {
            $this->logger->LogError("ThankYouPage: merge failed");
            $ret = false;
        }

		$page = $merge->getMessageBody();
		header("Content-Type: text/html");
        echo $page;
        return $ret;
    }

    function Redirect($url)
    {
        if(isset($_SESSION['sfm_from_iframe']) && 
           $_SESSION['sfm_from_iframe'] == '1')
        {
            //The code below is put in so that it works with iframe-embedded forms also
            $script = "window.open(\"$url\",\"_top\");";
            $noscript = "<a href=\"$url\" target=\"_top\">Submitted the form successfully. Click here to redirect</a>";

            $page = "<html><head></head><body>".
            "<script language='JavaScript'>$script</script><noscript>$noscript</noscript>".
            "</body></html>";
            header("Content-Type: text/html");
            echo $page;
        }
        else
        {
            header("Location: $url");
        }
        exit;
    }
}//FM_ThankYouPage

////Strings//////////////
define("E_VAL_REQUIRED_VALUE","Please enter the value for %s");
define("E_VAL_MAXLEN_EXCEEDED","Maximum length exceeded for %s.");
define("E_VAL_MAXLEN_EXTRA_INFO","[Maximum length:%d current length:%d]");
define("E_VAL_MINLEN_EXTRA_INFO","[Minimum length:%d current length:%d]");
define("E_VAL_MINLEN_CHECK_FAILED","Please enter input with length more than %d for %s");

define("E_VAL_ALNUM_CHECK_FAILED","Please provide an alpha-numeric input for %s");
define("E_VAL_ALNUM_S_CHECK_FAILED","Please provide an alpha-numeric input for %s");
define("E_VAL_NUM_CHECK_FAILED","Please provide numeric input for %s");
define("E_VAL_ALPHA_CHECK_FAILED","Please provide alphabetic input for %s");
define("E_VAL_ALPHA_S_CHECK_FAILED","Please provide alphabetic input for %s");
define("E_VAL_EMAIL_CHECK_FAILED","Please provide a valida email address");
define("E_VAL_NUMERIC_VALUE_EXPECTED","Numeric value required.");
define("E_VAL_LESSTHAN_CHECK_FAILED","Enter a value less than %f for %s");
define("E_VAL_GREATERTHAN_CHECK_FAILED","Enter a value greater than %f for %s");
define("E_VAL_REGEXP_CHECK_FAILED","Please provide a valid input for %s");
define("E_VAL_DONTSEL_CHECK_FAILED","Wrong option selected for %s");
define("E_VAL_SELMIN_CHECK_FAILED","Please select minimum %d options for %s");
define("E_VAL_SELMAX_CHECK_FAILED","Please select maximum %d options for %s");
define("E_VAL_SELONE_CHECK_FAILED","Please select an option for %s");
define("E_VAL_EQELMNT_CHECK_FAILED","Value of %s should be same as that of %s");
define("E_VAL_LTELMNT_CHECK_FAILED","Value of %s should be less than that of %s");
define("E_VAL_LEELMNT_CHECK_FAILED","Value of %s should be less than or equal to that of %s");
define("E_VAL_GTELMNT_CHECK_FAILED","Value of %s should be greater that of %s");
define("E_VAL_GEELMNT_CHECK_FAILED","Value of %s should be greater or equal to that of %s");
define("E_VAL_NEELMNT_CHECK_FAILED","Value of %s should not be same as that of %s");
define("E_VAL_VALIDATION_FAILED","Error: input validation failed.");
define("E_CONF_FORM_NAME_NOT_SET","Please set the form name");
define("E_CONF_COULD_NOT_OPEN_VALIDATION_FILE","Could not open the validation file for the form");
define("E_CONF_COULD_NOT_OPEN_OUTPUT_DATA_FILE","Failed Opening Output data file for saving the data from the Form");
define("E_CONF_FORM_VAR_NOT_SET","Form variable not set. You need to set a variable that indicates the form to  process");
define("E_CONF_FORM_VAR_NOT_CONFIGURED","The name of the variable that will contain the form name is not configured");
define("E_CONF_CONFIGURATION_FILE_NOT_FOUND","The configuration file for this form is not found");
define("E_CONF_ERROR_INTERNAL","There was a configuration error.");
define("E_CONF_ERROR_DEBUG_MODE_INFO","Enable debug mode ('Form processing options' page) for displaying errors.");
define("E_CONF_ERROR_TS_LINK",
    " <a href='http://www.simfatic.com/forms/troubleshoot/checksol.php?err=%s'>Click here</a> for troubleshooting information.");

define("E_CONF_ERROR_LOGGED","The error is logged.");
define("E_CONF_ERROR_NOT_LOGGED","Could not log the error");
define("E_CONF_ERROR_FILE_RENAME_FAILED","Failed renaming file %s new name %s");
define("E_CONF_ERROR_MAKE_FORMDATA_FOLDER","Failed making form data folder %s");
define("E_CONF_ERROR_FORM_DATA_FOLDER_EMPTY","The name of the form data folder provided is empty!");
define("E_CONF_ERROR_WRITE_FORMDATA_FOLDER","Error creating file in the form data folder: %s");
define("E_CONF_FORM_DATA_VAR_NOT_ALLOWED","The variable %s is reserved as an internal variable. ".
      "This variable can't be used as a form variable");
define("E_CONF_ERROR_FORMPAGE_MERGE","Failed merging form page");
define("E_CONF_FORM_NOT_INSTALLED_TITLE","The form is not installed yet.");
define("E_CONF_FORM_NOT_INSTALLED","The form is not installed yet. ".
                " If you uploaded the form manually, copy paste ".
                " the URL of the form to the Form URL box and press the ".
                " 'Install Form' button. It is required to Install the ".
                " form before you can use the form. ");
define("E_CONF_FORM_KEY_NOT_SET","Form key is not set");
define("E_CONF_FORM_ID_NOT_SET","Form ID is not set");
define("E_CONF_FORM_ID_NOT_MATCH","Form ID Does not match");
define("E_CONF_FILE_NAME_NOTSET","Name of the file to be downloaded is not mentioned");
define("E_CONF_FAIL_OPEN_FILE","Failed opening file %s");
define("E_ERR_NO_FILE_UPLOAD","No file uploads present for this form");
define("E_ERR_FAIL_OPEN_UPLD_FOLDER","Failed getting list of files from upload folder(%s)");
define("E_ERR_NO_FILE_UPLOAD_OBJ_SET","File upload object is not set.");
define("E_ERR_FAIL_READING_SCRIPT","Form Installer: failed reading the script.");
define("E_ERR_FAIL_FIND_MARKERS","Form Installer: could not find the markers");
define("E_ERR_FAIL_GET_FILE_LIST","Failed getting file list from directory: %s");
define("E_ERR_FAIL_WRITING_TO_SCRIPT","Failed opening the script: %s for writing. ".
"Please update the access permissions of the file (chmod to 777).".
" You can revert the permissions back after installing the script.");

define("E_VAL_REQ_FILE_INPUT","A file need to be uploaded for %s");
define("E_VAL_FILESIZE_EXCEEDED","%s: The file has exceeded the size limit %d");
define("E_VAL_EXTN_NOTALLOWED","%s: Only the following types of file extensions are allowed: %s");
define("E_VAL_SHOULD_SEL_CHECK_FAILED","You should select option %s for %s");
define("E_CONFIG_FILE_EXISTS","There exists a file with name %s. ".
            "Simfatic Forms needs to save configuration information in this file.".
            " Remove/rename the file %s and try again.");

define("E_CONF_NO_FORM_FOLDER","Config Error: No Form data folder is set; but tried to access form data folder");

define("ERROR_EMAIL_BODY","Error occured in form %s.\n\n%s\n\nLog:\n%s");
define("ERROR_EMAIL_SUBJ","Error occured in form %s.");


define("CONST_PHP_TAG_START","<"."?"."PHP");
define("CONST_PHP_TAG_END","?".">");

//////GlobalData//////////
class FM_GlobalData
{
   var $get_vars;
   var $post_vars;
   var $server_vars;
    var $files;
    var $formvars;
    var $saved_data_varname;
    var $config;

   function FM_GlobalData(&$config)
   {
      $this->get_vars   =NULL;
      $this->post_vars =NULL;    
      $this->server_vars   =NULL;
        $this->files=NULL;
        $this->formvars=NULL;
        $this->saved_data_varname="sfm_saved_formdata_var";
        $this->config = &$config;
   }
   function GetGlobalVars() 
   {
        global $HTTP_GET_VARS, $HTTP_POST_VARS, $HTTP_SERVER_VARS,$HTTP_POST_FILES;
        $parser_version = phpversion();
        if ($parser_version <= "4.1.0") 
        {
            $this->get_vars   = $HTTP_GET_VARS;
            $this->post_vars  = $HTTP_POST_VARS;
            $this->server_vars= $HTTP_SERVER_VARS;
            $this->files = $HTTP_POST_FILES;
        }
        if ($parser_version >= "4.1.0")
        {
            $this->get_vars    = $_GET;
            $this->post_vars   = $_POST;
            $this->server_vars= $_SERVER;
            $this->files = $_FILES;
        }

        if(strcmp($this->server_vars['REQUEST_METHOD'],'POST')==0)
        {
            $this->formvars = $this->get_post_vars();
        }
        else
        {
            $this->formvars = $this->get_get_vars();
        }

        magicQuotesRemove($this->formvars);

        

        if(isset($this->formvars[$this->saved_data_varname]))
        {
            $this->LoadFormDataFromSession();
        }
           
        $this->formvars[$this->config->visitor_ip_var] = 
                        $this->server_vars['REMOTE_ADDR'];

        $this->formvars[$this->config->submission_time_var]= 
                        date($this->config->getCommonDateTimeFormat());

        $this->formvars[$this->config->submission_date_var] = date($this->config->common_date_format);

        $this->formvars[$this->config->referer_page_var] =  $this->get_form_referer();

        $ua = $this->server_vars['HTTP_USER_AGENT'];

        $this->formvars[$this->config->user_agent_var] = $ua;

        $this->formvars[$this->config->visitors_os_var] = $this->DetectOS($ua);

        $this->formvars[$this->config->visitors_browser_var] = $this->DetectBrowser($ua);
   }

    function get_form_referer()
    {
        if(isset($_SESSION['sfm_referer_page']))
        {
           return  $_SESSION['sfm_referer_page'];
        }
        else
        {
            return $this->get_referer();
        }
    }

    function SetFormVar($name,$value)
    {
        $this->formvars[$name] = $value;
    }
    
    function LoadFormDataFromSession()
    {
        $varname = $this->formvars[$this->saved_data_varname];

         if(isset($_SESSION[$varname]))
         {
            $this->formvars = 
               array_merge($this->formvars,$_SESSION[$varname]);

            unset($_SESSION[$varname]);
            unset($_SESSION[$this->saved_data_varname]);
         }
    }

    function SaveFormDataToSession()
    {
        $varname = "sfm_form_var_".rand(1,1000)."_".rand(2,2000);

        $_SESSION[$varname] = $this->formvars;

        unset($_SESSION[$varname][$this->config->form_submit_variable]);

        return $varname;
    }

    function get_post_vars()
    {
        return $this->post_vars;
    }
    function get_get_vars()
    {
        return $this->get_vars;
    }

    function get_php_self() 
    {
        return htmlentities($this->server_vars['PHP_SELF']);
    }

    function get_referer()
    {
        return $this->server_vars['HTTP_REFERER'];
    }

    function DetectOS($user_agent)
    {
        //code by Andrew Pociu
        $OSList = array
        (
            'Windows 3.11' => 'Win16',

            'Windows 95' => '(Windows 95)|(Win95)|(Windows_95)',

            'Windows 98' => '(Windows 98)|(Win98)',

            'Windows 2000' => '(Windows NT 5\.0)|(Windows 2000)',

            'Windows XP' => '(Windows NT 5\.1)|(Windows XP)',

            'Windows Server 2003' => '(Windows NT 5\.2)',

            'Windows Vista' => '(Windows NT 6\.0)',

            'Windows 7' => '(Windows NT 7\.0)|(Windows NT 6\.1)',

            'Windows NT 4.0' => '(Windows NT 4\.0)|(WinNT4\.0)|(WinNT)|(Windows NT)',

            'Windows ME' => '(Windows 98)|(Win 9x 4\.90)|(Windows ME)',

            'Open BSD' => 'OpenBSD',

            'Sun OS' => 'SunOS',

            'Linux' => '(Linux)|(X11)',

            'Mac OS' => '(Mac_PowerPC)|(Macintosh)',

            'QNX' => 'QNX',

            'BeOS' => 'BeOS',

            'OS/2' => 'OS/2',

            'Search Bot'=>'(nuhk)|(Googlebot)|(Yammybot)|(Openbot)|(Slurp)|(MSNBot)|(Ask Jeeves/Teoma)|(ia_archiver)'
        );

        foreach($OSList as $CurrOS=>$Match)
        {
            if (preg_match("#$Match#i", $user_agent))
            {
                break;
            }
        }

        return $CurrOS;        
    }


    function DetectBrowser($agent) 
    {
        $ret ="";
        $browsers = array("firefox", "msie", "opera", "chrome", "safari",
                            "mozilla", "seamonkey",    "konqueror", "netscape",
                            "gecko", "navigator", "mosaic", "lynx", "amaya",
                            "omniweb", "avant", "camino", "flock", "aol");

        $agent = strtolower($agent);
        foreach($browsers as $browser)
        {
            if (preg_match("#($browser)[/ ]?([0-9.]*)#", $agent, $match))
            {
                $br = $match[1];
                $ver = $match[2];
                if($br =='safari' && preg_match("#version[/ ]?([0-9.]*)#", $agent, $match))
                {
                    $ver = $match[1];
                }
                $ret = ($br=='msie')?'Internet Explorer':ucfirst($br);
                $ret .= " ". $ver;
                break ;
            }
        }
        return $ret;
    }

}

class FM_ElementInfo
{
   var $elements;
   var $default_values;
      function FM_ElementInfo()
   {
      $this->elements = array();
   }
   function AddElementInfo($name,$type,$extrainfo="")
   {
      $this->elements[$name]["type"] = $type;
        $this->elements[$name]["extra"] = $extrainfo;
   }
   function AddDefaultValue($name,$value)
   {
      $this->default_values[$name] = $value;
   }
   function IsElementPresent($name)
   {
      return isset($this->elements[$name]);
   }
   function GetType($name)
   {
      return $this->elements[$name]["type"];
   }
    function GetExtraInfo($name)
    {
        return $this->elements[$name]["extra"];
    }
}

/////Config/////
class FM_Config
{
    var $msg_log_enabled;
    var $form_submit_variable; 
    var $form_page_code;
    var $error_display_variable;
    var $display_error_in_formpage;
    var $error_page_code;
    var $email_format_html;
    var $slashn;
    var $installed;
    var $log_flush_live;
    var $encr_key;
    var $form_id;
    var $sys_debug_mode;
    var $error_mail_to;
    var $use_smtp;
    var $smtp_host;
    var $smtp_uname;
    var $smtp_pwd;
    var $from_addr;
    var $variable_from;
    var $common_date_format;
    var $element_info;    
    
//User configurable (through extension modules)  
    var $form_file_folder;//location to save csv file, log file etc
    var $load_values_from_url;
    var $allow_nonsecure_file_attachments;
    var $file_upload_folder;
    var $debug_mode;    
    var $logfile_size;    

    function FM_Config()
    {
        $this->form_file_folder="";
        $this->file_upload_folder='';
        $this->installed = false;
        $this->msg_log_enabled = true;
        $this->form_submit_variable   ="sfm_form_submitted";
        $this->form_page_code="<HTML><BODY><H1>Error! code 104</h1>%sfm_error_display_loc%</body></HTML>";
        $this->error_display_variable = "sfm_error_display_loc";
        $this->show_errors_single_box = false;
        $this->self_script_variable = "sfm_self_script";
        $this->form_filler_variable="sfm_form_filler_place";
        $this->confirm_file_list_var = "sfm_file_uploads";
        $this->config_update_var = "sfm_conf_update";
        $this->config_update_val = "sfm_conf_update_val";
        $this->config_form_id_var = "sfm_form_id";
        $this->visitor_ip_var = "_sfm_visitor_ip_";
        $this->submission_time_var ="_sfm_form_submision_time_";
        $this->submission_date_var = "_sfm_form_submision_date_";
        $this->referer_page_var = "_sfm_referer_page_";
        $this->user_agent_var = "_sfm_user_agent_";
        $this->visitors_os_var = "_sfm_visitor_os_";
        $this->visitors_browser_var = "_sfm_visitor_browser_";
        $this->display_error_in_formpage=true;
        $this->error_page_code  ="<HTML><BODY><H1>Error!</h1>%sfm_error_display_loc%</body></HTML>";
        $this->email_format_html=false;
        $this->slashn = "\r\n";
        $this->logfile_size=5120;//In KBs
        $this->log_flush_live=false;
        $this->encr_key="";
        $this->form_id="";
        $this->error_mail_to="";
        $this->sys_debug_mode = false;
        $this->debug_mode = false;
        $this->element_info = new FM_ElementInfo();
        $this->use_smtp = false;
        $this->smtp_host='';
        $this->smtp_uname='';
        $this->smtp_pwd='';
        $this->smtp_port='';
        $this->from_addr='';
        $this->variable_from=false;
        $this->common_date_format = 'Y-m-d';
        $this->load_values_from_url = false;
        $this->allow_nonsecure_file_attachments = false;
    }
    
    function set_encrkey($key)
    {
        $this->encr_key=$key;
    }
    
    function set_form_id($form_id)
    {
        $this->form_id = $form_id;
    }
    function set_error_email($email)
   {
      $this->error_mail_to = $email;
   }
    function get_form_id()
    {
        return $this->form_id;
    }

   function setFormPage($formpage)
   {
      $this->form_page_code = $formpage;
   }

   function setDebugMode($enable)
   {
      $this->debug_mode = $enable;
        $this->log_flush_live = $enable?true:false;
   }

    function getCommonDateTimeFormat()
    {
        return $this->common_date_format." H:i:s T(O \G\M\T)";
    }

    function getFormConfigIncludeFileName($script_path,$form_name)
    {
        $dir_name = dirname($script_path);
        
        $conf_file = $dir_name."/".$form_name."_conf_inc.php";

        return $conf_file;
    }

    function getConfigIncludeSign()
    {
        return "//{__Simfatic Forms Config File__}";
    }

    function InitSMTP($host,$uname,$pwd,$port)
    {
        $this->use_smtp = true;
        $this->smtp_host=$host;
        $this->smtp_uname=$uname;
        $this->smtp_pwd=$pwd;
        $this->smtp_port = $port;
    }
   
}

/////Logger/////
class FM_Logger
{
   var $config;
   var $globaldata;
    var $log_file_path;
    var $formname;
    var $log_filename;
    var $whole_log;
   var $is_enabled;

   function FM_Logger(&$globaldata,&$config,$formname)
   {
      $this->config = &$config;
      $this->globaldata = &$globaldata;
        $this->formname = $formname;
        $this->log_filename="";
        $this->whole_log="";
      $this->is_enabled = false;
        $this->log_flushed = false;
   }   
   
   function EnableLogging($enable)
   {
      $this->is_enabled = $enable;
   }

    function CreateFileName()
    {
        $ret=false;
        $filename ="";
        if(strlen($this->log_filename)> 0)
        {
            $filename = $this->log_filename;
        }
        else
        if(strlen($this->config->get_form_id())>0)
        {
            $form_id_part = substr($this->config->get_form_id(),0,8);

            $filename = $this->formname.'-'.$form_id_part.'-log.php';
        }
        else
        {
            return false;
        }

        if(strlen($this->config->form_file_folder)>0)
        {
            $this->log_file_path = sfm_make_path($this->config->form_file_folder,
                                        $filename);
            $ret = true;
        }
        else
        {
            $this->log_file_path ="";
            $ret=false;
        }
        return $ret;
    }
   
   function LogString($string,$type)
   {
      $bret = false;
      $t_log = "\n";
        $t_log .= $this->globaldata->server_vars['REMOTE_ADDR']."|";
        
        $t_log .= date("Y-m-d h:i:s A|");
      $t_log .= "$type| ";
      $string = str_replace("\n","\\n",$string);      
      $t_log .= $string;

      if($this->is_enabled && $this->config->log_flush_live)
      {
         $bret = $this->writeToFile($t_log);
      }

        $this->whole_log .= $t_log;
        return $bret;
   }

    function FlushLog()
    {
        if($this->is_enabled && 
        !$this->log_flushed &&
        !$this->config->log_flush_live)
        {
            $this->LogInfo("Flushing the log ...");
            $this->writeToFile($this->get_log());
            $this->log_flushed = true;
        }
    }

    function print_log()
    {
        echo $this->whole_log;
    }

   function get_log()
   {
      return $this->whole_log;
   }

    function get_log_file_path()
    {
        if(strlen($this->log_file_path)<=0)
        {
            if(!$this->CreateFileName())
            {
                return "";
            }
        }
        return $this->log_file_path;
    }
   
    function writeToFile($t_log)
    {
        $this->get_log_file_path();

        if(strlen($this->log_file_path)<=0){ return false;}

        $fp =0;
        $create_file=false;

        if(file_exists($this->log_file_path))
        {
            $maxsize= $this->config->logfile_size * 1024;
            if(filesize($this->log_file_path) >= $maxsize)
             {
                $create_file = true;
             }
        }
        else
        {
           $create_file = true;
        }

        $ret = true;
        $file_maker = new SecureFileMaker($this->GetFileSignature());
        if(true == $create_file)
        {
            $ret = $file_maker->CreateFile($this->log_file_path,$t_log);
        }
        else
        {
            $ret = $file_maker->AppendLine($this->log_file_path,$t_log);
        }
      
      return $ret;
    }

    function GetFileSignature()
    {
        return "--Simfatic Forms Log File--";
    }

   function LogError($string)
   {
      return $this->LogString($string,"error");
   }
   
   function LogInfo($string)
   {
      if(false == $this->config->msg_log_enabled)     
      {
         return true;
      }
      return $this->LogString($string,"info");
   }
}
 
////ErrorHandler///////////
class FM_ErrorHandler
{
   var $logger;
   var $config;
    var $globaldata;
   var $formname;
   var $sys_error;
   var $disable_syserror_handling;
   var $formvars;
   
    function FM_ErrorHandler(&$logger,&$config,&$globaldata,$formname)
    {
      $this->logger = &$logger;
      $this->config = &$config;
        $this->globaldata = &$globaldata;
      $this->formname  = $formname;
      $this->sys_error="";
      $this->enable_error_formpagemerge=false;
    }
   
   function SetFormVars(&$formvars)
   {
      $this->formvars = &$formvars;
   }

    function InstallConfigErrorCatch()
    {
        set_error_handler(array(&$this, 'sys_error_handler'));
    }

   function EnableErrorFormMerge()
   {
      $this->enable_error_formpagemerge = true;
   }
   
   function GetLastSysError()
   {
      return $this->sys_error;
   }

   function IsSysError()
   {
      if(strlen($this->sys_error)>0){return true;}
      else { return false;}
   }
   function GetSysError()
   {
      return $this->sys_error;
   }

   function sys_error_handler($errno, $errstr, $errfile, $errline)
   {
      if($errno == E_USER_NOTICE || $errno == E_NOTICE ||
      (defined('E_STRICT') && $errno == E_STRICT) )
      {
         return;
      }
       $this->sys_error = "Error ($errno): $errstr\n file:$errfile\nline: $errline \n\n";

      if($this->disable_syserror_handling == true)
      {
         return;
      }
        $this->HandleConfigError($this->sys_error);

      exit;
   }
    
   function ShowError($error_code)
   {
      $this->DisplayError($error_code);
   }
   function ShowErrorEx($error_code,$error_extra_info)
   {
      $error_extra_info = trim($error_extra_info);
      $this->DisplayError($error_code."\n".$error_extra_info);
   }
    function ShowInputError($error_hash,$form_name)
    {
        $this->DisplayError("",$error_hash,$form_name);
    }
    function NeedSeperateErrorPage($error_hash)
    {
        if(null == $error_hash)
        {
            if(false === strpos($this->config->form_page_code,
                $this->config->error_display_variable))
            {
                return true;
            }
        }

        return false;
    }

   function DisplayError($str_error,$error_hash=null,$form_name="")
   {
      $str_error = trim($str_error);
      $this->logger->LogError($str_error);

        if(!$this->enable_error_formpagemerge)
        {
            echo $str_error;
            return;
        }        

      $str_error = nl2br($str_error);  
      
        $error_page="";

      if(true == $this->config->display_error_in_formpage && 
          isset($this->config->form_page_code) &&
          strlen($this->config->form_page_code) > 0 )
      {
            if($this->NeedSeperateErrorPage($error_hash))
            {
                $error_page = $this->config->error_page_code;
            }
            else
            {
                $error_page = $this->config->form_page_code;
            }
      }
      else
      {
            $error_page = $this->config->error_page_code;
      }
        

        $self_script = $this->globaldata->get_php_self();

        $var_map = array(
                    $this->config->error_display_variable => $str_error,
                    $this->config->self_script_variable => $self_script
                    );

      $fillerobj = new FM_FormFiller($this->config,$this->logger);


      $fillerobj->CreateServerSideVector($this->formvars,$var_map);

        if(null != $error_hash)
        {
            if($this->config->show_errors_single_box)
            {
                $this->CombineErrors($var_map,$error_hash);
            }   
            else
            {
                foreach($error_hash as $inpname => $inp_err)
                {
                    $err_var = $form_name."_".$inpname."_errorloc";
                    $var_map[$err_var] = $inp_err;
                }
            }
        }
        //discard any previous output
        ob_clean();
        $merge = new FM_PageMerger();
        if(false == $merge->Merge($error_page,$var_map))
        {
            return false;
        }                
        $strdisp = $merge->getMessageBody();

      echo $strdisp;
   }

    function CombineErrors(&$var_map,&$error_hash)
    {
        $error_str='';
        foreach($error_hash as $inpname => $inp_err)
        {
            $error_str .="\n<li>".$inp_err;
        }        

        if(!empty($error_str))
        {
            $error_str="\n<ul>".$error_str."\n</ul>";
        }

        $var_map[$this->config->error_display_variable]=
            $var_map[$this->config->error_display_variable].$error_str;

    }

   function EmailError($error_code)
   {
      $this->logger->LogInfo("Sending Error Email To: ".$this->config->error_mail_to);    
      $mailbody = sprintf(ERROR_EMAIL_BODY,$this->formname,$error_code,$this->logger->get_log());
      $subj =  sprintf(ERROR_EMAIL_SUBJ,$this->formname);
      @mail($this->config->error_mail_to, $subj, $mailbody, 
         "From: $this->formname<form.error@simfatic-forms.com>");
   }  

   function NotifyError($error_code)
   {
        $this->logger->LogError($error_code);
        if(strlen($this->config->error_mail_to)>0)
        {
            $this->EmailError($error_code);
        }        
   }
   
   function HandleConfigError($error_code,$extrainfo="")
   {
        $disp_error = "<p align='left'>";
      $disp_error .= E_CONF_ERROR_INTERNAL;

        $extrainfo .= "\n server: ".$_SERVER["SERVER_SOFTWARE"];
        
  
        $logged = $this->logger->LogError($error_code);


        $error_code_disp ='';
        $error_code_disp_link ='';

        if($this->config->debug_mode)
        {
            $error_code_disp = $error_code.$extrainfo;
        }
        else
        {
            if($logged)
          {
             $error_code_disp .= E_CONF_ERROR_LOGGED;
          }
          else
          {
             $error_code_disp .= E_CONF_ERROR_NOT_LOGGED;
          }

            $error_code_disp .= "<br/>".E_CONF_ERROR_DEBUG_MODE_INFO;
        }

        $link = sprintf(E_CONF_ERROR_TS_LINK,urlencode($error_code_disp));

        $disp_error .= "<br/>".$error_code_disp."<br/>$link";

        $disp_error .= "</p>";

      if(strlen($this->config->error_mail_to)>0)
      {
         $this->EmailError($error_code);
      }

      $this->DisplayError($disp_error);
   }
}

////FormMail////////////////
class FormMail
{
   var $globaldata;
   var $formvars;
   var $form_name;
   var $logger;
   var $config;
   var $error_handler;
    var $modules;
   var $ext_module_holder;

   function FormMail($formname)
   {
        ob_start();

        $this->form_name = $formname;
        $this->config = new FM_Config();
      $this->globaldata = new FM_GlobalData($this->config);
      
      $this->logger = new FM_Logger($this->globaldata,$this->config,$formname);
      $this->error_handler  = new FM_ErrorHandler($this->logger,$this->config,$this->globaldata,$formname);
      $this->error_handler->InstallConfigErrorCatch();
        $this->modules=array();
      $this->ext_module_holder = new FM_ExtensionModuleHolder();
        $this->SetDebugMode(true);//till it is disabled explicitely
        
      session_start();
   }

    function setEmailFormatHTML($ishtml)
    {
        $this->config->email_format_html = $ishtml;
    }
    function setFormFileFolder($folder)
    {
        $this->config->form_file_folder = $folder;
    }
    
    function setIsInstalled($installed)
    {
        $this->config->installed = $installed;   
    }

    function SetSingleBoxErrorDisplay($enabled)
    {
        $this->config->show_errors_single_box = $enabled;
    }

   function setFormPage($formpage)
   {
      $this->config->setFormPage($formpage);
   }
    
    function setFormID($id)
    {
        $this->config->set_form_id($id);
    }
    
    function setFormKey($key)
    {
        $this->config->set_encrkey($key);
    }

    function InitSMTP($host,$uname,$pwd,$port)
    {
        $this->config->InitSMTP($host,$uname,$pwd,$port);
    }

   function EnableLogging($enable)
   {
      $this->logger->EnableLogging($enable);
   }

   function SetErrorEmail($email)
   {
      $this->config->set_error_email($email);
   }
   
   function AddElementInfo($name,$type,$extra_info="")
   {
      $this->config->element_info->AddElementInfo($name,$type,$extra_info);
   }
   function AddDefaultValue($name,$value)
   {
      $this->config->element_info->AddDefaultValue($name,$value);
   }

   function SetDebugMode($enable)
   {
      $this->config->setDebugMode($enable);
   }

    function SetFromAddress($from)
    {
        $this->config->from_addr = $from;
    }

    function SetVariableFrom($enable)
    {
        $this->config->variable_from = $enable;
    }

    function SetCommonDateFormat($format)
    {
        $this->config->common_date_format = $format;
    }

    function EnableLoadFormValuesFromURL($enable)
    {
        $this->config->load_values_from_url = $enable;
    }

   function GetVars()
   {
        $this->logger->LogInfo("\n session variables ".print_r($_SESSION[$this->globaldata->saved_data_varname],true)."\n");
        $this->globaldata->GetGlobalVars();

        $this->formvars = &$this->globaldata->formvars;

        $this->logger->LogInfo("\n formvars ".print_r($this->formvars,true)."\n");


        if(!isset($this->form_name) ||
           strlen($this->form_name)==0)
        {
           $this->error_handler->HandleConfigError(E_CONF_FORM_NAME_NOT_SET,"");
           return false;            
        }
        $this->error_handler->SetFormVars($this->formvars);
        return true;
   }
   
    function addModule(&$module)
    {
        array_push_ref($this->modules,$module);
    }

   function AddExtensionModule(&$module)
   {
      $this->ext_module_holder->AddModule($module);
   }

   function getmicrotime()
    { 
        list($usec, $sec) = explode(" ",microtime()); 
        return ((float)$usec + (float)$sec); 
    } 

   function ProcessForm()
   {
        $timestart = $this->getmicrotime();
        
        $N = count($this->modules);

        for($i=0;$i<$N;$i++)
        {
            $mod = &$this->modules[$i];
            $mod->Init($this->config,$this->globaldata->formvars,
                $this->logger,$this->globaldata,
                $this->error_handler,$this->form_name,
            $this->ext_module_holder);
        }
        
        $this->ext_module_holder->Init($this->config,$this->globaldata->formvars,
               $this->logger,$this->globaldata,
               $this->error_handler,$this->form_name);
      

        if(false === $this->ext_module_holder->BeforeStartProcessing())
        {
            $this->logger->LogInfo("Extension module returns false for BeforeStartProcessing. Stopping.");
        }
        else
        {
            if(false == $this->GetVars())
            {
                $this->logger->LogInfo("GetVars() Failed");
                return false;
            }
            for($i=0;$i<$N;$i++)
            {
                $mod = &$this->modules[$i];
                $continue = true;

                $mod->Process($continue);
                if(!$continue){break;}
            }

            for($i=0;$i<$N;$i++)
            {
                $mod = &$this->modules[$i];
                $mod->Destroy();
            }
        }
        $timetaken  = $this->getmicrotime()-$timestart;

        $this->logger->LogInfo("Completed form processing timetaken:$timetaken");

        $this->logger->FlushLog();

        ob_end_flush();
        return true;
   }
   
   function showVars()
   {
      foreach($this->formvars as $name => $value)
      {
         echo "$name $value <br>";
      }
   }
   
}

class FM_Module
{
    var $config;
    var $formvars;
    var $logger;
    var $globaldata;
    var $error_handler;
    var $formname;
   var $ext_module;

    function FM_Module()
    {
    }

    function Init(&$config,&$formvars,&$logger,&$globaldata,&$error_handler,$formname,&$ext_module)
    {
        $this->config = &$config;
        $this->formvars = &$formvars;
        $this->logger = &$logger;
        $this->globaldata =&$globaldata;
        $this->error_handler = &$error_handler;
        $this->formname = $formname;
      $this->ext_module = &$ext_module;
        $this->OnInit();
    }

    function OnInit()
    {
    }

    function Process(&$continue)
    {
        return true;
    }

    function Destroy()
    {

    }
   function getFormDataFolder()
   {
      if(strlen($this->config->form_file_folder)<=0)
      {
         $this->error_handler->HandleConfigError(E_CONF_NO_FORM_FOLDER);
         exit;
      }
      return $this->config->form_file_folder;
   }


}

class FM_FormFillerScriptWriter extends FM_Module
{
   function FM_FormFillerScriptWriter()
   {
        $this->form_filler_variable="sfm_fill_the_form";
   }

   function Process(&$continue)
    {
        if($this->NeedReturnScript())
        {
            $continue=false;
            $this->ReturnFormFillerScript();
        }
        else
        {
            $continue=true;
        }
    }

   function NeedReturnScript()
   {
      if(isset($this->formvars[$this->form_filler_variable]))
      {
         return true;
      }
      return false;
   }
   
   
   function ReturnFormFillerScript()
   {
      $formvars=array();
      if(false == $this->ext_module->BeforeFormDisplay($formvars))
      {
         $this->logger->LogError("Extension Module 'BeforeFormDisplay' returned false! ");
         return false;
      }

      $filler = new FM_FormFiller($this->config,$this->logger);
      if($filler->CreateFormFillerScript($this->formvars))
      {
         echo $filler->GetFillerJSCode();
        
      }
   }

}

class FM_FormFiller 
{
   var $filler_js_code;
   var $config;
   var $logger;

   function FM_FormFiller(&$config,&$logger)
   {
      $this->filler_js_code="";
      $this->form_filler_variable = "sfm_fill_the_form";
      $this->logger = &$logger;
      $this->config = &$config;
   }
   function GetFillerJSCode()
   {
      return $this->filler_js_code;
   }
   function GetFormFillerScriptEmbedded($formvars)
   {
      $ret_code="";
      if($this->CreateFormFillerScript($formvars))
      {
         $self_script = $this->globaldata->get_php_self();
         $ret_code .= "<script language='JavaScript' src='$self_script?sfm_get_ref_file=form-filler-helper.js'></script>\n";
      
         $ret_code .= "<script language='JavaScript'>\n";
         $ret_code .= "\n$util_code\n";
         $ret_code .= $this->filler_js_code;
         $ret_code .= "\n</script>";
      }
      return $ret_code;
   }

   function CreateServerSideVector($formvars,&$outvector)
   {
      foreach($formvars as $name => $value)
      {
         if(!$this->config->element_info->IsElementPresent($name)||
         !isset($value))
         {
            continue; 
         }
         switch($this->config->element_info->GetType($name))
         {
            case "text":
            case "multiline":
            case "decimal":
            case "calcfield":
            case "datepicker":
            case "hidden":
               {
                  $outvector[$name] = $value;
                  break;
               }
            case "single_chk":
            case "radio_group":
            case "chk_group":
               {
                  $this->SetGroupItemValue($outvector,$name,$value,"checked");
                  break;
               } 
            case "listbox":
               {
                  $this->SetGroupItemValue($outvector,$name,$value,"selected");
                  break;
               }
         }//switch
      }//foreach
   }

   function SetGroupItemValue(&$outvector,$name,$value,$set_val)
   {
      if(is_array($value))
      {
         foreach($value as $val_item)
         {  
            $entry = md5($name.$val_item);
            $outvector[$entry]=$set_val;
         }
      }
      else
      {
         $entry = md5($name.$value);
         $outvector[$entry]=$set_val;
      }
      
   }

   function CreateFormFillerScript($formvars)
   {
      
      $func_body="";
      foreach($formvars as $name => $value)
      {
         if(!$this->config->element_info->IsElementPresent($name)||
         !isset($value))
         {
            continue; 
         }
         switch($this->config->element_info->GetType($name))
         {
            case "text":
            case "multiline":
                case "decimal":
                case "calcfield":
                case "datepicker":
               {
                  $value = str_replace("\n","\\n",$value);
                  $value = str_replace("'","\\'",$value);
                  $func_body .= "formobj.elements['$name'].value = '$value';\n";
                  break;
               }
            case "single_chk":
               {
                  if(strlen($value) > 0 && strcmp($value,"off")!=0)
                  {
                     $func_body .= "formobj.elements['$name'].checked = true;\n";
                  }
                  break;
               }
            
            case "chk_group":
               {
                  $name_tmp="$name"."[]";
                  foreach($value as $item)
                  {  
                     $func_body .= "SFM_SelectChkItem(formobj.elements['$name_tmp'],'$item');\n";
                  }
                  break;
               }
            case "radio_group":
               {
                  $func_body .= "SFM_SelectChkItem(formobj.elements['$name'],'$value');\n";
                  break;
               }
            case "listbox":
               {
                  if(is_array($value))
                  {
                     $name_tmp="$name"."[]";
                     foreach($value as $item)
                     {
                        $func_body .= "SFM_SelectListItem(formobj.elements['$name_tmp'],'$item');\n";
                     }
                  }
                  else
                  {
                     $func_body .= "formobj.elements['$name'].value = '$value';\n";
                  }
                  break;
               }
         }
      }//foreach

      $bret=false;
      $this->filler_js_code="";
      if(strlen($func_body)>0)
      {
         $function_name = "sfm_".$this->formname."formfiller"; 

         $this->filler_js_code .= "function $function_name (){\n";
         $this->filler_js_code .= " var formobj= document.forms['".$this->formname."'];\n";
         $this->filler_js_code .= $func_body;
         $this->filler_js_code .= "}\n";
         $this->filler_js_code .= "$function_name ();";
         $bret= true;
      }
      return $bret;
   }

}




///////PageMerger////////////////////
class FM_PageMerger
{
   var $message_body;
   function FM_PageMerger()
   {
      $this->message_body="";
   }

   function Merge($content,$variable_map)
   {
      $this->message_body = $content;
      $N = 0;
      $m = preg_match_all("/%([\w]*)%/", $content,$matches,PREG_PATTERN_ORDER);

      if($m > 0 || count($matches) > 1)
      {
         $N = count($matches[1]);
      }

      for($i=0;$i<$N;$i++)
      {
         $val = "";
         $key = $matches[1][$i];
         if(isset($variable_map[$key]))
         {
                if(is_array($variable_map[$key]))
                {
                    $val = implode(",",$variable_map[$key]);
                }
            else
                {
                $val = $variable_map[$key];
                }
         }
         else
         if(strlen($key)<=0)
         {
            $val ='%';
         }

         $this->message_body = str_replace($matches[0][$i],$val,$this->message_body);
      }

      return(strlen($this->message_body)>0?true:false);
   }  

   function getMessageBody()
   {
      return $this->message_body;
   }
}


class FM_ExtensionModule
{
    var $config;
    var $formvars;
    var $logger;
    var $globaldata;
    var $error_handler;
    var $formname;

    function Init(&$config,&$formvars,&$logger,&$globaldata,&$error_handler,$formname)
    {
        $this->config = &$config;
        $this->formvars = &$formvars;
        $this->logger = &$logger;
        $this->globaldata =&$globaldata;
        $this->error_handler = &$error_handler;
        $this->formname = $formname;
    }
    function BeforeStartProcessing()
    {
        return true;
    }
    
   function BeforeFormDisplay(&$formvars)
   {
      return true;
   }

    function DoValidate(&$formvars, &$error_hash)
    {
        return true;
    }

   function BeforeConfirmPageDisplay(&$formvars)
   {
      return true;      
   }

   function FormSubmitted(&$formvars)
   {
      return true;
   }

    function BeforeThankYouPageDisplay(&$formvars)
    {
        return true;
    }
    
    function BeforeSendingFormSubmissionEMail(&$receipient,&$subject,&$body)
    {
        return true;
    }
    
    function BeforeSendingAutoResponse(&$receipient,&$subject,&$body)
    {
        return true;
    }
   
}

class FM_ExtensionModuleHolder
{
    var $modules;

    var $config;
    var $formvars;
    var $logger;
    var $globaldata;
    var $error_handler;
    var $formname;

    function Init(&$config,&$formvars,&$logger,&$globaldata,&$error_handler,$formname)
    {
        $this->config = &$config;
        $this->formvars = &$formvars;
        $this->logger = &$logger;
        $this->globaldata =&$globaldata;
        $this->error_handler = &$error_handler;
        $this->formname = $formname;
      $this->InitModules();
    }

   function FM_ExtensionModuleHolder()
   {
      $this->modules = array();
   }
   
   function AddModule(&$module)
   {
      array_push_ref($this->modules,$module);
   }
   
   function InitModules()
   {
      $N = count($this->modules);

        for($i=0;$i<$N;$i++)
        {
            $mod = &$this->modules[$i];
            $mod->Init($this->config,$this->formvars,
                $this->logger,$this->globaldata,
                $this->error_handler,$this->form_name);
        }      
   }

   function Delegate($code,&$formvars)
   {
      $N = count($this->modules);
      $ret = true;
        for($i=0;$i<$N;$i++)
        {
            $mod = &$this->modules[$i];
             switch($code)
             {
                case "start_processing":
                {
                    $ret = $mod->BeforeStartProcessing();
                    break;
                }
                case "form_display":
                {
                   $ret = $mod->BeforeFormDisplay($formvars);
                   break;
                }
                case "confirm_page":
                {
                   $ret = $mod->BeforeConfirmPageDisplay($formvars);
                   break;
                }
                case "submitted":
                {
                   $ret = $mod->FormSubmitted($formvars);
                   break;               
                }
                case "thankyou_page":
                {
                    $ret = $mod->BeforeThankYouPageDisplay($formvars);
                    break;
                }
             }
        }
      return $ret;      
   }

   function BeforeStartProcessing()
   {
        return $this->Delegate("start_processing",$this->formvars);
   }
   
   function BeforeFormDisplay(&$formvars)
   {
      return $this->Delegate("form_display",$formvars);
   }
   
    
    function DoValidate(&$formvars, &$error_hash)
    {
        $N = count($this->modules);
        $ret = true;
        for($i=0;$i<$N;$i++)
        {
            $mod = &$this->modules[$i];
            $ret_c = $mod->DoValidate($formvars, $error_hash);
            $ret = $ret && $ret_c;
        }
        return $ret;
    }

   function BeforeConfirmPageDisplay(&$formvars)
   {
      return $this->Delegate("confirm_page",$formvars);        
   }

   function FormSubmitted(&$formvars)
   {
      return $this->Delegate("submitted",$formvars);
   }

   function BeforeThankYouPageDisplay(&$formvars)
   {
      return $this->Delegate("thankyou_page",$formvars);
   }
   
   function BeforeSendingFormSubmissionEMail(&$receipient,&$subject,&$body)
   {
        $N = count($this->modules);
        for($i=0;$i<$N;$i++)
        {
            if(false === $this->modules[$i]->BeforeSendingFormSubmissionEMail($receipient,$subject,$body))
            {
                return false;
            }
        }
        return true;
   }
   
    function BeforeSendingAutoResponse(&$receipient,&$subject,&$body)
    {
        $N = count($this->modules);
        for($i=0;$i<$N;$i++)
        {
            if(false === $this->modules[$i]->BeforeSendingAutoResponse($receipient,$subject,$body))
            {
                return false;
            }
        }
        return true;    
    }   
   
}

///////Global Functions///////
function sfm_make_path($part1,$part2)
{
    $part1 = rtrim($part1,"/\\");
    $ret_path = $part1."/".$part2;
    return $ret_path;
}
function magicQuotesRemove(&$array) 
{
   if(!get_magic_quotes_gpc())
   {
       return;
   }
   foreach($array as $key => $elem) 
   {
       if(is_array($elem))
      {
           magicQuotesRemove($elem);
      }
       else
      {
           $array[$key] = stripslashes($elem);
      }//else
   }//foreach
}

function CreateHiddenInput($name, $objvalue)
{
    $objvalue = htmlentities($objvalue,ENT_QUOTES,"UTF-8");
    $str_ret = " <input type='hidden' name='$name' value='$objvalue'>";
    return $str_ret;
}

function convert_html_entities_in_formdata($skip_var,&$datamap)
{
   foreach($datamap as $name => $value)
   {
    if(strlen($skip_var)>0 && strcmp($name,$skip_var)==0)
    {
        continue;
    }
      if(true == is_string($datamap[$name]))
   {
          $datamap[$name] = nl2br(htmlentities($datamap[$name],ENT_QUOTES,"UTF-8"));
   }
   }//foreach
}

function array_push_ref(&$target,&$value_array)
{
    if(!is_array($target))
    {
        return FALSE;
    }
    $target[]=&$value_array;
    return TRUE;
}

function sfm_checkConfigFileSign($conf_content,$strsign)
{
    $conf_content = substr($conf_content,strlen(CONST_PHP_TAG_START)+1);
    $conf_content = ltrim($conf_content); 

    if(0 == strncmp($conf_content,$strsign,strlen($strsign)))
    {
        return true;
    }
    return false;
}

function sfm_readfile($filepath)
{
    $retString = file_get_contents($filepath);
    return $retString;
}

function sfm_csv_escape($value)
{
    if(preg_match("/[\n\"\,\r]/i",$value))
    {
        $value = str_replace("\"","\"\"",$value);
        $value = "\"$value\"";
    }    
    return $value;
}

function sfm_getFieldValueAsString($var_name,&$file_uploader,&$formvars)
{
    $ret_val ="";
    if(NULL != $file_uploader &&
       $file_uploader->IsFieldFileUpload($var_name))
    {
       $ret_val = $file_uploader->GetUploadedFileName($var_name);
        
    }
    else
    {
        $value = $formvars[$var_name];

        if(is_array($value))
        {
            $val_str="";
            foreach($value as $v)
            {
                $val_str .= "$v,";
            }
            $ret_val = rtrim($val_str,',');
        }
        else
        {
            $ret_val = $value;
        }
    }
    return $ret_val;
}

function sfm_crypt_decrypt($in_str,$key)
{
    $blowfish =& Crypt_Blowfish::factory('ecb');
    $blowfish->setKey($key);
    
    $bin_data = pack("H*",$in_str);
    $decr_str = $blowfish->decrypt($bin_data);
    if(PEAR::isError($decr_str))
    {
        $this->logger->LogError(" decrypt error: ".$decr_str->getMessage());
        return "";
    }
    $decr_str = trim($decr_str);
    return $decr_str;
}

function sfm_filename_no_ext($fullpath)
{
    $filename = basename($fullpath);

    $pos = strrpos($filename, '.');
    if ($pos === false)
    { // dot is not found in the filename
        return $filename; // no extension
    }
    else
    {
        $justfilename = substr($filename, 0, $pos);
        return $justfilename;
    }
}



class SecureFileMaker
{
    var $signature_line;
    var $file_pos;

     function SecureFileMaker($signature)
     {
        $this->signature_line = $signature;
     }

     function CreateFile($filepath, $first_line)
     {
        $fp = fopen($filepath,"w");
        if(!$fp)
        {
          return false;
        }

        $header = $this->get_header()."\n";
        $first_line = trim($first_line);
        $header .= $first_line."\n";

        if(!fwrite($fp,$header))
        {
            return false;
        }

        $footer .= $this->get_footer();

        if(!fwrite($fp,$footer))
        {
            return false;
        }

        fclose($fp);

        return true;
     }
     
     function get_header()
     {
        return "<?PHP /* $this->signature_line";
     }

     function get_footer()
     {
        return "$this->signature_line */ ?>";
     }

    function gets_backward($fp)
    {
        $ret_str="";
        $t="";
        while ($t != "\n") 
        {
            if(0 != fseek($fp, $this->file_pos, SEEK_END))
            {
              rewind($fp);
              break;
            }
            $t = fgetc($fp);
            
            $ret_str = $t.$ret_str;
            $this->file_pos --;
        }
        return $ret_str;
    }

    function AppendLine($file_path,$insert_line)
    {
        $fp = fopen($file_path,"r+");

        if(!$fp)
        {
            return false;
        }
        $all_lines="";

        $this->file_pos = -1;
        fseek($fp,$this->file_pos,SEEK_END);
        

        while(1)
        {
            $pos = ftell($fp);
            if($pos <= 0)
            {
                break;
            }
            $line = $this->gets_backward($fp);
            $cmpline = trim($line);

            $all_lines .= $line;

            if(strcmp($cmpline,$this->get_footer())==0)
            {
              break;
            }
        }
        
        $all_lines = trim($all_lines);
        $insert_line = trim($insert_line);

        $all_lines = "$insert_line\n$all_lines";

        if(!fwrite($fp,$all_lines))
        {
            return false;
        }

        fclose($fp);
        return true;
    }

    function ReadNextLine($fp)
    {
        while(!feof($fp))
        {
            $line = fgets($fp);
            $line = trim($line);

            if(strcmp($line,$this->get_header())!=0 &&
               strcmp($line,$this->get_footer())!=0)
            {
                return $line;
            }
        }
        return "";
    }
}


/**
 * Crypt_Blowfish allows for encryption and decryption on the fly using
 * the Blowfish algorithm. Crypt_Blowfish does not require the MCrypt
 * PHP extension, but uses it if available, otherwise it uses only PHP.
 * Crypt_Blowfish supports encryption/decryption with or without a secret key.
 *
 *
 * PHP versions 4 and 5
 *
 * LICENSE: This source file is subject to version 3.0 of the PHP license
 * that is available through the world-wide-web at the following URI:
 * http://www.php.net/license/3_0.txt.  If you did not receive a copy of
 * the PHP License and are unable to obtain it through the web, please
 * send a note to license@php.net so we can mail you a copy immediately.
 *
 * @category   Encryption
 * @package    Crypt_Blowfish
 * @author     Matthew Fonda <mfonda@php.net>
 * @copyright  2005 Matthew Fonda
 * @license    http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version    CVS: $Id: encr-lib.php,v 1.2 2010/02/16 06:51:02 Prasanth Exp $
 * @link       http://pear.php.net/package/Crypt_Blowfish
 */



/**
 * Engine choice constants
 */
/**
 * To let the Crypt_Blowfish package decide which engine to use
 * @since 1.1.0
 */
define('CRYPT_BLOWFISH_AUTO',   1);
/**
 * To use the MCrypt PHP extension.
 * @since 1.1.0
 */
define('CRYPT_BLOWFISH_MCRYPT', 2);
/**
 * To use the PHP-only engine.
 * @since 1.1.0
 */
define('CRYPT_BLOWFISH_PHP',    3);


/**
 * Example using the factory method in CBC mode
 * <code>
 * $bf =& Crypt_Blowfish::factory('cbc');
 * if (PEAR::isError($bf)) {
 *     echo $bf->getMessage();
 *     exit;
 * }
 * $iv = 'abc123+=';
 * $key = 'My secret key';
 * $bf->setKey($key, $iv);
 * $encrypted = $bf->encrypt('this is some example plain text');
 * $bf->setKey($key, $iv);
 * $plaintext = $bf->decrypt($encrypted);
 * if (PEAR::isError($plaintext)) {
 *     echo $plaintext->getMessage();
 *     exit;
 * }
 * // Encrypted text is padded prior to encryption
 * // so you may need to trim the decrypted result.
 * echo 'plain text: ' . trim($plaintext);
 * </code>
 *
 * To disable using the mcrypt library, define the CRYPT_BLOWFISH_NOMCRYPT
 * constant. This is useful for instance on Windows platform with a buggy
 * mdecrypt_generic() function.
 * <code>
 * define('CRYPT_BLOWFISH_NOMCRYPT', true);
 * </code>
 *
 * @category   Encryption
 * @package    Crypt_Blowfish
 * @author     Matthew Fonda <mfonda@php.net>
 * @author     Philippe Jausions <jausions@php.net>
 * @copyright  2005-2006 Matthew Fonda
 * @license    http://www.php.net/license/3_0.txt  PHP License 3.0
 * @link       http://pear.php.net/package/Crypt_Blowfish
 * @version    @package_version@
 * @access     public
 */
 
 define('CRYPT_BLOWFISH_NOMCRYPT', true);

class Crypt_Blowfish
{
    /**
     * Implementation-specific Crypt_Blowfish object
     *
     * @var object
     * @access private
     */
    var $_crypt = null;

    /**
     * Initialization vector
     *
     * @var string
     * @access protected
     */
    var $_iv = null;

    /**
     * Holds block size
     *
     * @var integer
     * @access protected
     */
    var $_block_size = 8;

    /**
     * Holds IV size
     *
     * @var integer
     * @access protected
     */
    var $_iv_size = 8;

    /**
     * Holds max key size
     *
     * @var integer
     * @access protected
     */
    var $_key_size = 56;

    /**
     * Crypt_Blowfish Constructor
     * Initializes the Crypt_Blowfish object (in EBC mode), and sets
     * the secret key
     *
     * @param string $key
     * @access public
     * @deprecated Since 1.1.0
     * @see Crypt_Blowfish::factory()
     */
    function Crypt_Blowfish($key)
    {
        $this->_crypt =& Crypt_Blowfish::factory('ecb', $key);
        if (!PEAR::isError($this->_crypt)) {
            $this->_crypt->setKey($key);
        }
    }

    /**
     * Crypt_Blowfish object factory
     *
     * This is the recommended method to create a Crypt_Blowfish instance.
     *
     * When using CRYPT_BLOWFISH_AUTO, you can force the package to ignore
     * the MCrypt extension, by defining CRYPT_BLOWFISH_NOMCRYPT.
     *
     * @param string $mode operating mode 'ecb' or 'cbc' (case insensitive)
     * @param string $key
     * @param string $iv initialization vector (must be provided for CBC mode)
     * @param integer $engine one of CRYPT_BLOWFISH_AUTO, CRYPT_BLOWFISH_PHP
     *                or CRYPT_BLOWFISH_MCRYPT
     * @return object Crypt_Blowfish object or PEAR_Error object on error
     * @access public
     * @static
     * @since 1.1.0
     */
    function &factory($mode = 'ecb', $key = null, $iv = null, $engine = CRYPT_BLOWFISH_AUTO)
    {
        switch ($engine) {
            case CRYPT_BLOWFISH_AUTO:
                if (!defined('CRYPT_BLOWFISH_NOMCRYPT')
                    && extension_loaded('mcrypt')) {
                    $engine = CRYPT_BLOWFISH_MCRYPT;
                } else {
                    $engine = CRYPT_BLOWFISH_PHP;
                }
                break;
            case CRYPT_BLOWFISH_MCRYPT:
                if (!PEAR::loadExtension('mcrypt')) {
                    return PEAR::raiseError('MCrypt extension is not available.');
                }
                break;
        }

        switch ($engine) {
            case CRYPT_BLOWFISH_PHP:
                $mode = strtoupper($mode);
                $class = 'Crypt_Blowfish_' . $mode;
                
                $crypt = new $class(null);
                break;

            case CRYPT_BLOWFISH_MCRYPT:
                
                $crypt = new Crypt_Blowfish_MCrypt(null, $mode);
                break;
        }

        if (!is_null($key) || !is_null($iv)) {
            $result = $crypt->setKey($key, $iv);
            if (PEAR::isError($result)) {
                return $result;
            }
        }

        return $crypt;
    }

    /**
     * Returns the algorithm's block size
     *
     * @return integer
     * @access public
     * @since 1.1.0
     */
    function getBlockSize()
    {
        return $this->_block_size;
    }

    /**
     * Returns the algorithm's IV size
     *
     * @return integer
     * @access public
     * @since 1.1.0
     */
    function getIVSize()
    {
        return $this->_iv_size;
    }

    /**
     * Returns the algorithm's maximum key size
     *
     * @return integer
     * @access public
     * @since 1.1.0
     */
    function getMaxKeySize()
    {
        return $this->_key_size;
    }

    /**
     * Deprecated isReady method
     *
     * @return bool
     * @access public
     * @deprecated
     */
    function isReady()
    {
        return true;
    }

    /**
     * Deprecated init method - init is now a private
     * method and has been replaced with _init
     *
     * @return bool
     * @access public
     * @deprecated
     */
    function init()
    {
        return $this->_crypt->init();
    }

    /**
     * Encrypts a string
     *
     * Value is padded with NUL characters prior to encryption. You may
     * need to trim or cast the type when you decrypt.
     *
     * @param string $plainText the string of characters/bytes to encrypt
     * @return string|PEAR_Error Returns cipher text on success, PEAR_Error on failure
     * @access public
     */
    function encrypt($plainText)
    {
        return $this->_crypt->encrypt($plainText);
    }


    /**
     * Decrypts an encrypted string
     *
     * The value was padded with NUL characters when encrypted. You may
     * need to trim the result or cast its type.
     *
     * @param string $cipherText the binary string to decrypt
     * @return string|PEAR_Error Returns plain text on success, PEAR_Error on failure
     * @access public
     */
    function decrypt($cipherText)
    {
        return $this->_crypt->decrypt($cipherText);
    }

    /**
     * Sets the secret key
     * The key must be non-zero, and less than or equal to
     * 56 characters (bytes) in length.
     *
     * If you are making use of the PHP MCrypt extension, you must call this
     * method before each encrypt() and decrypt() call.
     *
     * @param string $key
     * @return boolean|PEAR_Error  Returns TRUE on success, PEAR_Error on failure
     * @access public
     */
    function setKey($key)
    {
        return $this->_crypt->setKey($key);
    }
}


/**
 * Crypt_Blowfish allows for encryption and decryption on the fly using
 * the Blowfish algorithm. Crypt_Blowfish does not require the mcrypt
 * PHP extension, but uses it if available, otherwise it uses only PHP.
 * Crypt_Blowfish support encryption/decryption with or without a secret key.
 *
 * PHP versions 4 and 5
 *
 * LICENSE: This source file is subject to version 3.0 of the PHP license
 * that is available through the world-wide-web at the following URI:
 * http://www.php.net/license/3_0.txt.  If you did not receive a copy of
 * the PHP License and are unable to obtain it through the web, please
 * send a note to license@php.net so we can mail you a copy immediately.
 *
 * @category   Encryption
 * @package    Crypt_Blowfish
 * @author     Matthew Fonda <mfonda@php.net>
 * @author     Philippe Jausions <jausions@php.net>
 * @copyright  2005-2006 Matthew Fonda
 * @license    http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version    CVS: $Id: encr-lib.php,v 1.2 2010/02/16 06:51:02 Prasanth Exp $
 * @link       http://pear.php.net/package/Crypt_Blowfish
 * @since      1.1.0
 */


/**
 * Common class for PHP-only implementations
 *
 * @category   Encryption
 * @package    Crypt_Blowfish
 * @author     Matthew Fonda <mfonda@php.net>
 * @author     Philippe Jausions <jausions@php.net>
 * @copyright  2005-2006 Matthew Fonda
 * @license    http://www.php.net/license/3_0.txt  PHP License 3.0
 * @link       http://pear.php.net/package/Crypt_Blowfish
 * @version    @package_version@
 * @access     public
 * @since      1.1.0
 */
class Crypt_Blowfish_PHP extends Crypt_Blowfish
{
    /**
     * P-Array contains 18 32-bit subkeys
     *
     * @var array
     * @access protected
     */
    var $_P = array();

    /**
     * Array of four S-Blocks each containing 256 32-bit entries
     *
     * @var array
     * @access protected
     */
    var $_S = array();

    /**
     * Whether the IV is required
     *
     * @var boolean
     * @access protected
     */
    var $_iv_required = false;
    
    /**
     * Hash value of last used key
     * 
     * @var     string
     * @access  protected
     */
    var $_keyHash = null;

    /**
     * Crypt_Blowfish_PHP Constructor
     * Initializes the Crypt_Blowfish object, and sets
     * the secret key
     *
     * @param string $key
     * @param string $mode operating mode 'ecb' or 'cbc'
     * @param string $iv initialization vector
     * @access protected
     */
    function __construct($key = null, $iv = null)
    {
        $this->_iv = $iv . ((strlen($iv) < $this->_iv_size)
                            ? str_repeat(chr(0), $this->_iv_size - strlen($iv))
                            : '');
        if (!is_null($key)) {
            $this->setKey($key, $this->_iv);
        }
    }

    /**
     * Initializes the Crypt_Blowfish object
     *
     * @access private
     */
    function _init()
    {
        $defaults = new Crypt_Blowfish_DefaultKey();
        $this->_P = $defaults->P;
        $this->_S = $defaults->S;
    }

    /**
     * Workaround for XOR on certain systems
     *
     * @param integer|float $l
     * @param integer|float $r
     * @return float
     * @access protected
     */
    function _binxor($l, $r)
    {
        $x = (($l < 0) ? (float)($l + 4294967296) : (float)$l)
             ^ (($r < 0) ? (float)($r + 4294967296) : (float)$r);

        return (float)(($x < 0) ? $x + 4294967296 : $x);
    }

    /**
     * Enciphers a single 64-bit block
     *
     * @param int &$Xl
     * @param int &$Xr
     * @access protected
     */
    function _encipher(&$Xl, &$Xr)
    {
        if ($Xl < 0) {
            $Xl += 4294967296;
        }
        if ($Xr < 0) {
            $Xr += 4294967296;
        }

        for ($i = 0; $i < 16; $i++) {
            $temp = $Xl ^ $this->_P[$i];
            if ($temp < 0) {
                $temp += 4294967296;
            }

            $Xl = fmod((fmod($this->_S[0][($temp >> 24) & 255]
                             + $this->_S[1][($temp >> 16) & 255], 4294967296) 
                        ^ $this->_S[2][($temp >> 8) & 255]) 
                       + $this->_S[3][$temp & 255], 4294967296) ^ $Xr;
            $Xr = $temp;
        }
        $Xr = $this->_binxor($Xl, $this->_P[16]);
        $Xl = $this->_binxor($temp, $this->_P[17]);
    }

    /**
     * Deciphers a single 64-bit block
     *
     * @param int &$Xl
     * @param int &$Xr
     * @access protected
     */
    function _decipher(&$Xl, &$Xr)
    {
        if ($Xl < 0) {
            $Xl += 4294967296;
        }
        if ($Xr < 0) {
            $Xr += 4294967296;
        }

        for ($i = 17; $i > 1; $i--) {
            $temp = $Xl ^ $this->_P[$i];
            if ($temp < 0) {
                $temp += 4294967296;
            }

            $Xl = fmod((fmod($this->_S[0][($temp >> 24) & 255]
                             + $this->_S[1][($temp >> 16) & 255], 4294967296) 
                        ^ $this->_S[2][($temp >> 8) & 255]) 
                       + $this->_S[3][$temp & 255], 4294967296) ^ $Xr;
            $Xr = $temp;
        }
        $Xr = $this->_binxor($Xl, $this->_P[1]);
        $Xl = $this->_binxor($temp, $this->_P[0]);
    }

    /**
     * Sets the secret key
     * The key must be non-zero, and less than or equal to
     * 56 characters (bytes) in length.
     *
     * If you are making use of the PHP mcrypt extension, you must call this
     * method before each encrypt() and decrypt() call.
     *
     * @param string $key
     * @param string $iv 8-char initialization vector (required for CBC mode)
     * @return boolean|PEAR_Error  Returns TRUE on success, PEAR_Error on failure
     * @access public
     * @todo Fix the caching of the key
     */
    function setKey($key, $iv = null)
    {
        if (!is_string($key)) {
            return PEAR::raiseError('Key must be a string', 2);
        }

        $len = strlen($key);

        if ($len > $this->_key_size || $len == 0) {
            return PEAR::raiseError('Key must be less than ' . $this->_key_size . ' characters (bytes) and non-zero. Supplied key length: ' . $len, 3);
        }

        if ($this->_iv_required) {
            if (strlen($iv) != $this->_iv_size) {
                return PEAR::raiseError('IV must be ' . $this->_iv_size . '-character (byte) long. Supplied IV length: ' . strlen($iv), 7);
            }
            $this->_iv = $iv;
        }

        if ($this->_keyHash == md5($key)) {
            return true;
        }

        $this->_init();

        $k = 0;
        $data = 0;
        $datal = 0;
        $datar = 0;

        for ($i = 0; $i < 18; $i++) {
            $data = 0;
            for ($j = 4; $j > 0; $j--) {
                    $data = $data << 8 | ord($key{$k});
                    $k = ($k+1) % $len;
            }
            $this->_P[$i] ^= $data;
        }

        for ($i = 0; $i <= 16; $i += 2) {
            $this->_encipher($datal, $datar);
            $this->_P[$i] = $datal;
            $this->_P[$i+1] = $datar;
        }
        for ($i = 0; $i < 256; $i += 2) {
            $this->_encipher($datal, $datar);
            $this->_S[0][$i] = $datal;
            $this->_S[0][$i+1] = $datar;
        }
        for ($i = 0; $i < 256; $i += 2) {
            $this->_encipher($datal, $datar);
            $this->_S[1][$i] = $datal;
            $this->_S[1][$i+1] = $datar;
        }
        for ($i = 0; $i < 256; $i += 2) {
            $this->_encipher($datal, $datar);
            $this->_S[2][$i] = $datal;
            $this->_S[2][$i+1] = $datar;
        }
        for ($i = 0; $i < 256; $i += 2) {
            $this->_encipher($datal, $datar);
            $this->_S[3][$i] = $datal;
            $this->_S[3][$i+1] = $datar;
        }

        $this->_keyHash = md5($key);
        return true;
    }
}

/**
 * PHP implementation of the Blowfish algorithm in ECB mode
 *
 * PHP versions 4 and 5
 *
 * LICENSE: This source file is subject to version 3.0 of the PHP license
 * that is available through the world-wide-web at the following URI:
 * http://www.php.net/license/3_0.txt.  If you did not receive a copy of
 * the PHP License and are unable to obtain it through the web, please
 * send a note to license@php.net so we can mail you a copy immediately.
 *
 * @category   Encryption
 * @package    Crypt_Blowfish
 * @author     Matthew Fonda <mfonda@php.net>
 * @author     Philippe Jausions <jausions@php.net>
 * @copyright  2005-2006 Matthew Fonda
 * @license    http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version    CVS: $Id: encr-lib.php,v 1.2 2010/02/16 06:51:02 Prasanth Exp $
 * @link       http://pear.php.net/package/Crypt_Blowfish
 * @since      1.1.0
 */


/**
 * Example
 * <code>
 * $bf =& Crypt_Blowfish::factory('ecb');
 * if (PEAR::isError($bf)) {
 *     echo $bf->getMessage();
 *     exit;
 * }
 * $bf->setKey('My secret key');
 * $encrypted = $bf->encrypt('this is some example plain text');
 * $plaintext = $bf->decrypt($encrypted);
 * echo "plain text: $plaintext";
 * </code>
 *
 * @category   Encryption
 * @package    Crypt_Blowfish
 * @author     Matthew Fonda <mfonda@php.net>
 * @author     Philippe Jausions <jausions@php.net>
 * @copyright  2005-2006 Matthew Fonda
 * @license    http://www.php.net/license/3_0.txt  PHP License 3.0
 * @link       http://pear.php.net/package/Crypt_Blowfish
 * @version    @package_version@
 * @access     public
 * @since      1.1.0
 */
class Crypt_Blowfish_ECB extends Crypt_Blowfish_PHP
{
    /**
     * Crypt_Blowfish Constructor
     * Initializes the Crypt_Blowfish object, and sets
     * the secret key
     *
     * @param string $key
     * @param string $iv initialization vector
     * @access public
     */
    function Crypt_Blowfish_ECB($key = null, $iv = null)
    {
        $this->__construct($key, $iv);
    }

    /**
     * Class constructor
     *
     * @param string $key
     * @param string $iv initialization vector
     * @access public
     */
    function __construct($key = null, $iv = null)
    {
        $this->_iv_required = false;
        parent::__construct($key, $iv);
    }

    /**
     * Encrypts a string
     *
     * Value is padded with NUL characters prior to encryption. You may
     * need to trim or cast the type when you decrypt.
     *
     * @param string $plainText string of characters/bytes to encrypt
     * @return string|PEAR_Error Returns cipher text on success, PEAR_Error on failure
     * @access public
     */
    function encrypt($plainText)
    {
        if (!is_string($plainText)) {
            return PEAR::raiseError('Input must be a string', 0);
        } elseif (empty($this->_P)) {
            return PEAR::raiseError('The key is not initialized.', 8);
        }

        $cipherText = '';
        $len = strlen($plainText);
        $plainText .= str_repeat(chr(0), (8 - ($len % 8)) % 8);

        for ($i = 0; $i < $len; $i += 8) {
            list(, $Xl, $Xr) = unpack('N2', substr($plainText, $i, 8));
            $this->_encipher($Xl, $Xr);
            $cipherText .= pack('N2', $Xl, $Xr);
        }

        return $cipherText;
    }

    /**
     * Decrypts an encrypted string
     *
     * The value was padded with NUL characters when encrypted. You may
     * need to trim the result or cast its type.
     *
     * @param string $cipherText
     * @return string|PEAR_Error Returns plain text on success, PEAR_Error on failure
     * @access public
     */
    function decrypt($cipherText)
    {
        if (!is_string($cipherText)) {
            return PEAR::raiseError('Cipher text must be a string', 1);
        }
        if (empty($this->_P)) {
            return PEAR::raiseError('The key is not initialized.', 8);
        }

        $plainText = '';
        $len = strlen($cipherText);
        $cipherText .= str_repeat(chr(0), (8 - ($len % 8)) % 8);

        for ($i = 0; $i < $len; $i += 8) {
            list(, $Xl, $Xr) = unpack('N2', substr($cipherText, $i, 8));
            $this->_decipher($Xl, $Xr);
            $plainText .= pack('N2', $Xl, $Xr);
        }

        return $plainText;
    }
}

class Crypt_Blowfish_DefaultKey
{
    var $P = array();
    
    var $S = array();
    
    function Crypt_Blowfish_DefaultKey()
    {
        $this->P = array(
            0x243f6a88, 0x85a308d3, 0x13198a2e, 0x03707344,
	        0xa4093822, 0x299f31d0, 0x082efa98, 0xec4e6c89,
	        0x452821e6, 0x38d01377, 0xbe5466cf, 0x34e90c6c,
	        0xc0ac29b7, 0xc97c50dd, 0x3f84d5b5, 0xb5470917,
	        0x9216d5d9, 0x8979fb1b
        );
        
        $this->S = array(
            array(
         0xd1310ba6, 0x98dfb5ac, 0x2ffd72db, 0xd01adfb7,
	     0xb8e1afed, 0x6a267e96, 0xba7c9045, 0xf12c7f99,
	     0x24a19947, 0xb3916cf7, 0x0801f2e2, 0x858efc16,
	     0x636920d8, 0x71574e69, 0xa458fea3, 0xf4933d7e,
	     0x0d95748f, 0x728eb658, 0x718bcd58, 0x82154aee,
	     0x7b54a41d, 0xc25a59b5, 0x9c30d539, 0x2af26013,
	     0xc5d1b023, 0x286085f0, 0xca417918, 0xb8db38ef,
	     0x8e79dcb0, 0x603a180e, 0x6c9e0e8b, 0xb01e8a3e,
	     0xd71577c1, 0xbd314b27, 0x78af2fda, 0x55605c60,
	     0xe65525f3, 0xaa55ab94, 0x57489862, 0x63e81440,
	     0x55ca396a, 0x2aab10b6, 0xb4cc5c34, 0x1141e8ce,
	     0xa15486af, 0x7c72e993, 0xb3ee1411, 0x636fbc2a,
	     0x2ba9c55d, 0x741831f6, 0xce5c3e16, 0x9b87931e,
	     0xafd6ba33, 0x6c24cf5c, 0x7a325381, 0x28958677,
	     0x3b8f4898, 0x6b4bb9af, 0xc4bfe81b, 0x66282193,
	     0x61d809cc, 0xfb21a991, 0x487cac60, 0x5dec8032,
	     0xef845d5d, 0xe98575b1, 0xdc262302, 0xeb651b88,
	     0x23893e81, 0xd396acc5, 0x0f6d6ff3, 0x83f44239,
	     0x2e0b4482, 0xa4842004, 0x69c8f04a, 0x9e1f9b5e,
	     0x21c66842, 0xf6e96c9a, 0x670c9c61, 0xabd388f0,
	     0x6a51a0d2, 0xd8542f68, 0x960fa728, 0xab5133a3,
	     0x6eef0b6c, 0x137a3be4, 0xba3bf050, 0x7efb2a98,
	     0xa1f1651d, 0x39af0176, 0x66ca593e, 0x82430e88,
	     0x8cee8619, 0x456f9fb4, 0x7d84a5c3, 0x3b8b5ebe,
	     0xe06f75d8, 0x85c12073, 0x401a449f, 0x56c16aa6,
	     0x4ed3aa62, 0x363f7706, 0x1bfedf72, 0x429b023d,
	     0x37d0d724, 0xd00a1248, 0xdb0fead3, 0x49f1c09b,
	     0x075372c9, 0x80991b7b, 0x25d479d8, 0xf6e8def7,
	     0xe3fe501a, 0xb6794c3b, 0x976ce0bd, 0x04c006ba,
	     0xc1a94fb6, 0x409f60c4, 0x5e5c9ec2, 0x196a2463,
	     0x68fb6faf, 0x3e6c53b5, 0x1339b2eb, 0x3b52ec6f,
	     0x6dfc511f, 0x9b30952c, 0xcc814544, 0xaf5ebd09,
	     0xbee3d004, 0xde334afd, 0x660f2807, 0x192e4bb3,
	     0xc0cba857, 0x45c8740f, 0xd20b5f39, 0xb9d3fbdb,
	     0x5579c0bd, 0x1a60320a, 0xd6a100c6, 0x402c7279,
	     0x679f25fe, 0xfb1fa3cc, 0x8ea5e9f8, 0xdb3222f8,
	     0x3c7516df, 0xfd616b15, 0x2f501ec8, 0xad0552ab,
	     0x323db5fa, 0xfd238760, 0x53317b48, 0x3e00df82,
	     0x9e5c57bb, 0xca6f8ca0, 0x1a87562e, 0xdf1769db,
	     0xd542a8f6, 0x287effc3, 0xac6732c6, 0x8c4f5573,
	     0x695b27b0, 0xbbca58c8, 0xe1ffa35d, 0xb8f011a0,
	     0x10fa3d98, 0xfd2183b8, 0x4afcb56c, 0x2dd1d35b,
	     0x9a53e479, 0xb6f84565, 0xd28e49bc, 0x4bfb9790,
	     0xe1ddf2da, 0xa4cb7e33, 0x62fb1341, 0xcee4c6e8,
	     0xef20cada, 0x36774c01, 0xd07e9efe, 0x2bf11fb4,
	     0x95dbda4d, 0xae909198, 0xeaad8e71, 0x6b93d5a0,
	     0xd08ed1d0, 0xafc725e0, 0x8e3c5b2f, 0x8e7594b7,
	     0x8ff6e2fb, 0xf2122b64, 0x8888b812, 0x900df01c,
	     0x4fad5ea0, 0x688fc31c, 0xd1cff191, 0xb3a8c1ad,
	     0x2f2f2218, 0xbe0e1777, 0xea752dfe, 0x8b021fa1,
	     0xe5a0cc0f, 0xb56f74e8, 0x18acf3d6, 0xce89e299,
	     0xb4a84fe0, 0xfd13e0b7, 0x7cc43b81, 0xd2ada8d9,
	     0x165fa266, 0x80957705, 0x93cc7314, 0x211a1477,
	     0xe6ad2065, 0x77b5fa86, 0xc75442f5, 0xfb9d35cf,
	     0xebcdaf0c, 0x7b3e89a0, 0xd6411bd3, 0xae1e7e49,
	     0x00250e2d, 0x2071b35e, 0x226800bb, 0x57b8e0af,
	     0x2464369b, 0xf009b91e, 0x5563911d, 0x59dfa6aa,
	     0x78c14389, 0xd95a537f, 0x207d5ba2, 0x02e5b9c5,
	     0x83260376, 0x6295cfa9, 0x11c81968, 0x4e734a41,
	     0xb3472dca, 0x7b14a94a, 0x1b510052, 0x9a532915,
	     0xd60f573f, 0xbc9bc6e4, 0x2b60a476, 0x81e67400,
	     0x08ba6fb5, 0x571be91f, 0xf296ec6b, 0x2a0dd915,
	     0xb6636521, 0xe7b9f9b6, 0xff34052e, 0xc5855664,
	     0x53b02d5d, 0xa99f8fa1, 0x08ba4799, 0x6e85076a
            ),
            array(
        0x4b7a70e9, 0xb5b32944, 0xdb75092e, 0xc4192623,
	     0xad6ea6b0, 0x49a7df7d, 0x9cee60b8, 0x8fedb266,
	     0xecaa8c71, 0x699a17ff, 0x5664526c, 0xc2b19ee1,
	     0x193602a5, 0x75094c29, 0xa0591340, 0xe4183a3e,
	     0x3f54989a, 0x5b429d65, 0x6b8fe4d6, 0x99f73fd6,
	     0xa1d29c07, 0xefe830f5, 0x4d2d38e6, 0xf0255dc1,
	     0x4cdd2086, 0x8470eb26, 0x6382e9c6, 0x021ecc5e,
	     0x09686b3f, 0x3ebaefc9, 0x3c971814, 0x6b6a70a1,
	     0x687f3584, 0x52a0e286, 0xb79c5305, 0xaa500737,
	     0x3e07841c, 0x7fdeae5c, 0x8e7d44ec, 0x5716f2b8,
	     0xb03ada37, 0xf0500c0d, 0xf01c1f04, 0x0200b3ff,
	     0xae0cf51a, 0x3cb574b2, 0x25837a58, 0xdc0921bd,
	     0xd19113f9, 0x7ca92ff6, 0x94324773, 0x22f54701,
	     0x3ae5e581, 0x37c2dadc, 0xc8b57634, 0x9af3dda7,
	     0xa9446146, 0x0fd0030e, 0xecc8c73e, 0xa4751e41,
	     0xe238cd99, 0x3bea0e2f, 0x3280bba1, 0x183eb331,
	     0x4e548b38, 0x4f6db908, 0x6f420d03, 0xf60a04bf,
	     0x2cb81290, 0x24977c79, 0x5679b072, 0xbcaf89af,
	     0xde9a771f, 0xd9930810, 0xb38bae12, 0xdccf3f2e,
	     0x5512721f, 0x2e6b7124, 0x501adde6, 0x9f84cd87,
	     0x7a584718, 0x7408da17, 0xbc9f9abc, 0xe94b7d8c,
	     0xec7aec3a, 0xdb851dfa, 0x63094366, 0xc464c3d2,
	     0xef1c1847, 0x3215d908, 0xdd433b37, 0x24c2ba16,
	     0x12a14d43, 0x2a65c451, 0x50940002, 0x133ae4dd,
	     0x71dff89e, 0x10314e55, 0x81ac77d6, 0x5f11199b,
	     0x043556f1, 0xd7a3c76b, 0x3c11183b, 0x5924a509,
	     0xf28fe6ed, 0x97f1fbfa, 0x9ebabf2c, 0x1e153c6e,
	     0x86e34570, 0xeae96fb1, 0x860e5e0a, 0x5a3e2ab3,
	     0x771fe71c, 0x4e3d06fa, 0x2965dcb9, 0x99e71d0f,
	     0x803e89d6, 0x5266c825, 0x2e4cc978, 0x9c10b36a,
	     0xc6150eba, 0x94e2ea78, 0xa5fc3c53, 0x1e0a2df4,
	     0xf2f74ea7, 0x361d2b3d, 0x1939260f, 0x19c27960,
	     0x5223a708, 0xf71312b6, 0xebadfe6e, 0xeac31f66,
	     0xe3bc4595, 0xa67bc883, 0xb17f37d1, 0x018cff28,
	     0xc332ddef, 0xbe6c5aa5, 0x65582185, 0x68ab9802,
	     0xeecea50f, 0xdb2f953b, 0x2aef7dad, 0x5b6e2f84,
	     0x1521b628, 0x29076170, 0xecdd4775, 0x619f1510,
	     0x13cca830, 0xeb61bd96, 0x0334fe1e, 0xaa0363cf,
	     0xb5735c90, 0x4c70a239, 0xd59e9e0b, 0xcbaade14,
	     0xeecc86bc, 0x60622ca7, 0x9cab5cab, 0xb2f3846e,
	     0x648b1eaf, 0x19bdf0ca, 0xa02369b9, 0x655abb50,
	     0x40685a32, 0x3c2ab4b3, 0x319ee9d5, 0xc021b8f7,
	     0x9b540b19, 0x875fa099, 0x95f7997e, 0x623d7da8,
	     0xf837889a, 0x97e32d77, 0x11ed935f, 0x16681281,
	     0x0e358829, 0xc7e61fd6, 0x96dedfa1, 0x7858ba99,
	     0x57f584a5, 0x1b227263, 0x9b83c3ff, 0x1ac24696,
	     0xcdb30aeb, 0x532e3054, 0x8fd948e4, 0x6dbc3128,
	     0x58ebf2ef, 0x34c6ffea, 0xfe28ed61, 0xee7c3c73,
	     0x5d4a14d9, 0xe864b7e3, 0x42105d14, 0x203e13e0,
	     0x45eee2b6, 0xa3aaabea, 0xdb6c4f15, 0xfacb4fd0,
	     0xc742f442, 0xef6abbb5, 0x654f3b1d, 0x41cd2105,
	     0xd81e799e, 0x86854dc7, 0xe44b476a, 0x3d816250,
	     0xcf62a1f2, 0x5b8d2646, 0xfc8883a0, 0xc1c7b6a3,
	     0x7f1524c3, 0x69cb7492, 0x47848a0b, 0x5692b285,
	     0x095bbf00, 0xad19489d, 0x1462b174, 0x23820e00,
	     0x58428d2a, 0x0c55f5ea, 0x1dadf43e, 0x233f7061,
	     0x3372f092, 0x8d937e41, 0xd65fecf1, 0x6c223bdb,
	     0x7cde3759, 0xcbee7460, 0x4085f2a7, 0xce77326e,
	     0xa6078084, 0x19f8509e, 0xe8efd855, 0x61d99735,
	     0xa969a7aa, 0xc50c06c2, 0x5a04abfc, 0x800bcadc,
	     0x9e447a2e, 0xc3453484, 0xfdd56705, 0x0e1e9ec9,
	     0xdb73dbd3, 0x105588cd, 0x675fda79, 0xe3674340,
	     0xc5c43465, 0x713e38d8, 0x3d28f89e, 0xf16dff20,
	     0x153e21e7, 0x8fb03d4a, 0xe6e39f2b, 0xdb83adf7
            ),
            array(
      0xe93d5a68, 0x948140f7, 0xf64c261c, 0x94692934,
	 0x411520f7, 0x7602d4f7, 0xbcf46b2e, 0xd4a20068,
	 0xd4082471, 0x3320f46a, 0x43b7d4b7, 0x500061af,
	 0x1e39f62e, 0x97244546, 0x14214f74, 0xbf8b8840,
	 0x4d95fc1d, 0x96b591af, 0x70f4ddd3, 0x66a02f45,
	 0xbfbc09ec, 0x03bd9785, 0x7fac6dd0, 0x31cb8504,
	 0x96eb27b3, 0x55fd3941, 0xda2547e6, 0xabca0a9a,
	 0x28507825, 0x530429f4, 0x0a2c86da, 0xe9b66dfb,
	 0x68dc1462, 0xd7486900, 0x680ec0a4, 0x27a18dee,
	 0x4f3ffea2, 0xe887ad8c, 0xb58ce006, 0x7af4d6b6,
	 0xaace1e7c, 0xd3375fec, 0xce78a399, 0x406b2a42,
	 0x20fe9e35, 0xd9f385b9, 0xee39d7ab, 0x3b124e8b,
	 0x1dc9faf7, 0x4b6d1856, 0x26a36631, 0xeae397b2,
	 0x3a6efa74, 0xdd5b4332, 0x6841e7f7, 0xca7820fb,
	 0xfb0af54e, 0xd8feb397, 0x454056ac, 0xba489527,
	 0x55533a3a, 0x20838d87, 0xfe6ba9b7, 0xd096954b,
	 0x55a867bc, 0xa1159a58, 0xcca92963, 0x99e1db33,
	 0xa62a4a56, 0x3f3125f9, 0x5ef47e1c, 0x9029317c,
	 0xfdf8e802, 0x04272f70, 0x80bb155c, 0x05282ce3,
	 0x95c11548, 0xe4c66d22, 0x48c1133f, 0xc70f86dc,
	 0x07f9c9ee, 0x41041f0f, 0x404779a4, 0x5d886e17,
	 0x325f51eb, 0xd59bc0d1, 0xf2bcc18f, 0x41113564,
	 0x257b7834, 0x602a9c60, 0xdff8e8a3, 0x1f636c1b,
	 0x0e12b4c2, 0x02e1329e, 0xaf664fd1, 0xcad18115,
	 0x6b2395e0, 0x333e92e1, 0x3b240b62, 0xeebeb922,
	 0x85b2a20e, 0xe6ba0d99, 0xde720c8c, 0x2da2f728,
	 0xd0127845, 0x95b794fd, 0x647d0862, 0xe7ccf5f0,
	 0x5449a36f, 0x877d48fa, 0xc39dfd27, 0xf33e8d1e,
	 0x0a476341, 0x992eff74, 0x3a6f6eab, 0xf4f8fd37,
	 0xa812dc60, 0xa1ebddf8, 0x991be14c, 0xdb6e6b0d,
	 0xc67b5510, 0x6d672c37, 0x2765d43b, 0xdcd0e804,
	 0xf1290dc7, 0xcc00ffa3, 0xb5390f92, 0x690fed0b,
	 0x667b9ffb, 0xcedb7d9c, 0xa091cf0b, 0xd9155ea3,
	 0xbb132f88, 0x515bad24, 0x7b9479bf, 0x763bd6eb,
	 0x37392eb3, 0xcc115979, 0x8026e297, 0xf42e312d,
	 0x6842ada7, 0xc66a2b3b, 0x12754ccc, 0x782ef11c,
	 0x6a124237, 0xb79251e7, 0x06a1bbe6, 0x4bfb6350,
	 0x1a6b1018, 0x11caedfa, 0x3d25bdd8, 0xe2e1c3c9,
	 0x44421659, 0x0a121386, 0xd90cec6e, 0xd5abea2a,
	 0x64af674e, 0xda86a85f, 0xbebfe988, 0x64e4c3fe,
	 0x9dbc8057, 0xf0f7c086, 0x60787bf8, 0x6003604d,
	 0xd1fd8346, 0xf6381fb0, 0x7745ae04, 0xd736fccc,
	 0x83426b33, 0xf01eab71, 0xb0804187, 0x3c005e5f,
	 0x77a057be, 0xbde8ae24, 0x55464299, 0xbf582e61,
	 0x4e58f48f, 0xf2ddfda2, 0xf474ef38, 0x8789bdc2,
	 0x5366f9c3, 0xc8b38e74, 0xb475f255, 0x46fcd9b9,
	 0x7aeb2661, 0x8b1ddf84, 0x846a0e79, 0x915f95e2,
	 0x466e598e, 0x20b45770, 0x8cd55591, 0xc902de4c,
	 0xb90bace1, 0xbb8205d0, 0x11a86248, 0x7574a99e,
	 0xb77f19b6, 0xe0a9dc09, 0x662d09a1, 0xc4324633,
	 0xe85a1f02, 0x09f0be8c, 0x4a99a025, 0x1d6efe10,
	 0x1ab93d1d, 0x0ba5a4df, 0xa186f20f, 0x2868f169,
	 0xdcb7da83, 0x573906fe, 0xa1e2ce9b, 0x4fcd7f52,
	 0x50115e01, 0xa70683fa, 0xa002b5c4, 0x0de6d027,
	 0x9af88c27, 0x773f8641, 0xc3604c06, 0x61a806b5,
	 0xf0177a28, 0xc0f586e0, 0x006058aa, 0x30dc7d62,
	 0x11e69ed7, 0x2338ea63, 0x53c2dd94, 0xc2c21634,
	 0xbbcbee56, 0x90bcb6de, 0xebfc7da1, 0xce591d76,
	 0x6f05e409, 0x4b7c0188, 0x39720a3d, 0x7c927c24,
	 0x86e3725f, 0x724d9db9, 0x1ac15bb4, 0xd39eb8fc,
	 0xed545578, 0x08fca5b5, 0xd83d7cd3, 0x4dad0fc4,
	 0x1e50ef5e, 0xb161e6f8, 0xa28514d9, 0x6c51133c,
	 0x6fd5c7e7, 0x56e14ec4, 0x362abfce, 0xddc6c837,
	 0xd79a3234, 0x92638212, 0x670efa8e, 0x406000e0
            ),
            array(
0x3a39ce37, 0xd3faf5cf, 0xabc27737, 0x5ac52d1b,
	 0x5cb0679e, 0x4fa33742, 0xd3822740, 0x99bc9bbe,
	 0xd5118e9d, 0xbf0f7315, 0xd62d1c7e, 0xc700c47b,
	 0xb78c1b6b, 0x21a19045, 0xb26eb1be, 0x6a366eb4,
	 0x5748ab2f, 0xbc946e79, 0xc6a376d2, 0x6549c2c8,
	 0x530ff8ee, 0x468dde7d, 0xd5730a1d, 0x4cd04dc6,
	 0x2939bbdb, 0xa9ba4650, 0xac9526e8, 0xbe5ee304,
	 0xa1fad5f0, 0x6a2d519a, 0x63ef8ce2, 0x9a86ee22,
	 0xc089c2b8, 0x43242ef6, 0xa51e03aa, 0x9cf2d0a4,
	 0x83c061ba, 0x9be96a4d, 0x8fe51550, 0xba645bd6,
	 0x2826a2f9, 0xa73a3ae1, 0x4ba99586, 0xef5562e9,
	 0xc72fefd3, 0xf752f7da, 0x3f046f69, 0x77fa0a59,
	 0x80e4a915, 0x87b08601, 0x9b09e6ad, 0x3b3ee593,
	 0xe990fd5a, 0x9e34d797, 0x2cf0b7d9, 0x022b8b51,
	 0x96d5ac3a, 0x017da67d, 0xd1cf3ed6, 0x7c7d2d28,
	 0x1f9f25cf, 0xadf2b89b, 0x5ad6b472, 0x5a88f54c,
	 0xe029ac71, 0xe019a5e6, 0x47b0acfd, 0xed93fa9b,
	 0xe8d3c48d, 0x283b57cc, 0xf8d56629, 0x79132e28,
	 0x785f0191, 0xed756055, 0xf7960e44, 0xe3d35e8c,
	 0x15056dd4, 0x88f46dba, 0x03a16125, 0x0564f0bd,
	 0xc3eb9e15, 0x3c9057a2, 0x97271aec, 0xa93a072a,
	 0x1b3f6d9b, 0x1e6321f5, 0xf59c66fb, 0x26dcf319,
	 0x7533d928, 0xb155fdf5, 0x03563482, 0x8aba3cbb,
	 0x28517711, 0xc20ad9f8, 0xabcc5167, 0xccad925f,
	 0x4de81751, 0x3830dc8e, 0x379d5862, 0x9320f991,
	 0xea7a90c2, 0xfb3e7bce, 0x5121ce64, 0x774fbe32,
	 0xa8b6e37e, 0xc3293d46, 0x48de5369, 0x6413e680,
	 0xa2ae0810, 0xdd6db224, 0x69852dfd, 0x09072166,
	 0xb39a460a, 0x6445c0dd, 0x586cdecf, 0x1c20c8ae,
	 0x5bbef7dd, 0x1b588d40, 0xccd2017f, 0x6bb4e3bb,
	 0xdda26a7e, 0x3a59ff45, 0x3e350a44, 0xbcb4cdd5,
	 0x72eacea8, 0xfa6484bb, 0x8d6612ae, 0xbf3c6f47,
	 0xd29be463, 0x542f5d9e, 0xaec2771b, 0xf64e6370,
	 0x740e0d8d, 0xe75b1357, 0xf8721671, 0xaf537d5d,
	 0x4040cb08, 0x4eb4e2cc, 0x34d2466a, 0x0115af84,
	 0xe1b00428, 0x95983a1d, 0x06b89fb4, 0xce6ea048,
	 0x6f3f3b82, 0x3520ab82, 0x011a1d4b, 0x277227f8,
	 0x611560b1, 0xe7933fdc, 0xbb3a792b, 0x344525bd,
	 0xa08839e1, 0x51ce794b, 0x2f32c9b7, 0xa01fbac9,
	 0xe01cc87e, 0xbcc7d1f6, 0xcf0111c3, 0xa1e8aac7,
	 0x1a908749, 0xd44fbd9a, 0xd0dadecb, 0xd50ada38,
	 0x0339c32a, 0xc6913667, 0x8df9317c, 0xe0b12b4f,
	 0xf79e59b7, 0x43f5bb3a, 0xf2d519ff, 0x27d9459c,
	 0xbf97222c, 0x15e6fc2a, 0x0f91fc71, 0x9b941525,
	 0xfae59361, 0xceb69ceb, 0xc2a86459, 0x12baa8d1,
	 0xb6c1075e, 0xe3056a0c, 0x10d25065, 0xcb03a442,
	 0xe0ec6e0e, 0x1698db3b, 0x4c98a0be, 0x3278e964,
	 0x9f1f9532, 0xe0d392df, 0xd3a0342b, 0x8971f21e,
	 0x1b0a7441, 0x4ba3348c, 0xc5be7120, 0xc37632d8,
	 0xdf359f8d, 0x9b992f2e, 0xe60b6f47, 0x0fe3f11d,
	 0xe54cda54, 0x1edad891, 0xce6279cf, 0xcd3e7e6f,
	 0x1618b166, 0xfd2c1d05, 0x848fd2c5, 0xf6fb2299,
	 0xf523f357, 0xa6327623, 0x93a83531, 0x56cccd02,
	 0xacf08162, 0x5a75ebb5, 0x6e163697, 0x88d273cc,
	 0xde966292, 0x81b949d0, 0x4c50901b, 0x71c65614,
	 0xe6c6c7bd, 0x327a140a, 0x45e1d006, 0xc3f27b9a,
	 0xc9aa53fd, 0x62a80f00, 0xbb25bfe2, 0x35bdd2f6,
	 0x71126905, 0xb2040222, 0xb6cbcf7c, 0xcd769c2b,
	 0x53113ec0, 0x1640e3d3, 0x38abbd60, 0x2547adf0,
	 0xba38209c, 0xf746ce76, 0x77afa1c5, 0x20756060,
	 0x85cbfe4e, 0x8ae88dd8, 0x7aaaf9b0, 0x4cf9aa7e,
	 0x1948c25c, 0x02fb8a8c, 0x01c36ae4, 0xd6ebe1f9,
	 0x90d4f869, 0xa65cdea0, 0x3f09252d, 0xc208e69f,
	 0xb74e6132, 0xce77e25b, 0x578fdfe3, 0x3ac372e6
            )
        );
    }
    
}


class FM_FormPageRenderer extends FM_Module
{
    function FM_FormPageRenderer()
    {
    }

    function Process(&$continue)
    {
        if($this->NeedDisplayFormPage())
        {
            $this->logger->LogInfo("Displaying form page");
            $this->DisplayFormPage();
            $continue=false;
        }
        else
        {
            $continue=true;
        }
    }

	function NeedDisplayFormPage()
	{
		if(false == isset($this->formvars[$this->config->form_submit_variable]))
		{
			return true;
		}
		return false;
	}

    function RecordVariables()
    {
        if(isset($this->globaldata->get_vars['sfm_from_iframe']) && 
        $this->globaldata->get_vars['sfm_from_iframe'] == '1')
        {
            $_SESSION['sfm_from_iframe']=1;
        }
        else
        {
            $_SESSION['sfm_from_iframe']=0;
        }
        
        $referer = $this->globaldata->get_referer();

        $this->logger->LogInfo(" FormPageRenderer; Referer : $referer ");

        if(!empty($referer))
        {
            $_SESSION['sfm_referer_page'] = $referer;
        }
        else
        {
            unset($_SESSION['sfm_referer_page']);
        }

    }
		
	function DisplayFormPage()
	{
        $self_script = $this->globaldata->get_php_self();
        
        $this->RecordVariables();

		$fillerobj = new FM_FormFiller($this->config,$this->logger);

        $formvars = null;
		if($this->config->load_values_from_url)
        {
            $formvars= $this->formvars;
        }
        else
        {
		    $formvars= array();
        }

		if(false == $this->ext_module->BeforeFormDisplay($formvars))
		{
			$this->logger->LogError("Extension Module 'BeforeFormDisplay' returned false! ");
			return false;
		}
	
	    $var_map = $this->config->element_info->default_values;
		$var_map[$this->config->error_display_variable]="";
		$var_map[$this->config->self_script_variable]= $self_script;

		$fillerobj->CreateServerSideVector($formvars,$var_map);	

        if($this->config->form_page_code != '')
        {
            $this->DisplayUsingTemplate($var_map);
        }
        else
        {
        //todo: display the same page
        }
	}

    function DisplayUsingTemplate(&$var_map)
    {
        $merge = new FM_PageMerger();
        if(false == $merge->Merge($this->config->form_page_code,$var_map))
        {
            $this->error_handler->HandleConfigError(E_CONF_ERROR_FORMPAGE_MERGE);
            return false;
        }
        $strdisp = $merge->getMessageBody();
		echo $strdisp;
    }
}

?>