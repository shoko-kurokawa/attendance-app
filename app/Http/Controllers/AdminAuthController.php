<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminLoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminAuthController extends Controller
{
    public function create(): View
    {
        return view('admin.admin-login');
    }

    public function store(AdminLoginRequest $request): RedirectResponse
    {
        $credentials = [
            'email' => $request->email,
            'password' => $request->password,
            'admin_status' => true,
        ];

        if (!Auth::attempt($credentials)) {
            return back()
                ->withErrors([
                    'email' => 'ログイン情報が登録されていません',
                ])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect('/admin/attendance/list');
    }
}
