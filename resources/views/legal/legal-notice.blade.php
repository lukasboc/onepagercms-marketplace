{{-- Template text — not legal advice. The operator should have the final wording reviewed. --}}
<x-public-layout title="Legal notice">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Legal notice</h1>
        </div>

        <section>
            <h2 class="text-2xl font-bold text-gray-900 mb-4">Imprint</h2>
            <p class="text-gray-600 mb-4">
                Information according to German legal requirements (§ 5 DDG).
            </p>
        </section>

        <section>
            <h3 class="text-xl font-semibold text-gray-900 mb-3">Operator</h3>

            <address class="not-italic text-gray-600 leading-relaxed">
                <strong class="text-gray-900">{{ $legal['operator']['name'] }}</strong><br>
                {{ $legal['operator']['street'] }}<br>
                {{ $legal['operator']['zip'] }} {{ $legal['operator']['city'] }}<br>
                {{ $legal['operator']['country'] }}
            </address>
        </section>

        <section class="mt-6">
            <h3 class="text-xl font-semibold text-gray-900 mb-3">Contact</h3>

            <p class="text-gray-600 mb-2">
                Email:
                <a href="mailto:{{ $legal['operator']['email'] }}" class="text-indigo-600 hover:text-indigo-500">
                    {{ $legal['operator']['email'] }}
                </a>
            </p>

            @if ($legal['operator']['phone'])
                <p class="text-gray-600">
                    Phone:
                    <a href="tel:{{ preg_replace('/[^+\d]/', '', $legal['operator']['phone']) }}"
                       class="text-indigo-600 hover:text-indigo-500">
                        {{ $legal['operator']['phone'] }}
                    </a>
                </p>
            @endif
        </section>

        <section class="mt-6">
            <h3 class="text-xl font-semibold text-gray-900 mb-3">Responsible for Content</h3>

            <p class="text-gray-600 mb-3">
                Responsible for the content of this website in accordance with § 18 Abs. 2 MStV:
            </p>

            <address class="not-italic text-gray-600 leading-relaxed">
                <strong class="text-gray-900">{{ $legal['responsible_name'] ?: $legal['operator']['name'] }}</strong><br>
                {{ $legal['operator']['street'] }}<br>
                {{ $legal['operator']['zip'] }} {{ $legal['operator']['city'] }}<br>
                {{ $legal['operator']['country'] }}
            </address>
        </section>

        @if ($legal['small_business'] || $legal['vat_id'])
            <section class="mt-6">
                <h3 class="text-xl font-semibold text-gray-900 mb-3">VAT Information</h3>

                @if ($legal['small_business'])
                    <p class="text-gray-600">
                        Small business operator according to Section 19 of the German VAT Act (UStG).
                    </p>
                @endif

                @if ($legal['vat_id'])
                    <p class="text-gray-600 mt-2">
                        VAT Identification Number: {{ $legal['vat_id'] }}
                    </p>
                @endif
            </section>
        @endif

        <section class="mt-6">
            <h3 class="text-xl font-semibold text-gray-900 mb-3">Platform Notice</h3>

            <p class="text-gray-600 mb-3">
                This website provides a community marketplace for plugins developed for OnePagerCMS.
                The marketplace itself is operated free of charge and does not sell plugins directly unless explicitly stated.
            </p>

            <p class="text-gray-600">
                Individual developers are solely responsible for their plugins, including content, licensing, pricing, and updates.
            </p>
        </section>

        <section class="mt-6">
            <h3 class="text-xl font-semibold text-gray-900 mb-3">Liability for Content</h3>

            <p class="text-gray-600">
                The contents of this website have been created with the greatest possible care.
                However, no guarantee is given for accuracy, completeness, or timeliness.
            </p>
        </section>

        <section class="mt-6">
            <h3 class="text-xl font-semibold text-gray-900 mb-3">Liability for External Links</h3>

            <p class="text-gray-600">
                This website may contain links to external websites.
                We have no influence over their content and therefore assume no liability.
                The respective operators are responsible for their content.
            </p>
        </section>

        <section class="mt-6">
            <h3 class="text-xl font-semibold text-gray-900 mb-3">Copyright</h3>

            <p class="text-gray-600">
                All content on this website is protected by copyright unless stated otherwise.
                Any use beyond copyright law requires prior written permission.
            </p>
        </section>

        <section class="mt-8 border-t border-gray-200 pt-6">
            <p class="text-sm text-gray-500">
                Last updated: {{ date('Y') }}
            </p>
        </section>
    </div>
</x-public-layout>
