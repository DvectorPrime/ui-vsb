<?php
/**
 * SimulatedPaymentGateway
 * Implements PaymentGatewayInterface for demo and portfolio purposes.
 * Evaluates test card numbers according to API Contract 2.9.
 * Simulates real-world gateway latency without any external network dependencies.
 */
class SimulatedPaymentGateway implements PaymentGatewayInterface
{
    private bool $simulateLatency;

    public function __construct(bool $simulateLatency = true)
    {
        $this->simulateLatency = $simulateLatency;
    }

    /**
     * Charges a card according to simulated business rules.
     *
     * @param array{card_name:string,card_number:string,expiry:string,cvv:string} $card
     * @param float $amount
     * @return array{success:bool,reason:?string,transaction_ref:string}
     */
    public function charge(array $card, float $amount): array
    {
        // Simulate network latency (~900ms) for realistic UX unless disabled in tests
        if ($this->simulateLatency && (!defined('DISABLE_GATEWAY_LATENCY') || !DISABLE_GATEWAY_LATENCY)) {
            usleep(900000);
        }

        $cleanCard = preg_replace('/\s+|-/', '', (string) ($card['card_number'] ?? ''));
        $expiry    = trim((string) ($card['expiry'] ?? ''));
        $txnRef    = $this->generateTransactionRef();

        // Check if expiry date is in the past
        if ($this->isExpired($expiry)) {
            return [
                'success'         => false,
                'reason'          => 'expired_card',
                'transaction_ref' => $txnRef,
            ];
        }

        // Test card specific decline simulation
        if ($cleanCard === '4000000000000002') {
            return [
                'success'         => false,
                'reason'          => 'card_declined',
                'transaction_ref' => $txnRef,
            ];
        }

        if ($cleanCard === '4000000000009995') {
            return [
                'success'         => false,
                'reason'          => 'insufficient_funds',
                'transaction_ref' => $txnRef,
            ];
        }

        // 4242... and all other Luhn-valid cards succeed
        return [
            'success'         => true,
            'reason'          => null,
            'transaction_ref' => $txnRef,
        ];
    }

    /**
     * Standard Luhn algorithm (Mod 10) validator.
     */
    public static function isValidLuhn(string $number): bool
    {
        $sum = 0;
        $numDigits = strlen($number);
        $parity = $numDigits % 2;

        for ($i = 0; $i < $numDigits; $i++) {
            $digit = (int) $number[$i];
            if ($i % 2 === $parity) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum += $digit;
        }

        return ($sum % 10 === 0);
    }

    /**
     * Checks if a MM/YY card expiry has already passed.
     */
    private function isExpired(string $expiry): bool
    {
        if (!preg_match('/^(0[1-9]|1[0-2])\/(\d{2})$/', $expiry, $matches)) {
            return false; // Handled by format validation
        }

        $month = (int) $matches[1];
        $year  = 2000 + (int) $matches[2];

        try {
            $lastDay = new DateTime("{$year}-{$month}-01 23:59:59");
            $lastDay->modify('last day of this month');
            $now = new DateTime('now');
            return $lastDay < $now;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Generates a unique transaction reference in format TXN-YYYYMMDD-XXXXXX.
     */
    private function generateTransactionRef(): string
    {
        $datePrefix = date('Ymd');
        $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randomPart = '';
        $maxIndex = strlen($chars) - 1;

        for ($i = 0; $i < 6; $i++) {
            $randomPart .= $chars[random_int(0, $maxIndex)];
        }

        return "TXN-{$datePrefix}-{$randomPart}";
    }
}
