<?php

namespace App\Domain\Retailer\Controllers;

use App\Domain\Retailer\Support\ActiveStoreResolver;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ActiveStoreController extends Controller
{
    public function update(Request $request)
    {
        $request->validate([
            'store_id' => 'required|integer',
        ]);

        $user = Auth::guard('retailer')->user();

        if ($user) {
            ActiveStoreResolver::setActiveStoreId($user, (int) $request->store_id);
        }

        return back();
    }
}
