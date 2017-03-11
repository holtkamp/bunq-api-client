<?php
namespace Snijder\Bunq\Subscriber;

use GuzzleHttp\Event\BeforeEvent;
use GuzzleHttp\Event\RequestEvents;
use GuzzleHttp\Event\SubscriberInterface;
use Snijder\Bunq\Client;

/**
 * Class RequestSigningSubscriber
 *
 * @package Snijder\Bunq\Subscriber
 * @author Dennis Snijder <Dennis@Snijder.io>
 */
class RequestSigningSubscriber implements SubscriberInterface
{
    /**
     * @var string
     */
    private $privateKey;


    /**
     * RequestSigningSubscriber constructor.
     *
     * @param $privateKey
     */
    public function  __construct($privateKey)
    {
        $this->privateKey = $privateKey;
    }

    /**
     * {@inheritdoc}
     */
    public function getEvents()
    {
        return [
            'before' => ['signRequest', RequestEvents::SIGN_REQUEST]
        ];
    }


    /**
     * @param BeforeEvent $event
     */
    public function signRequest(BeforeEvent $event)
    {
        $request = $event->getRequest();

        $request->addHeader(Client::HEADER_REQUEST_CUSTOM_SIGNATURE, $this->getSignature(
            $request->getMethod(),
            $request->getHeaders(),
            $request->getBody(),
            $request->getPath()
        ));
    }

    private function getSignature($method, $headers, $body, $endpoint)
    {
        // When signing the headers they need to be in alphabetical order.
        ksort($headers);

        // The first line of string that needs to be signed is for example: POST /v1/installation
        $toSign = $method. ' ' . $endpoint;

        foreach ($headers as $key => $value) {

            // Not all headers should be signed.
            // The User-Agent and Cash-Control headers need to be signed.
            if ($key === Client::HEADER_REQUEST_USER_AGENT || $key === Client::HEADER_REQUEST_CACHE_CONTROL) {
                // Example: Cache-Control: no-cache
                $toSign .= PHP_EOL . $key . ": " . $value[0];
            }

            // All headers with the prefix 'X-Bunq-' need to be signed.
            if (substr($key, Client::HEADER_BUNQ_PREFIX_START, Client::HEADER_BUNQ_PREFIX_LENGTH) ===
                Client::HEADER_BUNQ_PREFIX) {
                $toSign .= PHP_EOL . $key . Client::HEADER_SEPARATOR . $value[0];
            }
        }

        // Always add two newlines after the headers.
        $toSign .= PHP_EOL . PHP_EOL;

        // If we have a body in this request: add the body to the string that needs to be signed.
        if (!is_null($body)) {
            $toSign .= $body;
        }

        openssl_sign($toSign, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);

        // Don't forget to base64 encode the signature.
        return base64_encode($signature);
    }

}