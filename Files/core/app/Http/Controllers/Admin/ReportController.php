<?php

namespace App\Http\Controllers\Admin;

use App\Models\BvLog;
use App\Models\UserLogin;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Models\NotificationLog;
use App\Http\Controllers\Controller;

class ReportController extends Controller
{
    public function transaction(Request $request, $userId = null)
    {
        $pageTitle = 'Transaction Logs';

        $remarks = Transaction::distinct('remark')->orderBy('remark')->get('remark');

        $transactions = Transaction::searchable(['trx', 'user:username'])->filter(['trx_type', 'remark'])->dateFilter()->orderBy('id', 'desc')->with('user');
        if ($userId) {
            $transactions = $transactions->where('user_id', $userId);
        }
        $transactions = $transactions->paginate(getPaginate());

        return view('admin.reports.transactions', compact('pageTitle', 'transactions', 'remarks'));
    }

    public function loginHistory(Request $request)
    {
        $pageTitle = 'User Login History';
        $loginLogs = UserLogin::orderBy('id', 'desc')->searchable(['user:username'])->dateFilter()->with('user')->paginate(getPaginate());
        return view('admin.reports.logins', compact('pageTitle', 'loginLogs'));
    }

    public function loginIpHistory($ip)
    {
        $pageTitle = 'Login by - ' . $ip;
        $loginLogs = UserLogin::where('user_ip', $ip)->orderBy('id', 'desc')->with('user')->paginate(getPaginate());
        return view('admin.reports.logins', compact('pageTitle', 'loginLogs', 'ip'));
    }

    public function notificationHistory(Request $request)
    {
        $pageTitle = 'Notification History';
        $logs      = NotificationLog::orderBy('id', 'desc')->searchable(['user:username'])->dateFilter()->with('user')->paginate(getPaginate());
        return view('admin.reports.notification_history', compact('pageTitle', 'logs'));
    }

    public function emailDetails($id)
    {
        $pageTitle = 'Email Details';
        $email     = NotificationLog::findOrFail($id);
        return view('admin.reports.email_details', compact('pageTitle', 'email'));
    }

    public function invest(Request $request, $userId = null)
    {
        $pageTitle    = 'Invest Logs';
        $transactions = Transaction::searchable(['trx', 'user:username'])->where('remark', 'purchased_plan')->with('user');
        if ($userId) {
            $transactions = $transactions->where('user_id', $userId);
        }
        $transactions = $transactions->latest()->paginate(getPaginate());

        return view('admin.reports.transactions', compact('pageTitle', 'transactions'));
    }

    public function bvLog(Request $request, $userId = null)
    {
        
        if ($request->type) {
            if ($request->type == 'leftBV') {
                $pageTitle = "Left BV";
            } elseif ($request->type == 'rightBV') {
                $pageTitle = "Right BV";
            } elseif ($request->type == 'cutBV') {
                $pageTitle = "Cut BV";
            } else {
                $pageTitle = "All Paid BV";
            }
            $logs = $this->bvData($request->type);
        } else {
            $pageTitle = "BV Log";
            $logs      = $this->bvData();
        }
        if ($userId) {
            $logs = $logs->where('user_id', $userId);
        }

        $logs = $logs->latest('id')->paginate(getPaginate());

        return view('admin.reports.bvLog', compact('pageTitle', 'logs'));
    }

    protected function bvData($scope = null)
    {
        if ($scope) {
            $logs = BvLog::$scope();
        } else {
            $logs = BvLog::query();
        }
        return $logs->searchable(['user:username']);
    }

    public function refCom(Request $request, $userId = null)
    {
        $pageTitle    = 'Referral Commission Logs';
        $transactions = Transaction::searchable(['trx', 'user:username'])->where('remark', 'referral_commission')->with('user');
        if ($userId) {
            $transactions = $transactions->where('user_id', $userId);
        }
        $transactions = $transactions->latest()->paginate(getPaginate());

        return view('admin.reports.transactions', compact('pageTitle', 'transactions'));
    }

    public function binaryCom(Request $request, $userId = null)
    {
        $pageTitle    = 'Binary Commission Logs';
        $transactions = Transaction::searchable(['trx', 'user:username'])->where('remark', 'binary_commission')->with('user');
        if ($userId) {
            $transactions = $transactions->where('user_id', $userId);
        }
        $transactions = $transactions->latest()->paginate(getPaginate());

        return view('admin.reports.transactions', compact('pageTitle', 'transactions'));
    }
}
