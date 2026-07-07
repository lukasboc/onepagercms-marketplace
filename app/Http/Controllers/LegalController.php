<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class LegalController extends Controller
{
    public function legalNotice(): View
    {
        return view('legal.legal-notice', ['legal' => config('legal')]);
    }

    public function privacyPolicy(): View
    {
        return view('legal.privacy-policy', ['legal' => config('legal')]);
    }
}
