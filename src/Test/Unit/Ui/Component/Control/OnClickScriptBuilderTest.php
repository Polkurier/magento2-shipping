<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Ui\Component\Control;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Url\DecoderInterface;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Ui\Component\Control\DeleteButton;
use Polkurier\Shipping\Ui\Component\Control\OnClickScriptBuilder;
use Polkurier\Shipping\Ui\Component\Form\BankAccount\DeleteButton as BankAccountDeleteButton;
use Polkurier\Shipping\Ui\Component\Form\Pickup\BackButton;

/**
 * What the buttons run when clicked: every text and URL in it a JavaScript string of its own, so a
 * quote in one, as a URL taken from the request may hold, can't end it and run what follows.
 */
#[CoversClass(OnClickScriptBuilder::class)]
#[CoversClass(BackButton::class)]
#[CoversClass(DeleteButton::class)]
#[CoversClass(BankAccountDeleteButton::class)]
#[AllowMockObjectsWithoutExpectations]
class OnClickScriptBuilderTest extends TestCase
{
    private const string HOSTILE = "https://shop.test/admin/polkurier/x/?a=');alert(1);('\"</script>";

    public function testLinksPostsAndPromptsCarryTheirTextsWhole(): void
    {
        $onClick = new OnClickScriptBuilder(new Json());

        self::assertContains(self::HOSTILE, $this->strings($onClick->location(self::HOSTILE)));
        self::assertContains(
            self::HOSTILE,
            $this->strings($onClick->post(self::HOSTILE, 'It\'s "sure"?', self::HOSTILE)),
        );
        self::assertContains('It\'s "sure"?', $this->strings($onClick->post(self::HOSTILE, 'It\'s "sure"?', 'x')));
        self::assertContains(
            self::HOSTILE,
            $this->strings($onClick->prompt(self::HOSTILE, 'Title', 'Text', 'pickup_date')),
        );
    }

    public function testEveryButtonBuildsItsScriptFromSerializedStrings(): void
    {
        // Wherever a button sits: with its form or listing, or among the shared ones.
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
            __DIR__ . '/../../../../../Ui/Component',
            \FilesystemIterator::SKIP_DOTS,
        ));
        $checked = 0;
        foreach ($files as $path) {
            $file = (string)$path;
            $source = (string)\file_get_contents($file);
            if (!\str_ends_with($file, '.php') || !\str_contains($source, "'on_click'")) {
                continue;
            }
            self::assertMatchesRegularExpression(
                '/onClickScriptBuilder->|->serialize\(/',
                $source,
                \basename($file) . ' builds on_click through OnClickScriptBuilder or the JSON serializer',
            );
            self::assertDoesNotMatchRegularExpression(
                "/'on_click' => [^\\n]*\\. \\$/",
                $source,
                \basename($file) . ' concatenates nothing raw into on_click',
            );
            $checked++;
        }
        self::assertGreaterThanOrEqual(9, $checked, 'The buttons were found.');
    }

    public function testBackGoesOnlyToAPageOfThisAdmin(): void
    {
        $back = 'https://shop.test/admin/sales/order/view/order_id/29/?q=\'"';
        self::assertContains($back, $this->strings($this->pickupBack($back)));
        self::assertContains(
            'sales/order/view',
            $this->strings($this->pickupBack('https://evil.test/admin/')),
            'Elsewhere: back to the order.',
        );
    }

    public function testDeletingCarriesItsMessageAndUrlWhole(): void
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturn('7');
        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturn(self::HOSTILE);

        $delete = (new DeleteButton(
            $request,
            $url,
            new Json(),
            'polkurier/address/delete',
            'Delete "this" one\'s entry?',
        ))->getButtonData();
        $deleteAccount = (new BankAccountDeleteButton($request, $url, new Json()))->getButtonData();

        self::assertContains(self::HOSTILE, $this->strings((string)$delete['on_click']));
        self::assertContains('Delete "this" one\'s entry?', $this->strings((string)$delete['on_click']));
        self::assertContains(self::HOSTILE, $this->strings((string)$deleteAccount['on_click']));
    }

    private function pickupBack(string $back): string
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')
            ->willReturnCallback(
                static fn(string $name): mixed => ['order_id' => '29', 'uenc' => 'encoded'][$name] ?? null,
            );
        $url = $this->createStub(UrlInterface::class);
        $url->method('getBaseUrl')->willReturn('https://shop.test/admin/');
        $url->method('getUrl')->willReturnArgument(0);
        $decoder = $this->createStub(DecoderInterface::class);
        $decoder->method('decode')->willReturn($back);

        return (string)(new BackButton(
            $request,
            $url,
            $decoder,
            new OnClickScriptBuilder(new Json()),
        ))->getButtonData()['on_click'];
    }

    /**
     * @return string[] the script's string literals, as JavaScript reads them
     */
    private function strings(string $script): array
    {
        \preg_match_all('/"(?:[^"\\\\]|\\\\.)*"/', $script, $matches);
        $strings = [];
        foreach ($matches[0] as $literal) {
            $strings[] = (string)\json_decode($literal);
        }
        // Outside the literals nothing of the texts is left.
        $outside = (string)\preg_replace('/"(?:[^"\\\\]|\\\\.)*"/', '""', $script);
        self::assertStringNotContainsString('alert(1)', $outside);
        self::assertStringNotContainsString('</script>', $outside);

        return $strings;
    }
}
