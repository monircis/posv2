<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Exception;

/**
 * Demo staff, one or more for every role. Change passwords in User Management after the first sign-in.
 * Accounts that already exist are left as they are.
 * Branches live in the POS backend, so their ids are looked up by code from POS GET /api/branches
 * (start POS first: php artisan serve --port=8001).
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        // [name, email, password, role, POS branch code (null = all branches), status, PIN]
        $staff = [
            ['Aminul Haque', 'aminul@madinah.com.au', 'owner123', 'Owner', null, 'active', '1001'],
            ['Sara Rahman', 'sara.rahman@madinah.com.au', 'manager123', 'Manager', 'main', 'active', '2001'],
            ['Tariq Hossain', 'tariq.h@madinah.com.au', 'manager123', 'Manager', 'bank', 'active', '2002'],
            ['Yusuf Karim', 'yusuf.k@madinah.com.au', 'cashier123', 'Cashier', 'main', 'active', '3001'],
            ['Fatima Noor', 'fatima.noor@madinah.com.au', 'cashier123', 'Cashier', 'bank', 'active', '3002'],
            ['Layla Ahmed', 'layla.ahmed@madinah.com.au', 'cashier123', 'Cashier', 'main', 'active', '3003'],
            ['Rashid Alam', 'rashid.alam@madinah.com.au', 'cashier123', 'Cashier', 'main', 'off', null],
            ['Imran Sheikh', 'imran@madinah.com.au', 'kitchen123', 'Kitchen', 'main', 'active', '4001'],
            ['Hamza Ali', 'hamza.ali@madinah.com.au', 'kitchen123', 'Kitchen', 'bank', 'active', '4002'],
            ['Nadia Islam', 'nadia.islam@madinah.com.au', 'accounts123', 'Accountant', null, 'active', null],
            ['Omar Siddiq', 'omar.siddiq@madinah.com.au', null, 'Stock keeper', 'cold', 'invited', null],
            ['Zainab Uddin', 'zainab.u@madinah.com.au', 'stock123', 'Stock keeper', 'cold', 'active', '5001'],
        ];

        $branchIds = $this->getPosBranchIds();

        foreach ($staff as [$name, $email, $password, $role, $branch, $status, $pin]) {
            if (User::where('email', $email)->exists()) {
                continue;
            }

            $branchId = null;
            if ($branch && isset($branchIds[$branch])) {
                $branchId = $branchIds[$branch];
            }

            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'status' => $status,
                'pin' => $pin,
                'branch_id' => $branchId,
            ]);
            $user->assignRole($role);
        }
    }

    /** Branch ids by code from the POS backend, e.g. ['bank' => 1, 'main' => 3]. Empty if POS is not running. */
    private function getPosBranchIds(): array
    {
        try {
            $response = Http::acceptJson()->timeout(5)->get(config('services.pos.url').'/branches');
        } catch (Exception $e) {
            $response = null;
        }

        if (! $response || $response->failed()) {
            $this->command->warn('POS backend is not running, so demo users get "all branches".');

            return [];
        }

        $ids = [];
        foreach ($response->json() as $branch) {
            $ids[$branch['code']] = $branch['id'];
        }

        return $ids;
    }
}
