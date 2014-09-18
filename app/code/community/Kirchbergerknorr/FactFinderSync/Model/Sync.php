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
    public function start()
    {
        $limit = Mage::getStoreConfig('core/factfindersync/queue');

        $this->log("========================================");
        $timeStart = microtime(true);
        $this->log("Started FactFinderSync");
//        $this->resetNewProducts();
        $this->insertNewProducts();
        $this->updateImportedProducts();
        $timeEnd = microtime(true);
        $time = $timeEnd - $timeStart;
        $this->log("Finished FactFinderSync limit %s in %s seconds", $limit, $time);
    }

    public function log($message, $p1 = null, $p2 = null) {
        if(Mage::getStoreConfig('core/factfindersync/log')) {
            Mage::log(sprintf($message, $p1, $p2), null, 'kk_factfindersync.log');
        }
    }

    protected function resetNewProducts()
    {
        $this->log("Reseting products...");
        $collection = Mage::getModel('catalog/product')->getCollection()
            ->addAttributeToFilter('factfinder_updated', array('gt' => '0'), 'left');

        foreach ($collection as $product) {
            $product->setData('factfinder_updated', '0');
            $product->save();
            $this->log("Resetted #%s", $product->getId());
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
        $count = $factfinder->setCollection($collection);
        $this->log("Found %s new products: %s", $count,  $factfinder->getIds());
        try {
            $factfinder->insertProducts();
            $this->log("Finished import for %s products: %s", $count, $factfinder->getIds());
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
            $factfinder->updateProducts();
            $this->log("Finished update for %s products: %s", $count,  $factfinder->getIds());
        } catch (Exception $e) {
            $this->log("Error importing: %s", $e->getMessage());
        }
    }
}