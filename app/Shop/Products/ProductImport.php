<?php

namespace App\Shop\Products;

use App\Shop\Categories\Repositories\CategoryRepository;
use App\Shop\Brands\Repositories\BrandRepository;
use App\Shop\Products\Repositories\ProductRepository;
use App\Shop\Channels\Repositories\ChannelRepository;
use App\Shop\Import\BaseImport;

class ProductImport extends BaseImport {

    /**
     *
     * @var type 
     */
    protected $expectedHeaders = array(
        'name',
        'channels',
        'categories',
        'brand',
        'sku',
        'description',
        'quantity',
        'price',
        'sale_price',
        'weight',
        'mass_unit',
        'length',
        'width',
        'height',
        'distance_unit'
    );

    /**
     *
     * @var type 
     */
    protected $requiredFields = array(
        'name',
        'channels',
        'categories',
        'brand',
        'sku',
        'description',
        'quantity',
        'price',
        'sale_price',
        'weight',
        'mass_unit',
        'length',
        'width',
        'height',
        'distance_unit'
    );

    /**
     *
     * @var type 
     */
    private $arrProducts = [];

    /**
     *
     * @var type 
     */
    private $arrBrands = [];

    /**
     *
     * @var type 
     */
    private $arrCategories = [];

    /**
     *
     * @var type 
     */
    private $arrChannels = [];

    /**
     *
     * @var type 
     */
    private $arrExistingProducts = [];

    /**
     *
     * @var type 
     */
    private $productRepo;
    private $lineCount = 1;

    /**
     * 
     * @param CategoryRepository $categoryRepo
     * @param BrandRepository $brandRepo
     * @param ChannelRepository $channelRepo
     * @param ProductRepository $productRepo
     */
    public function __construct(
    CategoryRepository $categoryRepo, BrandRepository $brandRepo, ChannelRepository $channelRepo, ProductRepository $productRepo
    ) {
        parent::__construct();
        $this->productRepo = $productRepo;
        $this->arrCategories = array_change_key_case($categoryRepo->listCategories()->keyBy('name')->toArray(), CASE_LOWER);
        $this->arrBrands = array_change_key_case($brandRepo->listBrands()->keyBy('name')->toArray(), CASE_LOWER);
        $this->arrChannels = array_change_key_case($channelRepo->listChannels()->keyBy('name')->toArray(), CASE_LOWER);
        $this->arrExistingProducts = array_change_key_case($productRepo->listProducts()->keyBy('name')->toArray(), CASE_LOWER);
    }

    /**
     * 
     * @param type $file
     * @return boolean
     */
    private function importCsv($file) {

        $handle = fopen($file, 'r');

        if (!$handle)
        {
            return false;
        }

        //Parse the first row, instantiate all the validators
        $row = $this->parseFirstRow($this->fgetcsv($handle));

        if (!empty($this->arrErrors))
        {

            return false;
        }

        while (($data = $this->fgetcsv($handle)) !== false)
        {

            $order = array_map('trim', $this->mapData($data));

            foreach ($order as $key => $params)
            {

                $this->checkRule(['key' => $key, 'value' => $params]);
            }

            $arrSelectedCategories = $this->validateCategories($order['categories']);
            $arrSelectedChannels = $this->validateChannels($order['channels']);
            $brand = $this->validateBrand($order['brand']);
            $this->checkIfProductExists($order['name']);

            $this->lineCount++;

            if (!empty($this->arrErrors))
            {
                continue;
            }

            $this->buildProduct($order, $arrSelectedCategories, $arrSelectedChannels, $brand);
        }

        if (!empty($this->arrErrors))
        {
            return false;
        }

        if (!$this->saveImport())
        {
            $this->arrErrors[] = 'Failed to save import';
            return false;
        }

        fclose($handle);
    }

    /**
     * 
     * @param type $productName
     * @return boolean
     */
    private function checkIfProductExists($productName) {

        $productName = trim(strtolower($productName));

        if (isset($this->arrExistingProducts[$productName]))
        {
            $this->arrErrors[$this->lineCount]['product'] = 'The product you are trying to create already exists';
            return true;
        }

        return false;
    }

    private function mapData($data) {
        list(
                $order['name'],
                $order['channels'],
                $order['categories'],
                $order['brand'],
                $order['sku'],
                $order['description'],
                $order['quantity'],
                $order['price'],
                $order['sale_price'],
                $order['weight'],
                $order['mass_unit'],
                $order['length'],
                $order['width'],
                $order['height'],
                $order['distance_unit'],
                ) = $data;

        return $order;
    }

    /**
     * 
     * @return boolean
     */
    private function saveImport() {
        foreach ($this->arrProducts as $arrProduct)
        {

            $arrCategories = $arrProduct['categories'];
            $arrChannels = $arrProduct['channels'];

            unset($arrProduct['categories']);
            unset($arrProduct['channels']);

            $arrProduct['slug'] = str_slug($arrProduct['name']);

            $product = $this->productRepo->createProduct($arrProduct);
            $productRepo = new ProductRepository($product);

            // categories
            if (!empty($arrCategories))
            {
                $productRepo->syncCategories($arrCategories);
            }

            // channels
            if (!empty($arrChannels))
            {

                $productRepo->syncChannels($arrChannels);
            }
        }

        return true;
    }

    /**
     * Checks a CSV file for validity based on defined policies.
     *
     * Stops on the first violation
     *
     * @access public
     * @param string $file Full path
     * @return boolean
     */
    public function isValid($file) {

        if (!file_exists($file))
        {
            $this->arrErrors[$this->lineCount]['file'] = 'File ' . $file . ' does not exist.';
            return false;
        }

        $this->importCsv($file);

        return empty($this->arrErrors);
    }

    private function buildProduct($order, $arrSelectedCategories, $arrSelectedChannels, $brand) {
        $this->arrProducts[] = [
            'name'          => $order['name'],
            'sku'           => $order['sku'],
            'description'   => $order['description'],
            'quantity'      => $order['quantity'],
            'price'         => $order['price'],
            'status'        => 1,
            'weight'        => $order['weight'],
            'mass_unit'     => $order['mass_unit'],
            'sale_price'    => $order['sale_price'],
            'length'        => $order['length'],
            'width'         => $order['width'],
            'height'        => $order['height'],
            'distance_unit' => $order['distance_unit'],
            'categories'    => $arrSelectedCategories,
            'channels'      => $arrSelectedChannels,
            'brand_id'      => $brand
        ];
    }

    /**
     * 
     * @param type $categories
     * @return type
     */
    private function validateCategories($categories) {

        $categories = $this->normalizeRow(explode(',', $categories));

        $arrSelectedCategories = [];

        foreach ($categories as $category)
        {

            if (!isset($this->arrCategories[$category]))
            {
                $this->arrErrors[$this->lineCount]['category'] = "Category is invalid.";
                continue;
            }

            $arrSelectedCategories[] = $this->arrCategories[$category]['id'];
        }

        return $arrSelectedCategories;
    }

    /**
     * 
     * @param type $brand
     * @return boolean
     */
    private function validateBrand($brand) {

        $brandName = strtolower($brand);

        if (!isset($this->arrBrands[$brandName]))
        {
            $this->arrErrors[$this->lineCount]['brand'] = "Brand is invalid.";
            return false;
        }

        $brand = $this->arrBrands[$brandName]['id'];


        return $brand;
    }

    private function validateChannels($channels) {
        $channels = $this->normalizeRow(explode(',', $channels));

        $arrSelectedChannels = [];

        foreach ($channels as $channel)
        {

            if (!isset($this->arrChannels[$channel]))
            {
                $this->arrErrors[$this->lineCount]['channel'] = "Channel is invalid.";
                continue;
            }

            $arrSelectedChannels[] = $this->arrChannels[$channel]['id'];
        }

        return $arrSelectedChannels;
    }

}
