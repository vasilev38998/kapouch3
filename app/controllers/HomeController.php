<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Lib\Auth;

class HomeController {
    public function index(): void {
        view('home/index', ['user' => Auth::user()]);
    }
}
