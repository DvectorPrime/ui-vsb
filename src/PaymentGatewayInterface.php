<?php
/**
 * PaymentGatewayInterface
 * Contract for swappable payment gateways (contract 2.9, Phase B4).
 * Demonstrates polymorphism: the application depends on this interface
 * rather than a concrete third-party implementation.
 */
interface PaymentGatewayInterface
{
    /**
     * Charges a card.
     *
     * @param array{card_name:string,card_number:string,expiry:string,cvv:string} $card
     * @param float $amount
     * @return array{success:bool,reason:?string,transaction_ref:string}
     */
    public function charge(array $card, float $amount): array;
}
