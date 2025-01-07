<?php

declare(strict_types=1);

namespace Bavix\Wallet\Models;

use function app;
use function array_key_exists;
use Bavix\Wallet\Interfaces\Confirmable;
use Bavix\Wallet\Interfaces\Customer;
use Bavix\Wallet\Interfaces\Exchangeable;
use Bavix\Wallet\Interfaces\WalletFloat;
use Bavix\Wallet\Internal\Exceptions\ExceptionInterface;
use Bavix\Wallet\Internal\Exceptions\TransactionFailedException;
use Bavix\Wallet\Internal\Service\IdentifierFactoryServiceInterface;
use Bavix\Wallet\Internal\Service\MathServiceInterface;
use Bavix\Wallet\Services\AtomicServiceInterface;
use Bavix\Wallet\Services\RegulatorServiceInterface;
use Bavix\Wallet\Traits\CanConfirm;
use Bavix\Wallet\Traits\CanExchange;
use Bavix\Wallet\Traits\CanPayFloat;
use Bavix\Wallet\Traits\HasGift;
use function config;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\RecordsNotFoundException;
use Illuminate\Support\Str;

/**
 * Class Wallet.
 *
 * @property class-string $holder_type
 * @property int|non-empty-string $holder_id
 * @property string $slug
 * @property non-empty-string $uuid
 * @property int $decimal_places
 * @property Model $holder
 * @property string $currency
 * @property DateTimeInterface $created_at
 * @property DateTimeInterface $updated_at
 *
 * @method int getKey()
 */
class Wallet extends Model implements Customer, WalletFloat, Exchangeable
{
    use CanExchange;
    use CanPayFloat;
    use HasGift;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'holder_type',
        'holder_id',
        'slug',
        'uuid',
        'balance',
        'decimal_places',
        'created_at',
        'updated_at',
    ];

    /**
     * @var array<string, int|string>
     */
    protected $attributes = [
        'balance' => 0,
        'decimal_places' => 2,
    ];

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'decimal_places' => 'int',
        ];
    }

    public function getTable(): string
    {
        if ((string) $this->table === '') {
            $this->table = config('wallet.wallet.table', 'wallets');
        }

        return parent::getTable();
    }

    /**
     * Under ideal conditions, you will never need a method. Needed to deal with out-of-sync.
     *
     * @throws RecordsNotFoundException
     * @throws TransactionFailedException
     * @throws ExceptionInterface
     */
    public function refreshBalance(): bool
    {
        return app(AtomicServiceInterface::class)->block($this, function () {
            $whatIs = $this->getBalanceAttribute();
            $balance = $this->getAvailableBalanceAttribute();
            if (app(MathServiceInterface::class)->compare($whatIs, $balance) === 0) {
                return true;
            }

            return app(RegulatorServiceInterface::class)->sync($this, $balance);
        });
    }

    public function getOriginalBalanceAttribute(): string
    {
        $balance = (string) $this->getRawOriginal('balance', 0);

        // Perform assertion to check if balance is not an empty string
        assert($balance !== '', 'Balance should not be an empty string');

        return $balance;
    }

    public function getAvailableBalanceAttribute(): float|int|string
    {
        $balance = $this->walletTransactions()->sum('amount');

        // Perform assertion to check if balance is not an empty string
        assert($balance !== '', 'Balance should not be an empty string');

        return $balance;
    }

    /**
     * @return MorphTo<Model, self>
     */
    public function holder(): MorphTo
    {
        return $this->morphTo();
    }

    public function getCreditAttribute(): string
    {
        return '0';
    }

    public function getCurrencyAttribute(): string
    {
        return Str::upper($this->slug);
    }

    protected function initializeMorphOneWallet(): void
    {
        $this->uuid ??= app(IdentifierFactoryServiceInterface::class)->generate();
    }
}
