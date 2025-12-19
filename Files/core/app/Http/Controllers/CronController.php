<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\User;
use App\Models\CronJob;
use App\Lib\CurlRequest;
use App\Constants\Status;
use App\Models\UserExtra;
use App\Models\CronJobLog;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;

/**
 * CronController - Handles scheduled cron job execution
 *
 * Manages matching bonus calculations and BV (Business Volume) processing
 */
class CronController extends Controller
{
    /** @var int Carry flash mode: deduct only paid BV */
    private const CARRY_FLASH_DEDUCT_PAID = 0;

    /** @var int Carry flash mode: deduct weak side completely */
    private const CARRY_FLASH_DEDUCT_WEAK = 1;

    /** @var int Carry flash mode: flush all BV */
    private const CARRY_FLASH_FLUSH_ALL = 2;

    /** @var int BV position: left side */
    private const BV_POSITION_LEFT = 1;

    /** @var int BV position: right side */
    private const BV_POSITION_RIGHT = 2;

    /** @var int Zero charge for transactions */
    private const ZERO_CHARGE = 0;

    /** @var int Zero BV value */
    private const ZERO_BV = 0;

    /**
     * Execute scheduled cron jobs
     *
     * @return RedirectResponse|null
     */
    public function cron(): ?RedirectResponse
    {
        $general            = gs();
        $general->last_cron = now();
        $general->save();
        
        $crons = CronJob::with('schedule');

        if (request()->alias) {
            $crons->where('alias', request()->alias);
        } else {
            $crons->where('next_run', '<', now())->where('is_running', Status::YES);
        }
        $crons = $crons->get();
        foreach ($crons as $cron) {
            $cronLog              = new CronJobLog();
            $cronLog->cron_job_id = $cron->id;
            $cronLog->start_at    = now();
            if ($cron->is_default) {
                $controller = new $cron->action[0];
                try {
                    $method = $cron->action[1];
                    $controller->$method();
                } catch (\Exception $e) {
                    $cronLog->error = $e->getMessage();
                }
            } else {
                try {
                    CurlRequest::curlContent($cron->url);
                } catch (\Exception $e) {
                    $cronLog->error = $e->getMessage();
                }
            }
            $cron->last_run = now();
            $cron->next_run = now()->addSeconds($cron->schedule->interval);
            $cron->save();

            $cronLog->end_at = $cron->last_run;

            $startTime         = Carbon::parse($cronLog->start_at);
            $endTime           = Carbon::parse($cronLog->end_at);
            $diffInSeconds     = $startTime->diffInSeconds($endTime);
            $cronLog->duration = $diffInSeconds;
            $cronLog->save();
        }
        if (request()->target == 'all') {
            $notify[] = ['success', 'Cron executed successfully'];
            return back()->withNotify($notify);
        }
        if (request()->alias) {
            $notify[] = ['success', keyToTitle(request()->alias) . ' executed successfully'];
            return back()->withNotify($notify);
        }
    }


    /**
     * Calculate and distribute matching bonuses
     *
     * @return string Status message
     */
    private function matchingBound(): string
    { 
        $generalSetting = gs();
        if ($generalSetting->matching_bonus_time == 'daily') {
            $day = Date('H');
            if (strtolower($day) != $generalSetting->matching_when) {
                return '1';
            }
        }
      

        if ($generalSetting->matching_bonus_time == 'weekly') {
            $day = Date('D');
            if (strtolower($day) != $generalSetting->matching_when) {
                return '2';
            }
        }

        if ($generalSetting->matching_bonus_time == 'monthly') {
            $day = Date('d');
            if (strtolower($day) != $generalSetting->matching_when) {
                return '3';
            }
        }
       
     
        if (Carbon::now()->toDateString() > Carbon::parse($generalSetting->last_paid)->toDateString()) {
            $generalSetting->last_paid = Carbon::now()->toDateString();
            $generalSetting->save();

            $eligibleUsers = UserExtra::where('bv_left', '>=', $generalSetting->total_bv)->where('bv_right', '>=', $generalSetting->total_bv)->get();
            foreach ($eligibleUsers as $uex) {
                $weak = $uex->bv_left < $uex->bv_right ? $uex->bv_left : $uex->bv_right;
                $weaker = $weak < $generalSetting->max_bv ? $weak : $generalSetting->max_bv;

                $pair = intval($weaker / $generalSetting->total_bv);

                $bonus = $pair * $generalSetting->bv_price;

                $payment = User::find($uex->user_id);
                $payment->balance += $bonus;
                $payment->save();

                $user = $payment;

                $trx = new Transaction();
                $trx->user_id = $payment->id;
                $trx->amount = $bonus;
                $trx->charge = self::ZERO_CHARGE;
                $trx->trx_type = '+';
                $trx->post_balance = $payment->balance;
                $trx->remark = 'binary_commission';
                $trx->trx = getTrx();
                $trx->details = 'Paid ' . showAmount($bonus) . ' For ' . $pair * $generalSetting->total_bv . ' BV.';
                $trx->save();

                notify($user, 'MATCHING_BONUS', [
                    'amount' => showAmount($bonus,currencyFormat:false),
                    'paid_bv' => $pair * $generalSetting->total_bv,
                    'post_balance' => showAmount($payment->balance,currencyFormat:false),
                    'trx' =>  $trx->trx,
                ]);

                $paidbv = $pair * $generalSetting->total_bv;
                if ($generalSetting->cary_flash == self::CARRY_FLASH_DEDUCT_PAID) {
                    $bv['setl'] = $uex->bv_left - $paidbv;
                    $bv['setr'] = $uex->bv_right - $paidbv;
                    $bv['paid'] = $paidbv;
                    $bv['lostl'] = self::ZERO_BV;
                    $bv['lostr'] = self::ZERO_BV;
                }
                if ($generalSetting->cary_flash == self::CARRY_FLASH_DEDUCT_WEAK) {
                    $bv['setl'] = $uex->bv_left - $weak;
                    $bv['setr'] = $uex->bv_right - $weak;
                    $bv['paid'] = $paidbv;
                    $bv['lostl'] = $weak - $paidbv;
                    $bv['lostr'] = $weak - $paidbv;
                }
                if ($generalSetting->cary_flash == self::CARRY_FLASH_FLUSH_ALL) {
                    $bv['setl'] = self::ZERO_BV;
                    $bv['setr'] = self::ZERO_BV;
                    $bv['paid'] = $paidbv;
                    $bv['lostl'] = $uex->bv_left - $paidbv;
                    $bv['lostr'] = $uex->bv_right - $paidbv;
                }
                $uex->bv_left = $bv['setl'];
                $uex->bv_right = $bv['setr'];
                $uex->save();


                if ($bv['paid'] != self::ZERO_BV) {
                    createBVLog($user->id, self::BV_POSITION_LEFT, $bv['paid'], 'Paid ' . $bonus . ' ' . $generalSetting->cur_text . ' For ' . $paidbv . ' BV.');
                    createBVLog($user->id, self::BV_POSITION_RIGHT, $bv['paid'], 'Paid ' . $bonus . ' ' . $generalSetting->cur_text . ' For ' . $paidbv . ' BV.');
                }
                if ($bv['lostl'] != self::ZERO_BV) {
                    createBVLog($user->id, self::BV_POSITION_LEFT, $bv['lostl'], 'Flush ' . $bv['lostl'] . ' BV after Paid ' . $bonus . ' ' . $generalSetting->cur_text . ' For ' . $paidbv . ' BV.');
                }
                if ($bv['lostr'] != self::ZERO_BV) {
                    createBVLog($user->id, self::BV_POSITION_RIGHT, $bv['lostr'], 'Flush ' . $bv['lostr'] . ' BV after Paid ' . $bonus . ' ' . $generalSetting->cur_text . ' For ' . $paidbv . ' BV.');
                }
            }
            return '---';
        }
    }
}
