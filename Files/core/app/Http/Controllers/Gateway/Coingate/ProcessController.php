<?php

namespace App\Http\Controllers\Gateway\Coingate;

use App\Constants\Status;
use App\Models\Deposit;
use App\Http\Controllers\Controller;
use CoinGate\Client;
use CoinGate\Merchant\Order;
use App\Http\Controllers\Gateway\PaymentController;
use App\Lib\CurlRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProcessController extends Controller
{
    /*
     * Coingate Gateway 505
     */

    public static function process($deposit)
    {
        $coingateAcc = json_decode($deposit->gatewayCurrency()->gateway_parameter);

        $client = new Client();
        $client->setApiKey($coingateAcc->api_key);
        $client->setEnvironment('live');

        $postParams = array(
            'order_id' => $deposit->trx,
            'price_amount' => round($deposit->final_amount,2),
            'price_currency' => $deposit->method_currency,
            'receive_currency' => $deposit->method_currency,
            'callback_url' => route('ipn.'.$deposit->gateway->alias),
            'cancel_url' => route('home').$deposit->failed_url,
            'success_url' => route('home').$deposit->success_url,
            'title' => 'Payment to ' . gs('site_name'),
            'token' => $deposit->trx
        );

        try {
            $order = $client->order->create($postParams);
        } catch (\Exception $e) {
            $send['error'] = true;
            $send['message'] = $e->getMessage();
            return json_encode($send);
        }
        if ($order) {
            $send['redirect'] = true;
            $send['redirect_url'] = $order->payment_url;
        } else {
            $send['error'] = true;
            $send['message'] = 'Unexpected Error! Please Try Again';
        }
        $send['view'] = '';
        return json_encode($send);
    }

    public function ipn(Request $request)
    {
        // Validate and sanitize input
        $validated = $request->validate([
            'token' => 'required|string',
            'status' => 'required|string',
            'price_amount' => 'required|numeric',
        ]);

        Log::channel('gateway')->info('Coingate IPN received', [
            'token' => $validated['token'],
            'status' => $validated['status'],
            'amount' => $validated['price_amount'],
            'ip' => $request->ip(),
        ]);

        $ip = $request->ip();
        $url = 'https://api.coingate.com/v2/ips-v4';
        $response = CurlRequest::curlContent($url);
        if (strpos($response, $ip) !== false) {
            $deposit = Deposit::where('trx', $validated['token'])->orderBy('id', 'DESC')->first();
            if (!$deposit) {
                Log::channel('gateway')->error('Coingate IPN: Deposit not found', ['token' => $validated['token']]);
                abort(404);
            }

            if ($validated['status'] == 'paid' && $validated['price_amount'] == $deposit->final_amount && $deposit->status == Status::PAYMENT_INITIATE) {
                Log::channel('gateway')->info('Coingate IPN: Payment successful', ['token' => $validated['token']]);
                PaymentController::userDataUpdate($deposit);
            } else {
                Log::channel('gateway')->warning('Coingate IPN: Payment validation failed', [
                    'token' => $validated['token'],
                    'expected_status' => 'paid',
                    'received_status' => $validated['status'],
                    'expected_amount' => $deposit->final_amount,
                    'received_amount' => $validated['price_amount'],
                    'deposit_status' => $deposit->status
                ]);
            }
        } else {
            Log::channel('gateway')->error('Coingate IPN: IP not whitelisted', ['ip' => $ip]);
        }
    }
}
