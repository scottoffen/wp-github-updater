<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\Update;

use PHPUnit\Framework\TestCase;
use ScottOffen\WpGithubUpdater\Release\Outcome;
use ScottOffen\WpGithubUpdater\Release\Release;
use ScottOffen\WpGithubUpdater\Update\ReleaseState;

final class ReleaseStateTest extends TestCase
{
    private function release(): Release
    {
        return new Release(
            'v1.2.3',
            '1.2.3',
            'Some notes',
            '2026-10-01T12:00:00Z',
            'https://github.com/owner/repo/releases/tag/v1.2.3',
            42,
            'my-plugin-1.2.3.zip',
            'https://api.github.com/repos/owner/repo/releases/assets/42',
            2048,
            'sha256:abc123',
        );
    }

    /** @return array<string, mixed> */
    private function stored(): array
    {
        return (new ReleaseState(1_800_000_000, Outcome::OK, 200, 'W/"abc"', $this->release()))->toArray();
    }

    public function testTheEmptyStateHasNoCheckAndNoRelease(): void
    {
        $state = ReleaseState::empty();

        $this->assertFalse($state->hasChecked());
        $this->assertFalse($state->succeeded());
        $this->assertSame(0, $state->checkedAt);
        $this->assertSame('', $state->outcome);
        $this->assertSame('', $state->etag);
        $this->assertNull($state->release);
    }

    public function testAStateWithAReleaseSurvivesAnArrayRoundTrip(): void
    {
        $state = new ReleaseState(1_800_000_000, Outcome::OK, 200, 'W/"abc"', $this->release());

        $copy = ReleaseState::fromArray($state->toArray());

        $this->assertNotNull($copy);
        $this->assertSame($state->toArray(), $copy->toArray());
        $this->assertTrue($copy->hasChecked());
        $this->assertTrue($copy->succeeded());
        $this->assertSame('1.2.3', $copy->release?->version);
    }

    public function testAStateWithoutAReleaseSurvivesAnArrayRoundTrip(): void
    {
        $state = new ReleaseState(1_800_000_000, Outcome::NETWORK, 0);

        $copy = ReleaseState::fromArray($state->toArray());

        $this->assertNotNull($copy);
        $this->assertNull($copy->release);
        $this->assertFalse($copy->succeeded());
        $this->assertSame(Outcome::NETWORK, $copy->outcome);
    }

    public function testOnlyAnOkOutcomeCountsAsSucceeded(): void
    {
        foreach ([Outcome::NOT_MODIFIED, Outcome::NO_RELEASE, Outcome::ASSET_MISMATCH, Outcome::AUTH, Outcome::NETWORK, ''] as $outcome) {
            $this->assertFalse((new ReleaseState(1, $outcome))->succeeded(), $outcome);
        }

        $this->assertTrue((new ReleaseState(1, Outcome::OK))->succeeded());
    }

    public function testTheStoredArrayHoldsOnlyPlainValues(): void
    {
        $this->assertSame([], $this->objectsIn($this->stored()));
    }

    /**
     * @param array<mixed> $data
     *
     * @return list<string>
     */
    private function objectsIn(array $data): array
    {
        $found = [];

        array_walk_recursive($data, static function (mixed $value) use (&$found): void {
            if (is_object($value)) {
                $found[] = $value::class;
            }
        });

        return $found;
    }

    public function testAnOutcomeThisVersionDoesNotKnowIsKept(): void
    {
        $data = $this->stored();
        $data['outcome'] = 'a_code_from_a_newer_version';

        $state = ReleaseState::fromArray($data);

        $this->assertNotNull($state);
        $this->assertSame('a_code_from_a_newer_version', $state->outcome);
    }

    public function testExtraKeysAreIgnored(): void
    {
        $data = $this->stored();
        $data['added_by_a_newer_version'] = true;

        $this->assertNotNull(ReleaseState::fromArray($data));
    }

    public function testAnythingThatIsNotAnArrayIsUnreadable(): void
    {
        foreach ([null, false, '', 'state', 7, new \stdClass()] as $value) {
            $this->assertNull(ReleaseState::fromArray($value), var_export($value, true));
        }
    }

    public function testAnotherSchemaVersionIsUnreadable(): void
    {
        foreach ([0, 2, '1', null, 1.0] as $schema) {
            $data = $this->stored();
            $data['v'] = $schema;

            $this->assertNull(ReleaseState::fromArray($data), var_export($schema, true));
        }

        $data = $this->stored();
        unset($data['v']);

        $this->assertNull(ReleaseState::fromArray($data));
    }

    public function testAMissingKeyIsUnreadable(): void
    {
        foreach (['checked_at', 'outcome', 'status', 'etag', 'release'] as $key) {
            $data = $this->stored();
            unset($data[$key]);

            $this->assertNull(ReleaseState::fromArray($data), $key);
        }
    }

    public function testAKeyOfTheWrongTypeOrRangeIsUnreadable(): void
    {
        $wrong = [
            'checked_at' => '1800000000',
            'outcome' => 5,
            'status' => '200',
            'etag' => ['a'],
            'release' => 'v1.2.3',
        ];

        foreach ($wrong as $key => $value) {
            $data = $this->stored();
            $data[$key] = $value;

            $this->assertNull(ReleaseState::fromArray($data), $key);
        }

        foreach (['checked_at', 'status'] as $key) {
            $data = $this->stored();
            $data[$key] = -1;

            $this->assertNull(ReleaseState::fromArray($data), $key . ' below zero');
        }
    }

    public function testAReleaseThatCannotBeReadMakesTheStateUnreadable(): void
    {
        $data = $this->stored();
        $data['release'] = ['tag' => 'v1.2.3'];

        $this->assertNull(ReleaseState::fromArray($data));
    }
}
