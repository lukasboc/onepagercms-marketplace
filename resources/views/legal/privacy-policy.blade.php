{{-- Template text — not legal advice. The operator should have the final wording reviewed. --}}
<x-public-layout title="Privacy policy">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Privacy policy</h1>
        </div>

        <section>
            <p class="text-gray-600 mb-4">
                This privacy policy explains how personal data is processed when you use this website,
                in accordance with the EU General Data Protection Regulation (GDPR).
            </p>
        </section>

        <section class="mt-6">
            <h3 class="text-xl font-semibold text-gray-900 mb-3">Controller</h3>

            <p class="text-gray-600 mb-3">
                The controller responsible for data processing on this website (Art. 4(7) GDPR) is:
            </p>

            <address class="not-italic text-gray-600 leading-relaxed">
                <strong class="text-gray-900">{{ $legal['operator']['name'] }}</strong><br>
                {{ $legal['operator']['street'] }}<br>
                {{ $legal['operator']['zip'] }} {{ $legal['operator']['city'] }}<br>
                {{ $legal['operator']['country'] }}
            </address>

            <p class="text-gray-600 mt-3">
                Email:
                <a href="mailto:{{ $legal['operator']['email'] }}" class="text-indigo-600 hover:text-indigo-500">
                    {{ $legal['operator']['email'] }}
                </a>
            </p>
        </section>

        <section class="mt-6">
            <h3 class="text-xl font-semibold text-gray-900 mb-3">Overview</h3>

            <p class="text-gray-600">
                This website does not use tracking, analytics, or advertising services.
                Your data is not sold or shared with third parties for marketing purposes.
                Personal data is only processed to the extent necessary to operate the marketplace,
                as described below.
            </p>
        </section>

        <section class="mt-6">
            <h3 class="text-xl font-semibold text-gray-900 mb-3">Hosting and Server Logs</h3>

            <p class="text-gray-600 mb-3">
                When you visit this website, the web server automatically records technical access data:
                the IP address of your device, date and time of the request, the requested URL,
                the HTTP status code, and the user agent (browser and operating system) transmitted by your browser.
            </p>

            <p class="text-gray-600">
                This data is required to deliver the website, to ensure its stability and security,
                and to investigate misuse. The legal basis is our legitimate interest in the secure
                operation of the website (Art. 6(1)(f) GDPR). Log data is not merged with other data
                sources and is deleted after a short retention period.
            </p>
        </section>

        <section class="mt-6">
            <h3 class="text-xl font-semibold text-gray-900 mb-3">Cookies</h3>

            <p class="text-gray-600 mb-3">
                This website only uses strictly necessary cookies: a session cookie and a CSRF protection
                token (XSRF). These are technically required for logging in and for protecting forms
                against cross-site request forgery. No tracking, analytics, or advertising cookies are set.
            </p>

            <p class="text-gray-600">
                Strictly necessary cookies do not require consent (§ 25(2) TTDSG); the legal basis for the
                associated processing is Art. 6(1)(f) GDPR. This is why this website does not show a
                cookie consent banner.
            </p>
        </section>

        <section class="mt-6">
            <h3 class="text-xl font-semibold text-gray-900 mb-3">User Accounts</h3>

            <p class="text-gray-600">
                If you register an account, we process your name, email address, a cryptographically
                hashed version of your password, and your email verification status. This data is
                required to provide and secure your account. The legal basis is the performance of the
                user agreement (Art. 6(1)(b) GDPR). Account data is stored until you delete your account,
                which you can do yourself at any time on your profile page.
            </p>
        </section>

        <section class="mt-6">
            <h3 class="text-xl font-semibold text-gray-900 mb-3">Developer Submissions</h3>

            <p class="text-gray-600">
                If you submit plugins or themes, the uploaded ZIP archives, screenshots, and listing
                metadata are stored and linked to your account. Approved items are published in the
                public catalog together with your developer name. The legal basis is the performance
                of the user agreement (Art. 6(1)(b) GDPR).
            </p>
        </section>

        <section class="mt-6">
            <h3 class="text-xl font-semibold text-gray-900 mb-3">Transactional Email</h3>

            <p class="text-gray-600">
                We send emails only in connection with your account and submissions: email address
                verification, password resets, and notifications about the review or listing status of
                your items. We do not send marketing or newsletter emails. The legal basis is the
                performance of the user agreement (Art. 6(1)(b) GDPR).
            </p>
        </section>

        <section class="mt-6">
            <h3 class="text-xl font-semibold text-gray-900 mb-3">Your Rights</h3>

            <p class="text-gray-600 mb-3">
                Under the GDPR you have the right to access your personal data (Art. 15), rectification
                (Art. 16), erasure (Art. 17), restriction of processing (Art. 18), data portability
                (Art. 20), and to object to processing based on legitimate interests (Art. 21).
            </p>

            <p class="text-gray-600 mb-3">
                You can delete your account yourself at any time on your profile page. For all other
                requests, contact us at the email address given above.
            </p>

            <p class="text-gray-600">
                You also have the right to lodge a complaint with a data protection supervisory
                authority (Art. 77 GDPR), in particular with the authority of the German federal state
                in which the controller is established.
            </p>
        </section>

        <section class="mt-6">
            <h3 class="text-xl font-semibold text-gray-900 mb-3">No Automated Decision-Making</h3>

            <p class="text-gray-600">
                We do not use automated decision-making or profiling within the meaning of Art. 22 GDPR.
            </p>
        </section>

        <section class="mt-6">
            <h3 class="text-xl font-semibold text-gray-900 mb-3">Third Parties</h3>

            <p class="text-gray-600">
                By default, no personal data is transferred to third parties. Paid items listed in the
                catalog are distributed via the respective developer's own website; when you visit such
                external sites, the privacy policy of the respective operator applies.
            </p>
        </section>

        <section class="mt-6">
            <h3 class="text-xl font-semibold text-gray-900 mb-3">Changes to this Policy</h3>

            <p class="text-gray-600">
                We may update this privacy policy when the website or legal requirements change.
                The current version is always available on this page.
            </p>
        </section>

        <section class="mt-8 border-t border-gray-200 pt-6">
            <p class="text-sm text-gray-500">
                Last updated: {{ date('Y') }}
            </p>
        </section>
    </div>
</x-public-layout>
