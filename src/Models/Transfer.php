<?php

declare(strict_types=1);

namespace Bavix\Wallet\Models;

use function config;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class Transfer.
 *
 * @property string $status
 * @property int $deposit_id
 * @property int $withdraw_id
 * @property Wallet $from
 * @property int $from_id
 * @property Wallet $to
 * @property int $to_id
 * @property non-empty-string $uuid
 * @property Transaction $deposit
 * @property Transaction $withdraw
 * @property DateTimeInterface $created_at
 * @property DateTimeInterface $updated_at
 *
 * @method int getKey()
 */
class Transfer extends Model
{
    final public const STATUS_EXCHANGE = 'exchange';

    final public const STATUS_TRANSFER = 'transfer';

    final public const STATUS_PAID = 'paid';

    final public const STATUS_REFUND = 'refund';

    final public const STATUS_GIFT = 'gift';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'status',
        'deposit_id',
        'withdraw_id',
        'from_id',
        'to_id',
        'uuid',
        'created_at',
        'updated_at',
    ];

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'deposit_id' => 'int',
            'withdraw_id' => 'int',
        ];
    }

    public function getTable(): string
    {
        if ((string) $this->table === '') {
            $this->table = config('wallet.transfer.table', 'transfers');
        }

        return parent::getTable();
    }

    /**
     * @return BelongsTo<Wallet, self>
     */
    public function from(): BelongsTo
    {
        return $this->belongsTo(config('wallet.wallet.model', Wallet::class), 'from_id');
    }

    /**
     * @return BelongsTo<Wallet, self>
     */
    public function to(): BelongsTo
    {
        return $this->belongsTo(config('wallet.wallet.model', Wallet::class), 'to_id');
    }

    /**
     * @return BelongsTo<Transaction, self>
     */
    public function deposit(): BelongsTo
    {
        return $this->belongsTo(config('wallet.transaction.model', Transaction::class), 'deposit_id');
    }

    /**
     * @return BelongsTo<Transaction, self>
     */
    public function withdraw(): BelongsTo
    {
        return $this->belongsTo(config('wallet.transaction.model', Transaction::class), 'withdraw_id');
    }
}
