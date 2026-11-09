<?php

namespace App\Models;

/** Settings > Loyalty coins. One row: use CoinSetting::current(). */
class CoinSetting extends SingleRowSetting
{
    protected $table = 'coin_settings';

    protected $fillable = [
        'enabled', 'earn_per_dollar', 'coin_value', 'min_redeem', 'max_redeem_percent',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'earn_per_dollar' => 'float',
            'coin_value' => 'float',
            'min_redeem' => 'integer',
            'max_redeem_percent' => 'integer',
        ];
    }

    /** Coins earned on this much goods, e.g. $25.40 at 1 coin per $1 = 25. */
    public function earned(float $goods): int
    {
        return $this->enabled ? (int) floor(max(0, $goods) * $this->earn_per_dollar + 0.000001) : 0;
    }

    /** The most coins that may be spent on this much goods. */
    public function mostCoins(float $goods): int
    {
        if ($this->coin_value <= 0) {
            return 0;
        }

        return (int) floor($goods * $this->max_redeem_percent / 100 / $this->coin_value + 0.000001);
    }
}
