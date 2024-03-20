<?php

declare(strict_types=1);

namespace BeycanPress\CryptoPay\WPForms\Models;

use BeycanPress\CryptoPayLite\Models\AbstractTransaction;

class TransactionsLite extends AbstractTransaction
{
    public string $addon = 'wpforms';

    /**
     * @return void
     */
    public function __construct()
    {
        parent::__construct('wpforms_transaction');
    }
}
