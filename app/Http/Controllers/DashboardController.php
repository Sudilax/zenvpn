<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Show the authenticated user's dashboard.
     */
    public function index(Request $request)
    {
        $user    = $request->user()->load('vpnDevices');
        $devices = $user->vpnDevices()->latest('created_at')->get();

        return view('dashboard', compact('user', 'devices'));
    }
}
