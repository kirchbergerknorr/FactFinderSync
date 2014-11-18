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

class Kirchbergerknorr_FactFinderSync_Model_Adminhtml_Observer
{
    public function onBlockHtmlBefore(Varien_Event_Observer $observer) {
        $block = $observer->getBlock();
        if (!isset($block)) return;

        switch ($block->getType()) {
            case 'adminhtml/catalog_product_grid':
                /* @var $block Mage_Adminhtml_Block_Catalog_Product_Grid */
                $block->addColumn('factfinder_updated', array(
                    'header' => 'FactFinder Update',
                    'index'  => 'factfinder_updated',
                ));
                break;
        }
    }

    public function onEavLoadBefore(Varien_Event_Observer $observer) {
        $collection = $observer->getCollection();
        if (!isset($collection)) return;

        if (is_a($collection, 'Mage_Catalog_Model_Resource_Product_Collection')) {
            $collection->addAttributeToSelect('factfinder_updated');
        }
    }
}