<?php

namespace Imazed\HelpScoutSidebar\Tests\Unit;

use Illuminate\Http\Request;
use Imazed\HelpScoutSidebar\Support\SignatureVerifier;
use Imazed\HelpScoutSidebar\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class SignatureVerifierTest extends TestCase
{
    #[Test]
    public function it_accepts_a_correctly_signed_request(): void
    {
        $parameters = ['customer-id' => '4821', 'conversation-id' => '991'];

        $request = $this->request($parameters + [
            'X-HelpScout-Signature' => SignatureVerifier::calculate($parameters, 'shh'),
        ]);

        $this->assertTrue((new SignatureVerifier('shh'))->isValid($request));
    }

    #[Test]
    public function it_rejects_a_tampered_parameter(): void
    {
        $parameters = ['customer-id' => '4821'];
        $signature = SignatureVerifier::calculate($parameters, 'shh');

        $request = $this->request(['customer-id' => '9999', 'X-HelpScout-Signature' => $signature]);

        $this->assertFalse((new SignatureVerifier('shh'))->isValid($request));
    }

    #[Test]
    public function it_fails_closed_when_the_secret_is_missing(): void
    {
        $request = $this->request(['customer-id' => '1', 'X-HelpScout-Signature' => 'anything']);

        $this->assertFalse((new SignatureVerifier(null))->isValid($request));
        $this->assertFalse((new SignatureVerifier(''))->isValid($request));
    }

    #[Test]
    public function it_fails_closed_when_no_signature_is_present(): void
    {
        $this->assertFalse((new SignatureVerifier('shh'))->isValid($this->request(['customer-id' => '1'])));
    }

    #[Test]
    public function it_excludes_ignored_parameters_from_the_calculation(): void
    {
        // Help Scout signs only what it sends, so a parameter appended to the
        // callback URL has to be excluded for verification to succeed at all.
        $signed = ['customer-id' => '4821'];
        $signature = SignatureVerifier::calculate($signed, 'shh');

        $request = $this->request([
            'customer-id' => '4821',
            'tenant' => 'acme',
            'X-HelpScout-Signature' => $signature,
        ]);

        $this->assertTrue((new SignatureVerifier('shh', ignore: ['tenant']))->isValid($request));
    }

    /**
     * @param  array<string, scalar>  $parameters
     */
    protected function request(array $parameters): Request
    {
        return Request::create('/helpscout/sidebar', 'GET', $parameters);
    }
}
