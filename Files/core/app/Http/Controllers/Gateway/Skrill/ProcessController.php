<?php

namespace App\Http\Controllers\Gateway\Skrill;

use App\Constants\Status;
use App\Models\Deposit;
use App\Http\Controllers\Gateway\PaymentController;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProcessController extends Controller
{

    /*
     * Skrill Gateway
     */
    public static function process($deposit)
    {
        $general = gs();
        $skrillAcc = json_decode($deposit->gatewayCurrency()->gateway_parameter);


        $val['pay_to_email'] = trim($skrillAcc->pay_to_email);
        $val['transaction_id'] = "$deposit->trx";

        $val['return_url'] = route('home').$deposit->success_url;
        $val['return_url_text'] = "Return $general->site_name";
        $val['cancel_url'] = route('home').$deposit->failed_url;
        $val['status_url'] = route('ipn.'.$deposit->gateway->alias);
        $val['language'] = 'EN';
        $val['amount'] = round($deposit->final_amount,2);
        $val['currency'] = "$deposit->method_currency";
        $val['detail1_description'] = "$general->site_name";
        $val['detail1_text'] = "Pay To $general->site_name";
        $val['logo_url'] = siteLogo();

        $send['val'] = $val;
        $send['view'] = 'user.payment.redirect';
        $send['method'] = 'post';
        $send['url'] = 'https://www.moneybookers.com/app/payment.pl';
        return json_encode($send);
    }


    public function ipn(Request $request)
    {
        // Validate and sanitize input
        $validated = $request->validate([
            'transaction_id' => 'required|string',
            'merchant_id' => 'required|string',
            'mb_amount' => 'required|numeric',
            'mb_currency' => 'required|string',
            'status' => 'required|integer',
            'md5sig' => 'required|string',
            'pay_to_email' => 'required|email',
        ]);

        Log::channel('gateway')->info('Skrill IPN received', [
            'transaction_id' => $validated['transaction_id'],
            'status' => $validated['status'],
            'amount' => $validated['mb_amount'],
            'ip' => $request->ip(),
        ]);

        $deposit = Deposit::where('trx', $validated['transaction_id'])->orderBy('id', 'DESC')->first();
        if (!$deposit) {
            Log::channel('gateway')->error('Skrill IPN: Deposit not found', ['transaction_id' => $validated['transaction_id']]);
            abort(404);
        }

        $skrillrAcc = json_decode($deposit->gatewayCurrency()->gateway_parameter);
        $concatFields = $validated['merchant_id']
            . $validated['transaction_id']
            . strtoupper(md5($skrillrAcc->secret_key))
            . $validated['mb_amount']
            . $validated['mb_currency']
            . $validated['status'];

        if (strtoupper(md5($concatFields)) == $validated['md5sig'] && $validated['status'] == 2 && $validated['pay_to_email'] == $skrillrAcc->pay_to_email && $deposit->status == Status::PAYMENT_INITIATE) {
            Log::channel('gateway')->info('Skrill IPN: Payment successful', ['transaction_id' => $validated['transaction_id']]);
            PaymentController::userDataUpdate($deposit);
        } else {
            Log::channel('gateway')->warning('Skrill IPN: Validation failed', [
                'transaction_id' => $validated['transaction_id'],
                'expected_status' => 2,
                'received_status' => $validated['status'],
                'expected_email' => $skrillrAcc->pay_to_email,
                'received_email' => $validated['pay_to_email'],
                'deposit_status' => $deposit->status
            ]);
        }
    }
}
