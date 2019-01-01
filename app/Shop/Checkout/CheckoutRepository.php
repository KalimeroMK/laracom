<?php

namespace App\Shop\Checkout;

use App\Shop\Carts\Repositories\CartRepository;
use App\Shop\Carts\ShoppingCart;
use App\Shop\Orders\Order;
use App\Shop\Orders\Repositories\OrderRepository;

class CheckoutRepository {

    /**
     * @param array $data
     *
     * @return Order
     */
    public function buildCheckoutItems(array $data): Order {
        $orderRepo = new OrderRepository(new Order);
        $cartRepo = new CartRepository(new ShoppingCart);
                
        $order = $orderRepo->createOrder([
            'reference' => $data['reference'],
            'courier_id' => $data['courier_id'],
            'customer_id' => $data['customer_id'],
            'voucher_code' => !empty($data['voucher_id']) ? $data['voucher_id'] : null,
            'address_id' => $data['address_id'],
            'order_status_id' => $data['order_status_id'],
            'payment' => $data['payment'],
            'discounts' => $data['discounts'],
            'total_products' => $data['total_products'],
            'total' => $data['total'],
            'total_paid' => $data['total_paid'],
            'channel' => isset($data['channel']) ? $data['channel'] : [],
            'tax' => $data['tax']
        ]);
        $orderRepo = new OrderRepository($order);
        $orderRepo->buildOrderDetails($cartRepo->getCartItems());
        return $order;
    }

}
