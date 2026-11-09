<?php
declare(strict_types=1);

namespace Plaxswap\WooCommerce\Core;

/** Bind an authoritative API response to the immutable WooCommerce invoice. */
final class PaymentVerifier
{
    /**
     * Invoice fields: remote_id (empty until create), livemode (boolean), amount,
     * currency, order_ref, order_id and verification_ref. Never pass browser or
     * webhook fields as the immutable invoice or as an authoritative response.
     */
    public static function validate(array $payment, array $invoice): void
    {
        if (!array_key_exists('livemode', $invoice) || !is_bool($invoice['livemode'])
            || !Decimal::positive($invoice['amount'] ?? null)
            || !is_string($invoice['currency'] ?? null) || !Decimal::currencySupported($invoice['currency'])
            || !is_string($invoice['order_ref'] ?? null) || $invoice['order_ref'] === ''
            || (!is_int($invoice['order_id'] ?? null) && !is_string($invoice['order_id'] ?? null))
            || !preg_match('/^[1-9][0-9]*$/D', (string) $invoice['order_id'])
            || !is_string($invoice['verification_ref'] ?? null) || $invoice['verification_ref'] === ''
            || !array_key_exists('remote_id', $invoice) || !is_string($invoice['remote_id'])) {
            throw new \UnexpectedValueException('invalid_invoice');
        }

        $remoteId = $invoice['remote_id'];
        $metadata = isset($payment['metadata']) && is_array($payment['metadata']) ? $payment['metadata'] : [];
        $checks = [
            'invalid_payment_id' => is_string($payment['id'] ?? null) && preg_match('/^pay_[a-z0-9]{16}$/D', $payment['id'])
                && ($remoteId === '' || hash_equals($remoteId, $payment['id'])),
            'wrong_mode' => isset($payment['livemode']) && is_bool($payment['livemode']) && $payment['livemode'] === $invoice['livemode'],
            'wrong_amount' => Decimal::equal($payment['amount'] ?? null, $invoice['amount']),
            'wrong_currency' => ($payment['currency'] ?? null) === $invoice['currency'],
            'wrong_reference' => ($payment['orderId'] ?? null) === $invoice['order_ref'],
            'wrong_metadata' => ($metadata['wc_order'] ?? null) === (string) $invoice['order_id']
                && ($metadata['wc_verification'] ?? null) === $invoice['verification_ref'],
            'wrong_receiving_mode' => ($payment['receiveMode'] ?? null) === 'original',
            'invalid_status' => in_array($payment['status'] ?? null, ['pending', 'confirming', 'paid', 'partially_paid', 'expired', 'cancelled', 'failed'], true),
        ];
        foreach ($checks as $error => $valid) {
            if (!$valid) {
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Fixed internal validation code from $checks, never HTML output.
                throw new \UnexpectedValueException($error);
            }
        }

        if ($payment['status'] === 'paid') {
            if (($payment['underReview'] ?? false) !== false || !Signature::validDate($payment['paidAt'] ?? null)) {
                throw new \UnexpectedValueException('payment_not_final');
            }
            $credited = false;
            if (isset($payment['receivedBalances']) && is_array($payment['receivedBalances'])) {
                foreach ($payment['receivedBalances'] as $balance) {
                    if (is_array($balance) && Decimal::positive($balance['amount'] ?? null)) {
                        $credited = true;
                        break;
                    }
                }
            }
            if (!$credited && !Decimal::positive($payment['receivedAmount'] ?? null)) {
                throw new \UnexpectedValueException('no_merchant_credit');
            }
        }
    }

    /** Minimal reconciliation data: no customer, destination address or metadata. */
    public static function summary(array $payment): array
    {
        $keys = ['id', 'status', 'livemode', 'amount', 'currency', 'receiveMode', 'receiveAmount', 'expectedAmount', 'receivedAmount', 'late', 'overpaid', 'underReview', 'createdAt', 'paidAt', 'expiresAt'];
        $summary = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $payment) && (is_scalar($payment[$key]) || $payment[$key] === null)) {
                $summary[$key] = $payment[$key];
            }
        }
        if (isset($payment['receive']) && is_array($payment['receive'])) {
            $summary['receive'] = self::methodSummary($payment['receive']);
        }
        if (isset($payment['fee']) && is_array($payment['fee'])) {
            $summary['fee'] = self::scalarFields($payment['fee'], ['bps', 'paidBy', 'amount']);
        }
        $summary['receivedBalances'] = [];
        foreach (is_array($payment['receivedBalances'] ?? null) ? array_slice($payment['receivedBalances'], 0, 200) : [] as $balance) {
            if (!is_array($balance) || !is_array($balance['method'] ?? null) || !Decimal::positive($balance['amount'] ?? null)) {
                continue;
            }
            $summary['receivedBalances'][] = ['method' => self::methodSummary($balance['method']), 'amount' => $balance['amount']];
        }
        $summary['transactions'] = [];
        foreach (is_array($payment['transactions'] ?? null) ? array_slice($payment['transactions'], 0, 200) : [] as $transaction) {
            if (!is_array($transaction)) {
                continue;
            }
            $safe = self::scalarFields($transaction, ['id', 'status', 'amount', 'receivedAmount', 'txid', 'payoutTxid', 'detectedAt', 'settledAt', 'late']);
            foreach (['payCurrency', 'receiveCurrency'] as $key) {
                if (isset($transaction[$key]) && is_array($transaction[$key])) {
                    $safe[$key] = self::methodSummary($transaction[$key]);
                }
            }
            $summary['transactions'][] = $safe;
        }
        return $summary;
    }

    private static function methodSummary(array $method): array
    {
        return self::scalarFields($method, ['id', 'assetId', 'networkId', 'label', 'decimals', 'memoRequired']);
    }

    private static function scalarFields(array $input, array $keys): array
    {
        $output = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $input) && (is_scalar($input[$key]) || $input[$key] === null)) {
                $output[$key] = $input[$key];
            }
        }
        return $output;
    }
}
