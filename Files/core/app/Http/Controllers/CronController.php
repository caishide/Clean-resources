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

class CronController extends Controller
{
    public function cron()
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


    private function matchingBound()
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
                $trx->charge = 0;
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
                if ($generalSetting->cary_flash == 0) {
                    $bv['setl'] = $uex->bv_left - $paidbv;
                    $bv['setr'] = $uex->bv_right - $paidbv;
                    $bv['paid'] = $paidbv;
                    $bv['lostl'] = 0;
                    $bv['lostr'] = 0;
                }
                if ($generalSetting->cary_flash == 1) {
                    $bv['setl'] = $uex->bv_left - $weak;
                    $bv['setr'] = $uex->bv_right - $weak;
                    $bv['paid'] = $paidbv;
                    $bv['lostl'] = $weak - $paidbv;
                    $bv['lostr'] = $weak - $paidbv;
                }
                if ($generalSetting->cary_flash == 2) {
                    $bv['setl'] = 0;
                    $bv['setr'] = 0;
                    $bv['paid'] = $paidbv;
                    $bv['lostl'] = $uex->bv_left - $paidbv;
                    $bv['lostr'] = $uex->bv_right - $paidbv;
                }
                $uex->bv_left = $bv['setl'];
                $uex->bv_right = $bv['setr'];
                $uex->save();


                if ($bv['paid'] != 0) {
                    createBVLog($user->id, 1, $bv['paid'], 'Paid ' . $bonus . ' ' . $generalSetting->cur_text . ' For ' . $paidbv . ' BV.');
                    createBVLog($user->id, 2, $bv['paid'], 'Paid ' . $bonus . ' ' . $generalSetting->cur_text . ' For ' . $paidbv . ' BV.');
                }
                if ($bv['lostl'] != 0) {
                    createBVLog($user->id, 1, $bv['lostl'], 'Flush ' . $bv['lostl'] . ' BV after Paid ' . $bonus . ' ' . $generalSetting->cur_text . ' For ' . $paidbv . ' BV.');
                }
                if ($bv['lostr'] != 0) {
                    createBVLog($user->id, 2, $bv['lostr'], 'Flush ' . $bv['lostr'] . ' BV after Paid ' . $bonus . ' ' . $generalSetting->cur_text . ' For ' . $paidbv . ' BV.');
                }
            }
            return '---';
        }
    }
}
