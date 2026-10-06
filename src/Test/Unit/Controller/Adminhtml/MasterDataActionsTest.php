<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Controller\Adminhtml;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Ui\Component\MassAction\Filter;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use Polkurier\Shipping\Api\Data\SyncableInterface;
use Polkurier\Shipping\Controller\Adminhtml\Bankaccount\InlineEdit;
use Polkurier\Shipping\Controller\Adminhtml\Bankaccount\Save;
use Polkurier\Shipping\Controller\Adminhtml\MasterData\AbstractInlineEdit;
use Polkurier\Shipping\Controller\Adminhtml\MasterData\AbstractMassDelete;
use Polkurier\Shipping\Controller\Adminhtml\MasterData\AbstractSave;
use Polkurier\Shipping\Controller\Adminhtml\Packtemplate\MassDelete;
use Polkurier\Shipping\Enum\SyncEntityEnum;
use Polkurier\Shipping\Model\Admin\BankAccountHandler;
use Polkurier\Shipping\Model\Admin\FormActions;
use Polkurier\Shipping\Model\Admin\PackTemplateHandler;
use Polkurier\Shipping\Model\BankAccount;
use Polkurier\Shipping\Model\PackTemplate;
use Polkurier\Shipping\Model\ResourceModel\PackTemplate\CollectionFactory;
use Polkurier\Shipping\Model\Sync\SyncResult;
use Polkurier\Shipping\Test\Unit\Support\AdminActionTestCase;

#[CoversClass(AbstractSave::class)]
#[CoversClass(Save::class)]
#[CoversClass(AbstractMassDelete::class)]
#[CoversClass(AbstractInlineEdit::class)]
#[AllowMockObjectsWithoutExpectations]
class MasterDataActionsTest extends AdminActionTestCase
{
    /**
     * @var array<string, mixed>|null what the form keeps to be filled in again
     */
    private ?array $persisted = null;

    public function testASavedAccountGoesBackToTheList(): void
    {
        $this->post = ['id' => '3', 'name' => 'Firmowe', 'form_key' => 'x'];
        $handler = $this->bankAccounts($this->account(3));
        $handler->expects(self::once())->method('save')
            ->with(self::anything(), ['name' => 'Firmowe'])
            ->willReturn($this->synced(pushed: 1));

        $this->save($handler)->execute();

        self::assertSame(['*/*/index', []], $this->redirect);
        self::assertSame(['success' => ['Saved.']], $this->messages, 'One message, and nothing else.');
    }

    public function testAnAccountPolkurierRefusedIsSavedWithAWarning(): void
    {
        $this->post = ['id' => '3', 'name' => 'Firmowe'];
        $this->params = [FormActions::BACK_PARAM => FormActions::SAVE_AND_CONTINUE];

        $this->save($this->bankAccounts($this->account(3)->setSyncError('Błędny PIN')))->execute();

        self::assertSame(['*/*/edit', ['id' => 3]], $this->redirect);
        self::assertSame(['Saved in Magento, but not in Polkurier: Błędny PIN'], $this->messages['warning']);
    }

    public function testAFailedSaveKeepsTheFormButNotTheConfirmation(): void
    {
        $this->post = ['id' => '3', 'name' => 'Firmowe', BankAccountHandler::CONFIRMATION_PIN => '2342', 'two_factor_code' => '123456'];
        $handler = $this->bankAccounts($this->account(3));
        $handler->method('save')->willThrowException(new LocalizedException(\__('A synchronization with Polkurier is already running. Try again in a moment.')));

        $this->save($handler)->execute();

        self::assertSame(['*/*/edit', ['id' => 3]], $this->redirect);
        self::assertSame(['name' => 'Firmowe'], $this->persisted);
        self::assertStringContainsString('already running', $this->messages['error'][0]);
    }

    public function testTheFormsDeleteIsConfirmedWithThePinTypedThere(): void
    {
        // Posted by the form's Delete button, never carried in the address bar.
        $this->post = ['delete' => '1', 'id' => '3', BankAccountHandler::CONFIRMATION_PIN => '2342'];
        $handler = $this->bankAccounts($this->account(3));
        $handler->expects(self::once())->method('delete')
            ->with(self::anything(), [BankAccountHandler::CONFIRMATION_PIN => '2342'])
            ->willThrowException(new LocalizedException(\__('Polkurier did not delete bank account Firmowe.')));

        $this->save($handler)->execute();

        self::assertSame(['*/*/edit', ['id' => 3]], $this->redirect, 'Back to the form, where the PIN can be typed again.');
        self::assertSame(['Polkurier did not delete bank account Firmowe.'], $this->messages['error']);
    }

    public function testAMassDeleteCarriesOnPastARefusal(): void
    {
        $handler = $this->createMock(PackTemplateHandler::class);
        $handler->method('delete')->willReturnCallback(function (SyncableInterface $template): SyncResult {
            \assert($template instanceof PackTemplate);
            if ($template->getName() === 'Karton M') {
                throw new LocalizedException(\__('Polkurier did not delete Karton M.'));
            }

            return $this->synced(deleted: 1);
        });
        $filter = $this->createStub(Filter::class);
        $filter->method('getCollection')->willReturn([$this->template('Karton S'), $this->template('Karton M'), $this->template('Karton L')]);
        $collections = $this->createStub(CollectionFactory::class);
        $collections->method('create')->willReturn($this->createStub(\Polkurier\Shipping\Model\ResourceModel\PackTemplate\Collection::class));

        (new MassDelete($this->context(), $handler, $filter, $collections))->execute();

        self::assertSame(['warning' => ['2 deleted. 1 not deleted: Polkurier did not delete Karton M.']], $this->messages, 'One message for all of them.');
        self::assertSame(['*/*/index', []], $this->redirect);
    }

    public function testAMassDeletePolkurierDidntCarryOutYetIsToldOnce(): void
    {
        $handler = $this->createMock(PackTemplateHandler::class);
        $handler->method('delete')->willReturnCallback(fn(): SyncResult => $this->synced(failed: 1, errors: ['Polkurier could not be reached.']));
        $filter = $this->createStub(Filter::class);
        $filter->method('getCollection')->willReturn([$this->template('Karton S'), $this->template('Karton M')]);
        $collections = $this->createStub(CollectionFactory::class);
        $collections->method('create')->willReturn($this->createStub(\Polkurier\Shipping\Model\ResourceModel\PackTemplate\Collection::class));

        (new MassDelete($this->context(), $handler, $filter, $collections))->execute();

        self::assertSame(['warning' => [
            '2 deleted. Polkurier hasn\'t deleted 2 of them yet: Polkurier could not be reached. They\'re tried again with the next synchronization.',
        ]], $this->messages);
    }

    /**
     * @param string[] $errors
     */
    private function synced(int $pushed = 0, int $deleted = 0, int $failed = 0, array $errors = []): SyncResult
    {
        $result = new SyncResult(SyncEntityEnum::PackTemplate);
        $result->pushed = $pushed;
        $result->deleted = $deleted;
        $result->failed = $failed;
        $result->errors = $errors;

        return $result;
    }

    public function testAnAccountEditedInTheListSaysItWaitsForAConfirmation(): void
    {
        $this->params = ['items' => [3 => ['name' => 'Firmowe', 'sync_state' => 'synced', 'ext_id' => 'forged'], 4 => 'not a row']];
        $handler = $this->bankAccounts($this->account(3)->setSyncState(SyncableInterface::STATE_UNCONFIRMED));
        $handler->expects(self::once())->method('save')->with(self::anything(), ['name' => 'Firmowe']);

        (new InlineEdit($this->context(), $handler, $this->jsonFactory()))->execute();

        self::assertTrue($this->json['error'] ?? false);
        self::assertStringStartsWith('#3: Saved in Magento. It goes to Polkurier once you confirm it', $this->json['messages'][0]);
        self::assertCount(1, $this->json['messages']);
    }

    private function save(BankAccountHandler $handler): Save
    {
        $persistor = $this->createStub(DataPersistorInterface::class);
        $persistor->method('set')->willReturnCallback(function (string $key, mixed $data): void {
            $this->persisted = $data;
        });

        return new Save($this->context(), $handler, $persistor);
    }

    /**
     * @return BankAccountHandler&\PHPUnit\Framework\MockObject\MockObject
     */
    private function bankAccounts(BankAccount $account): BankAccountHandler
    {
        $handler = $this->createMock(BankAccountHandler::class);
        $handler->method('get')->willReturn($account);
        $handler->method('getPersistorKey')->willReturn('polkurier_bank_account');

        return $handler;
    }

    private function account(int $id): BankAccount
    {
        /** @var BankAccount $account */
        $account = (new ObjectManager($this))->getObject(BankAccount::class);
        $account->setId($id);

        return $account->setName('Firmowe');
    }

    private function template(string $name): PackTemplate
    {
        /** @var PackTemplate $template */
        $template = (new ObjectManager($this))->getObject(PackTemplate::class);

        return $template->setName($name);
    }
}
