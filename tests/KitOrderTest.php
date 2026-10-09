<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class KitOrderTest extends TestCase
{
    private array $c;
    private string $dir;
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/hwfc-kit-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0700);
        $this->c = json_decode(file_get_contents(__DIR__ . '/../kit/config.example.json'), true);
        $this->c['storageDir'] = $this->dir;
        $this->now = new DateTimeImmutable('2026-10-08T12:00:00+01:00');
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) unlink($file);
        foreach (glob($this->dir . '/.*') ?: [] as $file) if (!in_array(basename($file), ['.', '..'], true) && is_file($file)) unlink($file);
        rmdir($this->dir);
    }

    private function input(): array
    {
        return [
            'name'=>'Test Member',
            'email'=>'member@example.invalid',
            'phone'=>'+44 7700 900123',
            'section'=>"Men's",
            'accountHolder'=>'Test Account',
            'notes'=>'Please check sizes',
            'human'=>'yes',
            'website'=>'',
            'fulfilment'=>'collection',
            'lines'=>[['product'=>'playingShirt', 'size'=>'L', 'quantity'=>'2', 'initials'=>'AW']],
        ];
    }

    public static function windows(): array
    {
        return [['2026-10-01T07:59:59Z','before'], ['2026-10-01T08:00:00Z','open'], ['2026-11-01T16:59:59Z','open'], ['2026-11-01T17:00:00Z','closed']];
    }

    #[DataProvider('windows')]
    public function testWindowBoundaries(string $date, string $state): void
    {
        $now = new DateTimeImmutable($date);
        self::assertSame($state, kitWindowState($this->c, $now));
        self::assertSame($state !== 'open', count(kitValidateOrder($this->input(), $this->c, $now)['errors']) > 0);
    }

    public function testTotalsUseCamelCaseFields(): void
    {
        $i = $this->input();
        $i['claimFree'] = 'yes';
        $result = kitValidateOrder($i, $this->c, $this->now);
        self::assertSame([], $result['errors']);
        self::assertSame(3100, $result['order']['totalPence']);
        self::assertSame(1, $result['order']['lines'][0]['freeUnits']);
        self::assertSame(2500, $result['order']['lines'][0]['unitPence']);
        self::assertSame(300, $result['order']['lines'][0]['initialsPence']);
    }

    public function testInitialsAreRequiredWhenConfigured(): void
    {
        $i = $this->input();
        $i['lines'][0]['initials'] = '';
        self::assertStringContainsString('initials required', strtolower(implode(' ', kitValidateOrder($i, $this->c, $this->now)['errors'])));
    }

    public function testDuplicateProductLinesAreRejected(): void
    {
        $i = $this->input();
        $i['lines'][] = ['product'=>'playingShirt', 'size'=>'S', 'quantity'=>'1', 'initials'=>'TM'];
        self::assertStringContainsString('only once', implode(' ', kitValidateOrder($i, $this->c, $this->now)['errors']));
    }

    public function testClaimWithoutEligibleShirtFails(): void
    {
        $i = $this->input();
        $i['claimFree'] = 'yes';
        $i['lines'] = [['product'=>'trainingTop', 'size'=>'M', 'quantity'=>'1']];
        self::assertStringContainsString('eligible club shirt', implode(' ', kitValidateOrder($i, $this->c, $this->now)['errors']));
    }

    public function testDeliveryRequiresConfiguredAddress(): void
    {
        $i = $this->input();
        $i['fulfilment'] = 'delivery';
        self::assertCount(3, kitValidateOrder($i, $this->c, $this->now)['errors']);
        $this->c['deliveryRequired'][] = 'recipient';
        self::assertCount(4, kitValidateOrder($i, $this->c, $this->now)['errors']);
    }

    public function testCamelCaseConfigurationOnly(): void
    {
        kitValidateConfig($this->c);
        $bad = $this->c;
        $bad['manager_email'] = $bad['managerEmail'];
        unset($bad['managerEmail']);
        $this->expectException(RuntimeException::class);
        kitValidateConfig($bad);
    }

    public function testHyphenatedLegacyProductIdIsRejected(): void
    {
        $bad = $this->c;
        $bad['products']['playing-shirt'] = $bad['products']['playingShirt'];
        unset($bad['products']['playingShirt']);
        $this->expectException(RuntimeException::class);
        kitValidateConfig($bad);
    }

    public function testAcceptedOrderAndStoredJsonAreCamelCase(): void
    {
        $i = $this->input();
        $i['claimFree'] = 'yes';
        $o = kitAcceptOrder($i, $this->c, $this->now);
        self::assertMatchesRegularExpression('/^261008-MEMBER-01$/', $o['reference']);
        self::assertSame('test-autumn-2026', $o['batchId']);
        self::assertSame('Payment Pending', $o['status']);

        $stored = json_decode((string) file_get_contents($this->dir . '/' . $o['reference'] . '.json'), true);
        foreach (['batchId','batchName','submittedAt','managerMail','memberMail','managerAttempts','claimFree','deliveryPence','totalPence'] as $key) self::assertArrayHasKey($key, $stored);
        foreach (['batch_id','batch_name','submitted_at','manager_mail','member_mail','manager_attempts','claim_free','delivery_pence','total_pence'] as $key) self::assertArrayNotHasKey($key, $stored);
        self::assertArrayHasKey('unitPence', $stored['lines'][0]);
        self::assertArrayNotHasKey('unit_pence', $stored['lines'][0]);
    }

    public function testReferenceSequenceIsDaily(): void
    {
        $first = kitAcceptOrder($this->input(), $this->c, $this->now);
        $secondInput = $this->input();
        $secondInput['name'] = 'Another Smith';
        $second = kitAcceptOrder($secondInput, $this->c, $this->now);
        self::assertSame('261008-MEMBER-01', $first['reference']);
        self::assertSame('261008-SMITH-02', $second['reference']);
    }

    public function testReferenceNeverExceedsEighteenCharacters(): void
    {
        $i = $this->input();
        $i['name'] = 'Test Montgomeryshire';
        $o = kitAcceptOrder($i, $this->c, $this->now);
        self::assertSame('261008-MONTGOME-01', $o['reference']);
        self::assertLessThanOrEqual(18, strlen($o['reference']));
    }

    public function testMailAndRetryStatusesUseCamelCase(): void
    {
        $o = kitAcceptOrder($this->input(), $this->c, $this->now);
        $failed = kitNotify($o['reference'], $this->c, fn()=>false);
        self::assertSame('failed', $failed['managerMail']);
        self::assertSame('pending', $failed['memberMail']);

        $messages = [];
        $sent = kitNotify($o['reference'], $this->c, function($to,$subject,$body,$headers) use (&$messages) {
            $messages[] = compact('to','subject','body','headers');
            return true;
        });
        self::assertSame('sent', $sent['managerMail']);
        self::assertSame('sent', $sent['memberMail']);
        self::assertSame($this->c['managerEmail'], $messages[0]['to']);
        foreach (['Test Member','Test Account','Size: L','Quantity: 2','Supplier-applied initials: AW'] as $text) self::assertStringContainsString($text, $messages[0]['body']);
    }

    public function testReviewRetainsOnlyValidatedFormFields(): void
    {
        $i = $this->input();
        $i['bankAccount'] = 'do not retain';
        $i['totalPence'] = '0';
        $result = kitValidateOrder($i, $this->c, $this->now);
        $canonical = kitCanonicalInput($result['order']);
        self::assertArrayNotHasKey('bankAccount', $canonical);
        self::assertArrayNotHasKey('totalPence', $canonical);
        self::assertSame($result, kitValidateOrder($canonical, $this->c, $this->now));
    }
}
