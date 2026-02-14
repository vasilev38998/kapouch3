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
        $modifierGroups = [];
        $modifiers = [];
        try {
            $pdo = \App\Lib\Db::pdo();
            $items = $pdo->query("SELECT id,name,category,price,description,image_url,is_sold_out FROM menu_items WHERE is_active=1 ORDER BY category ASC, sort_order ASC, id DESC")->fetchAll();

            $itemIds = array_map(static fn($row): int => (int)$row['id'], $items ?: []);
            if (!empty($itemIds)) {
                $placeholders = implode(',', array_fill(0, count($itemIds), '?'));

                $stmtG = $pdo->prepare("SELECT id,menu_item_id,name,selection_mode,is_required,sort_order FROM menu_item_modifier_groups WHERE is_active=1 AND menu_item_id IN ($placeholders) ORDER BY sort_order ASC, id ASC");
                $stmtG->execute($itemIds);
                $modifierGroups = $stmtG->fetchAll() ?: [];

                $groupIds = array_map(static fn($row): int => (int)$row['id'], $modifierGroups ?: []);
                if (!empty($groupIds)) {
                    $gph = implode(',', array_fill(0, count($groupIds), '?'));
                    $stmtM = $pdo->prepare("SELECT id,group_id,name,price_delta,is_sold_out,sort_order FROM menu_item_modifiers WHERE is_active=1 AND group_id IN ($gph) ORDER BY sort_order ASC, id ASC");
                    $stmtM->execute($groupIds);
                    $modifiers = $stmtM->fetchAll() ?: [];
                }
            }
        } catch (\Throwable) {
            $items = [];
            $modifierGroups = [];
            $modifiers = [];
        }
        view('menu/index', [
            'items' => $items,
            'modifierGroups' => $modifierGroups,
            'modifiers' => $modifiers,
            'user' => Auth::user(),
        ]);
    }
}
