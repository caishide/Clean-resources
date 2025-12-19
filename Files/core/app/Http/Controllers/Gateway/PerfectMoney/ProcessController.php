<?php

namespace App\Http\Controllers\Gateway\PerfectMoney;

use App\Constants\Status;
use App\Models\Deposit;
use App\Http\Controllers\Gateway\PaymentController;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProcessController extends Controller
{

    /*
     * Perfect Money Gateway
     */
    public static function process($deposit)
    {
        $gateway_currency = $deposit->gatewayCurrency();

        $perfectAcc = json_decode($gateway_currency->gateway_parameter);

        $val['PAYEE_ACCOUNT'] = trim($perfectAcc->wallet_id);
        $val['PAYEE_NAME'] = gs('site_name');
        $val['PAYMENT_ID'] = "$deposit->trx";
        $val['PAYMENT_AMOUNT'] = round($deposit->final_amount,2);
        $val['PAYMENT_UNITS'] = "$deposit->method_currency";

        $val['STATUS_URL'] = route('ipn.'.$deposit->gateway->alias);
        $val['PAYMENT_URL'] = route('home').$deposit->success_url;
        $val['PAYMENT_URL_METHOD'] = 'POST';
        $val['NOPAYMENT_URL'] = route('home').$deposit->failed_url;
        $val['NOPAYMENT_URL_METHOD'] = 'POST';
        $val['SUGGESTED_MEMO'] = auth()->user()->username;
        $val['BAGGAGE_FIELDS'] = 'IDENT';


        $send['val'] = $val;
        $send['view'] = 'user.payment.redirect';
        $send['method'] = 'post';
        $send['url'] = 'https://perfectmoney.is/api/step1.asp';

        return json_encode($send);
    }
    public function ipn(Request $request)
    {
        // Validate and sanitize input
        $validated = $request->validate([
            'PAYMENT_ID' => 'required|string',
            'PAYEE_ACCOUNT' => 'required|string',
            'PAYMENT_AMOUNT' => 'required|numeric',
            'PAYMENT_UNITS' => 'required|string',
            'PAYMENT_BATCH_NUM' => 'required|string',
            'PAYER_ACCOUNT' => 'required|string',
            'V2_HASH' => 'required|string',
            'TIMESTAMPGMT' => 'required|string',
        ]);

        Log::channel('gateway')->info('PerfectMoney IPN received', [
            'payment_id' => $validated['PAYMENT_ID'],
            'amount' => $validated['PAYMENT_AMOUNT'],
            'units' => $validated['PAYMENT_UNITS'],
            'ip' => $request->ip(),
        ]);

        $deposit = Deposit::where('trx', $validated['PAYMENT_ID'])->orderBy('id', 'DESC')->first();
        if (!$deposit) {
            Log::channel('gateway')->error('PerfectMoney IPN: Deposit not found', ['payment_id' => $validated['PAYMENT_ID']]);
            abort(404);
        }

        $pmAcc = json_decode($deposit->gatewayCurrency()->gateway_parameter);
        $passphrase = strtoupper(md5($pmAcc->passphrase));

        define('ALTERNATE_PHRASE_HASH', $passphrase);
        $string =
            $validated['PAYMENT_ID'] . ':' . $validated['PAYEE_ACCOUNT'] . ':' .
            $validated['PAYMENT_AMOUNT'] . ':' . $validated['PAYMENT_UNITS'] . ':' .
            $validated['PAYMENT_BATCH_NUM'] . ':' .
            $validated['PAYER_ACCOUNT'] . ':' . ALTERNATE_PHRASE_HASH . ':' .
            $validated['TIMESTAMPGMT'];

        $hash = strtoupper(md5($string));
        $hash2 = $validated['V2_HASH'];

        if ($hash == $hash2) {
            Log::channel('gateway')->info('PerfectMoney IPN: Hash verified', ['payment_id' => $validated['PAYMENT_ID']]);

            $details = $request->all();
            $deposit->detail = $details;
            $deposit->save();

            $amount = $validated['PAYMENT_AMOUNT'];
            $unit = $validated['PAYMENT_UNITS'];
            if ($validated['PAYEE_ACCOUNT'] == $pmAcc->wallet_id && $unit == $deposit->method_currency && $amount == round($deposit->final_amount,2) && $deposit->status == Status::PAYMENT_INITIATE) {
                Log::channel('gateway')->info('PerfectMoney IPN: Payment successful', ['payment_id' => $validated['PAYMENT_ID']]);
                PaymentController::userDataUpdate($deposit);
            } else {
                Log::channel('gateway')->warning('PerfectMoney IPN: Payment validation failed', [
                    'payment_id' => $validated['PAYMENT_ID'],
                    'expected_account' => $pmAcc->wallet_id,
                    'received_account' => $validated['PAYEE_ACCOUNT'],
                    'expected_unit' => $deposit->method_currency,
                    'received_unit' => $unit,
                    'expected_amount' => round($deposit->final_amount,2),
                    'received_amount' => $amount,
                    'deposit_status' => $deposit->status
                ]);
            }
        } else {
            Log::channel('gateway')->error('PerfectMoney IPN: Hash verification failed', [
                'payment_id' => $validated['PAYMENT_ID'],
                'expected_hash' => $hash,
                'received_hash' => $hash2
            ]);
        }
    }
}
