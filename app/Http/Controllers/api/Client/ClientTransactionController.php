<?php

namespace App\Http\Controllers\api\Client;

use App\Http\Controllers\Controller;
use App\Models\Transactions;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ClientTransactionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Get filters from request
        $tokenName = $request->input('token_name');
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');

        // Start query
        $query = Transactions::where('user_id', $user->id);

        // ✅ Filter by token name (partial match)
        if (!empty($tokenName)) {
            $query->where('token_name', 'LIKE', "%{$tokenName}%");
        }

        // ✅ Filter by date range
        if (!empty($fromDate) && !empty($toDate)) {
            $query->whereBetween('created_at', [
                Carbon::parse($fromDate)->startOfDay(),
                Carbon::parse($toDate)->endOfDay(),
            ]);
        } elseif (!empty($fromDate)) {
            $query->whereDate('created_at', '>=', Carbon::parse($fromDate)->startOfDay());
        } elseif (!empty($toDate)) {
            $query->whereDate('created_at', '<=', Carbon::parse($toDate)->endOfDay());
        }

        // ✅ Pagination and sorting
        $transactions = $query->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(20);

        // ✅ Handle empty data
        if (!$transactions->count()) {
            return response()->json([
                'status' => false,
                'message' => 'No transactions found',
                'data' => [],
            ]);
        }

        // ✅ Return structured JSON
        return response()->json([
            'status' => true,
            'message' => 'Success',
            'data' => $transactions->items(),
            'total' => $transactions->total(),
            'last_page' => $transactions->lastPage(),
            'current_page' => $transactions->currentPage(),
            'from' => $transactions->firstItem(),
            'to' => $transactions->lastItem(),
            'per_page' => $transactions->perPage(),
        ]);
    }

}
