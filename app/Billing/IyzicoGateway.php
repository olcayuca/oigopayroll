<?php

namespace App\Billing;

use App\Models\Firm;
use App\Models\FirmPaymentCard;
use App\Models\User;
use Iyzipay\Model\Address;
use Iyzipay\Model\BasketItem;
use Iyzipay\Model\BasketItemType;
use Iyzipay\Model\Buyer;
use Iyzipay\Model\Cancel;
use Iyzipay\Model\Card;
use Iyzipay\Model\CheckoutForm;
use Iyzipay\Model\CheckoutFormInitialize;
use Iyzipay\Model\Locale;
use Iyzipay\Model\Payment;
use Iyzipay\Model\PaymentCard;
use Iyzipay\Model\PaymentChannel;
use Iyzipay\Model\PaymentGroup;
use Iyzipay\Model\Status;
use Iyzipay\Options;
use Iyzipay\Request\CreateCancelRequest;
use Iyzipay\Request\CreateCheckoutFormInitializeRequest;
use Iyzipay\Request\CreatePaymentRequest;
use Iyzipay\Request\DeleteCardRequest;
use Iyzipay\Request\RetrieveCheckoutFormRequest;
use Throwable;

/**
 * iyzico: Checkout Form (hosted page, 3-D Secure, "save my card") and payments with the stored card tokens.
 * Signatures in responses are verified with the secret key (HMAC-SHA256 of the documented fields).
 */
class IyzicoGateway implements PaymentGateway
{
    public function configured(): bool
    {
        return filled(config('services.iyzico.key')) && filled(config('services.iyzico.secret'));
    }

    public function startCheckout(Firm $firm, User $user, string $conversationId, string $basketId, string $description,
        string $amount, string $currency, string $callbackUrl, ?string $cardUserKey, string $ip): CheckoutStart
    {
        $request = new CreateCheckoutFormInitializeRequest;
        $request->setLocale(Locale::TR);
        $request->setConversationId($conversationId);
        $request->setPrice($amount);
        $request->setPaidPrice($amount);
        $request->setCurrency($currency);
        $request->setBasketId($basketId);
        $request->setPaymentGroup(PaymentGroup::SUBSCRIPTION);
        $request->setCallbackUrl($callbackUrl);
        $request->setEnabledInstallments([1]);
        $request->setBuyer($this->buyer($firm, $user, $ip));
        $request->setBillingAddress($this->address($firm, $user));
        $request->setBasketItems([$this->item($basketId, $description, $amount)]);

        if ($cardUserKey !== null) {
            $request->setCardUserKey($cardUserKey);
        }

        try {
            $result = CheckoutFormInitialize::create($request, $this->options());
        } catch (Throwable $e) {
            report($e);

            return new CheckoutStart(false, error: 'Ödeme sağlayıcısına ulaşılamadı.');
        }

        if ($result->getStatus() !== Status::SUCCESS || ! $this->verify([$result->getConversationId(), $result->getToken()], $result->getSignature())) {
            return new CheckoutStart(false, error: $result->getErrorMessage() ?: 'Ödeme sayfası açılamadı.');
        }

        return new CheckoutStart(true, $result->getToken(), $result->getPaymentPageUrl());
    }

    public function completeCheckout(string $token, string $conversationId): GatewayResult
    {
        $request = new RetrieveCheckoutFormRequest;
        $request->setLocale(Locale::TR);
        $request->setConversationId($conversationId);
        $request->setToken($token);

        try {
            $form = CheckoutForm::retrieve($request, $this->options());
        } catch (Throwable $e) {
            report($e);

            return new GatewayResult(false, error: 'Ödeme sonucu alınamadı.');
        }

        $signed = $this->verify([$form->getPaymentStatus(), $form->getPaymentId(), $form->getCurrency(), $form->getBasketId(),
            $form->getConversationId(), $form->getPaidPrice(), $form->getPrice(), $form->getToken()], $form->getSignature());

        if ($form->getStatus() !== Status::SUCCESS || $form->getPaymentStatus() !== 'SUCCESS' || ! $signed) {
            return new GatewayResult(false, error: $form->getErrorMessage() ?: 'Ödeme tamamlanmadı.');
        }

        return new GatewayResult(true, (string) $form->getPaymentId(), (string) $form->getPaidPrice(),
            cardUserKey: $form->getCardUserKey(), cardToken: $form->getCardToken(), lastFour: $form->getLastFourDigits(),
            binNumber: $form->getBinNumber(), cardAssociation: $form->getCardAssociation(), cardFamily: $form->getCardFamily());
    }

    public function chargeCard(Firm $firm, FirmPaymentCard $card, string $conversationId, string $basketId, string $description,
        string $amount, string $currency, string $ip): GatewayResult
    {
        $request = new CreatePaymentRequest;
        $request->setLocale(Locale::TR);
        $request->setConversationId($conversationId);
        $request->setPrice($amount);
        $request->setPaidPrice($amount);
        $request->setCurrency($currency);
        $request->setInstallment(1);
        $request->setBasketId($basketId);
        $request->setPaymentChannel(PaymentChannel::WEB);
        $request->setPaymentGroup(PaymentGroup::SUBSCRIPTION);

        $paymentCard = new PaymentCard;
        $paymentCard->setCardUserKey($card->card_user_key);
        $paymentCard->setCardToken($card->card_token);
        $request->setPaymentCard($paymentCard);

        $user = $card->adder ?? new User(['name' => $firm->contact_name ?: $firm->name, 'email' => $firm->email ?: 'fatura@'.parse_url(config('app.url'), PHP_URL_HOST)]);
        $request->setBuyer($this->buyer($firm, $user, $ip));
        $request->setBillingAddress($this->address($firm, $user));
        $request->setBasketItems([$this->item($basketId, $description, $amount)]);

        try {
            $payment = Payment::create($request, $this->options());
        } catch (Throwable $e) {
            report($e);

            return new GatewayResult(false, error: 'Ödeme sağlayıcısına ulaşılamadı.');
        }

        $signed = $this->verify([$payment->getPaymentId(), $payment->getCurrency(), $payment->getBasketId(), $payment->getConversationId(),
            $payment->getPaidPrice(), $payment->getPrice()], $payment->getSignature());

        if ($payment->getStatus() !== Status::SUCCESS || ! $signed) {
            return new GatewayResult(false, error: $payment->getErrorMessage() ?: 'Kart çekimi başarısız.');
        }

        return new GatewayResult(true, (string) $payment->getPaymentId(), (string) $payment->getPaidPrice());
    }

    public function cancel(string $paymentId, string $ip): bool
    {
        $request = new CreateCancelRequest;
        $request->setLocale(Locale::TR);
        $request->setConversationId('cancel-'.$paymentId);
        $request->setPaymentId($paymentId);
        $request->setIp($ip);

        try {
            return Cancel::create($request, $this->options())->getStatus() === Status::SUCCESS;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    public function deleteCard(FirmPaymentCard $card): void
    {
        $request = new DeleteCardRequest;
        $request->setLocale(Locale::TR);
        $request->setConversationId('card-'.$card->id);
        $request->setCardUserKey($card->card_user_key);
        $request->setCardToken($card->card_token);

        try {
            Card::delete($request, $this->options());
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function options(): Options
    {
        $options = new Options;
        $options->setApiKey((string) config('services.iyzico.key'));
        $options->setSecretKey((string) config('services.iyzico.secret'));
        $options->setBaseUrl((string) config('services.iyzico.base_url'));

        return $options;
    }

    /**
     * @param  list<mixed>  $fields
     */
    private function verify(array $fields, mixed $signature): bool
    {
        $expected = bin2hex(hash_hmac('sha256', implode(':', array_map(fn ($field) => (string) $field, $fields)), (string) config('services.iyzico.secret'), true));

        return is_string($signature) && hash_equals($expected, $signature);
    }

    private function buyer(Firm $firm, User $user, string $ip): Buyer
    {
        [$first, $last] = $this->splitName($user->name);
        $identity = preg_match('/^\d{11}$/', (string) $firm->tax_number) === 1 ? (string) $firm->tax_number : (string) config('billing.default_identity_number');

        $buyer = new Buyer;
        $buyer->setId('firm-'.$firm->id);
        $buyer->setName($first);
        $buyer->setSurname($last);
        $buyer->setEmail($user->email);
        $buyer->setIdentityNumber($identity);
        $buyer->setRegistrationAddress($firm->address ?: $firm->name);
        $buyer->setCity((string) config('billing.default_city'));
        $buyer->setCountry('Turkey');
        $buyer->setIp($ip);

        if (filled($firm->phone)) {
            $buyer->setGsmNumber((string) $firm->phone);
        }

        return $buyer;
    }

    private function address(Firm $firm, User $user): Address
    {
        $address = new Address;
        $address->setContactName($firm->title ?: $firm->name ?: $user->name);
        $address->setCity((string) config('billing.default_city'));
        $address->setCountry('Turkey');
        $address->setAddress($firm->address ?: $firm->name);

        return $address;
    }

    private function item(string $id, string $description, string $amount): BasketItem
    {
        $item = new BasketItem;
        $item->setId($id);
        $item->setName(mb_substr($description, 0, 100));
        $item->setCategory1('Yazılım');
        $item->setItemType(BasketItemType::VIRTUAL);
        $item->setPrice($amount);

        return $item;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $last = count($parts) > 1 ? (string) array_pop($parts) : '-';

        return [implode(' ', $parts) ?: $name, $last];
    }
}
