<?php

namespace App\Http\Controllers\Admin\Messages;

use App\User;
use Carbon\Carbon;
use Cmgmyr\Messenger\Models\Message;
use Cmgmyr\Messenger\Models\Participant;
use App\Shop\Orders\Order;
use App\Shop\Orders\Repositories\OrderRepository;
use Cmgmyr\Messenger\Models\Thread;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Session;
use App\Http\Controllers\Controller;

class MessageController extends Controller {

    /**
     * Show all of the message threads to the user.
     *
     * @return mixed
     */
    public function index() {
        // All threads, ignore deleted/archived participants
        $threads = \App\Shop\Messages\Thread::getAllLatest()->get();
        // All threads that user is participating in
        // $threads = Thread::forUser(Auth::id())->latest('updated_at')->get();
        // All threads that user is participating in, with new messages
        // $threads = Thread::forUserWithNewMessages(Auth::id())->latest('updated_at')->get();
        return view('admin.messages.index', compact('threads'));
    }

    /**
     * Shows a message thread.
     *
     * @param $id
     * @return mixed
     */
    public function show($id) {
        try {
            $thread = Thread::findOrFail($id);
        } catch (ModelNotFoundException $e) {
            Session::flash('error_message', 'The thread with ID: ' . $id . ' was not found.');
            return redirect()->route('messages');
        }
        // show current user in list if not a current participant
        // $users = User::whereNotIn('id', $thread->participantsUserIds())->get();
        // don't show the current user in list
        $userId = Auth::id();
        $users = User::whereNotIn('id', $thread->participantsUserIds($userId))->get();
        $thread->markAsRead($userId);
        return view('admin.messages.show', compact('thread', 'users'));
    }

    /**
     * Creates a new message thread.
     *
     * @return mixed
     */
    public function create() {
        $users = \App\Shop\Employees\Employee::where('id', '!=', Auth::id())->get();

        return view('admin.messages.create', compact('users'));
    }

    /**
     * Stores a new message thread.
     *
     * @return mixed
     */
    public function store() {

        $input = Input::all();

        if (!isset($input['thread_id'])) {
            $thread = \App\Shop\Messages\Thread::create([
                        'subject' => $input['subject'],
                        'order_id' => $input['order_id'],
                        'message_type' => $input['message_type']
            ]);
        } else {
            try {


                $thread = \App\Shop\Messages\Thread::findOrFail($input['thread_id']);
            } catch (ModelNotFoundException $e) {
                Session::flash('error_message', 'The thread with ID: ' . $id . ' was not found.');
                return redirect()->route('messages');
            }
        }
        // Message
        \App\Shop\Messages\Message::create([
            'thread_id' => $thread->id,
            'user_id' => auth()->guard('admin')->user()->id,
            'body' => $input['message'],
            'direction' => 'OUT'
        ]);
        // Sender
        \App\Shop\Messages\Participant::create([
            'thread_id' => $thread->id,
            'user_id' => auth()->guard('admin')->user()->id,
            'last_read' => new Carbon,
        ]);
        // Recipients
        if (Input::has('recipients')) {
            $thread->addParticipant($input['recipients']);
        }

        //mail($input['email_address'], $input['subject'], $input['message']);

        return response()->json(['http_code' => 200, 'message' => 'message sent successfully']);
    }

    /**
     * 
     * @param type $orderId
     */
    public function get($orderId) {

        $order = (new OrderRepository(new Order))->findOrderById($orderId);

        $messages = (new \App\Shop\Messages\Thread)->getByOrderIdAndType($orderId, 1);
        
        return view('admin.messages.get', ['messages' => $messages, 'order' => $order]);
    }

    /**
     * Adds a new message to a current thread.
     *
     * @param $id
     * @return mixed
     */
    public function update($id) {
        try {
            $thread = Thread::findOrFail($id);
        } catch (ModelNotFoundException $e) {
            Session::flash('error_message', 'The thread with ID: ' . $id . ' was not found.');
            return redirect()->route('messages');
        }
        $thread->activateAllParticipants();
        // Message
        Message::create([
            'thread_id' => $thread->id,
            'user_id' => Auth::id(),
            'body' => Input::get('message'),
        ]);
        // Add replier as a participant
        $participant = Participant::firstOrCreate([
                    'thread_id' => $thread->id,
                    'user_id' => Auth::id(),
        ]);
        $participant->last_read = new Carbon;
        $participant->save();
        // Recipients
        if (Input::has('recipients')) {
            $thread->addParticipant(Input::get('recipients'));
        }
        return redirect()->route('messages.show', $id);
    }

}
