<?php

namespace App\Http\Controllers\User;

use App\Models\Plan;
use App\Models\User;
use App\Models\BvLog;
use App\Models\UserExtra;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class PlanController extends Controller
{
    function planIndex()
    {
        $pageTitle = "Plans";
        $plans     = Plan::orderBy('price', 'asc')->active()->get();
        return view('Template::user.plan', compact('pageTitle', 'plans'));
    }

    function planStore(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|integer',
        ]);

        $plan = Plan::active()->where('id', $request->plan_id)->find($request->plan_id);

        if (!$plan) {
            $notify[] = ['error', 'The plan is currently unavailable'];
            return back()->withNotify($notify);
        }

        $user = auth()->user();

        if ($user->balance < $plan->price) {
            $notify[] = ['error', 'Insufficient Balance'];
            return back()->withNotify($notify);
        }

        $oldPlan             = $user->plan_id;
        $user->plan_id       = $plan->id;
        $user->balance      -= $plan->price;
        $user->total_invest += $plan->price;
        $user->save();

        $trx               = new Transaction();
        $trx->user_id      = $user->id;
        $trx->amount       = $plan->price;
        $trx->trx_type     = '-';
        $trx->details      = 'Purchased ' . $plan->name;
        $trx->remark       = 'purchased_plan';
        $trx->trx          = getTrx();
        $trx->post_balance = $user->balance;
        $trx->save();

        notify($user, 'PLAN_PURCHASED', [
            'plan'         => $plan->name,
            'amount'       => showAmount($plan->price, currencyFormat: false),
            'trx'          => $trx->trx,
            'post_balance' => showAmount($user->balance, currencyFormat: false),
        ]);

        if ($oldPlan == 0) {
            updatePaidCount($user->id);
        }

        $details = auth()->user()->username . ' Subscribed to ' . $plan->name . ' plan.';

        updateBV($user->id, $plan->bv, $details);

        if ($plan->tree_com > 0) {
            treeComission($user->id, $plan->tree_com, $details);
        }

        referralComission($user->id, $details);

        $notify[] = ['success', 'Purchased ' . $plan->name . ' successfully'];
        return back()->withNotify($notify);
    }

    public function binaryCom()
    {
        $pageTitle    = "Binary Commission";
        $logs         = Transaction::where('user_id', auth()->id())->where('remark', 'binary_commission')->orderBy('id', 'DESC')->paginate(getPaginate());
        $emptyMessage = 'No data found';
        return view('Template::user.transactions', compact('pageTitle', 'logs', 'emptyMessage'));
    }

    public function binarySummery()
    {
        $pageTitle = "Binary Summery";
        $logs      = UserExtra::where('user_id', auth()->id())->firstOrFail();
        return view('Template::user.binarySummery', compact('pageTitle', 'logs'));
    }

    public function bvlog(Request $request)
    {
        if ($request->type) {
            if ($request->type == 'leftBV') {
                $pageTitle = "Left BV";
            } elseif ($request->type == 'rightBV') {
                $pageTitle = "Right BV";
            } elseif ($request->type == 'cutBV') {
                $pageTitle = "Cut BV";
            } else {
                $pageTitle = "All Paid BV";
            }

            $logs = $this->bvData($request->type);
        } else {
            $pageTitle = "BV Log";
            $logs      = $this->bvData();
        }

        $logs = $logs->where('user_id', auth()->id())->latest('id')->paginate(getPaginate());

        return view('Template::user.bvLog', compact('pageTitle', 'logs'));
    }

    protected function bvData($scope = null)
    {
        if ($scope) {
            $logs = BvLog::$scope();
        } else {
            $logs = BvLog::query();
        }
        return $logs;
    }

    public function myRefLog()
    {
        $pageTitle = "My Referral";
        $logs      = User::where('ref_by', auth()->id())->latest()->paginate(getPaginate());
        return view('Template::user.myRef', compact('pageTitle', 'logs'));
    }

    public function myTree()
    {
        $tree      = showTreePage(auth()->user()->id);
        $pageTitle = "My Tree";
        $user      = auth()->user();
        return view('Template::user.myTree', compact('pageTitle', 'tree', 'user'));
    }
}
