<?php

namespace App\Http\Controllers\Dashboard\Subscription;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\Subscription\SubscriptionPlanRequest;
use Illuminate\Http\Request;
use App\Models\SubscriptionPlan;
use App\Traits\ResponseApi;

class SubscriptionPlanController extends Controller
{
    use ResponseApi ;
    public function __construct()
    {
        $this->authorizeResource(SubscriptionPlan::class, 'subscription_plan');
    }

    public function index()
    {
        $plans = SubscriptionPlan::latest()->paginate(15);
        return $this->successApi($plans, 'Subscription Plans fetched successfully');
    }

    public function store(SubscriptionPlanRequest $request)
    {
        $subscriptionPlan = SubscriptionPlan::create($request->validated());
        return $this->successApi($subscriptionPlan, 'Subscription Plan Created successfully', 201);
    }

    public function show(SubscriptionPlan $subscriptionPlan)
    {
        return $this->successApi($subscriptionPlan, 'Subscription Plan fetched successfully');
    }

    public function update(SubscriptionPlanRequest $request, SubscriptionPlan $subscriptionPlan)
    {
        $subscriptionPlan->update($request->validated());
        return $this->successApi($subscriptionPlan, 'Subscription Plan updated successfully');
    }

    public function destroy(SubscriptionPlan $subscriptionPlan)
    {
        if($subscriptionPlan->userSubscriptions()->exists()) {
            return $this->errorApi('Cannot delete subscription plan with active user subscriptions', 400);

        }
        $subscriptionPlan->delete();
        return $this->successApi(null, 'Subscription Plan deleted successfully');
    }
}
