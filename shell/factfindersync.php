<?php
/**
 * FactFinderSync shell script for manual run
 *
 * @category    Kirchbergerknorr
 * @package     Kirchbergerknorr
 * @author      Aleksey Razbakov <ar@kirchbergerknorr.de>
 * @copyright   Copyright (c) 2014 kirchbergerknorr GmbH (http://www.kirchbergerknorr.de)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

require_once "../app/Mage.php";
require_once 'abstract.php';

Mage::app('admin');
Mage::setIsDeveloperMode(true);

class Kirchbergerknorr_Shell_FactFinderSync extends Mage_Shell_Abstract
{
    public function run()
    {
        Mage::getModel('factfindersync/sync')->start();
    }
}

$shell = new Kirchbergerknorr_Shell_FactFinderSync();
$shell->run();