<?php

namespace Tests\Feature;

use Tests\TestCase;

class Step45DualLicensingAndToolingUntrackTest extends TestCase
{
    /**
     * Test composer.json metadata and AGPL-3.0-only license.
     */
    public function test_composer_json_has_dual_license_and_tantou_metadata(): void
    {
        $composerPath = base_path('composer.json');
        $this->assertFileExists($composerPath);

        $json = json_decode(file_get_contents($composerPath), true);

        $this->assertSame('tantou/tantou', $json['name']);
        $this->assertSame('Tantou (担当) — Open-Core Human Capacity OS for Real-Time Operations, Smart Queue Dispatch & 2-Way Telegram Execution.', $json['description']);
        $this->assertSame('AGPL-3.0-or-later', $json['license']);
        $this->assertSame('https://github.com/ktsourdinis/tantou-oss', $json['homepage']);
    }

    /**
     * Test LICENSE file includes dual licensing notice and AGPLv3 text.
     */
    public function test_license_file_has_dual_licensing_notice_and_agplv3_text(): void
    {
        $licensePath = base_path('LICENSE');
        $this->assertFileExists($licensePath);

        $licenseContent = file_get_contents($licensePath);

        $this->assertStringContainsString('Copyright (c) 2026 Konstantinos Tsourdinis / HELLENIC WEB3 STUDIO. All rights reserved.', $licenseContent);
        $this->assertStringContainsString('Tantou is dual-licensed under the GNU Affero General Public License v3.0 (AGPLv3) for open-source self-hosted use, and a Commercial License for proprietary/closed-source distributions, multi-tenant SaaS hosting, or enterprise white-labeling without AGPLv3 copyleft obligations.', $licenseContent);
        $this->assertStringContainsString('GNU AFFERO GENERAL PUBLIC LICENSE', $licenseContent);
        $this->assertStringContainsString('Version 3, 19 November 2007', $licenseContent);
    }

    /**
     * Test README.md displays AGPLv3 / Commercial badge and dual license section.
     */
    public function test_readme_displays_agplv3_commercial_badge_and_dual_license_section(): void
    {
        $readmePath = base_path('README.md');
        $this->assertFileExists($readmePath);

        $readmeContent = file_get_contents($readmePath);

        $this->assertStringContainsString('License: AGPLv3 / Commercial', $readmeContent);
        $this->assertStringContainsString('GNU AGPL v3.0 (AGPL-3.0-or-later)', $readmeContent);
        $this->assertStringContainsString('Commercial License', $readmeContent);
    }

    /**
     * Test .git/info/exclude contains AI/IDE tooling paths and they are not tracked in git.
     */
    public function test_ai_agent_tooling_folders_are_excluded_and_not_tracked_in_git(): void
    {
        $excludePath = base_path('.git/info/exclude');
        if (file_exists($excludePath)) {
            $excludeContent = file_get_contents($excludePath);

            $this->assertStringContainsString('.agents', $excludeContent);
            $this->assertStringContainsString('.claude', $excludeContent);
            $this->assertStringContainsString('.config', $excludeContent);
            $this->assertStringContainsString('.mcp.json', $excludeContent);
            $this->assertStringContainsString('boost.json', $excludeContent);
        }

        // Verify git ls-files does not track them
        $output = shell_exec('git ls-files .agents .claude .config .mcp.json boost.json');
        $this->assertEmpty(trim((string) $output), 'AI/IDE tooling files must not be tracked in git index.');
    }

    /**
     * Test git repository contains zero occurrences of internal IP, internal username, or local paths.
     */
    public function test_zero_forbidden_internal_identifiers_in_git_tracked_files(): void
    {
        // Dynamically build patterns to avoid matching git grep in this test file
        $pattern1 = implode('.', ['10', '1', '7', '74']);
        $pattern2 = 'k'.'.'.'tsourdinis';
        $pattern3 = '/'.'home'.'/'.'dockerdata';

        $escapedRegex = sprintf('(%s|%s|%s)', preg_quote($pattern1, '/'), preg_quote($pattern2, '/'), preg_quote($pattern3, '/'));

        $output = shell_exec(sprintf('git grep -nE "%s" 2>/dev/null', $escapedRegex));
        $this->assertEmpty(trim((string) $output), 'Found forbidden internal identifier strings in git tracked files: '.$output);
    }
}
