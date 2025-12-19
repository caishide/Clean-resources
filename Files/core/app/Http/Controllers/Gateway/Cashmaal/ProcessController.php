<?php

namespace App\Http\Controllers\Gateway\Cashmaal;

use App\Constants\Status;
use App\Models\Deposit;
use App\Models\GatewayCurrency;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Gateway\PaymentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProcessController extends Controller
{
    /*
     * Cashmaal
     */

    public static function process($deposit)
    {
    	$cashmaal = $deposit->gatewayCurrency();
    	$param = json_decode($cashmaal->gateway_parameter);
        $val['pay_method'] = " ";
        $val['amount'] = getAmount($deposit->final_amount);
        $val['currency'] = $cashmaal->currency;
        $val['succes_url'] = route('home').$deposit->success_url;
        $val['cancel_url'] = route('home').$deposit->failed_url;
        $val['client_email'] = auth()->user()->email;
        $val['web_id'] = $param->web_id;
        $val['order_id'] = $deposit->trx;
        $val['addi_info'] = "Deposit";
        $send['url'] = 'https://www.cashmaal.com/Pay/';
        $send['method'] = 'post';
        $send['view'] = 'user.payment.redirect';
        $send['val'] = $val;
        return json_encode($send);
    }

    public function ipn(Request $request)
    {
        // Validate and sanitize input
        $validated = $request->validate([
            'order_id' => 'required|string|max:255',
            'ipn_key' => 'required|string',
            'web_id' => 'required|string',
            'status' => 'required|integer',
            'currency' => 'required|string',
        ]);

        Log::channel('gateway')->info('Cashmaal IPN received', [
            'order_id' => $validated['order_id'],
            'status' => $validated['status'],
            'currency' => $validated['currency'],
            'ip' => $request->ip(),
        ]);

        $gateway = GatewayCurrency::where('gateway_alias','Cashmaal')->where('currency',$validated['currency'])->first();
        if (!$gateway) {
            Log::channel('gateway')->error('Cashmaal IPN: Gateway not found', ['currency' => $validated['currency']]);
            abort(404);
        }

        $IPN_key = json_decode($gateway->gateway_parameter)->ipn_key;
        $web_id = json_decode($gateway->gateway_parameter)->web_id;

        $deposit = Deposit::where('trx', $validated['order_id'])->orderBy('id', 'DESC')->first();
        if (!$deposit) {
            Log::channel('gateway')->error('Cashmaal IPN: Deposit not found', ['order_id' => $validated['order_id']]);
            abort(404);
        }

        if ($validated['ipn_key'] != $IPN_key && $web_id != $validated['web_id']) {
            Log::channel('gateway')->warning('Cashmaal IPN: Invalid credentials', ['order_id' => $validated['order_id']]);
            $notify[] = ['error','Data invalid'];
            return redirect($deposit->failed_url)->withNotify($notify);
        }

        if ($validated['status'] == 2) {
            Log::channel('gateway')->info('Cashmaal IPN: Payment pending', ['order_id' => $validated['order_id']]);
            $notify[] = ['info','Payment in pending'];
            return redirect($deposit->failed_url)->withNotify($notify);
        }

        if ($validated['status'] != 1) {
            Log::channel('gateway')->warning('Cashmaal IPN: Invalid status', ['order_id' => $validated['order_id'], 'status' => $validated['status']]);
            $notify[] = ['error','Data invalid'];
            return redirect($deposit->failed_url)->withNotify($notify);
        }

        if ($validated['status'] == 1 && $deposit->status == Status::PAYMENT_INITIATE && $validated['currency'] == $deposit->method_currency) {
            PaymentController::userDataUpdate($deposit);
            Log::channel('gateway')->info('Cashmaal IPN: Payment successful', ['order_id' => $validated['order_id']]);
            $notify[] = ['success', 'Transaction is successful'];
        } else {
            Log::channel('gateway')->warning('Cashmaal IPN: Payment validation failed', [
                'order_id' => $validated['order_id'],
                'deposit_status' => $deposit->status,
                'expected_currency' => $deposit->method_currency,
                'received_currency' => $validated['currency']
            ]);
            $notify[] = ['error','Payment failed'];
            return redirect($deposit->failed_url)->withNotify($notify);
        }
        return redirect($deposit->success_url)->withNotify($notify);
    }
}
