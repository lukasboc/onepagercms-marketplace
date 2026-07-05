<x-public-layout title="Publish your plugin or theme">
    <div class="bg-white border-b border-gray-200">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16 text-center">
            <h1 class="text-4xl font-extrabold tracking-tight text-gray-900">Publish your plugin or theme</h1>
            <p class="mt-4 text-lg text-gray-600">
                Share your work with every OnePagerCMS website. Submissions are free — every extension is
                reviewed by our team before it goes live.
            </p>
            <a href="{{ route('developer.items.create') }}"
               class="mt-8 inline-flex items-center px-6 py-3 bg-indigo-600 rounded-md text-white font-semibold hover:bg-indigo-500">
                Submit your extension
            </a>
            <p class="mt-2 text-sm text-gray-500">You need a (free) developer account — you will be asked to log in or
                register.</p>
        </div>
    </div>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-10">
        <section>
            <h2 class="text-2xl font-bold text-gray-900 mb-4">How it works</h2>
            <ol class="space-y-4">
                <li class="flex gap-4">
                    <span class="shrink-0 w-8 h-8 rounded-full bg-indigo-600 text-white flex items-center justify-center font-bold">1</span>
                    <div>
                        <h3 class="font-semibold text-gray-900">Register &amp; verify your email</h3>
                        <p class="text-gray-600 text-sm">Create a free developer account and confirm your email
                            address.</p>
                    </div>
                </li>
                <li class="flex gap-4">
                    <span class="shrink-0 w-8 h-8 rounded-full bg-indigo-600 text-white flex items-center justify-center font-bold">2</span>
                    <div>
                        <h3 class="font-semibold text-gray-900">Upload your ZIP</h3>
                        <p class="text-gray-600 text-sm">Submit your extension as a ZIP archive together with a short
                            summary and description. Name, slug, type and version are read from the manifest inside the
                            archive. Automated checks run immediately (valid ZIP, manifest rules, PHP syntax check,
                            max. 50&nbsp;MB).</p>
                    </div>
                </li>
                <li class="flex gap-4">
                    <span class="shrink-0 w-8 h-8 rounded-full bg-indigo-600 text-white flex items-center justify-center font-bold">3</span>
                    <div>
                        <h3 class="font-semibold text-gray-900">Human review</h3>
                        <p class="text-gray-600 text-sm">Our team reviews every submission by hand. You get an email
                            with the decision — and a note explaining what to fix if it is rejected. Updates to listed
                            extensions go through the same review.</p>
                    </div>
                </li>
                <li class="flex gap-4">
                    <span class="shrink-0 w-8 h-8 rounded-full bg-indigo-600 text-white flex items-center justify-center font-bold">4</span>
                    <div>
                        <h3 class="font-semibold text-gray-900">Go live</h3>
                        <p class="text-gray-600 text-sm">Once approved, your extension appears in this catalog and in
                            the Extensions area of every OnePagerCMS installation.</p>
                    </div>
                </li>
            </ol>
        </section>

        <section>
            <h2 class="text-2xl font-bold text-gray-900 mb-4">The manifest</h2>
            <p class="text-gray-600 mb-4">Every plugin needs a <code>plugin.json</code> (themes: <code>theme.json</code>)
                in the root of the archive:</p>
            <pre class="bg-gray-900 text-gray-100 rounded-lg p-4 text-sm overflow-x-auto"><code>{
  "slug": "my-plugin",
  "type": "plugin",
  "name": "My Plugin",
  "version": "1.0.0",
  "description": "What it does.",
  "author": "Jane Dev",
  "author_url": "https://janedev.example",
  "main": "my-plugin.php",
  "requires_opcms": "1.2.0",
  "requires_php": "7.4",
  "paid": false,
  "update_endpoint": null
}</code></pre>
            <ul class="mt-4 space-y-1 text-sm text-gray-600 list-disc list-inside">
                <li><code>slug</code>: lowercase letters, digits and dashes, 3–50 characters, unique in the marketplace.</li>
                <li><code>version</code>: <code>1.0</code> or <code>1.0.0</code> style; each update must increase it.</li>
                <li><code>main</code> (plugins only): the PHP entry file, included while your plugin is active.</li>
                <li>Plugins hook into the CMS via a WordPress-style API (<code>add_action</code>/<code>add_filter</code>);
                    themes override templates of the default theme. See the extension documentation shipped with
                    OnePagerCMS (<code>docs/EXTENSIONS.md</code>) for the full hook and template reference.</li>
            </ul>
        </section>

        <section>
            <h2 class="text-2xl font-bold text-gray-900 mb-4">Guidelines</h2>
            <ul class="space-y-2 text-gray-600 list-disc list-inside">
                <li>No obfuscated or encrypted code — reviewers must be able to read everything.</li>
                <li>No tracking, phoning home or remote code execution without clear user consent.</li>
                <li>Respect the user's site: clean up your own database tables in the uninstall hook.</li>
                <li>Declare compatible versions honestly (<code>requires_opcms</code>, <code>requires_php</code>).</li>
                <li>Trademarks: only use "OnePagerCMS" in your name as "... for OnePagerCMS".</li>
            </ul>
        </section>

        <section>
            <h2 class="text-2xl font-bold text-gray-900 mb-4">Free &amp; paid extensions</h2>
            <p class="text-gray-600 mb-3">
                <strong>Free extensions</strong> are hosted here: users download and update them directly from the
                marketplace.
            </p>
            <p class="text-gray-600 mb-3">
                <strong>Paid extensions</strong> are listed with a link to your own shop — the marketplace handles no
                payments. You sell the ZIP plus a license key on your site; customers install it via upload and enter
                the key in their backend. Updates and license checks run against your server: set
                <code>"paid": true</code> and an <code>update_endpoint</code> in your manifest that implements two
                simple actions:
            </p>
            <pre class="bg-gray-900 text-gray-100 rounded-lg p-4 text-sm overflow-x-auto"><code>GET {update_endpoint}?opcms_action=check_update&amp;slug=&amp;version=&amp;license=&amp;site=
  → { "slug", "new_version", "package": "&lt;zip url&gt;", "changelog", "requires_opcms" }

GET {update_endpoint}?opcms_action=activate_license&amp;slug=&amp;license=&amp;site=
  → { "success": true } | { "success": false, "error": "invalid|expired|site_limit" }</code></pre>
            <p class="text-gray-600 mt-3 text-sm">A ready-to-adapt reference implementation is available in the
                marketplace repository under <code>docs/license-server-example.php</code>. For review purposes you
                still submit your full ZIP — it is never distributed by the marketplace.</p>
        </section>

        <section class="bg-indigo-50 border border-indigo-100 rounded-xl p-8 text-center">
            <h2 class="text-2xl font-bold text-gray-900">Ready?</h2>
            <p class="mt-2 text-gray-600">Submitting takes about five minutes.</p>
            <a href="{{ route('developer.items.create') }}"
               class="mt-6 inline-flex items-center px-6 py-3 bg-indigo-600 rounded-md text-white font-semibold hover:bg-indigo-500">
                Submit your extension
            </a>
        </section>
    </div>
</x-public-layout>
