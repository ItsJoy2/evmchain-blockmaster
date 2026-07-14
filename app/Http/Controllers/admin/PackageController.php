<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Package;
use Illuminate\Support\Facades\Validator;

class PackageController extends Controller
{
    public function index()
    {
        $packages = Package::latest()->paginate(10);
        return view('admin.pages.packages.index', compact('packages'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'              => 'required|string|max:255',
            'price'             => 'required|numeric|min:0',
            'transaction_limit' => 'required|integer|min:1',
            'duration'          => 'required|integer|min:1',
            'status'            => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }

        $package = Package::create([
            'name'              => $request->name,
            'price'             => $request->price,
            'transaction_limit' => $request->transaction_limit,
            'duration'          => $request->duration,
            'status'            => $request->status,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Package created successfully.',
            'package' => $package
        ]);
    }

    public function update(Request $request, $id)
    {
        $package = Package::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name'              => 'required|string|max:255',
            'price'             => 'required|numeric|min:0',
            'transaction_limit' => 'required|integer|min:1',
            'duration'          => 'required|integer|min:1',
            'status'            => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }

        $package->update([
            'name'              => $request->name,
            'price'             => $request->price,
            'transaction_limit' => $request->transaction_limit,
            'duration'          => $request->duration,
            'status'            => $request->status,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Package updated successfully.',
            'package' => $package
        ]);
    }

    public function destroy($id)
    {
        $package = Package::findOrFail($id);
        $package->delete();

        return response()->json(['success' => true]);
    }
}
