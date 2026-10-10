<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\Settings;

use PHPUnit\Framework\TestCase;
use ScottOffen\WpGithubUpdater\Settings\Notice;

final class NoticeTest extends TestCase
{
    public function testTheListHasEveryCodeOnce(): void
    {
        $all = Notice::all();

        $this->assertSame(['saved', 'removed', 'checked', 'empty', 'invalid', 'locked', 'expired'], $all);
        $this->assertSame($all, array_values(array_unique($all)));
    }

    public function testOnlyTheCodesOnTheListAreKnown(): void
    {
        foreach (Notice::all() as $code) {
            $this->assertTrue(Notice::isKnown($code), $code);
        }

        foreach (['', 'unknown', 'SAVED', 'saved ', ' saved', '<script>', 'saved&x=1', 'checked,removed'] as $code) {
            $this->assertFalse(Notice::isKnown($code), var_export($code, true));
        }
    }

    public function testSavedRemovedAndCheckedReportSuccessAndTheRestReportAProblem(): void
    {
        foreach ([Notice::SAVED, Notice::REMOVED, Notice::CHECKED] as $code) {
            $this->assertTrue(Notice::isSuccess($code), $code);
        }

        foreach ([Notice::EMPTY, Notice::INVALID, Notice::LOCKED, Notice::EXPIRED, 'unknown', ''] as $code) {
            $this->assertFalse(Notice::isSuccess($code), $code);
        }
    }
}
