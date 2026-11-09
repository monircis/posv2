<?php

namespace App\Models;

/** Settings > Users & security. One row: use SecuritySetting::current(). */
class SecuritySetting extends SingleRowSetting
{
    protected $table = 'security_settings';

    protected $fillable = [
        'session_timeout', 'two_step_admins', 'pos_pin_sign_in',
    ];

    protected function casts(): array
    {
        return [
            'two_step_admins' => 'boolean',
            'pos_pin_sign_in' => 'boolean',
        ];
    }
}
