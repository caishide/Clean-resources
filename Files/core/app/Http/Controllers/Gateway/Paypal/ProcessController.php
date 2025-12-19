<?php

namespace App\Http\Controllers\Gateway\Paypal;

use App\Constants\Status;
use App\Models\Deposit;
use App\Http\Controllers\Gateway\PaymentController;
use App\Http\Controllers\Controller;
use App\Lib\CurlRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProcessController extends Controller
{

    public static function process($deposit)
    {
        $general = gs();
        $paypalAcc = json_decode($deposit->gatewayCurrency()->gateway_parameter);
        $val['cmd'] = '_xclick';
        $val['business'] = trim($paypalAcc->paypal_email);
        $val['cbt'] = $general->site_name;
        $val['currency_code'] = "$deposit->method_currency";
        $val['quantity'] = 1;
        $val['item_name'] = "Payment To $general->site_name Account";
        $val['custom'] = "$deposit->trx";
        $val['amount'] = round($deposit->final_amount,2);
        $val['return'] = route('home').$deposit->success_url;
        $val['cancel_return'] = route('home').$deposit->failed_url;
        $val['notify_url'] = route('ipn.'.$deposit->gateway->alias);
        $send['val'] = $val;
        $send['view'] = 'user.payment.redirect';
        $send['method'] = 'post';
        // $send['url'] = 'https://www.sandbox.paypal.com/'; // use for sandbod text
        $send['url'] = 'https://www.paypal.com/cgi-bin/webscr';
        return json_encode($send);
    }

    public function ipn(Request $request)
    {
        Log::channel('gateway')->info('Paypal IPN received', ['ip' => $request->ip()]);

        $raw_post_data = file_get_contents('php://input');
        $raw_post_array = explode('&', $raw_post_data);
        $myPost = array();
        foreach ($raw_post_array as $keyval) {
            $keyval = explode('=', $keyval);
            if (count($keyval) == 2)
                $myPost[$keyval[0]] = urldecode($keyval[1]);
        }

        // Validate critical fields
        if (!isset($myPost['custom']) || !isset($myPost['mc_gross'])) {
            Log::channel('gateway')->error('Paypal IPN: Missing required fields', ['fields' => array_keys($myPost)]);
            abort(400, 'Bad Request');
        }

        $req = 'cmd=_notify-validate';
        $details = array();
        foreach ($myPost as $key => $value) {
            $value = urlencode(stripslashes($value));
            $req .= "&$key=$value";
            $details[$key] = $value;
        }

        // $paypalURL = "https://ipnpb.sandbox.paypal.com/cgi-bin/webscr?"; // use for sandbox text
        $paypalURL = "https://ipnpb.paypal.com/cgi-bin/webscr?";
        $url = $paypalURL . $req;
        $response = CurlRequest::curlContent($url);

        if ($response == "VERIFIED") {
            Log::channel('gateway')->info('Paypal IPN: Verification successful', ['custom' => $myPost['custom']]);

            $deposit = Deposit::where('trx', $myPost['custom'])->orderBy('id', 'DESC')->first();
            if (!$deposit) {
                Log::channel('gateway')->error('Paypal IPN: Deposit not found', ['custom' => $myPost['custom']]);
                abort(404);
            }

            $deposit->detail = $details;
            $deposit->save();

            if ($myPost['mc_gross'] == round($deposit->final_amount,2) && $deposit->status == Status::PAYMENT_INITIATE) {
                Log::channel('gateway')->info('Paypal IPN: Payment successful', ['custom' => $myPost['custom']]);
                PaymentController::userDataUpdate($deposit);
            } else {
                Log::channel('gateway')->warning('Paypal IPN: Payment validation failed', [
                    'custom' => $myPost['custom'],
                    'expected_amount' => round($deposit->final_amount,2),
                    'received_amount' => $myPost['mc_gross'],
                    'deposit_status' => $deposit->status
                ]);
            }
        } else {
            Log::channel('gateway')->error('Paypal IPN: Verification failed', [
                'response' => $response,
                'custom' => $myPost['custom'] ?? 'N/A'
            ]);
        }
    }
}
