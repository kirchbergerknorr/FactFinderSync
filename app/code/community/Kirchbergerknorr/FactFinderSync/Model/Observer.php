<?php
/**
 * Observer Model
 *
 * @category    Kirchbergerknorr
 * @package     Kirchbergerknorr
 * @author      Aleksey Razbakov <ar@kirchbergerknorr.de>
 * @copyright   Copyright (c) 2014 kirchbergerknorr GmbH (http://www.kirchbergerknorr.de)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Kirchbergerknorr_FactFinderSync_Model_Observer
{
    public function startProductSync($observer)
    {
        if (!Mage::getStoreConfig('core/factfindersync/active')) {
            Mage::getModel('factfindersync/sync')->log('FactFinderSync is disabled');
            return false;
        }

        if (!Mage::getStoreConfig('core/factfindersync/enabled')) {
            Mage::getModel('factfindersync/sync')->log('FactFinder is disabled');
            return false;
        }

        if(!Mage::getStoreConfig('core/factfindersync/running'))
        {
            Mage::getModel('core/config')->saveConfig('core/factfindersync/running', 1);
            Mage::getModel('factfindersync/sync')->start();
            Mage::getModel('core/config')->saveConfig('core/factfindersync/running', 0);
        }
    }
}