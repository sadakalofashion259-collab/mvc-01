<?php

declare(strict_types=1);

/**
 * LoanStatus — লোনের অবস্থা।
 * active   = এখনো শোধ বাকি আছে
 * inactive = সম্পূর্ণ শোধ হয়ে গেছে (বন্ধ)
 */
enum LoanStatus: string
{
    case Active   = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active   => 'চলমান',
            self::Inactive => 'পরিশোধিত',
        };
    }

    public function toggled(): self
    {
        return $this === self::Active ? self::Inactive : self::Active;
    }

    public static function fromSafe(string $value): self
    {
        return self::tryFrom($value) ?? self::Active;
    }
}

/**
 * LoanFrequency — কিস্তির ধরন।
 */
enum LoanFrequency: string
{
    case Daily   = 'daily';
    case Weekly  = 'weekly';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::Daily   => 'দৈনিক',
            self::Weekly  => 'সাপ্তাহিক',
            self::Monthly => 'মাসিক',
        };
    }

    public function periodsPerYear(): int
    {
        return match ($this) {
            self::Daily   => 365,
            self::Weekly  => 52,
            self::Monthly => 12,
        };
    }

    /** DateInterval স্ট্রিং — কিস্তির তারিখ এগিয়ে দিতে। */
    public function interval(): string
    {
        return match ($this) {
            self::Daily   => 'P1D',
            self::Weekly  => 'P7D',
            self::Monthly => 'P1M',
        };
    }

    public function toMonths(int $installments): int
    {
        return match ($this) {
            self::Daily   => max(1, (int)round($installments / 30)),
            self::Weekly  => max(1, (int)round($installments / 4)),
            self::Monthly => $installments,
        };
    }

    public static function fromSafe(string $value): self
    {
        return self::tryFrom($value) ?? self::Monthly;
    }
}

/**
 * LedgerDirection — লেজার এন্ট্রির ধরন।
 *
 * আপনি লোন *নিয়েছেন*, তাই হিসাবের দিক:
 *   Debit  = আপনার দায় বাড়ে   (মূল টাকা নেওয়া, সুদ ধরা)
 *   Credit = আপনার দায় কমে     (কিস্তি শোধ করা)
 *
 * বাকি (current_balance) = মোট Debit − মোট Credit = এখনো যত শোধ করতে হবে।
 */
enum LedgerDirection: string
{
    case Debit  = 'debit';   // দায় বাড়ে
    case Credit = 'credit';  // দায় কমে (শোধ)

    public function column(): string
    {
        return $this === self::Debit ? 'debit_amount' : 'credit_amount';
    }
}
