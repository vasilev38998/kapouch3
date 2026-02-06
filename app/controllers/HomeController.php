<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Lib\Auth;

class HomeController {
    public function index(): void {
        view('home/index', ['user' => Auth::user()]);
    }

    public function menu(): void {
        $items = [];
        try {
            $items = \App\Lib\Db::pdo()->query('SELECT id,name,price,description,image_url FROM menu_items WHERE is_active=1 ORDER BY sort_order ASC, id DESC')->fetchAll();
        } catch (\Throwable) {
            $items = [];
        }
        view('menu/index', ['items' => $items, 'user' => Auth::user()]);
    }
}
