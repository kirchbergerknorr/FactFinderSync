<?php
/**
 * FactFinder Model
 *
 * @category    Kirchbergerknorr
 * @package     Kirchbergerknorr
 * @author      Aleksey Razbakov <ar@kirchbergerknorr.de>
 * @copyright   Copyright (c) 2014 kirchbergerknorr GmbH (http://www.kirchbergerknorr.de)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Kirchbergerknorr_FactFinderSync_Model_Factfinder
{
    protected $_products;
    protected $_ids;
    protected $_collection;
    protected $_updateTime;

    public function log($message, $p1 = null, $p2 = null)
    {
        Mage::getModel('factfindersync/sync')->log($message, $p1, $p2);
    }

    public function updateProductsDates()
    {
        foreach ($this->_collection as $product) {
            $product->setData('factfinder_updated', $this->_updateTime);
            $product->save();
        }
    }

    public function setCollection($collection)
    {
        $this->_updateTime = date('Y-m-d H:i:s', strtotime('+20 minutes'));
        $this->_collection = $collection;

        $attributesString = Mage::getStoreConfig('core/factfindersync/attributes');
        $attributes = explode(',', $attributesString);

        foreach ($attributes as $attribute) {
            $collection->addAttributeToSelect($attribute);
        }

        $this->_products = array();
        $this->_ids = array();

        $storeId = 1;

        $exportImageAndDeeplink = Mage::getStoreConfigFlag('factfinder/export/urls', $storeId);
        if ($exportImageAndDeeplink) {
            $imageType = Mage::getStoreConfig('factfinder/export/suggest_image_type', $storeId);
            $imageSize = (int) Mage::getStoreConfig('factfinder/export/suggest_image_size', $storeId);
        }

        foreach ($collection as $product) {
            $productAttributes = array();
            foreach ($attributes as $attribute) {
                $attribute = trim ($attribute);
                if ($attribute) {
                    $productAttributes[] = $product->getData($attribute);
                }
            }

            $product->setStoreId($storeId);
            $this->_ids[] = $product->getId();
            $productData = array(
                'id' => $product->getId(),
                'record' => array(
                    array(
                        'key' => 'id',
                        'value' => $product->getId(),
                    ),
                    array(
                        'key' => 'sku',
                        'value' => $product->getSku(),
                    ),
                    array(
                        'key' => 'price',
                        'value' => (float) $product->getPrice(),
                    ),
                    array(
                        'key' => 'name',
                        'value' => $product->getName(),
                    ),
                    array(
                        'key' => 'description',
                        'value' => $product->getDescription(),
                    ),
                    array(
                        'key' => 'short_description',
                        'value' => $product->getShortDescription(),
                    ),
                    array(
                        'key' => 'deeplink',
                        'value' => $product->getProductUrl(),
                    ),
                    array(
                        'key' => 'meta_description',
                        'value' => $product->getMetaDescription(),
                    ),
                    array(
                        'key' => 'category',
                        'value' => $this->_getCategoryName($product),
                    ),
                    array(
                        'key' => 'image',
                        'value' => (string) Mage::helper('catalog/image')->init($product, $imageType)->resize($imageSize)
                    ),
                    array(
                        'key' => 'filterable_attributes',
                        'value' => ""
                    )
                ),
                'refKey' => time(),
                'simiMalusAdd' => 1,
                'simiMalusMul' => 1,
                'visible' => true,
            );

            if (is_array($productAttributes) && count($productAttributes)>0) {
                $productData['record'][] = array(
                    'key' => 'searchable_attributes',
                    'value' => join(" ", $productAttributes)
                );
            }

            $this->_products[] = $productData;
        }

        return sizeof($this->_ids);
    }

    public function insertProducts()
    {
        $url = Mage::getStoreConfig('factfinder/search/address');
        $app = Mage::getStoreConfig('factfinder/search/context');
        $login = Mage::getStoreConfig('core/factfindersync/auth_user');
        $pass = md5(Mage::getStoreConfig('core/factfindersync/auth_password'));
        $prefix = Mage::getStoreConfig('factfinder/search/auth_advancedPrefix');
        $postfix = Mage::getStoreConfig('factfinder/search/auth_advancedPostfix');
        $timestamp = round(microtime(true) * 1000);
        $channel = Mage::getStoreConfig('factfinder/search/channel');

        $hash = md5($prefix.$timestamp.$pass.$postfix);

        $wsdlUrl = sprintf("http://%s/%s/webservice/ws69/Import?wsdl", $url, $app);

        if (!$this->_products) {
            return false;
        }

        $insertRecordRequest = array(
            'in0' => $this->_products,
            'in1' => $channel,
            'in2' => true,
            'in3' => array(
                'password' => $hash,
                'timestamp' => $timestamp,
                'username' => $login,
            ),
        );

        $client = new SoapClient($wsdlUrl);
        try {
            $client->insertRecords($insertRecordRequest);
        } catch (Exception $e) {
            $this->log("Exception while importing: %s", $e->getMessage());
            $isExists = strpos($e->getMessage(), 'de.factfinder.indexer.importer.RecordAlreadyExistException');
            $this->log("isExists: %s", $isExists);

            if ($isExists > -1) {
                preg_match("/Record with id '([^']+)'/is", $e->getMessage(), $matches);
                if (isset($matches[1])) {
                    $skippedProductId = $matches[1];
                    $product = Mage::getModel('catalog/product')->load($skippedProductId);
                    $product->setData('factfinder_updated', date('Y-m-d H:i:s', strtotime('-1 days')));
                    $product->save();
                    $this->log("Skipping id %s", $skippedProductId);
                }
            }
        }

        $this->updateProductsDates();
    }

    public function updateProducts()
    {
        $url = Mage::getStoreConfig('factfinder/search/address');
        $app = Mage::getStoreConfig('factfinder/search/context');
        $login = Mage::getStoreConfig('core/factfindersync/auth_user');
        $pass = md5(Mage::getStoreConfig('core/factfindersync/auth_password'));
        $channel = Mage::getStoreConfig('factfinder/search/channel');
        $prefix = Mage::getStoreConfig('factfinder/search/auth_advancedPrefix');
        $postfix = Mage::getStoreConfig('factfinder/search/auth_advancedPostfix');
        $timestamp = round(microtime(true) * 1000);

        $hash = md5($prefix.$timestamp.$pass.$postfix);

        $wsdlUrl = sprintf("http://%s/%s/webservice/ws69/Import?wsdl", $url, $app);

        if (!$this->_products) {
            return false;
        }

        foreach ($this->_products as $product) {
            $this->log("Updating #%s", $product['id']);
            $updateRecordRequest = array(
                'in0' => $product,
                'in1' => $channel,
                'in2' => true,
                'in3' => array(
                    'password' => $hash,
                    'timestamp' => $timestamp,
                    'username' => $login,
                ),
            );

            $client = new SoapClient($wsdlUrl);
            try {
                $client->updateRecord($updateRecordRequest);

                $product = Mage::getModel('catalog/product')->load($product['id']);
                $product->setData('factfinder_updated', $this->_updateTime);
                $product->save();
            } catch (Exception $e) {
                $this->log("Exception: %s", $e->getMessage());
                $isNotExists = strpos($e->getMessage(), 'de.factfinder.indexer.importer.RecordNotFoundException');
                $this->log("isNotExists: %s", $isNotExists);

                if ($isNotExists > -1) {
                    preg_match("/Record with id '([^']+)'/is", $e->getMessage(), $matches);
                    if (isset($matches[1])) {
                        $skippedProductId = $matches[1];
                        $product = Mage::getModel('catalog/product')->load($skippedProductId);
                        $product->setData('factfinder_updated', '0');
                        $product->save();
                        $this->log("Scheduled to insert id %s", $skippedProductId);
                    }
                }
            }
        }
    }

    protected function _getCategoryName($product)
    {
        $categoryIds = $product->getCategoryIds();

        $categoryNameArray = array();
        if(count($categoryIds) ){
            $firstCategoryId = $categoryIds[0];
            $_category = Mage::getModel('catalog/category')->load($firstCategoryId);

            $categoryNameArray[] = $_category->getName();
        }

        return implode(', ', $categoryNameArray);
    }

    public function getIds()
    {
        if (!$this->_ids) {
            return "[none]";
        }

        return join(', ', $this->_ids);
    }
}
