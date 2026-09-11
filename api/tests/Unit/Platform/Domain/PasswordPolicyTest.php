<?php

declare(strict_types=1);

namespace App\Tests\Unit\Platform\Domain;

use App\Platform\Domain\PasswordPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PasswordPolicyTest extends TestCase
{
    #[DataProvider('validPasswords')]
    public function testAcceptsPasswordsMeetingEveryRule(string $password): void
    {
        self::assertSame(1, preg_match(PasswordPolicy::REGEX, $password));
    }

    /**
     * @return iterable<string, list<string>>
     */
    public static function validPasswords(): iterable
    {
        yield 'minimum length, one of each class' => ['Abcde1!'];
        yield 'longer password' => ['Correct-Horse-Battery-1!'];
        yield 'special char other than hyphen' => ['Passw0rd$'];
    }

    #[DataProvider('invalidPasswords')]
    public function testRejectsPasswordsMissingAnyRule(string $password): void
    {
        self::assertSame(0, preg_match(PasswordPolicy::REGEX, $password));
    }

    /**
     * @return iterable<string, list<string>>
     */
    public static function invalidPasswords(): iterable
    {
        yield 'too short' => ['Ab1!'];
        yield 'no uppercase' => ['abcde1!'];
        yield 'no lowercase' => ['ABCDE1!'];
        yield 'no digit' => ['Abcdef!'];
        yield 'no special character' => ['Abcdef1'];
        yield 'empty' => [''];
    }
}
