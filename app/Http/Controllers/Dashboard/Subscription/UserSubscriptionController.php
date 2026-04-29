<?php

namespace App\Http\Controllers\Dashboard\Subscription;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\Subscription\UserSubscriptionRequest;
use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;
use App\Traits\ResponseApi;
use Illuminate\Http\Request;

class UserSubscriptionController extends Controller
{
    use ResponseApi ;
    public function index(Request $request)
    {
        $subscriptions = UserSubscription::latest()->status($request->status)->paginate(15) ;
        return $this->successApi($subscriptions , 'Subscription fetched successfully');
    }


    // assign subscription by admin
    public function store(UserSubscriptionRequest $request)
    {
        $validated = $request->validated();
        $plan = SubscriptionPlan::findOrFail($validated['plan_id']);

        $startAt = now();
        $endAt = $startAt->copy()->addMonths($plan->duration_months);

        $subscription = UserSubscription::create([
            'user_id' => $validated['user_id'],
            'plan_id' => $plan->id,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'price' => $validated['price'] ?? $plan->price,
            'status' => $validated['status'],
            'payment_status' => $validated['payment_status'],
        ]);

        return $this->successApi($subscription, 'Subscription assigned to user successfully');
    }


    public function show(UserSubscription $userSubscription)
    {
        return $this->successApi($userSubscription->load(['user:id,name,phone,email' , 'plan']) ,'Subscription fetch successfully') ;
    }

    public function update(Request $request, string $id)
    {
        //
    }

    public function activate(Request $request , $id)
    {
        $request->validate(['count_months' => 'required|integer|min:1']);
        $user_subscription = UserSubscription::findorfail($id) ;
        if($user_subscription->status == 'active') // احنا هنعمل Schdule عشان هيبقي في اشتراكات Expired
        {
            return $this->errorApi("Subscription already active until $user_subscription->end_at ") ;
        }

        $user_subscription->update([
            'status' => 'active',
            'end_at' => now()->addMonths($request->count_months),
        ]);

        return $this->successApi($user_subscription, 'Subscription Activated successfully');
    }


    public function cancel(Request $request , $id)
    {
        $user_subscription = UserSubscription::findorfail($id) ;
        if($user_subscription->status == 'inactive'){
            return $this->errorApi('Subscription already inactive') ;
        }
        $user_subscription->update([
            'status' => 'inactive',
            'canceled_at' => now(),
            'ended_reason' => $request->ended_reason,
        ]);
        return $this->successApi($user_subscription, 'Subscription become inactive successfully');
    }


    public function stats()
    {
        $stats = [
            'total' => UserSubscription::count(),
            'active' => UserSubscription::where('status', 'active')->count(),
            'inactive' => UserSubscription::where('status', 'inactive')->count(),
            'expired' => UserSubscription::where('status', 'expired')->count(),
            'subscription_gift' => UserSubscription::where('payment_status', 'gift')->count(),
            'revenue' => UserSubscription::where('payment_status', 'paid')->sum('price'),
        ];
        return $this->successApi($stats , 'Subscription of stats fetched successfully') ;

    }
}
