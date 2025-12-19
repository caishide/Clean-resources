<?php

namespace App\Http\Controllers\Gateway\Paytm;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Gateway\PaymentController;
use App\Http\Controllers\Gateway\Paytm\PayTM;
use App\Models\Deposit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProcessController extends Controller
{
    /*
     * PayTM Gateway
     */

    public static function process($deposit)
    {
        $PayTmAcc = json_decode($deposit->gatewayCurrency()->gateway_parameter);


        $alias = $deposit->gateway->alias;

        $val['MID'] = trim($PayTmAcc->MID);
        $val['WEBSITE'] = trim($PayTmAcc->WEBSITE);
        $val['CHANNEL_ID'] = trim($PayTmAcc->CHANNEL_ID);
        $val['INDUSTRY_TYPE_ID'] = trim($PayTmAcc->INDUSTRY_TYPE_ID);

        try {
            $checkSumHash = (new PayTM())->getChecksumFromArray($val, $PayTmAcc->merchant_key);
        } catch (\Exception $e) {
            $send['error'] = true;
            $send['message'] = $e->getMessage();
            return json_encode($send);
        }

        $val['ORDER_ID'] = $deposit->trx;
        $val['TXN_AMOUNT'] = round($deposit->final_amount,2);
        $val['CUST_ID'] = $deposit->user_id;
        $val['CALLBACK_URL'] = route('ipn.'.$alias);
        $val['CHECKSUMHASH'] = $checkSumHash;

        $send['val'] = $val;
        $send['view'] = 'user.payment.redirect';
        $send['method'] = 'post';
        $send['url'] = $PayTmAcc->transaction_url . "?orderid=" . $deposit->trx;

        return json_encode($send);
    }
    public function ipn(Request $request)
    {
        // Validate and sanitize input
        $validated = $request->validate([
            'ORDERID' => 'required|string',
            'CHECKSUMHASH' => 'required|string',
            'RESPCODE' => 'required|string',
            'TXNAMOUNT' => 'required|numeric',
            'RESPMSG' => 'required|string',
        ]);

        Log::channel('gateway')->info('Paytm IPN received', [
            'order_id' => $validated['ORDERID'],
            'response_code' => $validated['RESPCODE'],
            'amount' => $validated['TXNAMOUNT'],
            'ip' => $request->ip(),
        ]);

        $deposit = Deposit::where('trx', $validated['ORDERID'])->orderBy('id', 'DESC')->first();
        if (!$deposit) {
            Log::channel('gateway')->error('Paytm IPN: Deposit not found', ['order_id' => $validated['ORDERID']]);
            abort(404);
        }

        $PayTmAcc = json_decode($deposit->gatewayCurrency()->gateway_parameter);
        $ptm = new PayTM();

        if ($ptm->verifychecksum_e($_POST, $PayTmAcc->merchant_key, $validated['CHECKSUMHASH']) === "TRUE") {
            Log::channel('gateway')->info('Paytm IPN: Checksum verified', ['order_id' => $validated['ORDERID']]);

            if ($validated['RESPCODE'] == "01") {
                $requestParamList = array("MID" => $PayTmAcc->MID, "ORDERID" => $validated['ORDERID']);
                $StatusCheckSum = $ptm->getChecksumFromArray($requestParamList, $PayTmAcc->merchant_key);
                $requestParamList['CHECKSUMHASH'] = $StatusCheckSum;
                $responseParamList = $ptm->callNewAPI($PayTmAcc->transaction_status_url, $requestParamList);
                if ($responseParamList['STATUS'] == 'TXN_SUCCESS' && $responseParamList['TXNAMOUNT'] == $validated['TXNAMOUNT'] && $deposit->status == Status::PAYMENT_INITIATE) {
                    Log::channel('gateway')->info('Paytm IPN: Payment successful', ['order_id' => $validated['ORDERID']]);
                    PaymentController::userDataUpdate($deposit);
                    $notify[] = ['success', 'Transaction is successful'];
                    return redirect($deposit->success_url)->withNotify($notify);
                } else {
                    Log::channel('gateway')->warning('Paytm IPN: Transaction status check failed', [
                        'order_id' => $validated['ORDERID'],
                        'response_status' => $responseParamList['STATUS'] ?? 'N/A',
                        'expected_amount' => $validated['TXNAMOUNT'],
                        'received_amount' => $responseParamList['TXNAMOUNT'] ?? 'N/A'
                    ]);
                    $notify[] = ['error', 'It seems some issue in server to server communication. Kindly connect with administrator'];
                }
            } else {
                Log::channel('gateway')->warning('Paytm IPN: Payment failed', [
                    'order_id' => $validated['ORDERID'],
                    'response_code' => $validated['RESPCODE'],
                    'response_message' => $validated['RESPMSG']
                ]);
                $notify[] = ['error',  $validated['RESPMSG']];
            }
        } else {
            Log::channel('gateway')->error('Paytm IPN: Checksum verification failed', ['order_id' => $validated['ORDERID']]);
            $notify[] = ['error',  'Security error!'];
        }
        return redirect($deposit->failed_url)->withNotify($notify);
    }
}
