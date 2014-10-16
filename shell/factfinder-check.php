<?php

require_once "../app/Mage.php";
require_once 'abstract.php';

Mage::app('admin');
Mage::setIsDeveloperMode(true);

class Colors {
    private $foreground_colors = array();
    private $background_colors = array();

    public function __construct() {
        // Set up shell colors
        $this->foreground_colors['black'] = '0;30';
        $this->foreground_colors['dark_gray'] = '1;30';
        $this->foreground_colors['blue'] = '0;34';
        $this->foreground_colors['light_blue'] = '1;34';
        $this->foreground_colors['green'] = '0;32';
        $this->foreground_colors['light_green'] = '1;32';
        $this->foreground_colors['cyan'] = '0;36';
        $this->foreground_colors['light_cyan'] = '1;36';
        $this->foreground_colors['red'] = '0;31';
        $this->foreground_colors['light_red'] = '1;31';
        $this->foreground_colors['purple'] = '0;35';
        $this->foreground_colors['light_purple'] = '1;35';
        $this->foreground_colors['brown'] = '0;33';
        $this->foreground_colors['yellow'] = '1;33';
        $this->foreground_colors['light_gray'] = '0;37';
        $this->foreground_colors['white'] = '1;37';

        $this->background_colors['black'] = '40';
        $this->background_colors['red'] = '41';
        $this->background_colors['green'] = '42';
        $this->background_colors['yellow'] = '43';
        $this->background_colors['blue'] = '44';
        $this->background_colors['magenta'] = '45';
        $this->background_colors['cyan'] = '46';
        $this->background_colors['light_gray'] = '47';
    }

    // Returns colored string
    public function getColoredString($string, $foreground_color = null, $background_color = null) {
        $colored_string = "";

        // Check if given foreground color found
        if (isset($this->foreground_colors[$foreground_color])) {
            $colored_string .= "\033[" . $this->foreground_colors[$foreground_color] . "m";
        }
        // Check if given background color found
        if (isset($this->background_colors[$background_color])) {
            $colored_string .= "\033[" . $this->background_colors[$background_color] . "m";
        }

        // Add string and end coloring
        $colored_string .=  $string . "\033[0m";

        return $colored_string;
    }

    // Returns all foreground color names
    public function getForegroundColors() {
        return array_keys($this->foreground_colors);
    }

    // Returns all background color names
    public function getBackgroundColors() {
        return array_keys($this->background_colors);
    }
}

class Kirchbergerknorr_Shell_FactFinderCheck extends Mage_Shell_Abstract
{
    public function log($message, $p1 = null, $p2 = null)
    {
        echo sprintf($message, $p1, $p2)."\n";
    }

    public function logException($message, $p1 = null, $p2 = null)
    {
        $colors = new Colors();
        $message = $colors->getColoredString("[EXCEPTION]", "red", "black").' '.$message;
        $this->log($message, $p1, $p2);
    }

    public function logInfo($message, $p1 = null, $p2 = null)
    {
        $colors = new Colors();
        $message = $colors->getColoredString("[INFO]", "light_blue", "black").' '.$message;
        $this->log($message, $p1, $p2);
    }

    public function logSeparator()
    {
        $colors = new Colors();
        $message = $colors->getColoredString("\n\n------------------------------------------------------------", "white", "black").' ';
        $this->log($message);
    }


    protected function _fileGetContentsChunked($file, $chunk_size, $callback)
    {
        $handle = fopen($file, "r");
        $i = 0;
        while (!feof($handle))
        {
            call_user_func_array($callback, array(fread($handle, $chunk_size), &$handle, $i));
            $i++;
        }

        fclose($handle);

        return true;
    }

    protected function updateId($id) {
        $resource = Mage::getSingleton('core/resource');
        $table = $resource->getTableName('catalog_product_entity_int');
        $writeConnection = $resource->getConnection('core_write');
        $eavAttribute = new Mage_Eav_Model_Mysql4_Entity_Attribute();
        $code = $eavAttribute->getIdByCode('catalog_product', "factfinder_exists");
        $query = "REPLACE INTO {$table} (entity_id, entity_type_id, attribute_id, value) VALUES ('{$id}', 4, '{$code}', '1');";

        $writeConnection->query($query);
        $this->log('Updated %s', $id);
    }

    function load($fileName)
    {
        global $ids;
        $ids = array();

        $success = $this->_fileGetContentsChunked($fileName, 4096, function($chunk, &$handle, $iteration){
            $this->log('Iteration %s', $iteration);
            global $ids;
            preg_match_all('#(\s(\d+)).*#i', $chunk, $matches);
            unset($matches[2][0]);

            foreach($matches[2] as $id) {
                $this->updateId($id);
            }
        });

        if(!$success) {
            throw new Exception('Failed');
        }

        if($success) {
            $this->logInfo('Finished');
        }
    }

    public function run($params = false)
    {
        if (!$params || count($params) < 2) {
            $help = <<< HELP
USAGE:

    php factfinder-check.php <filename.csv> - save id (first column) from csv as factfinder_exists attribute

HELP;

            $this->log($help);
            return false;
        }

        $this->logInfo('Started');

        unset($params[0]);
        $fileName = $params[1];

        $this->log('Loading %s', $fileName);
        $this->load($fileName);
    }
}

$shell = new Kirchbergerknorr_Shell_FactFinderCheck();
$shell->logSeparator();

try {
    $shell->run($argv);
} catch (Exception $e) {
    $shell->logException($e->getMessage());
}
