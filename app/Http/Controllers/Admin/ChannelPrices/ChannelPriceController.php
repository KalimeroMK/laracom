<?php

namespace App\Http\Controllers\Admin\ChannelPrices;

use App\Shop\Products\Repositories\Interfaces\ProductRepositoryInterface;
use App\Shop\ChannelPrices\Repositories\ChannelPriceRepositoryInterface;
use App\Shop\ChannelPrices\ChannelPrice;
use App\Shop\ChannelPrices\Repositories\ChannelPriceRepository;
use App\Shop\ChannelPrices\Requests\UpdateChannelPriceRequest;
use App\Shop\Channels\Repositories\Interfaces\ChannelRepositoryInterface;
use App\Shop\Categories\Repositories\Interfaces\CategoryRepositoryInterface;
use App\Shop\Brands\Repositories\BrandRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Shop\ChannelPrices\ChannelPriceImport;
use App\Shop\Channels\Repositories\WarehouseRepository;
use App\Shop\Channels\Warehouse;
use App\Shop\ChannelPrices\Transformations\ChannelPriceTransformable;
use App\Shop\ChannelPrices\Transformations\ProductCsvTransformable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Search\ChannelPriceSearch;

class ChannelPriceController extends Controller {

    use ChannelPriceTransformable;
    use ProductCsvTransformable;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepo;

    /**
     * @var ChannelRepositoryInterface
     */
    private $channelRepo;

    /**
     * @var ChannelPriceRepositoryInterface
     */
    private $channelPriceRepo;

    /**
     * @var CategoryRepositoryInterface
     */
    private $categoryRepo;

    /**
     * @var BrandRepositoryInterface
     */
    private $brandRepo;

    /**
     * 
     * @param ProductRepositoryInterface $productRepository
     * @param ChannelRepositoryInterface $channelRepository
     * @param ChannelPriceRepositoryInterface $channelPriceRepository
     * @param CategoryRepositoryInterface $categoryRepository
     */
    public function __construct(
    ProductRepositoryInterface $productRepository, ChannelRepositoryInterface $channelRepository, ChannelPriceRepositoryInterface $channelPriceRepository, CategoryRepositoryInterface $categoryRepository, BrandRepositoryInterface $brandRepository
    ) {
        $this->productRepo = $productRepository;
        $this->channelRepo = $channelRepository;
        $this->channelPriceRepo = $channelPriceRepository;
        $this->categoryRepo = $categoryRepository;
        $this->brandRepo = $brandRepository;

        $this->middleware(['permission:create-channel-price, guard:admin'], ['only' => ['create', 'store']]);
        $this->middleware(['permission:update-channel-price, guard:admin'], ['only' => ['edit', 'update']]);
        $this->middleware(['permission:delete-channel-price, guard:admin'], ['only' => ['destroy']]);
        $this->middleware(['permission:view-channel-price, guard:admin'], ['only' => ['index', 'show']]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($channel) {

        $channel = $this->channelRepo->findByName($channel);
        $channels = $this->channelRepo->listChannels('name', 'asc');
        $categories = $this->categoryRepo->listCategories('name', 'asc')->where('parent_id', 1);
        $brands = $this->brandRepo->listBrands();
        $list = $this->channelPriceRepo->getChannelProducts($channel);

        $products = $list->map(function (ChannelPrice $item) {
                    return $this->transformProduct($item);
                })->all();

        return view('admin.channel-price.list', [
            'products'   => $this->channelPriceRepo->paginateArrayResults($products, 10),
            'channel'    => $channel,
            'categories' => $categories,
            'channels'   => $channels,
            'brands'     => $brands
        ]);
    }

    /**
     * 
     * @param Request $request
     */
    public function export(Request $request) {

        $list = ChannelPriceSearch::apply($request);

        $arrProducts = $list->map(function (ChannelPrice $item) {
                    return $this->transformProductForCsv($item);
                })->all();

        return response()->json($arrProducts);
    }

    /**
     * 
     * @param Request $request
     * @return type
     */
    public function search(Request $request) {

        $list = ChannelPriceSearch::apply($request);

        $products = $list->map(function (ChannelPrice $item) {

                    return $this->transformProduct($item);
                })->all();

        return view('admin.channel-price.search', [
            'products' => $this->channelPriceRepo->paginateArrayResults($products, 10)
                ]
        );
    }

    /**
     * Display the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function show(int $id) {
        $product = $this->channelPriceRepo->findProductById($id);

        return view('admin.products.show', [
            'product' => $product
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function edit(int $id) {


        $channelPrice = $this->channelPriceRepo->findChannelPriceById($id);
        $channel = $this->channelRepo->findChannelById($channelPrice->channel_id);
        $arrWarehouses = (new WarehouseRepository(new Warehouse))->listWarehouses('name', 'asc');

        $product = $this->productRepo->findProductById($channelPrice->product_id);
        $attributes = (new \App\Shop\ProductAttributes\Repositories\ProductAttributeRepository(new \App\Shop\ProductAttributes\ProductAttribute))->getAttributesForProduct($product);

        $channelVaraitions = $this->channelPriceRepo->getChannelVariations($channel)->keyBy('attribute_id');
        $assignedAttributes = $channelVaraitions->pluck('attribute_id')->toArray();

        return view('admin.channel-price.edit', [
            'warehouses'         => $arrWarehouses,
            'assignedAttributes' => $assignedAttributes,
            'channel_varaitions' => $channelVaraitions,
            'attributes'         => $attributes,
            'channelPrice'       => $channelPrice,
            'product'            => $product
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  UpdateProductRequest $request
     * @param  int $id
     *
     * @return \Illuminate\Http\Response
     * @throws \App\Shop\Products\Exceptions\ProductUpdateErrorException
     */
    public function update(Request $request, int $id) {

        $data = $request->except('_token', '_method', 'cost_price');

        $validator = Validator::make($data, (new UpdateChannelPriceRequest())->rules());

        if ($request->price < $request->cost_price)
        {
            $arrErrors['cost_price'] = ['The price cannot be less than the cost price.'];
            return response()->json(['http_code' => 400, 'errors' => $arrErrors]);
        }

        // Validate the input and return correct response
        if ($validator->fails())
        {
            return response()->json(['http_code' => 400, 'errors' => $validator->getMessageBag()->toArray()]);
        }

        $channel = $this->channelRepo->findChannelById($request->channel_id);

        if ($request->added == 1)
        {

            try {
                $channelPriceRepo = new ChannelPriceRepository(new \App\Shop\ChannelPrices\ChannelPrice);
                $channelPriceRepo->create([
                    'attribute_id' => !empty($request->attribute_id) ? $request->attribute_id : null,
                    'channel_id'   => $request->channel_id,
                    'product_id'   => $request->product_id,
                    'price'        => $request->price
                ]);
            } catch (Exception $ex) {
                return response()->json(['http_code' => 400, 'errors' => [$ex->getMessage()]]);
            }

            return response()->json(['http_code' => 200]);
        }

        try {

            if (isset($data['attribute_id']) && !empty($data['attribute_id']))
            {
                $channelPrice = $this->channelPriceRepo->findChannelPriceByAttributeId($request->attribute_id, $channel);
            }
            else
            {
                $channelPrice = $this->channelPriceRepo->findChannelPriceById($id);
            }

            $channelPriceRepo = new ChannelPriceRepository($channelPrice);
            $channelPriceRepo->updateChannelPrice($data);
        } catch (Exception $ex) {
            return response()->json(['http_code' => 400, 'errors' => [$ex->getMessage()]]);
        }

        return response()->json(['http_code' => 200]);
    }

    /**
     * 
     * @param type $id
     * @param Request $request
     * @return type
     */
    public function deleteAttribute($id, Request $request) {

        try {
            $channel = $this->channelRepo->findChannelById($request->channel_id);
            $this->channelPriceRepo->deleteAttribute($id, $channel);
        } catch (Exception $ex) {
            
        }

        return response()->json(['http_code' => 200]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id) {
        $this->channelPriceRepo->delete($id);
        return response()->json(['http_code' => 200]);
    }

    /**
     * 
     * @return type
     */
    public function import() {
        return view('admin.channel-price.importCsv');
    }

    /**
     * 
     * @param Request $request
     * @return type
     */
    public function saveImport(Request $request) {
        $file_path = $request->csv_file->path();

        $objChannelProductImport = new ChannelPriceImport(new WarehouseRepository(new Warehouse), $this->channelRepo, $this->productRepo, $this->channelPriceRepo
        );

        if (!$objChannelProductImport->isValid($file_path))
        {

            $arrErrors = $objChannelProductImport->getErrors();
            return response()->json(['http_code' => '400', 'arrErrors' => $arrErrors]);
        }

        return response()->json(['http_code' => '200']);
    }

    /**
     * 
     * @param Request $request
     * @return type
     */
    public function getProductsForSwap(Request $request) {


        $list = ChannelPriceSearch::apply($request);

        $products = $list->map(function (ChannelPrice $item) {
                    return $this->transformProductForCsv($item);
                })->all();

        return response()->json(['results' => $products]);
    }

    /**
     * 
     * @param Request $request
     * @return type
     */
    public function getAvailiableProducts(Request $request) {

        $channel = $this->channelRepo->findChannelById($request->channel_id);

        $availiableProducts = (new ChannelPriceRepository(new \App\Shop\ChannelPrices\ChannelPrice))
                ->getAvailiableProducts($channel, $request->product_name)
                ->toArray();

        return response()->json(['results' => $availiableProducts]);
    }

}
