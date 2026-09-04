<?php

declare(strict_types=1);

namespace App\Tests\Domain\ValueObject;

use App\Domain\Exception\InvalidEmailAddressException;
use App\Domain\ValueObject\EmailAddress;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EmailAddressTest extends TestCase
{
    #[DataProvider('validEmailProvider')]
    public function testAcceptsValidFormats(string $input, string $expected): void
    {
        $email = new EmailAddress($input);

        self::assertSame($expected, $email->value());
        self::assertSame($expected, (string) $email);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function validEmailProvider(): iterable
    {
        yield 'simple' => ['user@example.com', 'user@example.com'];
        yield 'with plus tag' => ['user+tag@example.com', 'user+tag@example.com'];
        yield 'with subdomain' => ['user@mail.example.com', 'user@mail.example.com'];
        yield 'uppercase is normalized to lowercase' => ['User@Example.COM', 'user@example.com'];
        yield 'surrounding whitespace is trimmed' => [' user@example.com ', 'user@example.com'];
    }

    #[DataProvider('invalidEmailProvider')]
    public function testRejectsInvalidFormats(string $input): void
    {
        $this->expectException(InvalidEmailAddressException::class);

        new EmailAddress($input);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidEmailProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => ['   '];
        yield 'missing at sign' => ['user.example.com'];
        yield 'missing domain' => ['user@'];
        yield 'missing local part' => ['@example.com'];
        yield 'double at sign' => ['user@@example.com'];
        yield 'contains spaces' => ['user name@example.com'];
    }

    public function testTwoEmailAddressesWithSameValueAreEqual(): void
    {
        $first = new EmailAddress('user@example.com');
        $second = new EmailAddress('USER@Example.com');

        self::assertTrue($first->equals($second));
    }

    public function testTwoEmailAddressesWithDifferentValuesAreNotEqual(): void
    {
        $first = new EmailAddress('user@example.com');
        $second = new EmailAddress('other@example.com');

        self::assertFalse($first->equals($second));
    }
}
