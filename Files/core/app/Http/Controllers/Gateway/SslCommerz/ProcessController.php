<?php
namespace App\Http\Controllers\Gateway\SslCommerz;

use App\Constants\Status;
use App\Models\Deposit;
use App\Http\Controllers\Gateway\PaymentController;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Lib\CurlRequest;
use Illuminate\Support\Facades\Log;
class ProcessController extends Controller{

    public static function process($deposit){
        $parameters = json_decode($deposit->gatewayCurrency()->gateway_parameter);
        $postData                    = array();
        $alias = $deposit->gateway->alias;
        $postData['store_id']        = $parameters->store_id;
        $postData['store_passwd']    = $parameters->store_password;
        $postData['total_amount']    = $deposit->final_amount;
        $postData['currency']        = $deposit->method_currency;
        $postData['tran_id']         = $deposit->trx;
        $postData['success_url']     = route('ipn.'.$alias);
        $postData['fail_url']        = route('home').$deposit->failed_url;
        $postData['cancel_url']      = route('home').$deposit->failed_url;
        $postData['emi_option'] = "0";

        if(auth()->check()){
            $user = auth()->user();
            $postData['cus_name']  = $user->fullname;
            $postData['cus_email'] = $user->email;
            $postData['cus_phone'] = $user->phone;
        }

        $paymentUrl = "https://securepay.sslcommerz.com/gwprocess/v3/api.php";
        // $paymentUrl = "https://sandbox.sslcommerz.com/gwprocess/v3/api.php";
        $response = CurlRequest::curlPostContent($paymentUrl, $postData);
        $response = json_decode($response);

        if(!$response || !@$response->status){
            $send['error'] = true;
            $send['message'] = 'Something went wrong';
            return json_encode($send);
        }

        if($response->status != 'SUCCESS'){
            $send['error'] = true;
            $send['message'] = 'Something went wrong';
            return json_encode($send);
        }
        $send['redirect']     = true;
        $send['redirect_url'] = $response->redirectGatewayURL;
        return json_encode($send);
    }

    public function ipn(Request $request){
        // Validate input to prevent IDOR attacks
        $validated = $request->validate([
            'tran_id' => 'required|string|max:50|regex:/^[a-zA-Z0-9_-]+$/',
            'status' => 'required|string|in:VALID,INVALID',
            'verify_sign' => 'required|string|size:32',
            'verify_key' => 'required|string',
        ]);

        Log::channel('gateway')->info('SslCommerz IPN received', [
            'tran_id' => $validated['tran_id'],
            'status' => $validated['status'],
            'ip' => $request->ip(),
        ]);

        $track = $validated['tran_id'];
        $status = $validated['status'];
        $deposit = Deposit::where('trx', $track)->orderBy('id', 'DESC')->first();

        if (!$deposit) {
            Log::channel('gateway')->error('SslCommerz IPN: Deposit not found', ['tran_id' => $track]);
            abort(404);
        }

        if ($status == 'VALID' && @$deposit->status == Status::PAYMENT_INITIATE) {
            // Validate that verify_key is properly formatted
            if (strpos($validated['verify_key'], ',') !== false && strlen($validated['verify_key']) < 255) {
                $preDefineKey = explode(',', $validated['verify_key']);
                $newData = array();

                // Only allow known safe keys
                $allowedKeys = ['tran_id', 'val_id', 'amount', 'card_type', 'store_amount', 'card_no', 'bank_tran_id',
                               'status', 'tran_date', 'error', 'error_title', 'error_description', 'store_id',
                               'card_issuer', 'card_brand', 'card_issuer_country', 'card_issuer_country_code', 'currency',
                               'currency_type', 'convertion_rate', 'convertion_amount', 'convertion_charge'];

                if (!empty($preDefineKey)) {
                    foreach ($preDefineKey as $value) {
                        // Sanitize key name to prevent injection
                        $sanitizedKey = preg_replace('/[^a-zA-Z0-9_-]/', '', $value);
                        if (in_array($sanitizedKey, $allowedKeys) && $request->has($sanitizedKey)) {
                            $newData[$sanitizedKey] = $request->input($sanitizedKey);
                        }
                    }
                }
                $parameters = json_decode($deposit->gatewayCurrency()->gateway_parameter);

                $newData['store_passwd'] = md5($parameters->store_password);

                ksort($newData);
                $hashString = "";
                foreach ($newData as $key => $value) {
                    // Ensure value is properly escaped
                    $hashString .= $key . '=' . addslashes($value) . '&';
                }
                $hashString = rtrim($hashString, '&');

                if (md5($hashString) == $validated['verify_sign']) {
                    Log::channel('gateway')->info('SslCommerz IPN: Hash verified', ['tran_id' => $track]);
                    $input  = $request->except(['method', '_token']);
                    $ssltxt = "";
                    foreach ($input as $key => $value) {
                        $ssltxt .= "$key : " . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . " <br>";
                    }
                    PaymentController::userDataUpdate($deposit);
                    $notify[] = ['success', 'Payment captured successfully'];
                    return redirect($deposit->success_url)->withNotify($notify);
                } else {
                    Log::channel('gateway')->error('SslCommerz IPN: Hash verification failed', [
                        'tran_id' => $track,
                        'expected_hash' => md5($hashString),
                        'received_hash' => $validated['verify_sign']
                    ]);
                }
            } else {
                Log::channel('gateway')->error('SslCommerz IPN: Invalid verify_key format', [
                    'tran_id' => $track,
                    'verify_key' => substr($validated['verify_key'], 0, 100)
                ]);
            }
        }
        Log::channel('gateway')->warning('SslCommerz IPN: Invalid request', ['tran_id' => $track, 'status' => $status]);
        $notify[] = ['error','Invalid request'];
        return redirect($deposit->failed_url)->withNotify($notify);
    }
}
