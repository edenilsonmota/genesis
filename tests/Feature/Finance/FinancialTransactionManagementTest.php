<?php

use App\Enums\FinancialMovementDirection;
use App\Enums\FinancialTransactionOrigin;
use App\Enums\FinancialTransactionStatus;
use App\Enums\FinancialTransactionType;
use App\Models\Area;
use App\Models\AuditLog;
use App\Models\Church;
use App\Models\Department;
use App\Models\FinancialAccount;
use App\Models\FinancialCategory;
use App\Models\FinancialMovement;
use App\Models\FinancialTransaction;
use App\Models\Member;
use App\Models\MemberChurchMembership;
use App\Models\User;
use App\PermissionLevel;
use App\Services\AuditService;
use App\Services\Finance\FinancialBalanceService;
use App\Services\Finance\FinancialTransactionService;
use App\Services\PermissionService;
use Illuminate\Support\Facades\Route;

/** @return array<string, mixed> */
function transactionContext(PermissionLevel $level = PermissionLevel::Write): array
{
    $area = Area::factory()->create();
    $church = Church::factory()->for($area)->create();
    $user = userWithPermission('finance.transactions', $level, $church);
    $account = FinancialAccount::factory()->forChurch($church)->create(['name' => 'Conta principal']);
    $incomeCategory = FinancialCategory::factory()->for($area)->create(['name' => 'Ofertas', 'type' => 'income']);
    $expenseCategory = FinancialCategory::factory()->for($area)->create(['name' => 'Manutenção', 'type' => 'expense']);

    return compact('area', 'church', 'user', 'account', 'incomeCategory', 'expenseCategory');
}

/** @param array<string, mixed> $context
 * @return array<string, mixed>
 */
function incomePayload(array $context, array $overrides = []): array
{
    return [
        'account_id' => $context['account']->id,
        'category_id' => $context['incomeCategory']->id,
        'department_id' => null,
        'responsible_member_id' => null,
        'title' => 'Oferta do culto',
        'amount' => '1200.00',
        'occurred_on' => '2026-09-10',
        'competence_month' => '2026-09',
        'payment_method' => 'pix',
        'counterparty_name' => 'Comunidade',
        'description' => 'Oferta geral',
        'status' => 'settled',
        ...$overrides,
    ];
}

it('enforces transaction read and write permissions in the backend', function () {
    $context = transactionContext(PermissionLevel::Read);
    $withoutPermission = userWithPermission('dashboard');

    $this->actingAs($context['user'])->get(route('finance.transactions.index'))->assertOk();
    $this->actingAs($context['user'])->get(route('finance.transactions.create'))->assertForbidden();
    $this->actingAs($context['user'])->post(route('finance.transactions.income.store'), incomePayload($context))->assertForbidden();
    $this->actingAs($withoutPermission)->get(route('finance.transactions.index'))->assertForbidden();
});

it('creates a settled income with one inflow and an audit record', function () {
    $context = transactionContext();

    $this->actingAs($context['user'])
        ->post(route('finance.transactions.income.store'), incomePayload($context))
        ->assertRedirect();

    $transaction = FinancialTransaction::query()->sole();
    $movement = FinancialMovement::query()->sole();
    expect($transaction->type)->toBe(FinancialTransactionType::Income)
        ->and($transaction->origin)->toBe(FinancialTransactionOrigin::Manual)
        ->and($transaction->status)->toBe(FinancialTransactionStatus::Settled)
        ->and($transaction->competence_month->toDateString())->toBe('2026-09-01')
        ->and($movement->direction)->toBe(FinancialMovementDirection::Inflow)
        ->and($movement->amount)->toBe('1200.00')
        ->and($movement->settled_on->toDateString())->toBe('2026-09-10')
        ->and(AuditLog::query()->where('action', 'financial_transaction.created')->where('record_id', $transaction->id)->exists())->toBeTrue();
});

it('rejects an incompatible category and protected fields from the generic form', function () {
    $context = transactionContext();

    $this->actingAs($context['user'])->post(route('finance.transactions.income.store'), incomePayload($context, [
        'category_id' => $context['expenseCategory']->id,
    ]))->assertSessionHasErrors('category_id');
    $this->actingAs($context['user'])->post(route('finance.transactions.income.store'), incomePayload($context, [
        'origin' => 'tithe',
        'member_id' => Member::factory()->create()->id,
    ]))->assertSessionHasErrors(['origin', 'member_id']);

    expect(FinancialTransaction::query()->count())->toBe(0);
});

it('requires positive values active accounts and payment details for confirmation', function () {
    $context = transactionContext();

    $this->actingAs($context['user'])->post(route('finance.transactions.income.store'), incomePayload($context, [
        'amount' => '0.00',
        'payment_method' => null,
    ]))->assertSessionHasErrors(['amount', 'payment_method']);
    $context['account']->update(['status' => 'inactive']);
    $this->actingAs($context['user'])->post(route('finance.transactions.income.store'), incomePayload($context))
        ->assertSessionHasErrors('account_id');

    expect(FinancialTransaction::query()->count())->toBe(0);
});

it('validates the department and responsible member against the account scope', function () {
    $context = transactionContext();
    $department = Department::factory()->for($context['area'])->create(['status' => 'inactive']);
    $otherChurch = Church::factory()->for($context['area'])->create();
    $responsible = Member::factory()->create();
    MemberChurchMembership::factory()->for($responsible)->for($otherChurch)->create();
    $payload = incomePayload($context, [
        'category_id' => $context['expenseCategory']->id,
        'department_id' => $department->id,
        'responsible_member_id' => $responsible->id,
        'document_number' => 'NF-42',
    ]);

    $this->actingAs($context['user'])->post(route('finance.transactions.expenses.store'), $payload)
        ->assertSessionHasErrors('department_id');
    $department->update(['status' => 'active']);
    $this->actingAs($context['user'])->post(route('finance.transactions.expenses.store'), $payload)
        ->assertSessionHasErrors('responsible_member_id');
});

it('allows a settled expense to leave a negative balance and returns a warning', function () {
    $context = transactionContext();

    $this->actingAs($context['user'])->post(route('finance.transactions.expenses.store'), incomePayload($context, [
        'category_id' => $context['expenseCategory']->id,
        'amount' => '500.00',
        'document_number' => 'REC-1',
    ]))->assertSessionHas('warning');

    expect(app(FinancialBalanceService::class)->forAccount($context['account']))->toBe('-500.00');
});

it('creates an atomic transfer only with writing permission in both scopes', function () {
    $context = transactionContext();
    $destinationChurch = Church::factory()->for($context['area'])->create();
    $destination = FinancialAccount::factory()->forChurch($destinationChurch)->create();
    grantPermissionToUser($context['user'], 'finance.transactions', PermissionLevel::Write, $destinationChurch);
    $payload = [
        'source_account_id' => $context['account']->id,
        'destination_account_id' => $destination->id,
        'title' => 'Repasse missionário',
        'amount' => '350.00',
        'occurred_on' => '2026-09-11',
        'payment_method' => 'bank_transfer',
        'description' => null,
        'status' => 'settled',
    ];

    $this->actingAs($context['user']->refresh())->post(route('finance.transactions.transfers.store'), $payload)->assertRedirect();

    $transaction = FinancialTransaction::query()->sole();
    expect($transaction->type)->toBe(FinancialTransactionType::Transfer)
        ->and($transaction->movements()->count())->toBe(2)
        ->and($transaction->movements()->distinct('amount')->count('amount'))->toBe(1)
        ->and($transaction->movements()->where('direction', 'inflow')->count())->toBe(1)
        ->and($transaction->movements()->where('direction', 'outflow')->count())->toBe(1)
        ->and(app(FinancialBalanceService::class)->forAccount($context['account']))->toBe('-350.00')
        ->and(app(FinancialBalanceService::class)->forAccount($destination))->toBe('350.00')
        ->and(app(FinancialBalanceService::class)->consolidatedForArea($context['area']))->toBe('0.00');
});

it('blocks a transfer UUID from a church where the actor lacks writing permission', function () {
    $context = transactionContext();
    $otherChurch = Church::factory()->for($context['area'])->create();
    $otherAccount = FinancialAccount::factory()->forChurch($otherChurch)->create();

    $this->actingAs($context['user'])->post(route('finance.transactions.transfers.store'), [
        'source_account_id' => $context['account']->id,
        'destination_account_id' => $otherAccount->id,
        'title' => 'Transferência indevida',
        'amount' => '100.00',
        'occurred_on' => '2026-09-11',
        'payment_method' => 'pix',
        'status' => 'settled',
    ])->assertSessionHasErrors('destination_account_id');

    expect(FinancialTransaction::query()->count())->toBe(0)
        ->and(FinancialMovement::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('action', 'financial_transaction.blocked')->exists())->toBeTrue();
});

it('rolls back a transfer and both movements when auditing fails', function () {
    $context = transactionContext();
    $destination = FinancialAccount::factory()->forChurch($context['church'])->create();
    $audit = Mockery::mock(AuditService::class);
    $audit->shouldReceive('record')->once()->andThrow(new RuntimeException('audit unavailable'));
    $service = new FinancialTransactionService($audit, app(PermissionService::class));

    expect(fn () => $service->createTransfer([
        'source_account_id' => $context['account']->id,
        'destination_account_id' => $destination->id,
        'title' => 'Transferência transitória',
        'amount' => '100.00',
        'occurred_on' => '2026-09-11',
        'payment_method' => 'pix',
        'description' => null,
        'status' => 'settled',
    ], $context['user']))->toThrow(RuntimeException::class);

    expect(FinancialTransaction::query()->count())->toBe(0)
        ->and(FinancialMovement::query()->count())->toBe(0);
});

it('edits and cancels drafts without affecting balance or deleting history', function () {
    $context = transactionContext();
    $this->actingAs($context['user'])->post(route('finance.transactions.income.store'), incomePayload($context, [
        'status' => 'draft',
        'payment_method' => null,
    ]))->assertRedirect();
    $transaction = FinancialTransaction::query()->sole();

    $update = incomePayload($context, [
        'title' => 'Oferta revisada',
        'amount' => '1300.00',
    ]);
    unset($update['status']);
    $this->actingAs($context['user'])->put(route('finance.transactions.update', $transaction), $update)
        ->assertSessionHasNoErrors()
        ->assertRedirect();
    $this->actingAs($context['user'])->patch(route('finance.transactions.cancel', $transaction), [
        'reason' => 'Lançamento duplicado.',
    ])->assertRedirect();

    expect($transaction->refresh()->title)->toBe('Oferta revisada')
        ->and($transaction->status)->toBe(FinancialTransactionStatus::Cancelled)
        ->and($transaction->cancellation_reason)->toBe('Lançamento duplicado.')
        ->and(FinancialTransaction::query()->whereKey($transaction->id)->exists())->toBeTrue()
        ->and(FinancialMovement::query()->where('financial_transaction_id', $transaction->id)->exists())->toBeTrue()
        ->and(app(FinancialBalanceService::class)->forAccount($context['account']))->toBe('0.00');
});

it('settles a pending transaction and reverses it without modifying original movements', function () {
    $context = transactionContext();
    $this->actingAs($context['user'])->post(route('finance.transactions.income.store'), incomePayload($context, [
        'status' => 'pending',
    ]))->assertRedirect();
    $original = FinancialTransaction::query()->sole();
    $originalMovementId = $original->movements()->sole()->id;

    $this->actingAs($context['user'])->patch(route('finance.transactions.settle', $original), [
        'settled_on' => '2026-09-12',
    ])->assertRedirect();
    $this->actingAs($context['user'])->put(route('finance.transactions.update', $original), incomePayload($context))
        ->assertForbidden();
    $this->actingAs($context['user'])->patch(route('finance.transactions.cancel', $original), ['reason' => 'Não permitido'])
        ->assertForbidden();
    $this->actingAs($context['user'])->post(route('finance.transactions.reverse', $original), [
        'reason' => 'Recebimento devolvido.',
    ])->assertRedirect();

    $reversal = FinancialTransaction::query()->where('type', 'reversal')->sole();
    expect($original->refresh()->status)->toBe(FinancialTransactionStatus::Settled)
        ->and($original->reversed_at)->not->toBeNull()
        ->and($original->movements()->sole()->id)->toBe($originalMovementId)
        ->and($reversal->reversal_of_transaction_id)->toBe($original->id)
        ->and($reversal->movements()->sole()->direction)->toBe(FinancialMovementDirection::Outflow)
        ->and(app(FinancialBalanceService::class)->forAccount($context['account']))->toBe('0.00');
    $this->actingAs($context['user'])->post(route('finance.transactions.reverse', $original), [
        'reason' => 'Tentativa repetida.',
    ])->assertForbidden();
});

it('isolates another church transaction and allows the global administrator', function () {
    $context = transactionContext();
    $otherChurch = Church::factory()->for($context['area'])->create();
    $otherAccount = FinancialAccount::factory()->forChurch($otherChurch)->create();
    $administrator = User::factory()->globalAdministrator()->create();
    $transaction = FinancialTransaction::factory()->for($context['incomeCategory'], 'category')->create();
    FinancialMovement::factory()->for($transaction, 'transaction')->for($otherAccount, 'account')->create([
        'amount' => $transaction->amount,
    ]);

    $this->actingAs($context['user'])->get(route('finance.transactions.show', $transaction))->assertForbidden();
    $this->actingAs($context['user'])->get(route('finance.transactions.index'))->assertDontSee($transaction->title);
    $this->actingAs($administrator)->get(route('finance.transactions.show', $transaction))->assertOk()->assertSee($transaction->title);
});

it('does not allow future tithe-origin transactions to be edited by the generic form', function () {
    $context = transactionContext();
    $transaction = FinancialTransaction::factory()->for($context['incomeCategory'], 'category')->create([
        'origin' => FinancialTransactionOrigin::Tithe,
        'created_by_user_id' => $context['user']->id,
    ]);
    FinancialMovement::factory()->for($transaction, 'transaction')->for($context['account'], 'account')->create([
        'amount' => $transaction->amount,
    ]);

    $this->actingAs($context['user'])->get(route('finance.transactions.edit', $transaction))->assertForbidden();
});

it('does not expose physical deletion routes', function () {
    expect(Route::has('finance.transactions.destroy'))->toBeFalse()
        ->and(Route::has('finance.movements.index'))->toBeFalse();
});
