<?php

namespace Imazed\HelpScoutSidebar\Http\Controllers;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Imazed\HelpScoutSidebar\Contracts\BuildsSidebar;
use Imazed\HelpScoutSidebar\Contracts\CustomerResolver;
use Imazed\HelpScoutSidebar\Diagnostics\SidebarDiagnostics;
use Imazed\HelpScoutSidebar\Sidebar\Sidebar;
use Imazed\HelpScoutSidebar\Support\HelpScoutContext;
use Imazed\HelpScoutSidebar\Support\SignatureVerifier;

/**
 * The single endpoint Help Scout loads in the conversation sidebar iframe.
 *
 * The flow is deliberately linear: verify the signature, parse the context,
 * resolve a customer, render. Everything that can fail does so by returning
 * null and falling through to the no-match state, so the only status codes this
 * route produces are 200 and 403.
 */
class SidebarController
{
    public function __invoke(
        Request $request,
        ConfigRepository $config,
        SignatureVerifier $signatures,
        CustomerResolver $customers,
        BuildsSidebar $builder,
        SidebarDiagnostics $diagnostics,
    ): Response {
        $signatureRequired = (bool) $config->get('helpscout-sidebar.signature.enabled', true);

        if ($signatureRequired && ! $signatures->isValid($request)) {
            abort(403, 'Invalid Help Scout signature.');
        }

        $context = HelpScoutContext::fromRequest(
            request: $request,
            // With verification switched off nothing is signed, so every
            // parameter is untrusted. Treating them as trusted anyway would
            // make local development behave unlike production, which is
            // precisely when that difference bites.
            untrustedKeys: $signatureRequired
                ? $signatures->ignoredParameters()
                : $request->query->keys(),
            signatureParameter: (string) $config->get('helpscout-sidebar.signature.parameter', 'X-HelpScout-Signature'),
        );

        $customer = $customers->resolve($context);

        $sidebar = match (true) {
            $customer !== null => $builder->build(Sidebar::make(), $customer, $context),
            (bool) $config->get('helpscout-sidebar.debug', false) => $diagnostics->build($context, $customers),
            default => $this->noMatch($config),
        };

        return $this->respond($config, $context, $customer, $sidebar);
    }

    /**
     * The state an agent sees when this conversation has no counterpart in the
     * host application. A perfectly ordinary outcome, so it renders as content
     * rather than as an error.
     */
    protected function noMatch(ConfigRepository $config): Sidebar
    {
        return Sidebar::make()->emptyState(
            (string) $config->get('helpscout-sidebar.no_match.title', 'No customer found'),
            (string) $config->get('helpscout-sidebar.no_match.message', 'No matching record was found.'),
        );
    }

    /**
     * Render the view and apply the frame policy.
     */
    protected function respond(
        ConfigRepository $config,
        HelpScoutContext $context,
        mixed $customer,
        Sidebar $sidebar,
    ): Response {
        $response = response()->view((string) $config->get('helpscout-sidebar.view'), [
            'context' => $context,
            'customer' => $customer,
            'sidebar' => $sidebar,
        ]);

        $csp = $config->get('helpscout-sidebar.content_security_policy');

        // Help Scout renders this route in an iframe, so it must not be denied
        // framing. Applications commonly send X-Frame-Options globally, which
        // would break the sidebar without any visible error.
        if (is_string($csp) && $csp !== '') {
            $response->headers->set('Content-Security-Policy', $csp);
            $response->headers->remove('X-Frame-Options');
        }

        return $response;
    }
}
