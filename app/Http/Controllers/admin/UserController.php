<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Crypto;
use Illuminate\Http\Request;

class UserController extends Controller
{
    protected Crypto $crypto;
    public function __construct(Crypto $crypto){
        $this->crypto = $crypto;
    }
    /**
     * Display a listing of the resource.
     */

    public function index(Request $request)
    {
        $query = User::query();

        // 🔍 Search by email or wallet address
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                    ->orWhere('wallet_address', 'like', "%{$search}%");
            });
        }

        // 🧾 Paginate users
        $users = $query->orderBy('id', 'desc')->paginate(10);

        // Keep search query in pagination links
        $users->appends($request->only('search'));

        return view('admin.pages.users.index', compact('users'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'is_block' => 'required|boolean',
        ]);

        $user = User::findOrFail($id);
        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'is_block' => $request->is_block,
        ]);

        return response()->json(['success' => true]);
    }

    public function revealWalletKey(Request $request, User $user)
    {
        $reveal_code = env('APP_CODE');
        if ($reveal_code == $request['reveal_code']) {
            $walletKey = $this->crypto->decrypt($user->two_factor_secret);
            return response()->json([
                'success' => true,
                'wallet_key' => $walletKey,
            ]);
        }else{
            return response()->json([
                'success' => false,
                'message' => 'thanks for revealing wallet',
            ]);
        }

    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
