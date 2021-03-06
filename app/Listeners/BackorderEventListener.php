<?php
namespace App\Listeners;
use App\Events\BackorderEvent;
use App\Shop\Orders\Repositories\OrderRepository;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
class BackorderEventListener {
    /**
     * Create the event listener.
     *
     */
    public function __construct() {
        //
    }
    /**
     * Handle the event.
     *
     * @param  DispatchCreateEvent  $event
     * @return void
     */
    public function handle(BackorderEvent $event) {
                       
        // send email to customer
        $orderRepo = new OrderRepository($event->order);
        $orderRepo->sendBackorderEmail();
                
        $orderRepo = new OrderRepository($event->order);
        $orderRepo->sendEmailNotificationToAdmin();        
    }
}
