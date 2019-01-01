<?php

namespace App\Shop\ChannelPrices\Transformations;

use App\Shop\ChannelPrices\ChannelPrice;
use App\Shop\Products\Product;
use App\Shop\Products\Repositories\ProductRepository;

use Illuminate\Support\Facades\Storage;

trait ChannelPriceTransformable {

    /**
     * Transform the product
     *
     * @param Product $product
     * @return Product
     */
    protected function transformProduct(ChannelPrice $channelPrice) {

        $chann = new ChannelPrice;
        $chann->channel_id = (int) $channelPrice->channel_id;
        $chann->product_id = $channelPrice->product_id;
        $chann->price = $channelPrice->price;
        $chann->id = $channelPrice->id;
       
        
        $productRepo = new ProductRepository(new Product);
        $product = $productRepo->findProductById($chann->product_id);
        
        $chann->name = $product->name;
        $chann->quantity = $product->quantity;
        $chann->cover = $product->cover;
        $chann->status = $product->status;


        return $chann;
    }

}