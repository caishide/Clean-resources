<?php

namespace App\Http\Controllers\Admin;

use App\Models\Plan;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class PlanController extends Controller
{
    public function index()
    {
        $pageTitle = "Plans";
        $plans     = Plan::orderBy('price')->searchable(['name'])->get();
        return view('admin.plan.index', compact('pageTitle', 'plans'));
    }

    public function store(Request $request, $id = 0)
    {
        $request->validate([
            'name'     => 'required',
            'price'    => 'required|numeric|min:0',
            'bv'       => 'required|min:0|integer',
            'ref_com'  => 'required|numeric|min:0',
            'tree_com' => 'required|numeric|min:0',
        ]);

        if (!$id) {
            $notification = 'Plan added successfully';
            $plan         = new Plan();
        } else {
            $notification = 'Plan updated successfully';
            $plan         = Plan::findOrFail($id);
        }

        $plan->name     = $request->name;
        $plan->price    = $request->price;
        $plan->bv       = $request->bv;
        $plan->ref_com  = $request->ref_com;
        $plan->tree_com = $request->tree_com;
        $plan->save();

        $notify[] = ['success', $notification];
        return back()->withNotify($notify);
    }

    public function status($id)
    {
        return Plan::changeStatus($id);
    }
}
