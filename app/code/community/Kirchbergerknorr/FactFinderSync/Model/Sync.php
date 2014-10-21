<?php
/**
 * Sync Model
 *
 * @category    Kirchbergerknorr
 * @package     Kirchbergerknorr
 * @author      Aleksey Razbakov <ar@kirchbergerknorr.de>
 * @copyright   Copyright (c) 2014 kirchbergerknorr GmbH (http://www.kirchbergerknorr.de)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Kirchbergerknorr_FactFinderSync_Model_Sync
{
    /**
     * Our process ID.
     */
    const PROCESS_ID = 'kk_factfindersync';

    /**
     * Mage_Index_Model_Process will provide us a lock file API.
     *
     * @var Mage_Index_Model_Process $indexProcess
     */
    private $indexProcess;

    /**
     * Constructor.  Instantiate the Process model, and set our custom
     * batch process ID.
     */
    public function __construct()
    {
        $this->indexProcess = new Mage_Index_Model_Process();

        $this->indexProcess->setId(self::PROCESS_ID);
    }

    public function start()
    {
        if ($this->indexProcess->isLocked())
        {
            $this->log("Another %s process is running! Aborted", self::PROCESS_ID);
            return false;
        }

        // Set an exclusive lock.
        $this->indexProcess->lockAndBlock();

        $limit = Mage::getStoreConfig('core/factfindersync/queue');

        $this->log("========================================");
        $timeStart = microtime(true);
        $this->log("Started FactFinderSync");
        $this->insertNewProducts();
        $this->updateImportedProducts();
        $timeEnd = microtime(true);
        $time = $timeEnd - $timeStart;
        $this->log("Finished FactFinderSync limit %s in %s seconds", $limit, $time);

        // Remove the lock.
        $this->indexProcess->unlock();

        return true;
    }

    public function log($message, $p1 = null, $p2 = null) {
        if(Mage::getStoreConfig('core/factfindersync/log')) {
            $line = sprintf($message, $p1, $p2);
            Mage::log($line, null, 'kk_factfindersync.log');
            if (defined('DEBUG_CONSOLE_LOG')) {
                echo $line."\n";
            }
        }
    }

    protected function insertNewProducts()
    {
        $this->log("Inserting products...");
        $limit = Mage::getStoreConfig('core/factfindersync/queue');

        $collection = Mage::getModel('catalog/product')->getCollection()
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('factfinder_updated', array('null' => true), 'left')
            ->setPageSize($limit);

        $factfinder = Mage::getModel('factfindersync/factfinder');
        $count = $factfinder->setCollection($collection, true);
        $this->log("Found %s new products: %s", $count,  $factfinder->getIds());
        try {
            $factfinder->insertProducts(true);
            $this->log("Finished import for %s products", $count);
        } catch (Exception $e) {
            $this->log("Error importing: %s", $e->getMessage());
        }
    }

    protected function updateImportedProducts()
    {
        $this->log("Updating products...");
        $limit = Mage::getStoreConfig('core/factfindersync/queue');

        $collection = Mage::getModel('catalog/product')->getCollection()
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('factfinder_updated', array('lt' => new Zend_Db_Expr('updated_at')))
            ->setPageSize($limit);

        $factfinder = Mage::getModel('factfindersync/factfinder');
        $count = $factfinder->setCollection($collection);
        $this->log("Found %s updated products: %s", $count,  $factfinder->getIds());

        try {
            $factfinder->insertProducts(false);
            $this->log("Finished update for %s products", $count);
        } catch (Exception $e) {
            $this->log("Error importing: %s", $e->getMessage());
        }
    }
}