<?php

declare(strict_types=1);

namespace BeycanPress\CryptoPay\WPForms\Models;

use BeycanPress\CryptoPay\Models\AbstractTransaction;

class TransactionsPro extends AbstractTransaction
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
