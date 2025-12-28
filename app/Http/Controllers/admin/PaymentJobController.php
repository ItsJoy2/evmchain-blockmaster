<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentJob;
use App\Models\User;
use Illuminate\Http\Request;

class PaymentJobController extends Controller
{
    public function index(Request $request)
    {
        $query = PaymentJob::with('user');

        // 🔍 Filter by username
        if ($request->filled('username')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->username . '%');
            });
        }

        // 🔍 Filter by token name
        if ($request->filled('token_name')) {
            $query->where('token_name', 'like', '%' . $request->token_name . '%');
        }

        // 🔍 Filter by date range
        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $paymentJobs = $query->orderBy('id', 'desc')->paginate(15);

        return view('admin.payment_jobs.index', compact('paymentJobs'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,processing,completed,expired',
            'tx_hash' => 'nullable|string',
        ]);

        $paymentJob = PaymentJob::findOrFail($id);
        $paymentJob->update([
            'tx_hash' => $request->tx_hash,
            'status' => $request->status,
        ]);

        return response()->json(['success' => true]);
    }
}
