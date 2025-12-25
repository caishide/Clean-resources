<?php

namespace Tests\Feature;

use App\Constants\Status;
use App\Models\Deposit;
use App\Models\Gateway;
use App\Models\GatewayCurrency;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentGatewaySecurityTest extends TestCase
{
    
    protected $user;
    protected $gateway;
    protected $gatewayCurrency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();

        // Create test user with minimal required fields
        $this->user = User::create([
            'name' => 'Test User',
            'firstname' => 'Test',
            'lastname' => 'User',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'balance' => 1000,
            'plan_id' => 1,
            'status' => Status::USER_ACTIVE,
            'ev' => Status::VERIFIED,
            'sv' => Status::VERIFIED,
        ]);

        // Create test gateway
        $this->gateway = Gateway::create([
            'alias' => 'Cashmaal',
            'name' => 'Cashmaal',
            'status' => Status::ENABLE,
        ]);

        // Create test gateway currency
        $this->gatewayCurrency = GatewayCurrency::create([
            'gateway_id' => $this->gateway->id,
            'currency' => 'USD',
            'rate' => 1,
            'gateway_alias' => 'Cashmaal',
            'gateway_parameter' => json_encode([
                'web_id' => 'test_web_id',
                'ipn_key' => 'test_ipn_key',
            ]),
        ]);
    }

    /** @test */
    public function cashmaal_ipn_validates_required_fields()
    {
        // Test missing required fields
        $response = $this->post(route('ipn.cashmaal'), [
            // Missing required fields
        ]);

        $response->assertSessionHasErrors([
            'order_id', 'ipn_key', 'web_id', 'status', 'currency'
        ]);
    }

    /** @test */
    public function cashmaal_ipn_accepts_valid_payment_data()
    {
        // Create a pending deposit
        $deposit = Deposit::create([
            'user_id' => $this->user->id,
            'method' => $this->gateway->id,
            'amount' => 100,
            'charge' => 5,
            'final_amount' => 105,
            'trx' => 'TEST_TRX_123',
            'status' => Status::PAYMENT_INITIATE,
            'gateway_currency' => 'USD',
            'method_currency' => 'USD',
        ]);

        // Mock valid IPN data
        $ipnData = [
            'order_id' => 'TEST_TRX_123',
            'ipn_key' => 'test_ipn_key',
            'web_id' => 'test_web_id',
            'status' => 1,
            'currency' => 'USD',
        ];

        $response = $this->post(route('ipn.cashmaal'), $ipnData);

        // Should redirect to success URL
        $response->assertRedirect();
        $deposit->refresh();
        $this->assertEquals(Status::PAYMENT_SUCCESS, $deposit->status);
    }

    /** @test */
    public function cashmaal_ipn_rejects_invalid_credentials()
    {
        // Create a pending deposit
        $deposit = Deposit::create([
            'user_id' => $this->user->id,
            'method' => $this->gateway->id,
            'amount' => 100,
            'charge' => 5,
            'final_amount' => 105,
            'trx' => 'TEST_TRX_124',
            'status' => Status::PAYMENT_INITIATE,
            'gateway_currency' => 'USD',
            'method_currency' => 'USD',
        ]);

        // Mock IPN data with wrong credentials
        $ipnData = [
            'order_id' => 'TEST_TRX_124',
            'ipn_key' => 'wrong_key',
            'web_id' => 'wrong_web_id',
            'status' => 1,
            'currency' => 'USD',
        ];

        $response = $this->post(route('ipn.cashmaal'), $ipnData);

        // Should redirect to failed URL
        $response->assertRedirect();
        $deposit->refresh();
        $this->assertEquals(Status::PAYMENT_INITIATE, $deposit->status);
    }

    /** @test */
    public function perfectmoney_ipn_validates_required_fields()
    {
        $response = $this->post(route('ipn.perfectmoney'), []);

        $response->assertSessionHasErrors([
            'PAYMENT_ID', 'PAYEE_ACCOUNT', 'PAYMENT_AMOUNT', 'PAYMENT_UNITS',
            'PAYMENT_BATCH_NUM', 'PAYER_ACCOUNT', 'V2_HASH', 'TIMESTAMPGMT'
        ]);
    }

    /** @test */
    public function skrill_ipn_validates_required_fields()
    {
        $response = $this->post(route('ipn.skrill'), []);

        $response->assertSessionHasErrors([
            'transaction_id', 'merchant_id', 'mb_amount', 'mb_currency',
            'status', 'md5sig', 'pay_to_email'
        ]);
    }

    /** @test */
    public function paypal_ipn_handles_missing_fields()
    {
        // PayPal IPN uses raw POST data, so it should handle missing custom/mc_gross
        $response = $this->withHeaders([
            'Content-Type' => 'application/x-www-form-urlencoded',
        ])->post(route('ipn.paypal'), http_build_query([
            'other_field' => 'value',
        ]));

        // Should handle gracefully (400 error or validation)
        $this->assertTrue(in_array($response->getStatusCode(), [400, 422]));
    }

    /** @test */
    public function paytm_ipn_validates_required_fields()
    {
        $response = $this->post(route('ipn.paytm'), []);

        $response->assertSessionHasErrors([
            'ORDERID', 'CHECKSUMHASH', 'RESPCODE', 'TXNAMOUNT', 'RESPMSG'
        ]);
    }

    /** @test */
    public function nmi_ipn_validates_required_fields()
    {
        $response = $this->get(route('ipn.nmi'));

        // Should fail validation for missing token-id
        $response->assertStatus(302); // Redirect due to validation error
    }

    /** @test */
    public function instamojo_ipn_validates_required_fields()
    {
        $response = $this->post(route('ipn.instamojo'), []);

        $response->assertSessionHasErrors([
            'payment_request_id', 'mac', 'status'
        ]);
    }

    /** @test */
    public function coingate_ipn_validates_required_fields()
    {
        $response = $this->post(route('ipn.coingate'), []);

        $response->assertStatus(302); // Validation error redirect
    }

    /** @test */
    public function sslcommerz_ipn_validates_required_fields()
    {
        $response = $this->post(route('ipn.sslcommerz'), []);

        $response->assertSessionHasErrors([
            'tran_id', 'status', 'verify_sign', 'verify_key'
        ]);
    }

    /** @test */
    public function deposit_uses_fillable_protection()
    {
        // Attempt mass assignment with sensitive fields
        $deposit = Deposit::create([
            'user_id' => $this->user->id,
            'method' => $this->gateway->id,
            'amount' => 100,
            'status' => Status::PAYMENT_SUCCESS, // Should not be mass assignable
        ]);

        // Status should not be set via mass assignment
        $this->assertEquals(Status::PAYMENT_INITIATE, $deposit->fresh()->status);
    }

    /** @test */
    public function transaction_uses_fillable_protection()
    {
        // Attempt to create transaction with protected field
        $transaction = \App\Models\Transaction::create([
            'user_id' => $this->user->id,
            'amount' => 100,
            'post_balance' => 900,
            'id' => 999999, // Protected field
        ]);

        // ID should be auto-generated, not user-provided
        $this->assertNotEquals(999999, $transaction->fresh()->id);
    }

    /** @test */
    public function user_uses_fillable_protection()
    {
        // Attempt to mass assign protected fields
        $user = User::create([
            'firstname' => 'Test',
            'lastname' => 'User',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'password',
            'is_admin' => true, // Protected field
        ]);

        // is_admin should not be set
        $this->assertFalse($user->fresh()->is_admin ?? false);
    }
}
