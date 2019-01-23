<?php

namespace App\Http\Controllers\Front;

use App\Shop\Couriers\Repositories\Interfaces\CourierRepositoryInterface;
use App\Shop\Returns\Repositories\Interfaces\ReturnRepositoryInterface;
use App\Shop\Customers\Repositories\CustomerRepository;
use App\Shop\Customers\Repositories\Interfaces\CustomerRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Shop\Orders\Order;
use App\Shop\Orders\Transformers\OrderTransformable;

class AccountsController extends Controller {

    use OrderTransformable;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepo;

    /**
     * @var ReturnRepositoryInterface
     */
    private $returnRepo;

    /**
     * @var CourierRepositoryInterface
     */
    private $courierRepo;

    /**
     * AccountsController constructor.
     *
     * @param CourierRepositoryInterface $courierRepository
     * @param CustomerRepositoryInterface $customerRepository
     * * @param ReturnRepositoryInterface $returnRepository
     */
    public function __construct(
    CourierRepositoryInterface $courierRepository, CustomerRepositoryInterface $customerRepository, ReturnRepositoryInterface $returnRepository
    ) {
        $this->customerRepo = $customerRepository;
        $this->courierRepo = $courierRepository;
        $this->returnRepo = $returnRepository;
    }

    public function index() {
        $customer = $this->customerRepo->findCustomerById(auth()->user()->id);
        $customerRepo = new CustomerRepository($customer);
        $orders = $customerRepo->findOrders(['*'], 'created_at');
        $orders->transform(function (Order $order) {
            return $this->transformOrder($order);
        });

       
        $addresses = $customerRepo->findAddresses();

        $returns = $this->returnRepo->listReturn('created_at', 'desc')->where('customer', auth()->user()->id);

        return view('front.accounts', [
            'customer' => $customer,
            'orders' => $this->customerRepo->paginateArrayResults($orders->toArray(), 15),
            'addresses' => $addresses,
            'returns' => $returns
        ]);
    }

}
