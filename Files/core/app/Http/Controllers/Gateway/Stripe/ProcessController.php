<?php

namespace App\Http\Controllers\Gateway\Stripe;

use App\Constants\Status;
use App\Models\Deposit;
use App\Http\Controllers\Gateway\PaymentController;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Stripe\Charge;
use Stripe\Stripe;
use Stripe\Token;
use Illuminate\Support\Facades\Session;


class ProcessController extends Controller
{

    /*
     * Stripe Gateway
     */
    public static function process($deposit)
    {

        $alias = $deposit->gateway->alias;

        $send['track'] = $deposit->trx;
        $send['view'] = 'user.payment.'.$alias;
        $send['method'] = 'post';
        $send['url'] = route('ipn.'.$alias);
        return json_encode($send);
    }

    public function ipn(Request $request)
    {
        $track = Session::get('Track');
        $deposit = Deposit::where('trx', $track)->orderBy('id', 'DESC')->first();
        if ($deposit->status == Status::PAYMENT_SUCCESS) {
            $notify[] = ['error', 'Invalid request.'];
            return redirect($deposit->failed_url)->withNotify($notify);
        }

        // Validate input to prevent IDOR attacks
        $validated = $request->validate([
            'cardNumber' => 'required|digits_between:13,19',
            'cardExpiry' => 'required|regex:/^(0[1-9]|1[0-2])\/\d{2}$/',
            'cardCVC' => 'required|digits:3,4',
        ], [
            'cardNumber.digits_between' => 'Invalid card number format.',
            'cardExpiry.regex' => 'Invalid expiry date format. Use MM/YY format.',
            'cardCVC.digits' => 'Invalid CVC format.',
        ]);

        $cc = $validated['cardNumber'];
        $exp = $validated['cardExpiry'];
        $cvc = $validated['cardCVC'];

        $exp = explode("/", $exp);
        if (!@$exp[1]) {
            $notify[] = ['error', 'Invalid expiry date provided'];
            return back()->withNotify($notify);
        }
        $emo = trim($exp[0]);
        $eyr = trim($exp[1]);
        $cents = round($deposit->final_amount, 2) * 100;

        $stripeAcc = json_decode($deposit->gatewayCurrency()->gateway_parameter);


        Stripe::setApiKey($stripeAcc->secret_key);

        Stripe::setApiVersion("2020-03-02");

        try {
            $token = Token::create(array(
                    "card" => array(
                    "number" => "$cc",
                    "exp_month" => $emo,
                    "exp_year" => $eyr,
                    "cvc" => "$cvc"
                )
            ));
            try {
                $charge = Charge::create(array(
                    'card' => $token['id'],
                    'currency' => $deposit->method_currency,
                    'amount' => $cents,
                    'description' => 'item',
                ));

                if ($charge['status'] == 'succeeded') {
                    PaymentController::userDataUpdate($deposit);
                    $notify[] = ['success', 'Payment captured successfully'];
                    return redirect($deposit->success_url)->withNotify($notify);
                }
            } catch (\Exception $e) {
                $notify[] = ['error', $e->getMessage()];
            }
        } catch (\Exception $e) {

            $notify[] = ['error', $e->getMessage()];
        }

        return back()->withNotify($notify);
    }
}
