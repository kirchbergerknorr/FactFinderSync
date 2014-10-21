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


class Kirchbergerknorr_Shell_FactFinder extends Mage_Shell_Abstract
{
    public function log($message, $p1 = null, $p2 = null, $p3 = null)
    {
        echo sprintf($message, $p1, $p2, $p3)."\n";
    }

    public function logException($message, $p1 = null, $p2 = null, $p3 = null)
    {
        $colors = new Colors();
        $message = $colors->getColoredString("[EXCEPTION]", "red", "black").' '.$message;
        $this->log($message, $p1, $p2, $p3);
    }

    public function logInfo($message, $p1 = null, $p2 = null, $p3 = null)
    {
        $colors = new Colors();
        $message = $colors->getColoredString("[INFO]", "light_blue", "black").' '.$message;
        $this->log($message, $p1, $p2, $p3);
    }

    public function logSeparator()
    {
        $colors = new Colors();
        $message = $colors->getColoredString("\n\n------------------------------------------------------------", "white", "black").' ';
        $this->log($message);
    }


    protected function _fileGetContentsChunked($file, $chunk_size, $callback)
    {
        global $padding;
        $padding = 0;
        $handle = fopen($file, "r");
        $i = 0;
        while (!feof($handle))
        {
            fseek($handle, $chunk_size * $i + $padding);

            $current = ftell($handle);
            $fileSize = filesize($file);
            echo sprintf("%s%% [%s bytes from %s bytes] \r", round($current/$fileSize*100), $current, $fileSize);

            call_user_func_array($callback, array(fread($handle, $chunk_size), &$handle, $i));
            $i++;
        }

        fclose($handle);

        return true;
    }

    protected function updateId($id, $iteration)
    {
        $resource = Mage::getSingleton('core/resource');
        $table = $resource->getTableName('catalog_product_entity_int');
        $writeConnection = $resource->getConnection('core_write');
        $eavAttribute = new Mage_Eav_Model_Mysql4_Entity_Attribute();
        $code = $eavAttribute->getIdByCode('catalog_product', "factfinder_exists");
        $query = "REPLACE INTO {$table} (entity_id, entity_type_id, attribute_id, value) VALUES ('{$id}', 4, '{$code}', '1');";

        if (!defined('DEBUG_NO_SAVE') || !DEBUG_NO_SAVE) {
            $writeConnection->query($query);
        }

        if (defined('DEBUG_LOG_STEPS') && DEBUG_LOG_STEPS) {
            $this->log('Updated %s', $id);
        }
    }

    function load($fileName)
    {
        global $ids;
        $ids = array();

        $success = $this->_fileGetContentsChunked($fileName, 4096, function($chunk, &$handle, $iteration){
            global $ids;
            global $padding;

            $debugIteration = -1;

            if (defined('DEBUG_ITERATION')) {
                $debugIteration = DEBUG_ITERATION;
            }

            if (defined('DEBUG_LOG_STEPS') && DEBUG_LOG_STEPS) {
                $this->log('Iteration %s', $iteration);
            }

            preg_match_all('#(\n(\d+)\t).*#i', $chunk, $matches);

            foreach($matches[2] as $id) {
                if (!$id) {
                    continue;
                }

//                $this->logException('----------------');
//                echo $chunk;
//                echo "\n\n=====\n\n";
//                print_r($matches);
//                die;

                if ($id < 1000 || $padding) {
                    $this->logInfo("padding: %s", $padding);
                    $this->logInfo("id: %s, iteration: %s\n\n %s \n\n", $id, $iteration, $chunk);

                    $padding = -50;
                } else {
                    $padding = 0;
                }

                $this->updateId($id, $iteration);
            }

            if ($debugIteration == $iteration) {
                print_r($matches[2]); die;
            }
        });

        if(!$success) {
            throw new Exception('Failed');
        }

        if($success) {
            $this->logInfo('Finished');
        }
    }

    public function sync()
    {
        Mage::getModel('factfindersync/sync')->start();
    }

    public function help()
    {
        $this->logInfo('FactFinderSync Help:');

        $help = <<< HELP

    Start sync:

      php factfinder.php sync

    Save id (first column) from csv as factfinder_exists attribute:

      php factfinder.php check <filename.csv>

    Check if product id in factfinder:

      php factfinder.php test-id <Product Id>

    Check if products from database exists in factfinder and print which id were not found.
    Optionally get ids from file:

      php factfinder.php test-all [filename.csv]

HELP;

        $this->log($help);
    }

    public function run($params = false)
    {
        if (!$params || count($params) < 2) {
            $this->help();
            return false;
        }

        $method = $params[1];

        switch ($method)
        {
            case 'sync':

                $this->sync();
                break;

            case 'check':

                if (count($params) < 3) {
                    $this->help();
                    return false;
                }

                $fileName = $params[2];

                define('DEBUG_NO_SAVE', false);
                define('DEBUG_LOG_STEPS', false);

                if (isset($params[3])) {
                    define('DEBUG_ITERATION', $params[3]);
                }

                $this->log('Loading %s', $fileName);
                $this->load($fileName);
                break;

            case 'test-id':

                if (count($params) < 3) {
                    $this->help();
                    return false;
                }

                $id = $params[2];
                $result = Mage::getModel('factfindersync/factfinder')->searchId($id);
                $this->log("Found: %b", $result);
                break;

            case 'test-all':

                $ids = array();

                if (count($params) == 3) {
                    $fileName = $params[2];
                    $handle = fopen($fileName, "r");
                    while ($row = fgets($handle)) {
                        $ids[] = trim($row);
                    }
                }

                if (count($params) == 2) {
                    $collection = Mage::getModel('catalog/product')->getCollection()
                        ->addAttributeToFilter('factfinder_checked', array('null' => true), 'left')
                        ->setPageSize(5000);

                    foreach ($collection as $product) {
                        $id = $product->getId();
                        $ids[] = $id;
                    }
                }

                foreach ($ids as $id) {
                    $result = Mage::getModel('factfindersync/factfinder')->searchId($id);
                    $this->log("checking %s", $id);
                    if (!$result) {
                        $this->log("NOT FOUND: %s", $id);
                    }
                }

                break;

            default:
            case 'help':

                $this->help();
                return false;
                break;
        }
    }
}


$shell = new Kirchbergerknorr_Shell_FactFinder();

try {
    $shell->run($argv);
} catch (Exception $e) {
    $shell->logException($e->getMessage());
}
