<?php

namespace App\Http\Controllers\Admin\Orders;

use App\Shop\Orders\Repositories\Interfaces\OrderRepositoryInterface;
use App\Shop\OrderStatuses\Repositories\Interfaces\OrderStatusRepositoryInterface;
use App\Shop\Products\Repositories\Interfaces\ProductRepositoryInterface;
use App\Shop\Products\Repositories\ProductRepository;
use App\Shop\Products\Product;
use App\Shop\Channels\Repositories\Interfaces\ChannelRepositoryInterface;
use App\Shop\OrderProducts\Repositories\Interfaces\OrderProductRepositoryInterface;
use App\Shop\OrderProducts\Repositories\OrderProductRepository;
use App\Shop\OrderProducts\OrderProduct;
use App\Shop\OrderStatuses\Repositories\OrderStatusRepository;
use App\Shop\OrderProducts\Requests\UpdateOrderProductRequest;
use App\Shop\Comments\OrderCommentRepository;
use App\Search\OrderProductSearch;
use App\Shop\Customers\Repositories\CustomerRepository;
use App\Shop\Customers\Customer;
use App\Shop\Orders\Order;
use App\Shop\OrderStatuses\OrderStatus;
use App\Shop\Couriers\Courier;
use App\Shop\Couriers\Repositories\CourierRepository;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use App\Shop\Orders\Requests\UpdateLineRequest;
use Illuminate\Support\Facades\Validator;
use App\Traits\OrderCommentTrait;

class OrderLineController extends Controller {

    use OrderCommentTrait;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepo;

    /**
     * @var OrderProductRepositoryInterface
     */
    private $orderLineRepo;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepo;

    /**
     * @var ChannelRepositoryInterface
     */
    private $channelRepo;

    /**
     * @var OrderStatusRepositoryInterface
     */
    private $orderStatusRepo;

    public function __construct(
    OrderRepositoryInterface $orderRepository, OrderStatusRepositoryInterface $orderStatusRepository, OrderProductRepositoryInterface $orderProductRepository, ProductRepositoryInterface $productRepository, ChannelRepositoryInterface $channelRepository
    ) {
        $this->orderRepo = $orderRepository;
        $this->orderStatusRepo = $orderStatusRepository;
        $this->orderLineRepo = $orderProductRepository;
        $this->productRepo = $productRepository;
        $this->channelRepo = $channelRepository;

        //$this->middleware(['permission:update-order, guard:employee'], ['only' => ['edit', 'update']]);
    }

    /**
     * 
     * @param Request $request
     */
    public function updateLineStatus(Request $request) {

        $arrErrors = [];

        foreach ($request->form as $arrData)
        {

            $validator = Validator::make($arrData, (new UpdateLineRequest())->rules());

            // Validate the input and return correct response
            if ($validator->fails())
            {
                return response()->json(['http_code' => 400, 'errors' => $validator->getMessageBag()->toArray()]);
            }

            $lineId = $arrData['line_id'];
            unset($arrData['line_id']);

            try {

                $orderProduct = $this->orderLineRepo->findOrderProductById($lineId);

                $orderProductRepo = new OrderProductRepository($orderProduct);

                $orderProductRepo->updateOrderProduct($arrData, $lineId);
            } catch (\Exception $e) {
                $arrErrors[$lineId][] = $e->getMessage();
            }
        }

        if (!empty($arrErrors))
        {
            return response()->json(['http_code' => 400, 'errors' => $arrErrors]);
        }

        return response()->json(['http_code' => 200]);
    }

    /**
     * 
     * @param UpdateOrderProductRequest $request
     */
    public function update(Request $request) {

        $arrResponse = array(
            'http_code' => 201,
        );

        $blError = false;
        $arrErrors = [];
        $arrSuccess = [];

        foreach ($request->form as $arrLine)
        {

            try {
                $orderProduct = $this->orderLineRepo->findOrderProductById($arrLine['line_id']);

                $orderId = $orderProduct->order_id;

                $order = $this->orderRepo->findOrderById($orderProduct->order_id);
                $postRepo = new OrderCommentRepository($order);
                $orderProductRepo = new OrderProductRepository($orderProduct);
                $product = $this->productRepo->findProductById($arrLine['product_id']);
                $orderProductRepo->updateProduct($product, $orderProduct);
                $productRepo = new ProductRepository($product);
                $reservedStock = $product->reserved_stock + $orderProduct->quantity;

                $productRepo->update(['reserved_stock' => $reservedStock], $product->id);

                $arrSuccess[$orderId][] = "order {$orderId} line {$arrLine['line_id']} was updated successfully";
            } catch (\Exception $e) {
                $arrErrors['errors'][$orderId][] = $e->getMessage();
                $blError = true;
            }

            $comment = $orderProduct->product_name . ' changed to ' . $product->name;
            $this->saveComment($order, $comment);
        }

        $arrResponse['details']['FAILURES'] = $arrErrors;
        $arrResponse['details']['SUCCESS'] = $arrSuccess;
        return response()->json($arrResponse);
    }

    /**
     * 
     * @param Request $request
     * @return type
     */
    public function search(Request $request) {

        $orders = $this->orderRepo->listOrders('is_priority', 'desc')->keyBy('id');
        $orders = $this->transFormOrder($orders);

        $arrProducts = $this->productRepo->listProducts()->keyBy('id');

        $list = OrderProductSearch::apply($request);

        $list = $list->transform(function (OrderProduct $order) {

                    return $order;
                })->all();

        $items = $this->orderLineRepo->paginateArrayResults($list, 10);

        return view('admin.order-lines.search', [
            'items'    => $items,
            'orders'   => $orders,
            'products' => $arrProducts
                ]
        );
    }

    /**
     * @param Collection $list
     * @return array
     */
    private function transFormOrder(Collection $list) {
        $courierRepo = new CourierRepository(new Courier());
        $customerRepo = new CustomerRepository(new Customer());
        $orderStatusRepo = new OrderStatusRepository(new OrderStatus());
        return $list->transform(function (Order $order) use ($courierRepo, $customerRepo, $orderStatusRepo) {
                    $order->courier = $courierRepo->findCourierById($order->courier_id);
                    $order->customer = $customerRepo->findCustomerById($order->customer_id);
                    $order->status = $orderStatusRepo->findOrderStatusById($order->order_status_id);
                    $order->channel = $this->channelRepo->findChannelById($order->channel);
                    return $order;
                })->all();
    }

    /**
     * 
     * @param int $digits
     * @return type
     */
    private function generatePIN(int $digits = 4) {
        $i = 0; //counter
        $pin = ""; //our default pin is blank.
        while ($i < $digits)
        {
            //generate a random number between 0 and 9.
            $pin .= mt_rand(0, 9);
            $i++;
        }
        return $pin;
    }

    /**
     * 
     * @param Request $request
     */
    public function doAllocation(Request $request) {

        $os = $this->orderStatusRepo->findByName('Waiting Allocation');
        $picklistRef = $this->generatePIN();

        $arrDone = [];
        $arrFailed = [];
        $blError = false;

        foreach ($request->lineIds as $orderId => $arrLines)
        {

            foreach ($arrLines as $lineId)
            {
                if (in_array($orderId, $arrDone))
                {

                    continue;
                }

                $order = $this->orderRepo->findOrderById($orderId);
                $channel = $this->channelRepo->findChannelById($order->channel);
                $statusCount = $this->orderLineRepo->chekIfAllLineStatusesAreEqual($order, $os->id);
                $arrProducts = $this->orderLineRepo->listOrderProducts()->where('order_id', $order->id);

                if ($statusCount === 0)
                {

                    foreach ($arrProducts as $objLine)
                    {

                        if ($objLine->status !== $os->id)
                        {

                            continue;
                        }

                        if ($channel->allocate_on_order === 0 || $order->payment === 'import')
                        {
                            // check enough quantity to fulfil line if not reject
                            // update stock

                            if (!$this->increaseReservedStock($objLine, false))
                            {
                                $arrFailed[$lineId][] = 'failed to update stock';
                            }
                        }

                        if (!$this->addToPicklist($lineId, $picklistRef, $order))
                        {
                            $arrFailed[$lineId][] = 'Unable to add picklist';
                        }
                    }
                }
                elseif ($channel->partial_shipment === 1)
                {

                    $objLine = $this->orderLineRepo->findOrderProductById($lineId);

                    if ($channel->allocate_on_order === 1 || $order->payment === 'import')
                    {

                        //$quantity = $objProduct->quantity - $objLine2->quantity;

                        if (!$this->increaseReservedStock($objLine))
                        {
                            $arrFailed[$lineId][] = 'failed to update stock';
                        }
                    }

                    if (!$this->addToPicklist($lineId, $picklistRef))
                    {
                        $arrFailed[$lineId][] = 'Unable to add picklist';
                    }
                }

                $arrDone[$lineId] = "Order {$orderId} Line Id {$lineId} was updated successfully";
            }
        }

        $http_code = $blError === true ? 400 : 200;
        return response()->json(['http_code' => $http_code, 'FAILURES' => $arrFailed, 'SUCCESS' => $arrDone]);
    }

    /**
     * 
     * @param type $lineId
     * @param type $picklistRef
     * @param type $order
     * @return boolean
     */
    private function addToPicklist($lineId, $picklistRef, $order = null) {
        try {

            $objNewStatus = $this->orderStatusRepo->findByName('ordered');
            // update line status
            $orderLineRepo = new OrderProductRepository(new OrderProduct);
            $orderLineRepo->update(['status' => $objNewStatus->id, 'picklist_ref' => $picklistRef], $lineId);

            if ($order !== null)
            {
                $order->order_status_id = $objNewStatus->id;
                $order->save();
            }
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * 
     * @param type $objLine
     * @param type $order
     * @return boolean
     */
    private function reserveStock($objLine, $order = null) {
        try {
            $objProduct = $this->productRepo->findProductById($objLine->product_id);

            $availiableQty = $objProduct->quantity - $objProduct->reserved_stock;

            if ($availiableQty < $objLine->quantity)
            {
                return false;
            }
            
            $objNewStatus = $this->orderStatusRepo->findByName('Waiting Allocation');
            $reserved_stock = $objProduct->reserved_stock + $objLine->quantity;
            
            $objProductRepo = new ProductRepository($objProduct);
            $objProductRepo->updateProduct(['reserved_stock' => $reserved_stock]);

            $orderLineRepo = new OrderProductRepository($objLine);
            $orderLineRepo->updateOrderProduct(['status' => $objNewStatus->id]);

            if ($order !== null)
            {
                $order->order_status_id = $objNewStatus->id;
                $order->save();
            }
        } catch (\Exception $e) {

            return false;
        }

        return true;
    }

    /**
     * 
     * @param type $objLine
     * @param type $blAllowPartial
     * @return boolean
     */
    private function increaseReservedStock($objLine, $blAllowPartial = true) {
        try {
            $objProduct = $this->productRepo->findProductById($objLine->product_id);

            $quantity = $objProduct->quantity - $objProduct->reserved_stock;

            if ($blAllowPartial === false && $objLine->quantity < $quantity)
            {
                return false;
            }

            $reserved_stock = $objProduct->reserved_stock + $objLine->quantity;
            $objProductRepo = new ProductRepository($objProduct);
            $objProductRepo->updateProduct(['reserved_stock' => $reserved_stock]);
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * 
     * @param Request $request
     */
    public function processBackorders(Request $request) {

        $productRepo = new ProductRepository(new Product);
        $os = $this->orderStatusRepo->findByName('Backorder');
        $arrDone = [];
        $arrFailed = [];
        $blError = false;

        foreach ($request->lineIds as $orderId => $arrLines)
        {

            foreach ($arrLines as $lineId)
            {
                if (in_array($orderId, $arrDone))
                {

                    continue;
                }

                $order = $this->orderRepo->findOrderById($orderId);
                $channel = $this->channelRepo->findChannelById($order->channel);

                // get all backoredered lines for order
                $arrProducts = $this->orderLineRepo->listOrderProducts()->where('order_id', $order->id);

                $total = $arrProducts->count();
                $backorderCount = 0;
                $intCantMove = 0;

                if ($total === 0)
                {
                    continue;
                }

                // work out how many lines can be moved

                foreach ($arrProducts as $objProductLine)
                {

                    $product = $productRepo->findProductById($objProductLine->product_id);

                    $availiableQty = $product->quantity - $product->reserved_stock;

                    if ($objProductLine->status === $os->id && $availiableQty <= $objProductLine->quantity)
                    {

                        $intCantMove++;
                    }

                    if ($objProductLine->status === $os->id)
                    {
                        $backorderCount++;
                    }
                }


                if ($total > $backorderCount && $channel->partial_shipment === 0)
                {

                    // cant complete because there are more than 1 line that are backordered and no partial shipping allowed
                    $arrFailed[$lineId][] = 'Unable to move';
                    $blError = true;

                    // if partial shipping allowed and more than 1 line backordered then move single line
                }
                elseif ($intCantMove === 0 && $backorderCount > 1)
                {

                    foreach ($arrProducts as $objLine2)
                    {

                        if (!$this->reserveStock($objLine2, $order))
                        {
                            $arrFailed[$lineId][] = 'failed to allocate stock';
                        }
                    }
                }
                elseif (($backorderCount === $total && $backorderCount === 1) || $channel->partial_shipment === 1)
                {
                    
                    $objLine2 = $this->orderLineRepo->findOrderProductById($lineId);

                    $updateOrder = null;

                    if ($total === 1 && $backorderCount === 1)
                    {                        
                        $updateOrder = $order;
                    }
                    
                    if (!$this->reserveStock($objLine2, $updateOrder))
                    {
                        $arrFailed[$lineId][] = 'failed to allocate stock';
                    }
                }

                $arrDone[$lineId] = "Order {$orderId} Line {$lineId} was updated successfully";
            }
        }

        $http_code = $blError === true ? 400 : 200;
        return response()->json(['http_code' => $http_code, 'FAILURES' => $arrFailed, 'SUCCESS' => $arrDone]);
    }

}
