<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Transactions;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    // 🟢 Show all transactions
    public function index(Request $request)
    {
        $query = Transactions::with('user')->orderBy('created_at', 'desc');

        if ($request->filled('username')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->username . '%');
            });
        }

        if ($request->filled('token')) {
            $query->where('token_name', 'like', '%' . $request->token . '%');
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $transactions = $query->paginate(20);

        return view('admin.pages.transactions.index', compact('transactions'));
    }


    // 🟢 Update transaction
    public function update(Request $request, $id)
    {
        $transaction = Transactions::findOrFail($id);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'status' => 'required|string',
            'trx_hash' => 'nullable|string|max:255',
            'token_name' => 'required|string|max:255',
            'type' => 'required|string|max:255',
        ]);

        $transaction->update($validated);

        return response()->json(['success' => true]);
    }
}
