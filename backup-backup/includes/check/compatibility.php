<?php
/**
 * This PHP code is designed to check the compatibility of the server environment with certain requirements.
 * It uses the Strategy Design Pattern to encapsulate the different compatibility checks into separate classes.
 */

// Namespace
namespace BMI\Plugin\Checker;

if (!defined('ABSPATH')) exit;

// Use
use BMI\Plugin\Backup_Migration_Plugin as BMP;



/**
 * The Comparision interface defines the method for comparing a value with a recommendation.
 */
interface Comparision {

    /**
     * Compare the value with the recommendation.
     * @param mixed $value The value to compare.
     * @param mixed $recommendation The recommendation to compare with.
     * @return bool True if the value is compatible with the recommendation, false otherwise.
     */
    public function compare($value, $recommendation);
}

/**
 * The Version_Comparision class implements the Comparision interface for version comparisons.
 */
class Version_Comparision implements Comparision {
    public function compare($value, $recommendation, $operator = '>=') {
        return version_compare($value, $recommendation, '>=');
    }
}

/**
 * The String_Comparision class implements the Comparision interface for string comparisons.
 */
class String_Comparision implements Comparision {
    public function compare($value, $recommendation)
    {
        return strpos($value, $recommendation) !== false;
    }
}

/**
 * The In_Comparision class implements the Comparision interface for checking if a value is in a set of recommendations.
 */
class In_Comparision implements Comparision {
    public function compare($value, $recommendation)
    {
        foreach ($recommendation as $rec) {
            if (strpos($value, $rec) !== false) {
                return true;
            }
        }
        return false;
    }
}

/**
 * The Int_Comparision class implements the Comparision interface for integer comparisons.
 */
class Int_Comparision implements Comparision {
    public function compare($value, $recommendation)
    {
        if (is_string($value)) {
            $value = intval($value);
            $recommendation = intval($recommendation);
        }
        return $value >= $recommendation;
    }
}

/**
 * The Bool_Comparision class implements the Comparision interface for boolean comparisons.
 */
class Bool_Comparision implements Comparision {
    public function compare($value, $recommendation)
    {
        return boolval($value) === boolval($recommendation);
    }
}

/**
 * The Compatibility_Attribute abstract class defines the structure for a compatibility attribute.
 * It uses a Comparision object to compare a system value with a recommendation.
 */
abstract class Compatibility_Attribute {

    /**
     * The key to access the system value.
     * @var string
     */
    protected $key;

    /**
     * The recommendation to compare with.
     * @var mixed
     */
    protected $recommendation;

    /**
     * The Comparision object to use for the comparison.
     * @var Comparision
     */
    protected $comparision;

    /**
     * The error message for the compatibility check.
     * @var string
     */
    protected $error_message;

    /**
     * Constructor
     * @param mixed $recommendation The recommendation to compare with.
     * @param string|null $key The key to access the system value. (if null, the compatability check will be determined by the checkValue method)
     * @param Comparision $comparision The Comparision object to use for the comparison.
     */
    public function __construct($recommendation, $key, $comparision)
    {
        $this->recommendation = $recommendation;
        $this->key = $key;
        $this->comparision = $comparision;
    }

    /**
     * Check if the system value is compatible with the recommendation.
     * @param array $system The system information.
     * @return bool True if the system value is compatible with the recommendation, false otherwise.
     */
    public function isCompatible($system){
        if ($this->keyExists($system)) {
            return $this->comparision->compare($system[$this->key], $this->recommendation);
        }else{
            if (method_exists($this, 'checkValue')) {
                return $this->checkValue();
            }
        }
        return false;
    }

    /**
     * Check if the key exists in the system information.
     * @param array $system The system information.
     * @return bool True if the key exists, false otherwise.
     */
    protected function keyExists($system) {
        return isset($system[$this->key]);
    }

    /**
     * Set the error message for the compatibility check.
     * @param string $message The error message.
     */
    function setErrorMessage($message){
        $this->error_message = $message;
    }
    
    /**
     * Get the error message for the compatibility check.
     * @return string The error message.
     */
    function getErrorMessage(){
        return $this->error_message;
    }
}

/**
 * The PHP_Version class extends Compatibility_Attribute to check the PHP version compatibility.
 */
class PHP_Version extends Compatibility_Attribute {

    function __construct($recommendation, $key, $comparision)
    {
        parent::__construct($recommendation, $key, $comparision);
        $this->error_message = __("Your site is on PHP 8+, and some of your plugins are only compatible with older PHP versions, causing issues during the backup creation. Either disable those plugins or temporarily change to an earlier PHP version.", 'backup-backup');

    }
    protected function checkValue() {
        $value = explode(' ', phpversion())[0]; 
        return !$this->comparision->compare($value, $this->recommendation);
    }
}

/**
 * The WP_Debug_Enabled class extends Compatibility_Attribute to check if WP_DEBUG is enabled.
 */
class WP_Debug_Enabled extends Compatibility_Attribute {

    function __construct($recommendation, $key, $comparision)
    {
        parent::__construct($recommendation, $key, $comparision);
        $this->error_message = __("The WP_DEBUG is not active. It is recommended to enable it.", 'backup-backup');
    }
    protected function checkValue() {
        return $this->comparision->compare(WP_DEBUG, $this->recommendation);
    }

}

/**
 * The KeepAlive_Timeout class extends Compatibility_Attribute to check the KeepAlive timeout compatibility.
 * Note: The checkValue method is not implemented yet.
 */
class KeepAlive_Timeout extends Compatibility_Attribute {

    function __construct($recommendation, $key, $comparision)
    {
        parent::__construct($recommendation, $key, $comparision);
        $this->error_message = __("The KeepAlive is not compatible. The recommended value is %s1.", 'backup-backup');
        $this->error_message= str_replace(
            ['%s1'],
            [$this->recommendation],
            $this->error_message
        );
    }
    protected function checkValue() {
        //TODO: implement the check        
    }
   
}


class CURL_Enabled extends Compatibility_Attribute {

    function __construct($recommendation, $key, $comparision)
    {
        parent::__construct($recommendation, $key, $comparision);
        $this->error_message = __("The CURL is not enabled. It is recommended to enable it.", 'backup-backup');
    }

    // return false if curl is not enabled and user use alternative backup method
    protected function checkValue() {
        return $this->comparision->compare(System_Info::is_curl_work(), $this->recommendation);

    }
}

class PHP_CLI_Enabled extends Compatibility_Attribute {

    function __construct($recommendation, $key, $comparision)
    {
        parent::__construct($recommendation, $key, $comparision);
        $this->error_message = __("The PHP CLI is not active. It is recommended to enable it.", 'backup-backup');
    }

    // return false if php_cli is not enabled and user use default backup method
    protected function checkValue() {
        return $this->comparision->compare(System_Info::is_php_cli_runnable(), $this->recommendation);
    }

    function getErrorMessage() {
        return $this->error_message;
    }
}

class Disk_Space extends Compatibility_Attribute {

    protected $available_space;

    public function __construct($recommendation, $key, $comparision)
    {
        parent::__construct($recommendation, $key, $comparision);
        $this->available_space = $this->getDiskAvaialbleSpace();
        $this->error_message = __("The minimum required disk space is %s1, while the available space is %s2.", 'backup-backup');
        $this->error_message = str_replace(
            ['%s1', '%s2'],
            [BMP::humanSize(intval($this->recommendation)), BMP::humanSize(intval($this->available_space))],
            $this->error_message
        );
    }
    protected function checkValue() {
        return $this->comparision->compare(intval($this->available_space), intval($this->recommendation));
    }

    public function getDiskAvaialbleSpace(){

        return $this->getDiskAvaialbleSpaceByHARDWay();
    }

    public function getDiskAvaialbleSpaceByHARDWay() {

        $file = BMI_BACKUPS . '/' . '.space_check';
        try {
            $size = $this->recommendation;
            $fh = fopen($file, 'w');
            while($size > 0){
                $chunk = 1024;
                fputs($fh, str_pad('', min($chunk, $size)));
                $size -= $chunk;
            }
            fclose($fh);

            $fs = filesize($file);
            @unlink($file);

            return $fs;


        } catch (\Exception $e) {
            if (file_exists($file)){
                $fileSize  = filesize($file);
                unlink($file);
                return $fileSize;
            }

        } catch (\Throwable $e) {
            if (file_exists($file)){
                $fileSize = filesize($file);
                unlink($file);
                return $fileSize;
            } 

        }
        return false;
      
    }
}

class Normal_Attribute extends Compatibility_Attribute{
    
    function __construct($recommendation, $key, $comparision, $error_message = '')
    {
        parent::__construct($recommendation, $key, $comparision);
        $this->error_message = $error_message != '' ? $error_message : __("The %s1 is not compatible. The recommended value is %s2.", 'backup-backup');
        $recommendation = $this->recommendation;
        if (is_array($recommendation)) {
            $recommendation = implode('/', $recommendation);
        }
        $this->error_message= str_replace(
            ['%s1', '%s2'],
            [$this->key, $recommendation],
            $this->error_message
        );
        
    }
}


/**
 * The Compatibility class is used to add compatibility strategies and check the compatibility of the system.
 */
class Compatibility {
    private $attrs = [];
    protected $errors = [];
    protected $system_info;

    protected $for;

    /**
     * Constructor to initialize the system information and add default compatibility strategies.
     */
    public function __construct($for = 'backup') {
        require_once BMI_INCLUDES . DIRECTORY_SEPARATOR . 'check' . DIRECTORY_SEPARATOR . 'system_info.php';
        $system = new System_Info();
        $this->system_info = $system->to_array();
        $this->for = $for;
        $this->addDefaultStrategies();
        $this->addMoreRecommendations();
    }

    /**
     * Add default compatibility strategies based on the type of operation.
     */
    public function addDefaultStrategies() {
        if ($this->for == 'backup') {
            $this->addBackupDefaultStrategies();
        } else if ($this->for == 'migration') {
            $this->addMigrationDefaultStrategies();
        }
    }

    /**
     * Add default compatibility strategies for migration.
     */
    public function addMigrationDefaultStrategies() {
        $this->addStrategy(new Normal_Attribute(true, 'php_allow_url_fopen', new Bool_Comparision(), __("allow_url_fopen is not enabled.", 'backup-backup')));
        $this->addStrategy(new Normal_Attribute('5.5', 'mysql_version', new Version_Comparision(), __("MySQL version is not compatible. recommended to use version 5.5+.", 'backup-backup')));
        $this->addStrategy(new Normal_Attribute(['Apache', 'Nginx'], 'web_server_name', new In_Comparision(), __("We recommend using Apache/Nginx server type.", 'backup-backup')));
        $this->addStrategy(new PHP_Version('8.0', null, new Version_Comparision()));

        $max_execution_time = new Normal_Attribute('180', 'php_max_execution_time', new Int_Comparision());
        $max_execution_time_error = __("PHP max execution time is %s1. The recommended value is %s2.", 'backup-backup');
        $max_execution_time_error = str_replace(
            ['%s1', '%s2'],
            [$this->system_info['php_max_execution_time'], '180'],
            $max_execution_time_error
        );
        $max_execution_time->setErrorMessage($max_execution_time_error);
        $this->addStrategy($max_execution_time);
    }

    /**
     * Add default compatibility strategies for backup.
     */
    public function addBackupDefaultStrategies() {

        $this->addStrategy(new CURL_Enabled(true, null, new Bool_Comparision()));
        $this->addStrategy(new Normal_Attribute(true, 'php_allow_url_fopen', new Bool_Comparision(), __("allow_url_fopen is not enabled.", 'backup-backup')));
        $this->addStrategy(new Normal_Attribute('5.5', 'mysql_version', new Version_Comparision(), __("MySQL version is not compatible. recommended to use version 5.5+.", 'backup-backup')));
        $this->addStrategy(new Normal_Attribute(['Apache', 'Nginx'], 'web_server_name', new In_Comparision(), __("We recommend using Apache/Nginx server type.", 'backup-backup')));
        $this->addStrategy(new PHP_Version('8.0', null, new Version_Comparision()));

        $max_execution_time = new Normal_Attribute('180', 'php_max_execution_time', new Int_Comparision());
        $max_execution_time_error = __("PHP max execution time is %s1. The recommended value is %s2.", 'backup-backup');
        $max_execution_time_error = str_replace(
            ['%s1', '%s2'],
            [$this->system_info['php_max_execution_time'], '180'],
            $max_execution_time_error
        );
        $max_execution_time->setErrorMessage($max_execution_time_error);
        $this->addStrategy($max_execution_time);
    }

    /**
     * Add more recommendations based on verbose in log.
     * @return void
     */
    public function addMoreRecommendations() {
        // e.g.
        // $this->addRecommendation('missing space.', __("The disk space is not enough. Please free up some space.", 'backup-backup'));
        if ($this->for == 'backup') {
            $this->addBackupRecommendations();
        } else if ($this->for == 'migration') {
            $this->addMigrationRecommendations();
        }
    }

    public function addMigrationRecommendations() {
    }

    public function addBackupRecommendations() {
        $this->addRecommendation('Disk quota exceeded', __("There is not enough space for the backup, please free up some space.", 'backup-backup'));
    }

    /**
     * Add recommendation based on vebose in log
     * @param string $verbose The verbose in log
     * @param string $the message to show
     * @return void
     */
    public function addRecommendation($verbose, $message) {
        $file = dirname(BMI_BACKUPS) . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . 'latest.log';
        $content = file_get_contents($file);
        $pattern = '#^\[VERBOSE\] \[[0-9-]+ [0-9:]+\] ' . $verbose . '#mi'; // e.g. [VERBOSE] [2021-12-31 23:59:59] missing space.
        if (preg_match($pattern, $content, $matches)) {
            array_push($this->errors, $message);
        }

    }
    /**
     * Add a compatibility strategy.
     * @param Compatibility_Attribute $strategy The compatibility strategy to add.
     */
    public function addStrategy( Compatibility_Attribute $strategy) {
        array_push($this->attrs, $strategy);
    }

    /**
     * Check the compatibility of the system.
     * @return array The errors found during the compatibility check.
     */
    public function check() {
        foreach ($this->attrs as $attribute) {
            if(is_array($this->system_info) && !$attribute->isCompatible($this->system_info)) {
                array_push($this->errors, $attribute->getErrorMessage());
            }
        }
        return $this->errors;
    }

    /**
     * Get backup size from backup log.
     * @return int $bytes 
     */
    public function getBackupSize() {
        if(current_user_can('manage_options') && current_user_can('administrator')) {
            return BMP::getRecentSize() * 1.4;
        }
    }
    
}

