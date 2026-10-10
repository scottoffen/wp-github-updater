<?php

declare(strict_types=1);

namespace ScottOffen\WpGithubUpdater\Tests\Token;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ScottOffen\WpGithubUpdater\Internal\Token\TokenStore;
use ScottOffen\WpGithubUpdater\Tests\Support\FakeConstants;
use ScottOffen\WpGithubUpdater\Tests\Support\FakeOptionStore;

final class TokenStoreTest extends TestCase
{
    private const TOKEN = 'github_pat_ABCDEFGHIJ1234567890';

    private FakeOptionStore $options;

    private FakeConstants $constants;

    protected function setUp(): void
    {
        $this->options = new FakeOptionStore();
        $this->constants = new FakeConstants();
    }

    private function store(string $prefix = 'fdcrm'): TokenStore
    {
        return new TokenStore($this->options, $this->constants, $prefix);
    }

    public function testTheNamesComeFromThePrefix(): void
    {
        $store = $this->store('fdcrm');

        $this->assertSame('FDCRM_GITHUB_TOKEN', $store->constantName());
        $this->assertSame('fdcrm_updater_github_token', $store->optionName());
        $this->assertSame('MY_THEME_2_GITHUB_TOKEN', $this->store('my_theme_2')->constantName());
    }

    public function testAPrefixThatIsNotLowercaseLettersDigitsAndUnderscoresIsRejected(): void
    {
        foreach (['', 'FDCRM', 'Fdcrm', '1abc', '_abc', 'my-theme', 'my theme', 'a.b', "abc\n", 'abc;'] as $prefix) {
            try {
                $this->store($prefix);
                $this->fail('Accepted: ' . var_export($prefix, true));
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testWithNothingSetThereIsNoToken(): void
    {
        $store = $this->store();

        $this->assertNull($store->get());
        $this->assertSame(TokenStore::SOURCE_NONE, $store->source());
        $this->assertFalse($store->isLockedByConstant());
        $this->assertSame('', $store->masked());
    }

    public function testASavedTokenIsStoredInTheOptionWithAutoloadOff(): void
    {
        $store = $this->store();

        $this->assertTrue($store->save(self::TOKEN));

        $this->assertSame(self::TOKEN, $this->options->get('fdcrm_updater_github_token'));
        $this->assertFalse($this->options->autoloads('fdcrm_updater_github_token'));
        $this->assertSame(self::TOKEN, $store->get());
        $this->assertSame(TokenStore::SOURCE_SETTINGS, $store->source());
        $this->assertFalse($store->isLockedByConstant());
    }

    public function testSavingTrimsSurroundingWhitespace(): void
    {
        $store = $this->store();

        $this->assertTrue($store->save("  " . self::TOKEN . "\n"));
        $this->assertSame(self::TOKEN, $store->get());
    }

    public function testATokenThatIsEmptyOrUnsafeToSendInAHeaderIsNotSaved(): void
    {
        $store = $this->store();

        foreach (['', '   ', "abc def", "abc\ndef", "abc\r\nX-Evil: 1", "tab\there", "caf\u{e9}-token", "nul\0byte"] as $token) {
            $this->assertFalse($store->save($token), var_export($token, true));
        }

        $this->assertSame([], $this->options->names());
        $this->assertNull($store->get());
    }

    public function testAFailedSaveLeavesTheSavedTokenUnchanged(): void
    {
        $store = $this->store();
        $store->save(self::TOKEN);

        $this->assertFalse($store->save('bad token'));
        $this->assertSame(self::TOKEN, $store->get());
    }

    public function testAConstantTakesPrecedenceOverASavedToken(): void
    {
        $store = $this->store();
        $store->save(self::TOKEN);
        $this->constants->define('FDCRM_GITHUB_TOKEN', 'from-the-constant');

        $this->assertSame('from-the-constant', $store->get());
        $this->assertSame(TokenStore::SOURCE_CONSTANT, $store->source());
        $this->assertTrue($store->isLockedByConstant());
    }

    public function testAConstantWorksWithNothingSaved(): void
    {
        $this->constants->define('FDCRM_GITHUB_TOKEN', '  from-the-constant ');
        $store = $this->store();

        $this->assertSame('from-the-constant', $store->get());
        $this->assertSame(TokenStore::SOURCE_CONSTANT, $store->source());
    }

    public function testSavingAndClearingDoNothingWhileAConstantIsInControl(): void
    {
        $store = $this->store();
        $store->save(self::TOKEN);
        $this->constants->define('FDCRM_GITHUB_TOKEN', 'from-the-constant');

        $this->assertFalse($store->save('another-token-value'));
        $this->assertFalse($store->clear());
        $this->assertSame(self::TOKEN, $this->options->get('fdcrm_updater_github_token'));
    }

    public function testTheSavedTokenIsBackInControlWhenTheConstantGoesAway(): void
    {
        $store = $this->store();
        $store->save(self::TOKEN);
        $other = new FakeConstants();
        $other->define('FDCRM_GITHUB_TOKEN', 'from-the-constant');

        $this->assertSame('from-the-constant', (new TokenStore($this->options, $other, 'fdcrm'))->get());
        $this->assertSame(self::TOKEN, $store->get());
    }

    public function testAConstantThatIsNotANonEmptyStringIsIgnored(): void
    {
        $store = $this->store();
        $store->save(self::TOKEN);

        foreach (['', '   ', 123, true, ['a'], null] as $value) {
            $this->constants->define('FDCRM_GITHUB_TOKEN', $value);

            $this->assertSame(self::TOKEN, $store->get(), var_export($value, true));
            $this->assertSame(TokenStore::SOURCE_SETTINGS, $store->source(), var_export($value, true));
            $this->assertFalse($store->isLockedByConstant(), var_export($value, true));
        }
    }

    public function testAStoredValueThatIsNotANonEmptyStringIsIgnored(): void
    {
        $store = $this->store();

        foreach (['', '   ', 123, ['a'], false] as $value) {
            $this->options->set('fdcrm_updater_github_token', $value);

            $this->assertNull($store->get(), var_export($value, true));
            $this->assertSame(TokenStore::SOURCE_NONE, $store->source(), var_export($value, true));
        }
    }

    public function testClearingRemovesTheSavedToken(): void
    {
        $store = $this->store();
        $store->save(self::TOKEN);

        $this->assertTrue($store->clear());

        $this->assertNull($store->get());
        $this->assertFalse($this->options->has('fdcrm_updater_github_token'));
        $this->assertSame(TokenStore::SOURCE_NONE, $store->source());
    }

    public function testClearingWithNothingSavedIsFine(): void
    {
        $this->assertTrue($this->store()->clear());
    }

    public function testAMaskedTokenShowsBulletsAndTheLastFourCharacters(): void
    {
        $store = $this->store();
        $store->save(self::TOKEN);

        $masked = $store->masked();

        $this->assertSame(str_repeat("\u{2022}", 8) . '7890', $masked);
        $this->assertStringNotContainsString('github_pat', $masked);
        $this->assertStringNotContainsString(self::TOKEN, $masked);
    }

    public function testAShortTokenShowsOnlyBullets(): void
    {
        $store = $this->store();
        $store->save('short-token');

        $this->assertSame(str_repeat("\u{2022}", 8), $store->masked());
    }

    public function testATokenOfTwelveCharactersShowsItsTail(): void
    {
        $store = $this->store();
        $store->save('abcdefghijkl');

        $this->assertSame(str_repeat("\u{2022}", 8) . 'ijkl', $store->masked());
    }

    public function testTheTokenFromAConstantIsMaskedTheSameWay(): void
    {
        $this->constants->define('FDCRM_GITHUB_TOKEN', self::TOKEN);

        $this->assertSame(str_repeat("\u{2022}", 8) . '7890', $this->store()->masked());
    }

    public function testTwoPrefixesNeverShareATokenOrAConstant(): void
    {
        $first = $this->store('first');
        $second = $this->store('second');

        $first->save('first-token-value');
        $this->constants->define('SECOND_GITHUB_TOKEN', 'second-constant-value');

        $this->assertSame('first-token-value', $first->get());
        $this->assertSame(TokenStore::SOURCE_SETTINGS, $first->source());
        $this->assertSame('second-constant-value', $second->get());
        $this->assertSame(TokenStore::SOURCE_CONSTANT, $second->source());
        $this->assertFalse($first->isLockedByConstant());
    }
}
