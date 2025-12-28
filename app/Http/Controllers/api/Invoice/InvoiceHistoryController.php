<?php

namespace App\Http\Controllers\api\Invoice;

use App\Http\Controllers\Controller;
use App\Models\PaymentJobs;
use Illuminate\Http\Request;

class InvoiceHistoryController extends Controller
{
    public function index(Request $request){
        $user = $request->user();
        $data = PaymentJobs::where('user_id', $user->id)->orderBy('id', 'DESC')->paginate(10);
        return response()->json([
            'status' => true,
            'data' => $data->items(),
            'pagination' => [
                'total' => $data->total(),
                'per_page' => $data->perPage(),
                'current_page' => $data->currentPage(),
                'last_page' => $data->lastPage(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem()
            ]

        ]);
    }
}
