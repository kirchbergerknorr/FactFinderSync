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

        Mage::getModel('factfindersync/sync')->start();
    }
}