<?php
/**
 * 
 * Client for Mailup Http Api
 * -----------------------------------------------------------------------------
 * Performs (one user for request)
 *  - recipient subscription
 *  - recipient unsubscription, update on a list
 *  - recipient's personal data update
 *  - recipient's status check
 * 
 * 
 * @docs (Mailup) http://help.mailup.com/display/mailupapi/HTTP+API+Specifications
 * @author - Emanuele Fornasier
 * @version 2016/03/11
 * 
 */
namespace SirFaenor\MailupHttpClient;
use \Exception as Exception;
class Client {


    /**
     * Connection
     *
     * @var curl instance
     */
    protected $_connection;
    
    
    /**
     * Last Mailup error 
     *
     * @var int
     */
    private $_lastError = array("code" => null, "message" => null);


    /**
     * Base url
     *
     * @var string: url for Mailup web service
     */
    protected $_baseUrl;

    /**
     * List data
     *
     * @var string
     */
    protected $_listId;

    /**
     * Personal data codes map
     *
     * @var array
     */
    protected $_csvMap = array();


    /**
     * Last curl response
     */
    protected $lastResponse = null;


    /**
     * Last curl response's httpcdode
     */
    protected $lastResponseCode = null;

    /**
     * Curl connection and options
     * 
     * @param $consoleUrl string :  Mailup Console Url (e.g. http://xxxxxx.yyy.it)
     * @return curl resource
     * @throws generic Exception
     */
    public function __construct ($consoleUrl, $csvMap = null) {
            
        if (!$consoleUrl) {
            throw new Exception("Mailup console url missing.");
        }

        // Inits curl
        $this->_connection = curl_init();
        if (!$this->_connection) {
            throw new Exception("Error estabilishing curl connection.");
        }
    
        // Sets options        
        curl_setopt($this->_connection, CURLOPT_HEADER, 0); 
        curl_setopt($this->_connection, CURLOPT_POST,1);
        curl_setopt($this->_connection, CURLOPT_RETURNTRANSFER, true); // per non stampare direttamente output

        $this->_baseUrl = rtrim($consoleUrl, '/');
        $this->_baseUrl = str_replace("/frontend", "", $this->_baseUrl);
        $this->_baseUrl .= '/frontend/';
       
        // Sets defaults map
        $this->_csvMap = $csvMap ? $csvMap : $this->_csvMap;

        return $this->_connection;
    }



    /**
     * Sets personal data codes map
     * 
     * @param array $map("mailup name" => custom name)
     */
    public function setCsvMap(array $map) {
        $this->_csvMap = $map;
    }


    /**
     * Checks incoming csv fields against defined map
     * 
     * @param array $data
     * @return array filtered data
     */
    private function _parseCsv($data) {

        if (!$data) {
            return null;
        }

        $returnData = array();

        foreach ($data as $customName => $value): 
            
            $mailupName = array_search($customName, $this->_csvMap);

            if ($mailupName) {
                $returnData[$mailupName] = $value;
            }      
        endforeach;

        return $returnData;

    }


    /**
     * Performs a single http request via curl
     * 
     * @param string $methodUrl: end point for operation 
     * @param array $data post fields
     * @return raw curl response
     * @throws generic exception
     */
    private function _exec($methodUrl, $data) {
    
        $response = curl_setopt($this->_connection, CURLOPT_URL, $this->_baseUrl.$methodUrl);

        if ($response == false) {
            throw new Exception("Error setting curl option.");
        }

        curl_setopt($this->_connection, CURLOPT_POSTFIELDS, $data);

        $response = curl_exec($this->_connection);

        $httpcode = curl_getinfo($this->_connection, CURLINFO_HTTP_CODE);

        $this->lastResponse = $response;
        $this->lastResponseCode = $httpcode;
        
        if ($httpcode !== 200 || $response == false) {
            throw new Exception("Error calling url: ".$this->_baseUrl.$methodUrl);
        }

        return $response;
    
    }


    /**
     * Subscription
     * 
     * @param string $mailOrSms
     * @param int $list list ID or array of IDs
     * @param int $group group ID or array of IDs
     * @param array $csvFld personal data csv field
     * @param bool $confirm 1 to double opt-in
     * @return true on success
     * @throws generic Exception with mailup error
     * 
     */
    public function subscribe($mailOrSms, $list, $group, $csvFld = array(), $confirm = 0) {

        $data = array();
        $data["Email"] =  $this->validateEmail($mailOrSms) ? $mailOrSms : null;
        $data["Sms"] =  $data["Email"] ? null : $mailOrSms;
        $data["List"] =  is_array($list) ? implode(",", $list) : $list;
        $data["Group"] =  is_array($group) ? implode(",", $group) : $group;
        $data["Confirm"] = $confirm;

        //  1 to receive a response code instead of a text string
        $data["retCode"] = 1;
     
        $filteredData =  $csvFld ? $this->_parseCsv($csvFld) : array();
        $data["csvFldNames"] = implode(";",array_keys($filteredData));
        $data["csvFldValues"] = implode(";", $filteredData);

        $response = $this->_exec("xmlSubscribe.aspx", $data);

        if ((int)$response != 0) {
            
            $this->_lastError["code"] = $response;

            switch ($response) :
                case 1 : $error = 'Generic error'; break;
                case 2 : $error = 'Invalid email address or mobile number'; break;
                case 3 : $error = 'Recipient already subscribed'; break;
                case -1011 : $error = 'Generic error'; break; 
                default : $error = 'Unknown error';
            endswitch;
           
            $this->_lastError["message"] = $error;

            throw new Exception("Subscription error ".$error);
        } 

        return true;

    }

    
    /**
     * Unsubscription
     * 
     * @param string $mailOrSms
     * @param int $list list ID
     * @param string $listGuid
     * @throws generic Exception with mailup error
     * @return true on success
     * 
     */
    public function unsubscribe($mailOrSms, $list, $listGuid) {

        $data = array();
        $data["Email"] =  $this->validateEmail($mailOrSms) ? $mailOrSms : null;
        $data["Sms"] =  $data["Email"] ? null : $mailOrSms;
        $data["List"] =  $list;
        $data["ListGuid"] =  $listGuid;

        $response = (int) $this->_exec("Xmlunsubscribe.aspx", $data);

        if ($response != 0) {
            
            $this->_lastError["code"] = $response;

            switch ($response) :
                case 1 : $error = 'Generic error'; break;
                case 3 : $error = 'Recipient unknown / already unsubscribed'; break;
                default : $error = 'Unknown error';
            endswitch;
           
            $this->_lastError["message"] = $error;

            throw new Exception("Unsubscription error ".$error);
        } 

        return true;
    }

    
     /**
     * Checks subscription status
     * 
     * @param string $mailOrSms
     * @param int $list list ID
     * @param string $listGuid
     * @throws generic Exception with mailup error
     * @return recipient current status
     * 
     */
    public function checkSubscription($mailOrSms, $list, $listGuid) {
        
        $data = array();
        $data["Email"] = $this->validateEmail($mailOrSms) ? $mailOrSms : null;
        $data["Sms"] =  $data["Email"] ? null : $mailOrSms;
        $data["List"] =  $list;
        $data["ListGuid"] =  $listGuid;

        $response = (int) $this->_exec("Xmlchksubscriber.aspx", $data);

        if ($response == 1) {
            
            $this->_lastError["code"] = $response;

            switch ($response) :
                case 1 : $error = 'Generic error / recipient does not exist (check ListId and ListGuid)'; break;
                default : $error = 'Unknown error';
            endswitch;
           
            $this->_lastError["message"] = $error;

            throw new Exception("Subscription check error ".$error);
        } 

        $statusCodes = array(
             2 => "subscribed"
            ,3 => "unsubscribed"
            ,4 => "pending"
        );

        return $statusCodes[$response];

    }


     /**
     * Updates a recipient's profile
     * 
     * @param string $mailOrSms
     * @param int $list list ID
     * @param string $listGuid
     * @param array $csvFld personal data csv field
     * @param int|array $group group ID or array of IDs
     * @param boolean true to enable group replacement
     * @return true on success
     * @throws generic Exception with mailup error
     * 
     */
   public function update($mailOrSms, $list, $listGuid, $csvFld = array(), $group = null, $groupReplacement = false) {
        
        $data = array();
        $data["Email"] =  $this->validateEmail($mailOrSms) ? $mailOrSms : null;
        $data["Sms"] =  $data["Email"] ? null : $mailOrSms;
        $data["List"] =  $list;
        $data["ListGuid"] =  $listGuid;
        $data["Group"] =  is_array($group) ? implode(";", $group) : $group;
        $data["Replace"] =  $groupReplacement;
     
        $filteredData =  $this->_parseCsv($csvFld);
        $data["csvFldNames"] = implode(";",array_keys($filteredData));
        $data["csvFldValues"] = implode(";", $filteredData);

        $response = (int) $this->_exec("xmlUpdSubscriber.aspx", $data);

        if ($response != 0) {
            
            $this->_lastError["code"] = $response;

            switch ($response) :
                case 1 : $error = 'Generic error'; break;
                default : $error = 'Unknown error';
            endswitch;
           
            $this->_lastError["message"] = $error;

            throw new Exception("Subscription check error ".$error);
        } 

        return true;

    }


    /**
     * Returns last Mailup error
     * 
     * @param $getAsString int : "1" for original error message
     * @return last mailup api error info
     */
    public function getLastError($getAsString = 0) {
        return ! $getAsString ? (int)$this->_lastError["code"] : $this->_lastError["message"];
    }

    
    /**
     * Returns last curl response
     */
    public function getLastResponse() {
        return $this->lastResponse;
    }

    /**
     * Returns last curl httpdoce
     */
    public function getLastResponseCode() {
        return $this->lastResponseCode;
    }



    /**
     * Validates incoming email address
     * @param string $email 
     * @return string $email or null
     */
    protected function validateEmail($mail) {
        return filter_var($mail, FILTER_VALIDATE_EMAIL);

    }


}
