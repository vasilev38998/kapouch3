<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Lib\Auth;
use App\Lib\Db;

class ReferralController {
    public function capture(string $refCode): void {
        $stmt = Db::pdo()->prepare('SELECT id FROM users WHERE ref_code=?');
        $stmt->execute([strtoupper($refCode)]);
        $id = $stmt->fetchColumn();
        if ($id) {
            $_SESSION['ref_user_id'] = (int)$id;
        }

        if (Auth::user()) {
            redirect('/profile/invite');
        }
        redirect('/auth');
    }
}
