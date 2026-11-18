<?php

/**
 * Tantou (担当) — Human Capacity OS
 *
 * @author    Konstantinos Tsourdinis / HELLENIC WEB3 STUDIO
 * @copyright 2026 Konstantinos Tsourdinis / HELLENIC WEB3 STUDIO
 * @license   AGPL-3.0-or-later OR LicenseRef-Tantou-Commercial
 *
 * @link      https://github.com/ktsourdinis/tantou-oss
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later OR LicenseRef-Tantou-Commercial
 */

namespace App\Support;

class Timezone
{
    /**
     * Common grouped IANA timezones supported across Tantou.
     *
     * @var array<string, string>
     */
    public const COMMON = [
        'Europe/Athens' => 'Athens (UTC+2 / UTC+3)',
        'UTC' => 'UTC (Coordinated Universal Time)',
        'Europe/London' => 'London (UTC+0 / UTC+1)',
        'Europe/Berlin' => 'Berlin / Paris / Rome (UTC+1 / UTC+2)',
        'Europe/Nicosia' => 'Nicosia (UTC+2 / UTC+3)',
        'Europe/Helsinki' => 'Helsinki / Sofia / Bucharest (UTC+2 / UTC+3)',
        'America/New_York' => 'New York / Eastern Time (UTC-5 / UTC-4)',
        'America/Chicago' => 'Chicago / Central Time (UTC-6 / UTC-5)',
        'America/Denver' => 'Denver / Mountain Time (UTC-7 / UTC-6)',
        'America/Los_Angeles' => 'Los Angeles / Pacific Time (UTC-8 / UTC-7)',
        'America/Sao_Paulo' => 'São Paulo (UTC-3)',
        'Asia/Dubai' => 'Dubai (UTC+4)',
        'Asia/Tokyo' => 'Tokyo (UTC+9)',
        'Asia/Singapore' => 'Singapore (UTC+8)',
        'Australia/Sydney' => 'Sydney (UTC+10 / UTC+11)',
    ];

    /**
     * Get all common timezones.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        return self::COMMON;
    }
}
